<?php
require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Forbidden | Magal Creator </title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #faf5f8;">
    <div style="max-width: 500px; padding: 40px; background: #ffffff; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center;">
        <div style="width: 70px; height: 70px; margin: 0 auto 20px; background: #F1E0DB; color: #8C3A27; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px;">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h1 style="font-size: 24px; color: #1e293b; margin-bottom: 10px;">Access Restricted</h1>
        <p style="color: #64748b; line-height: 1.6; margin-bottom: 25px;">
            You do not have administrative permission to view this section. Please ensure you are logged into an authorized account.
        </p>
        <div style="display: flex; gap: 12px; justify-content: center;">
            <a href="<?php echo BASE_URL; ?>" class="btn btn-primary" style="padding: 10px 20px; border-radius: 8px; text-decoration: none;">Return Home</a>
            <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-outline" style="padding: 10px 20px; border-radius: 8px; text-decoration: none; border: 1px solid #cbd5e1; color: #334155;">Switch Account</a>
        </div>
    </div>
</body>
</html>

