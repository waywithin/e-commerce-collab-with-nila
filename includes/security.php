<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Security & Input Sanitization Library
 * Location: /includes/security.php
 */

require_once __DIR__ . '/../config/config.php';

// -------------------------------------------------------------
// 1. SECURE SESSION INITIALIZATION
// -------------------------------------------------------------
function init_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        $cookieParams = [
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (APP_ENV === 'production' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')),
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        session_set_cookie_params($cookieParams);
        session_start();
    }
}

// Ensure session is started for all pages including this file
init_secure_session();

// -------------------------------------------------------------
// 2. CSRF PROTECTION (Cross-Site Request Forgery)
// -------------------------------------------------------------
/**
 * Generate or retrieve the current session's CSRF token
 * @return string
 */
function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden HTML CSRF input field
 * @return string
 */
function csrf_field(): string {
    $token = get_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate submitted CSRF token against session token
 * @param string|null $submittedToken
 * @return bool
 */
function verify_csrf_token(?string $submittedToken): bool {
    if (empty($submittedToken) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submittedToken);
}

// -------------------------------------------------------------
// 3. OUTPUT ESCAPING & INPUT SANITIZATION
// -------------------------------------------------------------
/**
 * Escape output string to prevent XSS (Cross-Site Scripting)
 * @param mixed $string
 * @return string
 */
function e(mixed $string): string {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize plain single-line string input
 * @param mixed $input
 * @return string
 */
function sanitize_string(mixed $input): string {
    $clean = trim((string)($input ?? ''));
    return strip_tags($clean);
}

/**
 * Sanitize multiline text (preserving newlines but stripping harmful scripts)
 * @param mixed $input
 * @return string
 */
function sanitize_textarea(mixed $input): string {
    $clean = trim((string)($input ?? ''));
    return htmlspecialchars($clean, ENT_QUOTES, 'UTF-8');
}

/**
 * Validate and sanitize email address
 * @param mixed $email
 * @return string|null Valid email or null
 */
function sanitize_email(mixed $email): ?string {
    $email = trim((string)($email ?? ''));
    $sanitized = filter_var($email, FILTER_SANITIZE_EMAIL);
    return filter_var($sanitized, FILTER_VALIDATE_EMAIL) ? $sanitized : null;
}

/**
 * Validate float/decimal monetary value
 * @param mixed $val
 * @return float
 */
function sanitize_amount(mixed $val): float {
    return max(0.0, (float) filter_var($val, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
}

// -------------------------------------------------------------
// 4. URL SLUG GENERATOR
// -------------------------------------------------------------
/**
 * Converts a title to a clean URL-friendly slug
 * @param string $text
 * @return string
 */
function slugify(string $text): string {
    // Replace non letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    // Transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
    // Trim
    $text = trim($text, '-');
    // Remove duplicate -
    $text = preg_replace('~-+~', '-', $text);
    // Lowercase
    $text = strtolower($text);

    return empty($text) ? 'item-' . time() : $text;
}

