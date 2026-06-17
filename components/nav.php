<?php
// Calculate the base URL dynamically to ensure portability between local development and production
$base_url = str_replace(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '', str_replace('\\', '/', dirname(__DIR__)));

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: Arial, sans-serif;
    }

    nav {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background-color: #f8f9fa;
      padding: 12px 32px;
      border-bottom: 1px solid #e0e0e0;
    }

    /* Logo */
    .navbar-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      font-size: 1.3rem;
      font-weight: 700;
      color: #333;
    }

    .navbar-brand img {
      height: 40px;
      width: auto;
    }

    /* Nav links */
    .navbar-nav {
      display: flex;
      align-items: center;
      list-style: none;
      gap: 8px;
    }

    .nav-link {
      text-decoration: none;
      color: #333;
      padding: 8px 16px;
      border-radius: 4px;
      font-size: 0.95rem;
      transition: color 0.2s;
    }

    .nav-link:hover {
      color: red;
    }

    /* Sign In button */
    .btn-signin {
      text-decoration: none;
      padding: 8px 20px;
      background-color: red;
      color: #fff;
      border-radius: 4px;
      font-size: 0.95rem;
      font-weight: 600;
      transition: background-color 0.2s;
    }

    .btn-signin:hover {
      background-color: #cc0000;
    }
  </style>

<nav>
  <a class="navbar-brand" href="<?php echo $base_url; ?>/pages/index.php">
    <img src="<?php echo $base_url; ?>/public/images/logo.jpeg" alt="JengaFund Logo">
    JengaFund
  </a>
  <br>

  <ul class="navbar-nav">
    <li><a class="nav-link" href="<?php echo $base_url; ?>/pages/index.php">Home</a></li>
    <li><a class="nav-link" href="about.php">About</a></li>
    <li><a class="nav-link" href="contact.php">Contact Us</a></li>
  </ul>
  
  <?php 
  // Detect if the user is currently viewing a dashboard page
  $is_dashboard = strpos($_SERVER['SCRIPT_NAME'], 'dashboard.php') !== false;
  if ($is_dashboard): ?>
    <a class="btn-signin" href="<?php echo $base_url; ?>/backend/logout.php">Logout</a>
  <?php else: ?>
    <a class="btn-signin" href="<?php echo $base_url; ?>/pages/login.php">Sign In</a>
  <?php endif; ?>
</nav>