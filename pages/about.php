<?php
$currentPage = 'about';
require_once __DIR__ . '/../components/nav.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us | JengaFund</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/app.css">
    <link rel="stylesheet" href="<?php echo $base_url; ?>/assets/css/public.css">
</head>
<body class="jf-public">

<main class="jf-content-page">
    <h1>About JengaFund</h1>
    <p class="jf-lead">We help students turn bold ideas into funded reality, with transparency, verification, and secure M-PESA payments.</p>

    <div class="jf-content-block">
        <h2>Our mission</h2>
        <p>JengaFund exists to lower the barrier between student innovation and the resources needed to execute it. Too many promising projects stall because funding is hard to access and hard to trust. We built a platform where students can present their work professionally and donors can contribute with confidence.</p>
    </div>

    <div class="jf-content-block">
        <h2>Who we serve</h2>
        <ul>
            <li><strong>Students:</strong> Create campaigns for school or personal innovation projects, upload verification documents, and receive funds through milestone-based or full payouts.</li>
            <li><strong>Donors:</strong> Discover vetted campaigns, donate via M-PESA, and track every contribution in My Donations.</li>
            <li><strong>Administrators:</strong> Review student accounts and campaigns, approve disbursements, and keep the platform safe and accountable.</li>
        </ul>
    </div>

    <div class="jf-content-block">
        <h2>What makes us different</h2>
        <div class="jf-values">
            <div class="jf-value-card">
                <h3>Verified students</h3>
                <p>Students submit ID and academic documents. Admins approve accounts before campaigns can go live.</p>
            </div>
            <div class="jf-value-card">
                <h3>Campaign review</h3>
                <p>Every campaign is reviewed by an admin before donors can contribute. No unvetted requests go live on the platform.</p>
            </div>
            <div class="jf-value-card">
                <h3>M-PESA integration</h3>
                <p>Donations happen through Safaricom M-PESA STK Push. Familiar, fast, and traceable for everyone involved.</p>
            </div>
            <div class="jf-value-card">
                <h3>Milestone accountability</h3>
                <p>Larger campaigns release funds in stages as students submit evidence, so donors see progress before more money moves.</p>
            </div>
        </div>
    </div>

    <div class="jf-content-block">
        <h2>Built for Kenya</h2>
        <p>JengaFund is built for how Kenyans move money (M-PESA first) and how student projects run: proposals, timelines, proof of work, and admin oversight. Whether you're pitching a health initiative, a tech prototype, or a community project, JengaFund gives you a structured place to ask for support.</p>
    </div>

    <div class="jf-hero-actions" style="justify-content: flex-start;">
        <a class="jf-btn jf-btn-brand" href="<?php echo $base_url; ?>/pages/signup.php">Join JengaFund</a>
        <a class="jf-btn jf-btn-outline" href="<?php echo $base_url; ?>/pages/faq.php">Read the FAQ</a>
    </div>
</main>

<?php require_once __DIR__ . '/../components/public_footer.php'; ?>
</body>
</html>
