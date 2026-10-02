<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Service Provider Dashboard Header
 * Location: /provider/includes/header.php
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_provider();

$db = getDB();
$providerId = current_provider_id();

// Fetch provider profile
$spStmt = $db->prepare("SELECT * FROM service_providers WHERE id = :id LIMIT 1");
$spStmt->execute(['id' => $providerId]);
$currentProvider = $spStmt->fetch();

$currentScript = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? 'Entrepreneur Studio'); ?> | Magal Creator</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/components.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/dashboard.css">
</head>
<body>

<!-- Top Navigation Bar -->
<header class="site-header">
    <div style="padding: 0 24px;">
        <nav class="navbar">
            <a href="<?php echo BASE_URL; ?>/provider/index.php" class="brand-logo">
                <div class="brand-icon">
                    <i class="fa-solid fa-crown"></i>
                </div>
                <div class="brand-text">
                    <span class="brand-name">Magal Creator</span>
                    <span class="brand-tagline">Entrepreneur Studio</span>
                </div>
            </a>

            <div class="nav-actions">
                <a href="<?php echo BASE_URL; ?>/provider-profile.php?slug=<?php echo urlencode($currentProvider['slug']); ?>" target="_blank" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> View Public Profile
                </a>
                <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-globe"></i> Marketplace
                </a>
                <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-outline btn-sm" title="Log Out">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </nav>
    </div>
</header>

<div class="dashboard-wrapper">
    <!-- Sidebar Navigation -->
    <aside class="dashboard-sidebar">
        <div class="sidebar-profile">
            <img src="<?php echo e(get_image_url($currentProvider['logo_image'], 'provider')); ?>" alt="<?php echo e($currentProvider['business_name']); ?>" class="sidebar-avatar">
            <div>
                <div class="sidebar-user-name"><?php echo e($currentProvider['business_name']); ?></div>
                <div class="sidebar-user-role"><?php echo e($_SESSION['user_name'] ?? 'Founder'); ?></div>
            </div>
        </div>

        <ul class="sidebar-nav">
            <li>
                <a href="<?php echo BASE_URL; ?>/provider/index.php" class="sidebar-link <?php echo ($currentScript === 'index.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-pie"></i> Studio Overview
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>/provider/products.php" class="sidebar-link <?php echo (in_array($currentScript, ['products.php', 'product-edit.php'])) ? 'active' : ''; ?>">
                    <i class="fa-solid fa-box-open"></i> My Offerings
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>/provider/product-add.php" class="sidebar-link <?php echo ($currentScript === 'product-add.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-plus-circle"></i> Add New Offering
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>/provider/orders.php" class="sidebar-link <?php echo ($currentScript === 'orders.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-calendar-check"></i> Bookings &amp; Orders
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>/provider/settlements.php" class="sidebar-link <?php echo ($currentScript === 'settlements.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-wallet"></i> Payouts &amp; Settlements
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>/provider/profile.php" class="sidebar-link <?php echo ($currentScript === 'profile.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-store"></i> Business Profile
                </a>
            </li>
            <li style="margin-top: auto; padding-top: 20px; border-top: 1px solid #1f2937;">
                <a href="<?php echo BASE_URL; ?>/logout.php" class="sidebar-link" style="color: #f87171;">
                    <i class="fa-solid fa-right-from-bracket"></i> Sign Out
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content Body -->
    <main class="dashboard-main">
        <?php echo render_flash(); ?>

