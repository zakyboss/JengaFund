<?php
$currentPage = 'faq';
require_once __DIR__ . '/../components/nav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FAQ | JengaFund</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/app.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/public.css">
</head>
<body class="jf-public">

<main class="jf-content-page">
    <h1>Frequently asked questions</h1>
    <p class="jf-lead">Answers for students, donors, and anyone exploring JengaFund.</p>

    <div class="jf-faq-list">
        <details class="jf-faq-item">
            <summary>What is JengaFund?</summary>
            <div class="jf-faq-answer">JengaFund is a crowdfunding platform for student innovation projects. Students create campaigns, donors contribute via M-PESA, and administrators review accounts and campaigns to keep the process trustworthy.</div>
        </details>

        <details class="jf-faq-item">
            <summary>Who can sign up?</summary>
            <div class="jf-faq-answer">Students and donors can create accounts. Students must upload verification documents (such as ID and academic certificate) and wait for admin approval before launching campaigns. Donors can browse and donate once their account is active.</div>
        </details>

        <details class="jf-faq-item">
            <summary>How do I donate to a campaign?</summary>
            <div class="jf-faq-answer">Sign in as a donor, open a campaign from Checkout Campaigns, enter the amount and your M-PESA phone number, then approve the STK Push on your phone. You will receive a receipt under My Donations once payment is confirmed.</div>
        </details>

        <details class="jf-faq-item">
            <summary>How does M-PESA payment work?</summary>
            <div class="jf-faq-answer">When you click donate, JengaFund sends an M-PESA STK Push to your phone. Enter your PIN to complete payment. Safaricom sends a callback to confirm the transaction, and your donation status updates automatically. If confirmation is slow, the system also checks payment status directly with Safaricom.</div>
        </details>

        <details class="jf-faq-item">
            <summary>What are milestone campaigns?</summary>
            <div class="jf-faq-answer">For campaigns with a goal of KES 30 or more, funds are released in milestones. The student submits evidence for each stage (photos, documents, etc.), and an admin approves before the next portion is disbursed. This keeps donors accountable to real progress.</div>
        </details>

        <details class="jf-faq-item">
            <summary>What happens when a small campaign reaches its goal?</summary>
            <div class="jf-faq-answer">Campaigns under KES 30 use a full payout model. When the goal is fully funded, the campaign moves to awaiting disbursement and an admin can mark it as paid to the student. No milestone evidence is required for these smaller projects.</div>
        </details>

        <details class="jf-faq-item">
            <summary>How long does student approval take?</summary>
            <div class="jf-faq-answer">After you sign up and verify your email, an administrator reviews your documents. You will receive a notification when your account is approved or if more information is needed. You cannot publish campaigns until your account is approved.</div>
        </details>

        <details class="jf-faq-item">
            <summary>Can I edit my campaign after submitting?</summary>
            <div class="jf-faq-answer">Yes. Students can update campaign details from My Campaigns while the campaign is still open. Major changes may require admin review depending on campaign status.</div>
        </details>

        <details class="jf-faq-item">
            <summary>Is my payment information stored?</summary>
            <div class="jf-faq-answer">JengaFund does not store M-PESA PINs. We only record the donation amount, status, and Safaricom reference needed for receipts and campaign accounting. Payments are processed through Safaricom Daraja API.</div>
        </details>

        <details class="jf-faq-item">
            <summary>What if my M-PESA payment fails or times out?</summary>
            <div class="jf-faq-answer">If you cancel the STK Push or do not enter your PIN in time, the donation is marked failed and no money is taken. You can try again. During development, the Safaricom sandbox can occasionally be slow. If payment succeeded on your phone but the app is still waiting, check My Donations after a minute or contact support with your M-PESA message.</div>
        </details>

        <details class="jf-faq-item">
            <summary>How do I get help?</summary>
            <div class="jf-faq-answer">Use the in-app Notifications page for updates on your account and campaigns. For exam or demo support, refer to your project supervisor or platform administrator.</div>
        </details>
    </div>

    <div class="jf-hero-actions" style="justify-content: flex-start; margin-top: 40px;">
        <a class="jf-btn jf-btn-brand" href="<?php echo $base_url; ?>/pages/signup.php">Create account</a>
        <a class="jf-btn jf-btn-outline" href="<?php echo $base_url; ?>/pages/about.php">About us</a>
    </div>
</main>

<?php require_once __DIR__ . '/../components/public_footer.php'; ?>
</body>
</html>
