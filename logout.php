<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Logout Script
 * Location: /logout.php
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

logout_user();
set_flash('info', 'You have been safely signed out. Thank you for visiting Magal Creator.');
header("Location: " . BASE_URL . "/login.php");
exit;

