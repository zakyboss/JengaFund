<?php
/**
 * Site favicon — uses the JengaFund logo from public/images.
 */
if (!isset($base_url)) {
    $base_url = str_replace(
        str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']),
        '',
        str_replace('\\', '/', dirname(__DIR__))
    );
}

$iconUrl = htmlspecialchars($base_url . '/public/images/logo.jpeg', ENT_QUOTES, 'UTF-8');
?>
<link rel="icon" type="image/jpeg" href="<?php echo $iconUrl; ?>">
<link rel="shortcut icon" type="image/jpeg" href="<?php echo $iconUrl; ?>">
<link rel="apple-touch-icon" href="<?php echo $iconUrl; ?>">
