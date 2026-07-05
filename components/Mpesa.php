<?php
/**
 * Safaricom Daraja API wrapper (STK Push + query).
 * Credentials loaded from .env via config/database.php.
 */
class Mpesa
{
    private string $consumerKey;
    private string $consumerSecret;
    private string $env;
    private string $shortCode;
    private string $passKey;
    private string $callbackUrl;

    public function __construct()
    {
        $this->consumerKey    = trim(getenv('MPESA_CONSUMER_KEY') ?: '');
        $this->consumerSecret = trim(getenv('MPESA_CONSUMER_SECRET') ?: '');
        $this->env            = trim(getenv('MPESA_ENV') ?: 'sandbox');
        $this->shortCode      = trim(getenv('MPESA_SHORTCODE') ?: '174379');
        $this->passKey        = trim(getenv('MPESA_PASSKEY') ?: '');
        $this->callbackUrl    = trim(getenv('MPESA_CALLBACK_URL') ?: '');

        if ($this->consumerKey === '' || $this->consumerSecret === '') {
            throw new RuntimeException('M-PESA credentials are not configured. Set MPESA_CONSUMER_KEY and MPESA_CONSUMER_SECRET in .env.');
        }
        if ($this->passKey === '') {
            throw new RuntimeException('MPESA_PASSKEY is not configured in .env.');
        }
        if ($this->callbackUrl === '') {
            throw new RuntimeException('MPESA_CALLBACK_URL is not configured. Use your ngrok HTTPS URL + path to backend/mpesa_callback.php.');
        }
    }

    public function getAccessToken(): string
    {
        $url = $this->env === 'sandbox'
            ? 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $response = $this->darajaRequest('GET', $url, [
            'Authorization: Basic ' . base64_encode($this->consumerKey . ':' . $this->consumerSecret),
        ]);

        $data = json_decode($response);
        if (!isset($data->access_token)) {
            throw new RuntimeException($this->parseDarajaError($response, 'OAuth'));
        }

        return $data->access_token;
    }

    /**
     * Initiate Lipa na M-PESA Online (STK Push).
     *
     * @return string Raw JSON response from Daraja
     */
    public function initiateStkPush(float $amount, string $phoneNumber, string $accountReference): string
    {
        $partyA = mpesaNormalizePhone($phoneNumber);
        if ($partyA === null) {
            throw new InvalidArgumentException('Invalid M-PESA phone number.');
        }

        $accessToken = $this->getAccessToken();
        $timestamp   = date('YmdHis');
        $password    = base64_encode($this->shortCode . $this->passKey . $timestamp);

        $url = $this->env === 'sandbox'
            ? 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            : 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

        $payload = [
            'BusinessShortCode' => $this->shortCode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => (int) round($amount),
            'PartyA'            => $partyA,
            'PartyB'            => $this->shortCode,
            'PhoneNumber'       => $partyA,
            'CallBackURL'       => $this->callbackUrl,
            'AccountReference'  => substr($accountReference, 0, 12),
            'TransactionDesc'   => 'JengaFund donation',
        ];

        return $this->darajaRequest('POST', $url, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ], json_encode($payload));
    }

    /**
     * Query STK transaction status by CheckoutRequestID.
     *
     * @return string Raw JSON response from Daraja
     */
    public function queryTransaction(string $checkoutRequestId): string
    {
        $accessToken = $this->getAccessToken();
        $timestamp   = date('YmdHis');
        $password    = base64_encode($this->shortCode . $this->passKey . $timestamp);

        $url = $this->env === 'sandbox'
            ? 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query'
            : 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query';

        $payload = [
            'BusinessShortCode' => $this->shortCode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        return $this->darajaRequest('POST', $url, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ], json_encode($payload));
    }

    /**
     * @param array<int, string> $headers
     */
    private function darajaRequest(string $method, string $url, array $headers, ?string $body = null): string
    {
        $attempts = 3;
        $lastError = 'Unknown Daraja error';

        for ($i = 1; $i <= $attempts; $i++) {
            $curl = curl_init();
            $options = [
                CURLOPT_URL            => $url,
                CURLOPT_HTTPHEADER     => array_merge([
                    'Accept: application/json',
                    'User-Agent: JengaFund/1.0',
                ], $headers),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT        => 30,
            ];

            if ($method === 'POST') {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = $body ?? '';
            }

            curl_setopt_array($curl, $options);
            $response = curl_exec($curl);
            $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($response === false) {
                $lastError = 'Network error contacting Safaricom: ' . $curlError;
            } elseif ($this->looksLikeBlockedHtml($response)) {
                $lastError = 'Safaricom sandbox temporarily blocked the request (Incapsula/WAF). Wait 30–60 seconds and try again.';
            } elseif ($httpCode >= 500) {
                $lastError = 'Safaricom server error (HTTP ' . $httpCode . '). Try again shortly.';
            } elseif ($httpCode >= 400 && $httpCode < 500) {
                $lastError = $this->parseDarajaError($response, 'Daraja HTTP ' . $httpCode);
            } else {
                return $response;
            }

            if ($i < $attempts) {
                usleep(500000 * $i);
            }
        }

        throw new RuntimeException($lastError);
    }

    private function looksLikeBlockedHtml(string $response): bool
    {
        $trimmed = ltrim($response);
        return str_starts_with($trimmed, '<')
            || stripos($response, 'Incapsula') !== false
            || stripos($response, '_Incapsula_Resource') !== false;
    }

    private function parseDarajaError(string $response, string $context): string
    {
        if ($this->looksLikeBlockedHtml($response)) {
            return $context . ': Safaricom sandbox blocked the connection (Incapsula). This is external — wait a minute, check your internet/VPN, then retry. Your app code is fine.';
        }

        $data = json_decode($response);
        if (isset($data->errorMessage)) {
            return $context . ': ' . $data->errorMessage;
        }
        if (isset($data->error_description)) {
            return $context . ': ' . $data->error_description;
        }

        $snippet = trim(preg_replace('/\s+/', ' ', $response));
        if (strlen($snippet) > 180) {
            $snippet = substr($snippet, 0, 180) . '…';
        }

        return $context . ' failed: ' . ($snippet !== '' ? $snippet : 'empty response from Safaricom');
    }
}
