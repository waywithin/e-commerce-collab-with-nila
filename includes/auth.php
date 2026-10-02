<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Authentication & Role Authorization Library
 * Location: /includes/auth.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';

// -------------------------------------------------------------
// 1. STATE & USER CHECKERS
// -------------------------------------------------------------
/**
 * Check if a user is currently authenticated
 * @return bool
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Get the current user's profile array from session
 * @return array|null
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'],
        'name'      => $_SESSION['user_name'] ?? 'User',
        'email'     => $_SESSION['user_email'] ?? '',
        'role'      => $_SESSION['user_role'] ?? 'customer',
        'provider_id' => $_SESSION['provider_id'] ?? null
    ];
}

function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function current_user_role(): ?string {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Returns the service_providers.id linked to current user if role is provider
 * @return int|null
 */
function current_provider_id(): ?int {
    return $_SESSION['provider_id'] ?? null;
}

// -------------------------------------------------------------
// 2. ACCESS GUARDS & ROUTE PROTECTORS
// -------------------------------------------------------------
/**
 * Enforce that the user must be logged in
 * @param string|null $redirectAfter
 */
function require_login(?string $redirectAfter = null): void {
    if (!is_logged_in()) {
        $target = $redirectAfter ?? $_SERVER['REQUEST_URI'];
        header("Location: " . BASE_URL . "/login.php?redirect=" . urlencode($target));
        exit;
    }
}

/**
 * Enforce a specific role or redirect with 403 Forbidden
 * @param string|array $allowedRoles
 */
function require_role(string|array $allowedRoles): void {
    require_login();
    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    $currentRole = current_user_role();

    if (!in_array($currentRole, $roles, true)) {
        http_response_code(403);
        include __DIR__ . '/../403.php';
        exit;
    }
}

/** Guard for Manager-only routes */
function require_manager(): void {
    require_role('manager');
}

/** Guard for Service Provider-only routes */
function require_provider(): void {
    require_role('provider');
    $db = getDB();
    $stmt = $db->prepare("SELECT id, approval_status FROM service_providers WHERE user_id = :uid LIMIT 1");
    $stmt->execute(['uid' => current_user_id()]);
    $provider = $stmt->fetch();

    if (!$provider) {
        http_response_code(403);
        die("Access Denied: No service provider profile found for this account.");
    }

    if ($provider['approval_status'] !== 'approved') {
        http_response_code(403);
        die($provider['approval_status'] === 'pending'
            ? 'Your provider account is awaiting manager approval.'
            : 'Your provider account is not approved. Please contact the platform manager.');
    }

    $_SESSION['provider_id'] = (int)$provider['id'];
    $_SESSION['approval_status'] = $provider['approval_status'];
}

/** Guard for Customer-only routes */
function require_customer(): void {
    require_role('customer');
}

/**
 * Verify that the currently logged-in provider owns the specified product
 * @param int $productId
 * @return array The product data if authorized
 */
function verify_product_ownership(int $productId): array {
    require_provider();
    $providerId = current_provider_id();
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM products WHERE id = :pid AND provider_id = :prov_id LIMIT 1");
    $stmt->execute(['pid' => $productId, 'prov_id' => $providerId]);
    $product = $stmt->fetch();

    if (!$product) {
        http_response_code(403);
        die("<div style='font-family:sans-serif;padding:30px;text-align:center;'>
            <h2>403 - Unauthorized Access</h2>
            <p>You do not have permission to view or modify this listing.</p>
            <p><a href='" . BASE_URL . "/provider/products.php'>Return to My Listings</a></p>
        </div>");
    }

    return $product;
}

// -------------------------------------------------------------
// 3. AUTHENTICATION ACTIONS (Login, Register, Logout)
// -------------------------------------------------------------
/**
 * Attempt user login with email and plain password
 * @param string $email
 * @param string $password
 * @return array ['success' => bool, 'message' => string, 'role' => string|null]
 */
function attempt_login(string $email, string $password): array {
    $db = getDB();
    $email = strtolower(trim($email));

    $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid email address or password.'];
    }

    if ($user['status'] !== 'active') {
        return ['success' => false, 'message' => 'Your account is currently inactive or suspended. Please contact support.'];
    }

    $provider = null;
    if ($user['role'] === 'provider') {
        $pstmt = $db->prepare("SELECT id, business_name, approval_status FROM service_providers WHERE user_id = :uid LIMIT 1");
        $pstmt->execute(['uid' => $user['id']]);
        $provider = $pstmt->fetch();

        if (!$provider) {
            return ['success' => false, 'message' => 'No service provider profile is linked to this account.'];
        }

        if ($provider['approval_status'] !== 'approved') {
            return [
                'success' => false,
                'message' => $provider['approval_status'] === 'pending'
                    ? 'Your provider registration is awaiting manager approval.'
                    : 'Your provider account is not approved. Please contact the platform manager.'
            ];
        }
    }

    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    if ($provider) {
        $_SESSION['provider_id'] = (int)$provider['id'];
        $_SESSION['business_name'] = $provider['business_name'];
        $_SESSION['approval_status'] = $provider['approval_status'];
    }

    // Log activity
    log_activity($user['id'], 'LOGIN', 'user', $user['id'], 'User logged in successfully.');

    return ['success' => true, 'message' => 'Login successful', 'role' => $user['role']];
}

