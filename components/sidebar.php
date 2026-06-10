<?php
/**
 * Dashboard Sidebar Component
 * Features a sliding navigation panel from the right
 */
$base_url = str_replace(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '', str_replace('\\', '/', dirname(__DIR__)));
$role = $_SESSION['role'] ?? 'guest';
$userName = $_SESSION['user_name'] ?? 'Guest User';
$userEmail = $_SESSION['email'] ?? 'guest@jengafund.com';

// Generate initials for the avatar
$nameParts = explode(' ', trim($userName));
$initials = strtoupper(substr($nameParts[0], 0, 1));
if (count($nameParts) > 1) {
    $initials .= strtoupper(substr($nameParts[count($nameParts) - 1], 0, 1));
}
?>
<!-- Font Awesome for modern icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .db-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fff;
        padding: 12px 32px;
        border-bottom: 1px solid #e0e0e0;
        position: sticky;
        top: 0;
        z-index: 900;
    }
    .user-toggle-btn {
        font-size: 24px;
        cursor: pointer;
        color: #333;
        transition: color 0.2s;
    }
    .user-toggle-btn:hover { color: red; }
    
    /* Sidebar Styles */
    .sidebar-container {
        position: fixed;
        top: 10px;
        right: 10px;
        width: 320px;
        height: calc(100vh - 20px);
        background: white;
        box-shadow: -5px 0 15px rgba(0,0,0,0.1);
        transition: transform 0.3s ease-in-out, visibility 0.3s;
        transform: translateX(120%); /* Properly hidden further away */
        visibility: hidden;
        z-index: 1001;
        padding: 30px;
        border-radius: 20px; /* Added smooth border radius */
        box-sizing: border-box;
    }
    .sidebar-container.active { 
        transform: translateX(0); 
        visibility: visible;
    }
    
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.4);
        display: none;
        z-index: 1000;
    }
    .sidebar-overlay.active { display: block; }
    
    .sidebar-close { font-size: 24px; cursor: pointer; color: #666; float: left; margin-bottom: 30px; }
    .sidebar-close:hover { color: red; }
    
    .sidebar-menu { list-style: none; padding: 0; clear: both; }
    .sidebar-menu li { margin-bottom: 10px; }
    .sidebar-menu a {
        text-decoration: none;
        color: #333;
        font-size: 1rem;
        padding: 12px 15px;
        display: flex;
        align-items: center;
        gap: 15px;
        border-radius: 6px;
        transition: background 0.2s;
    }
    .sidebar-menu a:hover { background: #f8f9fa; color: red; }
    
    .sidebar-menu i {
        width: 20px; /* Fixed width for icon alignment */
        text-align: center;
        font-size: 1.1rem;
    }

    /* Profile Section Styling */
    .user-profile-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid #f0f0f0;
    }
    .avatar-circle {
        width: 70px;
        height: 70px;
        background-color: red;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: bold;
        margin-bottom: 12px;
        box-shadow: 0 4px 8px rgba(255, 0, 0, 0.2);
    }
    .profile-name { font-weight: 700; color: #333; margin: 0; font-size: 1.1rem; }
    .profile-email { font-size: 0.85rem; color: #888; margin: 4px 0 0; }
</style>

<header class="db-header">
    <a href="<?php echo $base_url; ?>/pages/index.php" class="navbar-brand" style="display: flex; align-items: center; gap: 10px; text-decoration:none; color:#333; font-weight:700; font-size:1.3rem;">
        <img src="<?php echo $base_url; ?>/public/images/logo.jpeg" alt="JengaFund Logo" style="height: 40px; width: auto;">
        <span>JengaFund</span>
    </a>
    <div class="user-toggle-btn" onclick="toggleSidebar()"><i class="fa-regular fa-circle-user"></i></div>
</header>

<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="sidebar-container" id="dashboardSidebar">
    <span class="sidebar-close" onclick="toggleSidebar()"><i class="fa-solid fa-xmark"></i></span>
    
    <div class="user-profile-header">
        <div class="avatar-circle"><?php echo $initials; ?></div>
        <h3 class="profile-name"><?php echo htmlspecialchars($userName); ?></h3>
        <p class="profile-email"><?php echo htmlspecialchars($userEmail); ?></p>
    </div>

    <ul class="sidebar-menu">
        <?php if ($role === 'student'): ?>
            <li><a href="#"><i class="fa-solid fa-list-check"></i> My Campaigns</a></li>
            <li><a href="#"><i class="fa-solid fa-bell"></i> Notifications</a></li>
            <li><a href="#"><i class="fa-solid fa-gear"></i> Settings</a></li>
        <?php elseif ($role === 'donor'): ?>
            <li><a href="#"><i class="fa-solid fa-earth-africa"></i> Campaigns</a></li>
            <li><a href="#"><i class="fa-solid fa-bell"></i> Notifications</a></li>
            <li><a href="#"><i class="fa-solid fa-gear"></i> Settings</a></li>
        <?php endif; ?>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
        <li><a href="<?php echo $base_url; ?>/backend/logout.php" style="color: red; font-weight: 600;"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
    </ul>
</div>

<script>
function toggleSidebar() {
    document.getElementById('dashboardSidebar').classList.toggle('active');
    document.querySelector('.sidebar-overlay').classList.toggle('active');
}
</script>