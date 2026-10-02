<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Global Public Header Component
 * Location: /includes/header.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

$pageTitle = $pageTitle ?? 'Magal Creator - One Platform. Many Women Entrepreneurs.';
$metaDescription = $metaDescription ?? 'Discover and book services from inspiring women-owned businesses in fashion, beauty, henna, gourmet baking, wellness, and more in one digital marketplace.';
$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>
    <meta name="description" content="<?php echo e($metaDescription); ?>">
    
    <!-- Google Fonts & Font Awesome 6 -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
        
    <!-- Core & Components CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/components.css">
</head>
<body>

<header class="site-header">
    <div class="container">
        <nav class="navbar">
            <!-- Brand Logo -->
            <a href="<?php echo BASE_URL; ?>/index.php" class="navbar-brand d-flex align-items-center gap-2">
            <img src="<?php echo BASE_URL; ?>/assets/images/branding/Magal_Creator_Icon.png" alt="Magal Creator Logo" class="brand-logo-image">
            <span class="fw-bold fs-4" style="color: var(--color-primary);">Magal Creator</span>
            </a> 

            <!-- Navigation Links -->
            <ul class="nav-links">
                <li><a href="<?php echo BASE_URL; ?>/index.php" class="nav-link">Home</a></li>
                <li><a href="<?php echo BASE_URL; ?>/browse.php" class="nav-link">Explore Services</a></li>
                <li><a href="<?php echo BASE_URL; ?>/browse.php?tab=providers" class="nav-link">Women Entrepreneurs</a></li>
                <li><a href="<?php echo BASE_URL; ?>/index.php#how-it-works" class="nav-link">How It Works</a></li>
            </ul>

            <!-- Role Action Buttons -->
            <div class="nav-actions">
                <?php if ($currentUser): ?>
                    <?php if ($currentUser['role'] === 'manager'): ?>
                        <a href="<?php echo BASE_URL; ?>/manager/index.php" class="btn btn-secondary btn-sm">
                            <i class="fa-solid fa-shield-halved"></i> Manager Panel
                        </a>
                    <?php elseif ($currentUser['role'] === 'provider'): ?>
                        <a href="<?php echo BASE_URL; ?>/provider/index.php" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-store"></i> Provider Studio
                        </a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/user/my-orders.php" class="btn btn-outline btn-sm">
                            <i class="fa-solid fa-bag-shopping"></i> My Bookings
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-outline btn-sm" title="Log Out">
                        <i class="fa-solid fa-right-from-bracket"></i>
                    </a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/register-provider.php" class="btn btn-provider-cta btn-sm">
                        <i class="fa-solid fa-hand-holding-heart"></i> Join as Provider
                    </a>
                    <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-user"></i> Sign In
                    </a>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'provider'): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center gap-1" href="<?php echo BASE_URL; ?>/provider/magal-circle.php">
                    <span class="magal-circle-nav-logo">
                        <img src="<?php echo BASE_URL; ?>/assets/images/branding/Magal_Circle.png" alt="Magal Circle">
                    </span>
                    <span>Magal Circle</span>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Mobile Menu Hamburger -->
                <button class="nav-toggle-btn" aria-label="Toggle navigation">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </nav>
    </div>
</header>

<!-- Global Flash Messages Notification -->
<div class="container" style="margin-top: 20px;">
    <?php echo render_flash(); ?>
</div>

