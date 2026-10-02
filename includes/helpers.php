 <?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Global Helper Functions & Offer Engine
 * Location: /includes/helpers.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';

// -------------------------------------------------------------
// 1. CURRENCY & FORMATTING HELPERS
// -------------------------------------------------------------
/**
 * Format an amount in Indian Rupees (INR)
 * @param float|int $amount
 * @param bool $showSymbol
 * @return string
 */
function format_price(float|int $amount, bool $showSymbol = true): string {
    $formatted = number_format((float)$amount, 2);
    return $showSymbol ? CURRENCY_SYMBOL . ' ' . $formatted : $formatted;
}

/**
 * Format date for human reading
 * @param string|null $datetime
 * @param bool $includeTime
 * @return string
 */
function format_date(?string $datetime, bool $includeTime = false): string {
    if (empty($datetime)) {
        return 'N/A';
    }
    $format = $includeTime ? 'd M Y, h:i A' : 'd M Y';
    return date($format, strtotime($datetime));
}

// -------------------------------------------------------------
// 2. LIMITED-TIME OFFER CALCULATION ENGINE
// Server-side authoritative evaluation of product price & countdown
// -------------------------------------------------------------
/**
 * Determine the exact offer state and effective price for a product.
 * Evaluates against authoritative server time.
 *
 * @param array $product Record containing price, discount_price, offer_enabled, offer_price, offer_start_at, offer_end_at
 * @return array [
 *     'has_offer'          => bool,
 *     'status'             => 'ACTIVE' | 'UPCOMING' | 'EXPIRED' | 'NONE',
 *     'effective_price'    => float,
 *     'original_price'     => float,
 *     'savings_amount'     => float,
 *     'savings_percent'    => int,
 *     'remaining_seconds'  => int,
 *     'starts_in_seconds'  => int,
 *     'end_timestamp_iso'  => string|null,
 *     'start_timestamp_iso'=> string|null
 * ]
 */
function get_product_offer_details(array $product): array {
    $regularPrice = (float)($product['price'] ?? 0);
    $discountPrice = !empty($product['discount_price']) ? (float)$product['discount_price'] : null;
    
    // Default fallback state (Normal pricing)
    $effectivePrice = $discountPrice !== null ? $discountPrice : $regularPrice;
    $result = [
        'has_offer'           => false,
        'status'              => 'NONE',
        'effective_price'     => $effectivePrice,
        'original_price'      => $regularPrice,
        'savings_amount'      => max(0, $regularPrice - $effectivePrice),
        'savings_percent'     => ($regularPrice > 0) ? (int)round((($regularPrice - $effectivePrice) / $regularPrice) * 100) : 0,
        'remaining_seconds'   => 0,
        'starts_in_seconds'   => 0,
        'end_timestamp_iso'   => null,
        'start_timestamp_iso' => null
    ];

    // Check if the limited-time offer feature is toggled on and valid
    if (empty($product['offer_enabled']) || empty($product['offer_price']) || empty($product['offer_start_at']) || empty($product['offer_end_at'])) {
        return $result;
    }

    $now = time();
    $startTime = strtotime($product['offer_start_at']);
    $endTime = strtotime($product['offer_end_at']);
    $offerPrice = (float)$product['offer_price'];

    $result['start_timestamp_iso'] = date('c', $startTime);
    $result['end_timestamp_iso'] = date('c', $endTime);

    // 1. ACTIVE OFFER: current time is between start and end
    if ($now >= $startTime && $now < $endTime) {
        $result['has_offer'] = true;
        $result['status'] = 'ACTIVE';
        $result['effective_price'] = $offerPrice;
        $result['original_price'] = $regularPrice;
        $result['savings_amount'] = max(0, $regularPrice - $offerPrice);
        $result['savings_percent'] = ($regularPrice > 0) ? (int)round((($regularPrice - $offerPrice) / $regularPrice) * 100) : 0;
        $result['remaining_seconds'] = max(0, $endTime - $now);
    }
    // 2. UPCOMING OFFER: current time is before start
    elseif ($now < $startTime) {
        $result['has_offer'] = true;
        $result['status'] = 'UPCOMING';
        // Effective price remains regular price until offer activates
        $result['effective_price'] = $discountPrice ?? $regularPrice;
        $result['starts_in_seconds'] = max(0, $startTime - $now);
    }
    // 3. EXPIRED OFFER: current time has passed end
    else {
        $result['has_offer'] = false;
        $result['status'] = 'EXPIRED';
        $result['effective_price'] = $discountPrice ?? $regularPrice;
        $result['remaining_seconds'] = 0;
    }

    return $result;
}

// -------------------------------------------------------------
// 3. PLATFORM SETTINGS ACCESSOR
// -------------------------------------------------------------
/**
 * Retrieve a setting value from platform_settings table
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function get_platform_setting(string $key, mixed $default = null): mixed {
    static $settingsCache = [];

    if (empty($settingsCache)) {
        try {
            $db = getDB();
            $stmt = $db->query("SELECT setting_key, setting_value FROM platform_settings");
            while ($row = $stmt->fetch()) {
                $settingsCache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception) {
            return $default;
        }
    }

    return $settingsCache[$key] ?? $default;
}

// -------------------------------------------------------------
// 4. FLASH NOTIFICATIONS (Session-based alerts)
// -------------------------------------------------------------
/**
 * Set a one-time flash notification message
 * @param string $type 'success', 'danger', 'warning', 'info'
 * @param string $message
 */
function set_flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        init_secure_session();
    }
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Retrieve and clear the flash message
 * @return array|null
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Render flash message as an alert HTML block
 * @return string
 */
function render_flash(): string {
    $flash = get_flash();
    if (!$flash) {
        return '';
    }

    $type = htmlspecialchars($flash['type']);
    $msg = htmlspecialchars($flash['message']);

    $icon = match($type) {
        'success' => 'fa-check-circle',
        'danger'  => 'fa-exclamation-circle',
        'warning' => 'fa-triangle-exclamation',
        default   => 'fa-circle-info'
    };

    return '<div class="alert alert-' . $type . '">
        <i class="fa-solid ' . $icon . '"></i>
        <span>' . $msg . '</span>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>';
}

// -------------------------------------------------------------
// 5. IMAGE URL RESOLVER
// -------------------------------------------------------------
/**
 * Resolves an image path from DB to a valid web URL with fallback
 * @param string|null $path
 * @param string $type 'product' or 'provider' or 'banner'
 * @return string
 */
function get_image_url(?string $path, string $type = 'product'): string {
    if (!empty($path)) {
        // If stored as full path 'uploads/products/xyz.jpg'
        if (file_exists(ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path))) {
            return BASE_URL . '/' . $path;
        }
        // If stored as just filename
        $folder = ($type === 'provider' || $type === 'banner') ? 'providers' : 'products';
        $fullPath = ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $path;
        if (file_exists($fullPath)) {
            return BASE_URL . '/uploads/' . $folder . '/' . $path;
        }
    }

    // Default fallback SVG/PNG placeholder
    return BASE_URL . '/assets/images/placeholder-' . $type . '.svg';
}

