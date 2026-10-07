<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Manager Global Reports & Data Export Center
 * Location: /manager/reports.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Enforce manager authorization
require_manager();
$db = getDB();

$pageTitle = 'Marketplace Reports & Exports';

/**
 * Defense against CSV Formula Injection (CWE-1236)
 * Prepends a single quote if string begins with =, +, -, @, \t, \r
 */
function escape_csv_cell($val) {
    if ($val === null) {
        return '';
    }
    $str = (string)$val;
    if (preg_match('/^[\=\+\-\@\t\r]/', $str)) {
        return "'" . $str;
    }
    return $str;
}

// --------------------------------------------------------------------
// 1. REPORT SELECTION & PARAMS
// --------------------------------------------------------------------
$allowedReports = ['users', 'providers', 'listings', 'orders', 'subscriptions'];
$report = sanitize_string($_GET['report'] ?? 'users');
if (!in_array($report, $allowedReports, true)) {
    $report = 'users';
}

$export = sanitize_string($_GET['export'] ?? '');
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Common Dropdowns for Filters
$categoriesList = $db->query("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$providersList = $db->query("SELECT id, business_name FROM service_providers ORDER BY business_name ASC")->fetchAll();

// --------------------------------------------------------------------
// 2. BUILD DATASETS PER REPORT TYPE
// --------------------------------------------------------------------
$whereClauses = [];
$queryParams = [];
$summaryStats = [];
$totalRecords = 0;
$reportRows = [];
$csvHeaders = [];
$csvRowMapper = null;
$appliedFilterDescriptions = [];

if ($report === 'users') {
    // ------------------- USERS REPORT -------------------
    $role = sanitize_string($_GET['role'] ?? 'all');
    $status = sanitize_string($_GET['status'] ?? 'all');
    $search = sanitize_string($_GET['search'] ?? '');
    $dateFrom = sanitize_string($_GET['date_from'] ?? '');
    $dateTo = sanitize_string($_GET['date_to'] ?? '');

    $allowedRoles = ['all', 'customer', 'provider', 'manager'];
    $allowedStatuses = ['all', 'active', 'inactive', 'suspended'];
    if (!in_array($role, $allowedRoles, true)) $role = 'all';
    if (!in_array($status, $allowedStatuses, true)) $status = 'all';

    // Normalize date order if inverted
    if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
        $temp = $dateFrom;
        $dateFrom = $dateTo;
        $dateTo = $temp;
    }

    if ($role !== 'all') {
        $whereClauses[] = "u.role = :role";
        $queryParams['role'] = $role;
        $appliedFilterDescriptions[] = "Role: " . ucfirst($role);
    }
    if ($status !== 'all') {
        $whereClauses[] = "u.status = :status";
        $queryParams['status'] = $status;
        $appliedFilterDescriptions[] = "Status: " . ucfirst($status);
    }
    if ($search !== '') {
        $whereClauses[] = "(u.full_name LIKE :s1 OR u.email LIKE :s2 OR u.phone LIKE :s3)";
        $queryParams['s1'] = "%{$search}%";
        $queryParams['s2'] = "%{$search}%";
        $queryParams['s3'] = "%{$search}%";
        $appliedFilterDescriptions[] = "Search: '{$search}'";
    }
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $whereClauses[] = "u.created_at >= :df";
        $queryParams['df'] = "{$dateFrom} 00:00:00";
        $appliedFilterDescriptions[] = "From: {$dateFrom}";
    }
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $whereClauses[] = "u.created_at <= :dt";
        $queryParams['dt'] = "{$dateTo} 23:59:59";
        $appliedFilterDescriptions[] = "To: {$dateTo}";
    }

    $whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

    // Real Summary Aggregates
    $sumStmt = $db->prepare("
        SELECT 
            COUNT(*) AS total_count,
            SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN u.status = 'suspended' THEN 1 ELSE 0 END) AS suspended_count,
            SUM(CASE WHEN u.role = 'customer' THEN 1 ELSE 0 END) AS customer_count,
            SUM(CASE WHEN u.role = 'provider' THEN 1 ELSE 0 END) AS provider_count
        FROM users u
        {$whereSQL}
    ");
    $sumStmt->execute($queryParams);
    $summaryStats = $sumStmt->fetch() ?: ['total_count' => 0, 'active_count' => 0, 'suspended_count' => 0, 'customer_count' => 0, 'provider_count' => 0];
    $totalRecords = (int)$summaryStats['total_count'];

    // Base query without passwords/secrets
    $baseSQL = "
        SELECT u.id, u.full_name, u.email, u.phone, u.role, u.status, u.created_at, u.updated_at
        FROM users u
        {$whereSQL}
        ORDER BY u.id DESC
    ";

    $csvHeaders = ['User ID', 'Full Name', 'Email', 'Phone', 'Role', 'Status', 'Registered At', 'Last Updated'];
    $csvRowMapper = function($r) {
        return [
            $r['id'],
            $r['full_name'],
            $r['email'],
            $r['phone'] ?? 'N/A',
            ucfirst($r['role']),
            ucfirst($r['status']),
            $r['created_at'],
            $r['updated_at']
        ];
    };

} elseif ($report === 'providers') {
    // ------------------- BUSINESS OWNERS REPORT -------------------
    $approvalStatus = sanitize_string($_GET['approval_status'] ?? 'all');
    $featured = sanitize_string($_GET['featured'] ?? 'all');
    $categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: 0;
    $city = sanitize_string($_GET['city'] ?? '');
    $search = sanitize_string($_GET['search'] ?? '');
    $dateFrom = sanitize_string($_GET['date_from'] ?? '');
    $dateTo = sanitize_string($_GET['date_to'] ?? '');

    $allowedApprovals = ['all', 'pending', 'approved', 'rejected'];
    if (!in_array($approvalStatus, $allowedApprovals, true)) $approvalStatus = 'all';

    // Normalize date order if inverted
    if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
        $temp = $dateFrom;
        $dateFrom = $dateTo;
        $dateTo = $temp;
    }

    if ($approvalStatus !== 'all') {
        $whereClauses[] = "sp.approval_status = :app_st";
        $queryParams['app_st'] = $approvalStatus;
        $appliedFilterDescriptions[] = "Approval: " . ucfirst($approvalStatus);
    }
    if ($featured === '1') {
        $whereClauses[] = "sp.is_featured = 1";
        $appliedFilterDescriptions[] = "Featured: Yes";
    } elseif ($featured === '0') {
        $whereClauses[] = "sp.is_featured = 0";
        $appliedFilterDescriptions[] = "Featured: Standard";
    }
    if ($categoryId > 0) {
        $whereClauses[] = "sp.category_id = :cat_id";
        $queryParams['cat_id'] = $categoryId;
        $appliedFilterDescriptions[] = "Category ID: {$categoryId}";
    }
    if ($city !== '') {
        $whereClauses[] = "sp.city LIKE :city";
        $queryParams['city'] = "%{$city}%";
        $appliedFilterDescriptions[] = "City: '{$city}'";
    }
    if ($search !== '') {
        $whereClauses[] = "(sp.business_name LIKE :s1 OR u.full_name LIKE :s2 OR u.email LIKE :s3 OR sp.contact_phone LIKE :s4)";
        $queryParams['s1'] = "%{$search}%";
        $queryParams['s2'] = "%{$search}%";
        $queryParams['s3'] = "%{$search}%";
        $queryParams['s4'] = "%{$search}%";
        $appliedFilterDescriptions[] = "Search: '{$search}'";
    }
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $whereClauses[] = "sp.created_at >= :df";
        $queryParams['df'] = "{$dateFrom} 00:00:00";
        $appliedFilterDescriptions[] = "From: {$dateFrom}";
    }
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $whereClauses[] = "sp.created_at <= :dt";
        $queryParams['dt'] = "{$dateTo} 23:59:59";
        $appliedFilterDescriptions[] = "To: {$dateTo}";
    }

    $whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

    // Real Summary Aggregates
    $sumStmt = $db->prepare("
        SELECT 
            COUNT(*) AS total_count,
            SUM(CASE WHEN sp.approval_status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
            SUM(CASE WHEN sp.approval_status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
            SUM(CASE WHEN sp.approval_status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
            SUM(CASE WHEN sp.is_featured = 1 THEN 1 ELSE 0 END) AS featured_count
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        LEFT JOIN categories c ON sp.category_id = c.id
        {$whereSQL}
    ");
    $sumStmt->execute($queryParams);
    $summaryStats = $sumStmt->fetch() ?: ['total_count' => 0, 'approved_count' => 0, 'pending_count' => 0, 'rejected_count' => 0, 'featured_count' => 0];
    $totalRecords = (int)$summaryStats['total_count'];

    // Query excluding bank credentials/secrets
    $baseSQL = "
        SELECT sp.id, sp.business_name, sp.slug, sp.city, sp.location, sp.contact_phone, sp.contact_email,
               sp.approval_status, sp.is_featured, sp.created_at,
               u.full_name AS owner_name, u.email AS owner_email, u.status AS user_status,
               c.name AS category_name
        FROM service_providers sp
        JOIN users u ON sp.user_id = u.id
        LEFT JOIN categories c ON sp.category_id = c.id
        {$whereSQL}
        ORDER BY sp.id DESC
    ";

    $csvHeaders = ['Provider ID', 'Business Name', 'Owner Name', 'Account Email', 'Contact Phone', 'Category', 'City', 'Location', 'Approval Status', 'Featured', 'Account Status', 'Registered At'];
    $csvRowMapper = function($r) {
        return [
            $r['id'],
            $r['business_name'],
            $r['owner_name'],
            $r['owner_email'],
            $r['contact_phone'] ?? 'N/A',
            $r['category_name'] ?? 'Uncategorized',
            $r['city'] ?? 'N/A',
            $r['location'] ?? 'N/A',
            ucfirst($r['approval_status']),
            ((int)$r['is_featured'] === 1) ? 'Yes' : 'No',
            ucfirst($r['user_status']),
            $r['created_at']
        ];
    };

} elseif ($report === 'listings') {
    // ------------------- LISTINGS / OFFERINGS REPORT -------------------
    $serviceType = sanitize_string($_GET['service_type'] ?? 'all');
    $status = sanitize_string($_GET['status'] ?? 'all');
    $categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: 0;
    $providerId = filter_input(INPUT_GET, 'provider_id', FILTER_VALIDATE_INT) ?: 0;
    $search = sanitize_string($_GET['search'] ?? '');
    $dateFrom = sanitize_string($_GET['date_from'] ?? '');
    $dateTo = sanitize_string($_GET['date_to'] ?? '');

    $allowedTypes = ['all', 'service', 'product', 'package'];
    $allowedStatuses = ['all', 'active', 'draft', 'inactive'];
    if (!in_array($serviceType, $allowedTypes, true)) $serviceType = 'all';
    if (!in_array($status, $allowedStatuses, true)) $status = 'all';

    // Normalize date order if inverted
    if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
        $temp = $dateFrom;
        $dateFrom = $dateTo;
        $dateTo = $temp;
    }

    if ($serviceType !== 'all') {
        $whereClauses[] = "p.service_type = :stype";
        $queryParams['stype'] = $serviceType;
        $appliedFilterDescriptions[] = "Type: " . ucfirst($serviceType);
    }
    if ($status !== 'all') {
        $whereClauses[] = "p.status = :status";
        $queryParams['status'] = $status;
        $appliedFilterDescriptions[] = "Status: " . ucfirst($status);
    }
    if ($categoryId > 0) {
        $whereClauses[] = "p.category_id = :cat_id";
        $queryParams['cat_id'] = $categoryId;
        $appliedFilterDescriptions[] = "Category ID: {$categoryId}";
    }
    if ($providerId > 0) {
        $whereClauses[] = "p.provider_id = :prov_id";
        $queryParams['prov_id'] = $providerId;
        $appliedFilterDescriptions[] = "Provider ID: {$providerId}";
    }
    if ($search !== '') {
        $whereClauses[] = "(p.title LIKE :s1 OR sp.business_name LIKE :s2)";
        $queryParams['s1'] = "%{$search}%";
        $queryParams['s2'] = "%{$search}%";
        $appliedFilterDescriptions[] = "Search: '{$search}'";
    }
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $whereClauses[] = "p.created_at >= :df";
        $queryParams['df'] = "{$dateFrom} 00:00:00";
        $appliedFilterDescriptions[] = "From: {$dateFrom}";
    }
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $whereClauses[] = "p.created_at <= :dt";
        $queryParams['dt'] = "{$dateTo} 23:59:59";
        $appliedFilterDescriptions[] = "To: {$dateTo}";
    }

    $whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

    // Real Summary Aggregates
    $sumStmt = $db->prepare("
        SELECT 
            COUNT(*) AS total_count,
            SUM(CASE WHEN p.status = 'active' THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN p.status = 'draft' THEN 1 ELSE 0 END) AS draft_count,
            SUM(CASE WHEN p.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_count,
            SUM(CASE WHEN p.offer_enabled = 1 AND p.offer_end_at > NOW() THEN 1 ELSE 0 END) AS promo_count,
            COALESCE(AVG(p.price), 0) AS avg_price
        FROM products p
        JOIN service_providers sp ON p.provider_id = sp.id
        LEFT JOIN categories c ON p.category_id = c.id
        {$whereSQL}
    ");
    $sumStmt->execute($queryParams);
    $summaryStats = $sumStmt->fetch() ?: ['total_count' => 0, 'active_count' => 0, 'draft_count' => 0, 'inactive_count' => 0, 'promo_count' => 0, 'avg_price' => 0];
    $totalRecords = (int)$summaryStats['total_count'];

    $baseSQL = "
        SELECT p.id, p.title, p.slug, p.service_type, p.price, p.discount_price, p.status,
               p.offer_enabled, p.offer_price, p.created_at, p.updated_at,
               sp.business_name, sp.slug AS provider_slug,
               c.name AS category_name
        FROM products p
        JOIN service_providers sp ON p.provider_id = sp.id
        LEFT JOIN categories c ON p.category_id = c.id
        {$whereSQL}
        ORDER BY p.id DESC
    ";

    $csvHeaders = ['Offering ID', 'Title', 'Business Name', 'Category', 'Service Type', 'Price (INR)', 'Discount Price (INR)', 'Status', 'Promo Active', 'Promo Price (INR)', 'Created At', 'Last Updated'];
    $csvRowMapper = function($r) {
        return [
            $r['id'],
            $r['title'],
            $r['business_name'],
            $r['category_name'] ?? 'Uncategorized',
            ucfirst($r['service_type']),
            $r['price'],
            $r['discount_price'] ?? 'None',
            ucfirst($r['status']),
            ((int)$r['offer_enabled'] === 1) ? 'Yes' : 'No',
            $r['offer_price'] ?? 'N/A',
            $r['created_at'],
            $r['updated_at']
        ];
    };

} elseif ($report === 'orders') {
    // ------------------- ORDERS & BOOKINGS REPORT -------------------
    $orderStatus = sanitize_string($_GET['order_status'] ?? 'all');
    $paymentStatus = sanitize_string($_GET['payment_status'] ?? 'all');
    $providerId = filter_input(INPUT_GET, 'provider_id', FILTER_VALIDATE_INT) ?: 0;
    $search = sanitize_string($_GET['search'] ?? '');
    $dateFrom = sanitize_string($_GET['date_from'] ?? '');
    $dateTo = sanitize_string($_GET['date_to'] ?? '');

    $allowedOrderStatuses = ['all', 'pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
    $allowedPaymentStatuses = ['all', 'completed', 'processing', 'failed', 'created'];
    if (!in_array($orderStatus, $allowedOrderStatuses, true)) $orderStatus = 'all';
    if (!in_array($paymentStatus, $allowedPaymentStatuses, true)) $paymentStatus = 'all';

    // Normalize date order if inverted
    if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
        $temp = $dateFrom;
        $dateFrom = $dateTo;
        $dateTo = $temp;
    }

    if ($orderStatus !== 'all') {
        $whereClauses[] = "o.order_status = :ost";
        $queryParams['ost'] = $orderStatus;
        $appliedFilterDescriptions[] = "Order Status: " . ucfirst($orderStatus);
    }
    if ($paymentStatus !== 'all') {
        $whereClauses[] = "pay.payment_status = :pst";
        $queryParams['pst'] = $paymentStatus;
        $appliedFilterDescriptions[] = "Payment Status: " . ucfirst($paymentStatus);
    }
    if ($providerId > 0) {
        $whereClauses[] = "o.provider_id = :prov_id";
        $queryParams['prov_id'] = $providerId;
        $appliedFilterDescriptions[] = "Provider ID: {$providerId}";
    }
    if ($search !== '') {
        $whereClauses[] = "(o.order_number LIKE :s1 OR o.customer_name LIKE :s2 OR o.customer_phone LIKE :s3 OR sp.business_name LIKE :s4)";
        $queryParams['s1'] = "%{$search}%";
        $queryParams['s2'] = "%{$search}%";
        $queryParams['s3'] = "%{$search}%";
        $queryParams['s4'] = "%{$search}%";
        $appliedFilterDescriptions[] = "Search: '{$search}'";
    }
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $whereClauses[] = "o.created_at >= :df";
        $queryParams['df'] = "{$dateFrom} 00:00:00";
        $appliedFilterDescriptions[] = "From: {$dateFrom}";
    }
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $whereClauses[] = "o.created_at <= :dt";
        $queryParams['dt'] = "{$dateTo} 23:59:59";
        $appliedFilterDescriptions[] = "To: {$dateTo}";
    }

    $whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

    // Real Summary Aggregates (using isolated subqueries for commissions & payments to guarantee 0 duplication)
    $sumStmt = $db->prepare("
        SELECT 
            COUNT(*) AS total_count,
            COALESCE(SUM(o.total_amount), 0) AS total_gmv,
            COALESCE(SUM(comm.platform_commission_amount), 0) AS total_commission,
            COALESCE(SUM(comm.provider_payable_amount), 0) AS total_payable,
            SUM(CASE WHEN o.order_status = 'completed' THEN 1 ELSE 0 END) AS completed_count
        FROM orders o
        JOIN service_providers sp ON o.provider_id = sp.id
        JOIN products p ON o.product_id = p.id
        LEFT JOIN (
            SELECT order_id, 
                   SUM(platform_commission_amount) AS platform_commission_amount, 
                   SUM(provider_payable_amount) AS provider_payable_amount 
            FROM commissions 
            GROUP BY order_id
        ) comm ON o.id = comm.order_id
        LEFT JOIN (
            SELECT order_id, 
                   MAX(payment_status) AS payment_status, 
                   MAX(payment_method) AS payment_method, 
                   MAX(gateway_name) AS gateway_name 
            FROM payments 
            GROUP BY order_id
        ) pay ON o.id = pay.order_id
        {$whereSQL}
    ");
    $sumStmt->execute($queryParams);
    $summaryStats = $sumStmt->fetch() ?: ['total_count' => 0, 'total_gmv' => 0, 'total_commission' => 0, 'total_payable' => 0, 'completed_count' => 0];
    $totalRecords = (int)$summaryStats['total_count'];

    // Query excluding payment gateway tokens/secrets with guaranteed 1-to-1 row mapping
    $baseSQL = "
        SELECT o.id, o.order_number, o.customer_name, o.customer_phone, o.unit_price, o.quantity, o.total_amount,
               o.order_status, o.booking_date, o.created_at,
               sp.business_name, p.title AS product_title, p.service_type,
               comm.platform_commission_amount, comm.provider_payable_amount,
               pay.payment_status, pay.payment_method, pay.gateway_name
        FROM orders o
        JOIN service_providers sp ON o.provider_id = sp.id
        JOIN products p ON o.product_id = p.id
        LEFT JOIN (
            SELECT order_id, 
                   SUM(platform_commission_amount) AS platform_commission_amount, 
                   SUM(provider_payable_amount) AS provider_payable_amount 
            FROM commissions 
            GROUP BY order_id
        ) comm ON o.id = comm.order_id
        LEFT JOIN (
            SELECT order_id, 
                   MAX(payment_status) AS payment_status, 
                   MAX(payment_method) AS payment_method, 
                   MAX(gateway_name) AS gateway_name 
            FROM payments 
            GROUP BY order_id
        ) pay ON o.id = pay.order_id
        {$whereSQL}
        ORDER BY o.id DESC
    ";

    $csvHeaders = ['Order ID', 'Order Number', 'Date', 'Customer Name', 'Customer Phone', 'Business Name', 'Offering Title', 'Type', 'Qty', 'Total Amount (INR)', 'Platform Commission (INR)', 'Provider Payout (INR)', 'Order Status', 'Payment Status', 'Payment Method', 'Gateway'];
    $csvRowMapper = function($r) {
        return [
            $r['id'],
            $r['order_number'],
            $r['created_at'],
            $r['customer_name'],
            $r['customer_phone'],
            $r['business_name'],
            $r['product_title'],
            ucfirst($r['service_type']),
            $r['quantity'],
            $r['total_amount'],
            $r['platform_commission_amount'] ?? '0.00',
            $r['provider_payable_amount'] ?? '0.00',
            ucfirst($r['order_status']),
            ucfirst($r['payment_status'] ?? 'Unpaid'),
            $r['payment_method'] ?? 'N/A',
            strtoupper($r['gateway_name'] ?? 'N/A')
        ];
    };
}

// --------------------------------------------------------------------
// 3. HANDLE SERVER-SIDE CSV EXPORT (BEFORE ANY HTML OUTPUT)
// --------------------------------------------------------------------
if ($export === 'csv' && $report !== 'subscriptions' && !empty($baseSQL)) {
    $cleanReportName = str_replace('_', '-', $report);
    $filename = "{$cleanReportName}-report-" . date('Y-m-d_His') . ".csv";

    // Clean output buffer to ensure pure CSV stream
    if (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // Output UTF-8 BOM for Microsoft Excel compatibility
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Write header row
    fputcsv($out, $csvHeaders);

    // Stream records row-by-row
    $exportStmt = $db->prepare($baseSQL);
    $exportStmt->execute($queryParams);

    while ($row = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
        $mappedRow = $csvRowMapper ? $csvRowMapper($row) : array_values($row);
        $safeRow = array_map('escape_csv_cell', $mappedRow);
        fputcsv($out, $safeRow);
    }

    fclose($out);
    exit;
}

// --------------------------------------------------------------------
// 4. FETCH PAGINATED RECORDS FOR WEB UI
// --------------------------------------------------------------------
if ($report !== 'subscriptions' && !empty($baseSQL)) {
    $paginatedSQL = $baseSQL . " LIMIT :limit OFFSET :offset";
    $pageStmt = $db->prepare($paginatedSQL);
    foreach ($queryParams as $k => $v) {
        $pageStmt->bindValue(":{$k}", $v);
    }
    $pageStmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
    $pageStmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $pageStmt->execute();
    $reportRows = $pageStmt->fetchAll(PDO::FETCH_ASSOC);
}

$totalPages = ($totalRecords > 0) ? (int)ceil($totalRecords / $perPage) : 1;

// Build query string helper for pagination & exports
function build_filter_url($extraParams = []) {
    $params = array_merge($_GET, $extraParams);
    unset($params['export']); // avoid sticky export param
    return '?' . http_build_query($params);
}

include __DIR__ . '/includes/header.php';
?>

<!-- Print-Friendly CSS Styles -->
<style>
@media print {
    body {
        background: #ffffff !important;
        color: #000000 !important;
        font-size: 10pt;
    }
    .site-header,
    .dashboard-sidebar,
    .report-tabs-nav,
    .report-filter-bar,
    .report-actions,
    .pagination-wrapper,
    .nav-actions,
    .btn,
    .alert {
        display: none !important;
    }
    .dashboard-wrapper {
        display: block !important;
        min-height: auto !important;
    }
    .dashboard-main {
        padding: 0 !important;
        background: #ffffff !important;
    }
    .print-only-header {
        display: block !important;
        margin-bottom: 20px;
        border-bottom: 2px solid #8C3A27;
        padding-bottom: 12px;
    }
    .data-table-card {
        border: 1px solid #cccccc !important;
        box-shadow: none !important;
        margin-bottom: 20px !important;
    }
    .data-table th {
        background: #f1f5f9 !important;
        color: #000000 !important;
        border-bottom: 1px solid #cccccc !important;
        font-size: 9pt !important;
        padding: 8px 10px !important;
    }
    .data-table td {
        border-bottom: 1px solid #eeeeee !important;
        font-size: 9pt !important;
        padding: 8px 10px !important;
    }
}
.print-only-header {
    display: none;
}
</style>

<!-- Print Preview Header -->
<div class="print-only-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="font-size: 20px; color: #8C3A27; margin: 0;">Magal Creator Multi-Vendor Marketplace</h2>
            <h3 style="font-size: 15px; color: #334155; margin: 4px 0 0 0;">
                <?php 
                $titles = [
                    'users'     => 'Registered Users Report',
                    'providers' => 'Business Owners / Service Providers Report',
                    'listings'  => 'Marketplace Offerings & Listings Report',
                    'orders'    => 'Orders & Transaction Ledger Report',
                ];
                echo $titles[$report] ?? 'Marketplace Report';
                ?>
            </h3>
        </div>
        <div style="text-align: right; font-size: 11px; color: #64748b;">
            <div>Generated: <?php echo date('d M Y, h:i A'); ?></div>
            <div>Total Records: <strong><?php echo $totalRecords; ?></strong></div>
        </div>
    </div>
    <?php if (!empty($appliedFilterDescriptions)): ?>
        <div style="margin-top: 8px; font-size: 11px; color: #475569;">
            <strong>Applied Filters:</strong> <?php echo e(implode(" | ", $appliedFilterDescriptions)); ?>
        </div>
    <?php endif; ?>
</div>

<!-- Header Title & Action Buttons -->
<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-file-invoice" style="color: #8C3A27;"></i>
            Marketplace Reports &amp; Exports
        </h1>
        <p style="font-size: 14px; color: #64748b;">
            Exportable operational records, business owner compliance logs, catalog status, and order ledgers.
        </p>
    </div>

    <!-- Export & Print Actions -->
    <div class="report-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
        <?php if ($report !== 'subscriptions'): ?>
            <a href="<?php echo build_filter_url(['export' => 'csv']); ?>" class="btn btn-primary btn-sm" style="display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-file-csv"></i> Export CSV
            </a>
            <button type="button" onclick="window.print();" class="btn btn-outline btn-sm" style="display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-print"></i> Print Report
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Report Navigation Tabs -->
<div class="report-tabs-nav" style="display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 2px solid var(--border-color); padding-bottom: 12px; flex-wrap: wrap;">
    <a href="?report=users" class="btn btn-sm <?php echo ($report === 'users') ? 'btn-primary' : 'btn-outline'; ?>" style="border-radius: 20px; font-size: 13px;">
        <i class="fa-solid fa-users"></i> Users
    </a>
    <a href="?report=providers" class="btn btn-sm <?php echo ($report === 'providers') ? 'btn-primary' : 'btn-outline'; ?>" style="border-radius: 20px; font-size: 13px;">
        <i class="fa-solid fa-users-viewfinder"></i> Business Owners
    </a>
    <a href="?report=listings" class="btn btn-sm <?php echo ($report === 'listings') ? 'btn-primary' : 'btn-outline'; ?>" style="border-radius: 20px; font-size: 13px;">
        <i class="fa-solid fa-box-open"></i> Listings &amp; Offerings
    </a>
    <a href="?report=orders" class="btn btn-sm <?php echo ($report === 'orders') ? 'btn-primary' : 'btn-outline'; ?>" style="border-radius: 20px; font-size: 13px;">
        <i class="fa-solid fa-calendar-check"></i> Orders &amp; Bookings
    </a>
    <a href="?report=subscriptions" class="btn btn-sm <?php echo ($report === 'subscriptions') ? 'btn-primary' : 'btn-outline'; ?>" style="border-radius: 20px; font-size: 13px;">
        <i class="fa-solid fa-receipt"></i> Subscriptions
    </a>
</div>

<?php if ($report === 'subscriptions'): ?>
    <!-- ==========================================================================
         SUBSCRIPTIONS REPORT STATE (Strictly Not Tracked / Module Inactive)
         ========================================================================== -->
    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 48px 24px; text-align: center; box-shadow: var(--shadow-sm); max-width: 680px; margin: 40px auto;">
        <div style="width: 56px; height: 56px; border-radius: 50%; background: #F1E0DB; color: #8C3A27; display: flex; align-items: center; justify-content: center; font-size: 24px; margin: 0 auto 16px auto;">
            <i class="fa-solid fa-receipt"></i>
        </div>
        <h3 style="font-size: 18px; color: var(--secondary); margin-bottom: 8px;">Subscription Reporting Unavailable</h3>
        <p style="font-size: 14px; color: #64748b; line-height: 1.6; margin-bottom: 20px;">
            Subscription reporting is not available yet — subscription billing module is not implemented in the current database schema.
        </p>
        <span class="badge" style="background: #f1f5f9; color: #64748b; font-size: 12px; padding: 6px 14px; border: 1px solid #e2e8f0;">
            <i class="fa-solid fa-shield-halved"></i> Data Transparency Policy: Zero Mocked Records
        </span>
    </div>

<?php else: ?>

    <!-- ==========================================================================
         SUMMARY CARDS FOR ACTIVE REPORT
         ========================================================================== -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
        <!-- Total Matching Records -->
        <div class="stat-card" style="padding: 16px 20px;">
            <div>
                <div class="stat-value" style="font-size: 22px; color: #0f172a;"><?php echo $totalRecords; ?></div>
                <div class="stat-label">Total Matching Records</div>
            </div>
            <div class="stat-icon primary" style="width: 40px; height: 40px; font-size: 18px;">
                <i class="fa-solid fa-list-ol"></i>
            </div>
        </div>

        <?php if ($report === 'users'): ?>
            <div class="stat-card" style="padding: 16px 20px;">
                <div>
                    <div class="stat-value" style="font-size: 22px; color: #059669;"><?php echo (int)($summaryStats['active_count'] ?? 0); ?></div>
                    <div class="stat-label">Active Users</div>
                </div>
                <div class="stat-icon success" style="width: 40px; height: 40px; font-size: 18px;">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            </div>
            <div class="stat-card" style="padding: 16px 20px;">
                <div>
                    <div class="stat-value" style="font-size: 22px; color: #7e22ce;"><?php echo (int)($summaryStats['customer_count'] ?? 0); ?></div>
                    <div class="stat-label">Customers</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: 8px; background: #f3e8ff; color: #7e22ce; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                    <i class="fa-solid fa-user-tag"></i>
                </div>
            </div>
            <div class="stat-card" style="padding: 16px 20px;">
                <div>
                    <div class="stat-value" style="font-size: 22px; color: #8C3A27;"><?php echo (int)($summaryStats['provider_count'] ?? 0); ?></div>
                    <div class="stat-label">Provider Accounts</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: 8px; background: #F1E0DB; color: #8C3A27; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                    <i class="fa-solid fa-store"></i>
                </div>
            </div>

        <?php elseif ($report === 'providers'): ?>
            <div class="stat-card" style="padding: 16px 20px;">
                <div>
                    <div class="stat-value" style="font-size: 22px; color: #059669;"><?php echo (int)($summaryStats['approved_count'] ?? 0); ?></div>
                    <div class="stat-label">Approved Profiles</div>
                </div>
                <div class="stat-icon success" style="width: 40px; height: 40px; font-size: 18px;">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
            </div>
            <div class="stat-card" style="padding: 16px 20px;">
                <div>
                    <div class="stat-value" style="font-size: 22px; color: #d97706;"><?php echo (int)($summaryStats['pending_count'] ?? 0); ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-icon warning" style="width: 40px; height: 40px; font-size: 18px;">
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>
            <div class="stat-card" style="padding: 16px 20px;">
                <div>
                    <div class="stat-value" style="font-size: 22px; color: #7e22ce;"><?php echo (int)($summaryStats['featured_count'] ?? 0); ?></div>
                    <div class="stat-label">Featured Spotlight</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: 8px; background: #f3e8ff; color: #7e22ce; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                    <i class="fa-solid fa-star"></i>
                </div>
            </div>

        <?php elseif ($report === 'listings'): ?>
            <div class="stat-card" style="padding: 16px 20px;">
                <div>
                    <div class="stat-value" style="font-size: 22px; color: #059669;"><?php echo (int)($summaryStats['active_count'] ?? 0); ?></div>
                    <div class="stat-label">Active &amp; Live</div>
                </div>
                <div class="stat-icon success" style="width: 40px; height: 40px; font-size: 18px;">
                    <i class="fa-solid fa-box-archive"></i>
                </div>
            </div>
            <div class="stat-card" style="padding: 16px 20px;">
                <div>
                    <div class="stat-value" style="font-size: 22px; color: #d97706;"><?php echo (int)($summaryStats['draft_count'] ?? 0); ?></div>
                    <div class="stat-label">Draft / Hidden</div>
                </div>
                <div class="stat-icon warning" style="width: 40px; height: 40px; font-size: 18px;">
                    <i class="fa-solid fa-file-pen"></i>
                </div>
            </div>
            <div class="stat-card" style="padding: 16px 20px;">
                <div>
                    <div class="stat-value" style="font-size: 22px; color: #8C3A27;"><?php echo format_price((float)($summaryStats['avg_price'] ?? 0)); ?></div>
                    <div class="stat-label">Average Price</div>
                </div>
                <div style="width: 40px; height: 40px; border-radius: 8px; background: #F1E0DB; color: #8C3A27; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                    <i class="fa-solid fa-tag"></i>
                </div>
            </div>

        <?php elseif ($report === 'orders'): ?>
            <div class="stat-card" style="padding: 16px 20px;">
                <div>
                    <div class="stat-value" style="font-size: 22px; color: #0f172a;"><?php echo format_price((float)($summaryStats['total_gmv'] ?? 0)); ?></div>
                    <div class="stat-label">Matching Gross GMV</div>
                </div>
                <div class="stat-icon primary" style="width: 40px; height: 40px; font-size: 18px;">
                    <i class="fa-solid fa-indian-rupee-sign"></i>
                </div>
            </div>
            <div class="stat-card" style="padding: 16px 20px;">
                <div>
                    <div class="stat-value" style="font-size: 22px; color: #059669;"><?php echo format_price((float)($summaryStats['total_commission'] ?? 0)); ?></div>
                    <div class="stat-label">Platform Commission</div>
                </div>
                <div class="stat-icon success" style="width: 40px; height: 40px; font-size: 18px;">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
            </div>
            <div class="stat-card" style="padding: 16px 20px;">
                <div>
                    <div class="stat-value" style="font-size: 22px; color: #d97706;"><?php echo format_price((float)($summaryStats['total_payable'] ?? 0)); ?></div>
                    <div class="stat-label">Provider Payable</div>
                </div>
                <div class="stat-icon warning" style="width: 40px; height: 40px; font-size: 18px;">
                    <i class="fa-solid fa-building-columns"></i>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ==========================================================================
         FILTER FORM BAR
         ========================================================================== -->
    <div class="report-filter-bar" style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 18px 20px; margin-bottom: 24px; box-shadow: var(--shadow-sm);">
        <form method="GET" action="<?php echo BASE_URL; ?>/manager/reports.php" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;">
            <input type="hidden" name="report" value="<?php echo e($report); ?>">

            <?php if ($report === 'users'): ?>
                <div style="flex: 1; min-width: 140px;">
                    <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Role</label>
                    <select name="role" class="form-control" style="font-size: 13px; height: 38px;">
                        <option value="all" <?php echo ($role === 'all') ? 'selected' : ''; ?>>All Roles</option>
                        <option value="customer" <?php echo ($role === 'customer') ? 'selected' : ''; ?>>Customer</option>
                        <option value="provider" <?php echo ($role === 'provider') ? 'selected' : ''; ?>>Provider</option>
                        <option value="manager" <?php echo ($role === 'manager') ? 'selected' : ''; ?>>Manager</option>
                    </select>
                </div>
                <div style="flex: 1; min-width: 140px;">
                    <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Status</label>
                    <select name="status" class="form-control" style="font-size: 13px; height: 38px;">
                        <option value="all" <?php echo ($status === 'all') ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="active" <?php echo ($status === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo ($status === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>

            <?php elseif ($report === 'providers'): ?>
                <div style="flex: 1; min-width: 140px;">
                    <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Approval Status</label>
                    <select name="approval_status" class="form-control" style="font-size: 13px; height: 38px;">
                        <option value="all" <?php echo ($approvalStatus === 'all') ? 'selected' : ''; ?>>All Approvals</option>
                        <option value="approved" <?php echo ($approvalStatus === 'approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="pending" <?php echo ($approvalStatus === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="rejected" <?php echo ($approvalStatus === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div style="flex: 1; min-width: 130px;">
                    <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Featured</label>
                    <select name="featured" class="form-control" style="font-size: 13px; height: 38px;">
                        <option value="all" <?php echo ($featured === 'all') ? 'selected' : ''; ?>>All</option>
                        <option value="1" <?php echo ($featured === '1') ? 'selected' : ''; ?>>Featured Only</option>
                        <option value="0" <?php echo ($featured === '0') ? 'selected' : ''; ?>>Standard Only</option>
                    </select>
                </div>
                <div style="flex: 1.2; min-width: 160px;">
                    <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Category</label>
                    <select name="category_id" class="form-control" style="font-size: 13px; height: 38px;">
                        <option value="0">All Categories</option>
                        <?php foreach ($categoriesList as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($categoryId === (int)$c['id']) ? 'selected' : ''; ?>>
                                <?php echo e($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex: 1; min-width: 130px;">
                    <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">City</label>
                    <input type="text" name="city" value="<?php echo e($city); ?>" class="form-control" placeholder="e.g. Bengaluru" style="font-size: 13px; height: 38px;">
                </div>

            <?php elseif ($report === 'listings'): ?>
                <div style="flex: 1; min-width: 140px;">
                    <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Service Type</label>
                    <select name="service_type" class="form-control" style="font-size: 13px; height: 38px;">
                        <option value="all" <?php echo ($serviceType === 'all') ? 'selected' : ''; ?>>All Types</option>
                        <option value="service" <?php echo ($serviceType === 'service') ? 'selected' : ''; ?>>Service</option>
                        <option value="product" <?php echo ($serviceType === 'product') ? 'selected' : ''; ?>>Product</option>
                        <option value="package" <?php echo ($serviceType === 'package') ? 'selected' : ''; ?>>Package</option>
                    </select>
                </div>
                <div style="flex: 1; min-width: 140px;">
                    <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Status</label>
                    <select name="status" class="form-control" style="font-size: 13px; height: 38px;">
                        <option value="all" <?php echo ($status === 'all') ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="active" <?php echo ($status === 'active') ? 'selected' : ''; ?>>Active (Live)</option>
                        <option value="draft" <?php echo ($status === 'draft') ? 'selected' : ''; ?>>Draft (Hidden)</option>
                        <option value="inactive" <?php echo ($status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div style="flex: 1.2; min-width: 160px;">
                    <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Category</label>
                    <select name="category_id" class="form-control" style="font-size: 13px; height: 38px;">
                        <option value="0">All Categories</option>
                        <?php foreach ($categoriesList as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($categoryId === (int)$c['id']) ? 'selected' : ''; ?>>
                                <?php echo e($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex: 1.2; min-width: 160px;">
                    <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Business Owner</label>
                    <select name="provider_id" class="form-control" style="font-size: 13px; height: 38px;">
                        <option value="0">All Providers</option>
                        <?php foreach ($providersList as $pv): ?>
                            <option value="<?php echo $pv['id']; ?>" <?php echo ($providerId === (int)$pv['id']) ? 'selected' : ''; ?>>
                                <?php echo e($pv['business_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            <?php elseif ($report === 'orders'): ?>
                <div style="flex: 1; min-width: 140px;">
                    <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Order Status</label>
                    <select name="order_status" class="form-control" style="font-size: 13px; height: 38px;">
                        <option value="all" <?php echo ($orderStatus === 'all') ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo ($orderStatus === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo ($orderStatus === 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="in_progress" <?php echo ($orderStatus === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo ($orderStatus === 'completed') ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo ($orderStatus === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div style="flex: 1; min-width: 140px;">
                    <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Payment Status</label>
                    <select name="payment_status" class="form-control" style="font-size: 13px; height: 38px;">
                        <option value="all" <?php echo ($paymentStatus === 'all') ? 'selected' : ''; ?>>All Payments</option>
                        <option value="completed" <?php echo ($paymentStatus === 'completed') ? 'selected' : ''; ?>>Completed</option>
                        <option value="processing" <?php echo ($paymentStatus === 'processing') ? 'selected' : ''; ?>>Processing</option>
                        <option value="failed" <?php echo ($paymentStatus === 'failed') ? 'selected' : ''; ?>>Failed</option>
                        <option value="created" <?php echo ($paymentStatus === 'created') ? 'selected' : ''; ?>>Created (Unpaid)</option>
                    </select>
                </div>
                <div style="flex: 1.2; min-width: 160px;">
                    <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Business Owner</label>
                    <select name="provider_id" class="form-control" style="font-size: 13px; height: 38px;">
                        <option value="0">All Providers</option>
                        <?php foreach ($providersList as $pv): ?>
                            <option value="<?php echo $pv['id']; ?>" <?php echo ($providerId === (int)$pv['id']) ? 'selected' : ''; ?>>
                                <?php echo e($pv['business_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Common Search & Date Range Filters -->
            <div style="flex: 1.5; min-width: 180px;">
                <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Search Keyword</label>
                <input type="text" name="search" value="<?php echo e($search); ?>" class="form-control" placeholder="Search name, phone, email..." style="font-size: 13px; height: 38px;">
            </div>

            <div style="flex: 1; min-width: 130px;">
                <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Date From</label>
                <input type="date" name="date_from" value="<?php echo e($dateFrom); ?>" class="form-control" style="font-size: 13px; height: 38px;">
            </div>

            <div style="flex: 1; min-width: 130px;">
                <label style="font-size: 12px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Date To</label>
                <input type="date" name="date_to" value="<?php echo e($dateTo); ?>" class="form-control" style="font-size: 13px; height: 38px;">
            </div>

            <div style="display: flex; gap: 6px;">
                <button type="submit" class="btn btn-primary" style="height: 38px; padding: 0 16px; font-size: 13px;">
                    <i class="fa-solid fa-filter"></i> Apply
                </button>
                <a href="<?php echo BASE_URL; ?>/manager/reports.php?report=<?php echo e($report); ?>" class="btn btn-outline" style="height: 38px; padding: 0 12px; font-size: 13px;" title="Reset Filters">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- ==========================================================================
         REPORT DATA TABLE
         ========================================================================== -->
    <div class="data-table-card" style="margin-bottom: 24px;">
        <div class="table-header-bar">
            <div>
                <h3 style="font-size: 16px; color: var(--secondary); margin-bottom: 2px;">
                    <?php 
                    $sectionTitles = [
                        'users'     => 'Registered User Directory',
                        'providers' => 'Service Provider Registry',
                        'listings'  => 'Marketplace Offerings Catalog',
                        'orders'    => 'Orders &amp; Bookings Ledger',
                    ];
                    echo $sectionTitles[$report] ?? 'Report Records';
                    ?>
                </h3>
                <p style="font-size: 12px; color: #64748b;">
                    Showing <?php echo count($reportRows); ?> of <?php echo $totalRecords; ?> matching records (Page <?php echo $page; ?> of <?php echo $totalPages; ?>)
                </p>
            </div>
            <span class="badge" style="background: #f1f5f9; color: #475569;">
                <i class="fa-solid fa-database"></i> Stored Records
            </span>
        </div>

        <?php if (!empty($reportRows)): ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <?php if ($report === 'users'): ?>
                                <th>ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th style="text-align: right;">Registered</th>

                            <?php elseif ($report === 'providers'): ?>
                                <th>ID</th>
                                <th>Business Name</th>
                                <th>Owner</th>
                                <th>Category</th>
                                <th>City / Location</th>
                                <th>Approval</th>
                                <th>Featured</th>
                                <th>Account</th>
                                <th style="text-align: right;">Registered</th>

                            <?php elseif ($report === 'listings'): ?>
                                <th>ID</th>
                                <th>Offering Title</th>
                                <th>Business Owner</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th style="text-align: right;">Price</th>
                                <th>Status</th>
                                <th>Flash Deal</th>
                                <th style="text-align: right;">Created</th>

                            <?php elseif ($report === 'orders'): ?>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Business Owner</th>
                                <th>Offering</th>
                                <th style="text-align: right;">Gross</th>
                                <th style="text-align: right;">Comm.</th>
                                <th>Order Status</th>
                                <th>Payment</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportRows as $row): ?>
                            <tr>
                                <?php if ($report === 'users'): ?>
                                    <td><span style="font-family: monospace; font-size: 12px; color: #64748b;">#<?php echo (int)$row['id']; ?></span></td>
                                    <td><strong><?php echo e($row['full_name']); ?></strong></td>
                                    <td><span style="font-size: 13px; color: #475569;"><?php echo e($row['email']); ?></span></td>
                                    <td><span style="font-size: 12px; color: #64748b;"><?php echo e($row['phone'] ?? 'N/A'); ?></span></td>
                                    <td>
                                        <span class="badge" style="background: #f1f5f9; color: #334155; font-size: 11px;">
                                            <?php echo ucfirst($row['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'active'): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php elseif ($row['status'] === 'suspended'): ?>
                                            <span class="badge badge-danger">Suspended</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning"><?php echo ucfirst($row['status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; font-size: 12px; color: #64748b; white-space: nowrap;">
                                        <?php echo format_date($row['created_at']); ?>
                                    </td>

                                <?php elseif ($report === 'providers'): ?>
                                    <td><span style="font-family: monospace; font-size: 12px; color: #64748b;">#<?php echo (int)$row['id']; ?></span></td>
                                    <td>
                                        <strong><?php echo e($row['business_name']); ?></strong>
                                        <div style="font-size: 11px; color: #94a3b8;"><?php echo e($row['contact_phone'] ?? ''); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo e($row['owner_name']); ?></div>
                                        <small style="color: #64748b; font-size: 11px;"><?php echo e($row['owner_email']); ?></small>
                                    </td>
                                    <td><span style="font-size: 12px; color: #475569;"><?php echo e($row['category_name'] ?? 'General'); ?></span></td>
                                    <td>
                                        <div style="font-size: 12.5px;"><?php echo e($row['city'] ?? 'Unspecified'); ?></div>
                                        <small style="color: #94a3b8; font-size: 11px;"><?php echo e($row['location'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($row['approval_status'] === 'approved'): ?>
                                            <span class="badge badge-success">Approved</span>
                                        <?php elseif ($row['approval_status'] === 'pending'): ?>
                                            <span class="badge badge-warning">Pending</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$row['is_featured'] === 1): ?>
                                            <span class="badge" style="background: #fef08a; color: #854d0e; font-size: 11px;">
                                                <i class="fa-solid fa-star"></i> Featured
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 12px;">Standard</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['user_status'] === 'active'): ?>
                                            <span class="badge badge-success" style="font-size: 10px;">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger" style="font-size: 10px;"><?php echo ucfirst($row['user_status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; font-size: 12px; color: #64748b; white-space: nowrap;">
                                        <?php echo format_date($row['created_at']); ?>
                                    </td>

                                <?php elseif ($report === 'listings'): ?>
                                    <td><span style="font-family: monospace; font-size: 12px; color: #64748b;">#<?php echo (int)$row['id']; ?></span></td>
                                    <td>
                                        <strong><?php echo e($row['title']); ?></strong>
                                    </td>
                                    <td><span style="font-size: 12.5px; color: #334155;"><?php echo e($row['business_name']); ?></span></td>
                                    <td><span style="font-size: 12px; color: #64748b;"><?php echo e($row['category_name'] ?? 'General'); ?></span></td>
                                    <td>
                                        <span class="badge" style="background: #f1f5f9; color: #475569; font-size: 11px;">
                                            <?php echo ucfirst($row['service_type']); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right; font-weight: 700; color: #0f172a;">
                                        <?php echo format_price((float)$row['price']); ?>
                                    </td>
                                    <td>
                                        <?php if ($row['status'] === 'active'): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php elseif ($row['status'] === 'draft'): ?>
                                            <span class="badge badge-warning">Draft</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$row['offer_enabled'] === 1): ?>
                                            <span class="badge badge-info" style="font-size: 11px;">
                                                <i class="fa-solid fa-bolt"></i> <?php echo format_price((float)$row['offer_price']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 12px;">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; font-size: 12px; color: #64748b; white-space: nowrap;">
                                        <?php echo format_date($row['created_at']); ?>
                                    </td>

                                <?php elseif ($report === 'orders'): ?>
                                    <td>
                                        <strong><?php echo e($row['order_number']); ?></strong>
                                    </td>
                                    <td style="font-size: 12px; color: #64748b; white-space: nowrap;">
                                        <?php echo format_date($row['created_at']); ?>
                                    </td>
                                    <td>
                                        <div><?php echo e($row['customer_name']); ?></div>
                                        <small style="color: #94a3b8; font-size: 11px;"><?php echo e($row['customer_phone']); ?></small>
                                    </td>
                                    <td><span style="font-size: 12.5px; color: #334155;"><?php echo e($row['business_name']); ?></span></td>
                                    <td>
                                        <div style="font-size: 12.5px;"><?php echo e($row['product_title']); ?></div>
                                        <small style="color: #64748b; font-size: 11px;">Qty: <?php echo (int)$row['quantity']; ?></small>
                                    </td>
                                    <td style="text-align: right; font-weight: 700; color: #0f172a;">
                                        <?php echo format_price((float)$row['total_amount']); ?>
                                    </td>
                                    <td style="text-align: right; font-weight: 600; color: #059669;">
                                        <?php echo format_price((float)$row['platform_commission_amount']); ?>
                                    </td>
                                    <td>
                                        <?php if ($row['order_status'] === 'completed'): ?>
                                            <span class="badge badge-success">Completed</span>
                                        <?php elseif ($row['order_status'] === 'in_progress'): ?>
                                            <span class="badge badge-info">In Progress</span>
                                        <?php elseif ($row['order_status'] === 'confirmed'): ?>
                                            <span class="badge badge-primary">Confirmed</span>
                                        <?php elseif ($row['order_status'] === 'cancelled'): ?>
                                            <span class="badge badge-danger">Cancelled</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($row['payment_status'] ?? '') === 'completed'): ?>
                                            <span class="badge badge-success" style="font-size: 10px;">Paid</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning" style="font-size: 10px;"><?php echo ucfirst($row['payment_status'] ?? 'Unpaid'); ?></span>
                                        <?php endif; ?>
                                        <div style="font-size: 10.5px; color: #94a3b8; margin-top: 2px;">
                                            <?php echo e($row['payment_method'] ?? 'N/A'); ?>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-wrapper" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-top: 1px solid var(--border-color); flex-wrap: wrap; gap: 12px;">
                    <div style="font-size: 13px; color: #64748b;">
                        Showing page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo build_filter_url(['page' => 1]); ?>" class="btn btn-outline btn-sm">&laquo; First</a>
                            <a href="<?php echo build_filter_url(['page' => $page - 1]); ?>" class="btn btn-outline btn-sm">&lsaquo; Prev</a>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($p = $startPage; $p <= $endPage; $p++):
                        ?>
                            <a href="<?php echo build_filter_url(['page' => $p]); ?>" class="btn btn-sm <?php echo ($p === $page) ? 'btn-primary' : 'btn-outline'; ?>" style="min-width: 32px; padding: 4px 8px;">
                                <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo build_filter_url(['page' => $page + 1]); ?>" class="btn btn-outline btn-sm">Next &rsaquo;</a>
                            <a href="<?php echo build_filter_url(['page' => $totalPages]); ?>" class="btn btn-outline btn-sm">Last &raquo;</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div style="text-align: center; padding: 48px 16px; color: #94a3b8;">
                <i class="fa-regular fa-folder-open" style="font-size: 36px; margin-bottom: 12px; color: #cbd5e1;"></i>
                <h4 style="font-size: 16px; color: #475569; margin-bottom: 4px;">No matching records found</h4>
                <p style="font-size: 13px; margin-bottom: 0;">Try adjusting or clearing your search filters above.</p>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php
include __DIR__ . '/includes/footer.php';
