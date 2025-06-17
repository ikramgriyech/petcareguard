

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        @import url('https://fonts.cdnfonts.com/css/cooper-black');

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

header {
    background: white;
    padding: 1rem 0;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.56);
    width: 80%;
    margin: 0 auto;
    margin-top: 20px;
    height: 67px;
    border-radius:12px;
}

nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    height: 80%;
  
}

.logo {
    display: flex;
    width: 78px;
}

.nav-links {
    display: flex;
    list-style: none;
    gap: 2rem;
    margin-left: 200px;
}

.nav-links a {
    text-decoration: none;
    color: #666;
    transition: color 0.3s;
    margin-left: 40px;
}

.nav-links a:hover {
    color: #333;
}

.login-btn {
    background: transparent;
    border: 2px solid #333;
    padding: 0.5rem 1.5rem;
    border-radius: 25px;
    text-decoration: none;
    color: #333;
    transition: all 0.3s;
}

.login-btn:hover {
    background: #333;
    color: white;
}
.header-container {
 padding: 0 20px;   
}
/* Responsive header */
/* @media (max-width: 768px) {
    .nav-links {
        display: none;
    }
} */

    </style>
</head>
<body>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detect current page
$currentPage = basename($_SERVER['PHP_SELF']);
$isAuthPage = strpos($_SERVER['PHP_SELF'], '/auth/') !== false;

// Paths
$logoutPath = $isAuthPage ? '../auth/logout.php' : 'auth/logout.php';
$loginPath = $isAuthPage ? '../auth/login.php' : 'auth/login.php';
$profilePath = $isAuthPage ? '../profile.php' : 'profile.php';
$logoPath = $isAuthPage ? '../assets/img/logo.png' : 'assets/img/logo.png';
?>

<header>
  <nav class="header-container">
    <img src="<?= $logoPath ?>" class="logo">

    <ul class="nav-links">
      <?php if (isset($_SESSION['user'])): ?>
        <?php if ($currentPage === 'login.php'): ?>
          <!-- If on login.php and logged in, show only Home -->
          <li><a href="#contact">Contact</a></li>
        <li><a href="#services">Services</a></li>
        <li><a href="#learn">Home</a></li>
        <?php else: ?>
          <!-- If on other pages and logged in, show all -->
          <li><a href="dashboard.php">Dashboard</a></li>
          <li><a href="caregivers.php">Cargivers</a></li>
          <li><a href="landing.php">Home</a></li>
          <li><a href="<?= $profilePath ?>">My Profile</a></li>
        <?php endif; ?>

      <?php endif; ?>
    </ul>

    <?php if (isset($_SESSION['user'])): ?>
      <a href="<?= $logoutPath ?>" class="login-btn">Log out</a>
    <?php else: ?>
      <a href="<?= $loginPath ?>" class="login-btn">Log in</a>
    <?php endif; ?>
  </nav>
</header>



</body>
</html>