/**
 * Register a standard Customer
 */
function register_customer(string $name, string $email, string $phone, string $password): array {
    $db = getDB();
    $email = strtolower(trim($email));

    // Check duplicate email
    $check = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $check->execute(['email' => $email]);
    if ($check->fetch()) {
        return ['success' => false, 'message' => 'An account with this email address already exists.'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $db->prepare("INSERT INTO users (full_name, email, phone, password_hash, role, status) 
                          VALUES (:name, :email, :phone, :hash, 'customer', 'active')");
    $stmt->execute([
        'name'  => $name,
        'email' => $email,
        'phone' => $phone,
        'hash'  => $hash
    ]);

    $userId = (int)$db->lastInsertId();
    log_activity($userId, 'REGISTER_CUSTOMER', 'user', $userId, 'New customer registration.');

    return ['success' => true, 'user_id' => $userId];
}

/**
 * Register a Service Provider (Woman Entrepreneur)
 */
function register_provider(
    string $fullName,
    string $email,
    string $phone,
    string $password,
    string $businessName,
    int $categoryId,
    string $description,
    string $city,
    string $location
): array {
    $db = getDB();
    $email = strtolower(trim($email));

    // Check duplicate email
    $check = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $check->execute(['email' => $email]);
    if ($check->fetch()) {
        return ['success' => false, 'message' => 'An account with this email address already exists.'];
    }

    try {
        $db->beginTransaction();

        // 1. Create User
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $uStmt = $db->prepare("INSERT INTO users (full_name, email, phone, password_hash, role, status) 
                               VALUES (:name, :email, :phone, :hash, 'provider', 'active')");
        $uStmt->execute([
            'name'  => $fullName,
            'email' => $email,
            'phone' => $phone,
            'hash'  => $hash
        ]);
        $userId = (int)$db->lastInsertId();

        // 2. Create Service Provider Profile
        $slug = slugify($businessName);
        // Ensure unique slug
        $sCheck = $db->prepare("SELECT id FROM service_providers WHERE slug = :slug LIMIT 1");
        $sCheck->execute(['slug' => $slug]);
        if ($sCheck->fetch()) {
            $slug .= '-' . $userId;
        }

        $pStmt = $db->prepare("INSERT INTO service_providers 
            (user_id, business_name, slug, category_id, description, location, city, contact_phone, contact_email, approval_status)
            VALUES (:uid, :bname, :slug, :cat_id, :desc, :loc, :city, :phone, :email, 'pending')");
        $pStmt->execute([
            'uid'     => $userId,
            'bname'   => $businessName,
            'slug'    => $slug,
            'cat_id'  => $categoryId,
            'desc'    => $description,
            'loc'     => $location,
            'city'    => $city,
            'phone'   => $phone,
            'email'   => $email
        ]);
        $providerId = (int)$db->lastInsertId();

        $db->commit();
        log_activity($userId, 'REGISTER_PROVIDER', 'service_provider', $providerId, 'New service provider registration: ' . $businessName);

        return ['success' => true, 'user_id' => $userId, 'provider_id' => $providerId];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
    }
}

/**
 * Terminate user session and logout
 */
function logout_user(): void {
    if (is_logged_in()) {
        log_activity(current_user_id(), 'LOGOUT', 'user', current_user_id(), 'User logged out.');
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Record action in audit log
 */
function log_activity(?int $userId, string $action, string $entityType, ?int $entityId, ?string $details = null): void {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, ip_address, details) 
                              VALUES (:uid, :act, :etype, :eid, :ip, :det)");
        $stmt->execute([
            'uid'   => $userId,
            'act'   => $action,
            'etype' => $entityType,
            'eid'   => $entityId,
            'ip'    => $ip,
            'det'   => $details
        ]);
    } catch (Exception) {
        // Suppress logging failures to never crash the main application
    }
}

