<?php
if (!isset($base_url)) {
    $base_url = str_replace(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '', str_replace('\\', '/', dirname(__DIR__)));
}
?>
<footer class="jf-public-footer">
    <div class="jf-footer-inner">
        <div class="jf-footer-brand">
            <strong>JengaFund</strong>
            <p>Crowdfunding platform for student innovation projects across Kenya. Connect students with donors who believe in building the future.</p>
        </div>
        <div class="jf-footer-links">
            <h4>Explore</h4>
            <ul>
                <li><a href="<?php echo $base_url; ?>/pages/index.php">Home</a></li>
                <li><a href="<?php echo $base_url; ?>/pages/about.php">About Us</a></li>
                <li><a href="<?php echo $base_url; ?>/pages/faq.php">FAQ</a></li>
            </ul>
        </div>
        <div class="jf-footer-links">
            <h4>Get Started</h4>
            <ul>
                <li><a href="<?php echo $base_url; ?>/pages/signup.php">Create Account</a></li>
                <li><a href="<?php echo $base_url; ?>/pages/login.php">Sign In</a></li>
            </ul>
        </div>
    </div>
    <div class="jf-footer-bottom">
        &copy; <?php echo date('Y'); ?> JengaFund. All rights reserved.
    </div>
</footer>
