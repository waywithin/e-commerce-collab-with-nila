<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Manager Command Center Header
 * Location: /manager/includes/header.php
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

require_manager();

$db = getDB();
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle ?? 'Manager Portal'); ?> | Magal Creator</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/components.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/dashboard.css">
</head>
<body>

<header class="site-header" style="border-bottom: 2px solid #881337;">
    <div style="padding: 0 24px;">
        <nav class="navbar">
            <a href="<?php echo BASE_URL; ?>/manager/index.php" class="brand-logo">
                <div class="brand-icon" style="background: linear-gradient(135deg, #881337, #4c0519);">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div class="brand-text">
                    <span class="brand-name">Magal Creator</span>
                    <span class="brand-tagline" style="color: #732D1D;">Platform Management</span>
                </div>
            </a>

            <div class="nav-actions">
                <a href="<?php echo BASE_URL; ?>/index.php" target="_blank" class="btn btn-outline btn-sm">
                    <i class="fa-solid fa-globe"></i> View Live Marketplace
                </a>
                <span style="font-size: 13px; font-weight: 600; color: #475569; padding: 0 8px;">
                    <i class="fa-solid fa-user-tie"></i> <?php echo e($_SESSION['user_name'] ?? 'Director'); ?>
                </span>
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
        <div class="sidebar-profile" style="background: #0f172a;">
            <div style="width: 44px; height: 44px; border-radius: 50%; background: #732D1D; color: white; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 800;">
                M
            </div>
            <div>
                <div class="sidebar-user-name">Platform Manager</div>
                <div class="sidebar-user-role" style="color: #38bdf8;">Super Administrator</div>
            </div>
        </div>

        <ul class="sidebar-nav">
            <li>
                <a href="<?php echo BASE_URL; ?>/manager/index.php" class="sidebar-link <?php echo ($currentScript === 'index.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gauge-high"></i> Dashboard Overview
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>/manager/providers.php" class="sidebar-link <?php echo ($currentScript === 'providers.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users-viewfinder"></i> Service Providers
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>/manager/categories.php" class="sidebar-link <?php echo ($currentScript === 'categories.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-tags"></i> Categories
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>/manager/products.php" class="sidebar-link <?php echo ($currentScript === 'products.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-box-open"></i> All Offerings
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>/manager/orders.php" class="sidebar-link <?php echo ($currentScript === 'orders.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-calendar-check"></i> Orders &amp; Bookings
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>/manager/commissions.php" class="sidebar-link <?php echo ($currentScript === 'commissions.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-hand-holding-dollar"></i> Commissions &amp; Payouts
                </a>
            </li>
            <li>
                <a href="<?php echo BASE_URL; ?>/manager/settings.php" class="sidebar-link <?php echo ($currentScript === 'settings.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-sliders"></i> Platform Settings
                </a>
            </li>
            <li style="margin-top: auto; padding-top: 20px; border-top: 1px solid #1f2937;">
                <a href="<?php echo BASE_URL; ?>/logout.php" class="sidebar-link" style="color: #f87171;">
                    <i class="fa-solid fa-right-from-bracket"></i> Sign Out
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="dashboard-main">
        <?php echo render_flash(); ?>

