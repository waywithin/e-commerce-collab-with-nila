<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Global Public Footer Component
 * Location: /includes/footer.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';

// Fetch active categories for footer
try {
    $db = getDB();
    $footerCats = $db->query("SELECT name, slug FROM categories WHERE status = 'active' ORDER BY name ASC LIMIT 6")->fetchAll();
} catch (Exception) {
    $footerCats = [];
}
?>
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <!-- Brand Overview -->
            <div class="footer-brand">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 14px;">
                    <div style="width: 36px; height: 36px; background: #8C3A27; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fa-solid fa-crown"></i>
                    </div>
                    <h3 style="margin: 0; color: #ffffff;">Magal Creator</h3>
                </div>
                <p>
                    A dedicated multi-vendor digital marketplace connecting conscious customers with inspiring women-owned businesses, boutiques, artists, and service entrepreneurs.
                </p>
                <div style="display: flex; gap: 14px; font-size: 18px; color: #cbd5e1;">
                    <a href="#" style="color: inherit;"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" style="color: inherit;"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#" style="color: inherit;"><i class="fa-brands fa-whatsapp"></i></a>
                    <a href="#" style="color: inherit;"><i class="fa-brands fa-linkedin"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="footer-col">
                <h4>Marketplace</h4>
                <ul class="footer-links">
                    <li><a href="<?php echo BASE_URL; ?>/index.php">Home</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/browse.php">Explore Services</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/browse.php?tab=providers">Women Entrepreneurs</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/index.php#how-it-works">How It Works</a></li>
                </ul>
            </div>

            <!-- Top Categories -->
            <div class="footer-col">
                <h4>Categories</h4>
                <ul class="footer-links">
                    <?php foreach ($footerCats as $fcat): ?>
                        <li><a href="<?php echo BASE_URL; ?>/browse.php?category=<?php echo urlencode($fcat['slug']); ?>"><?php echo e($fcat['name']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Entrepreneur & Manager Portal -->
            <div class="footer-col">
                <h4>Entrepreneurs</h4>
                <ul class="footer-links">
                    <li><a href="<?php echo BASE_URL; ?>/register-provider.php"><i class="fa-solid fa-sparkles" style="color: #E8D4CE;"></i> List Your Business</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/login.php">Entrepreneur Dashboard</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/login.php">Platform Manager Login</a></li>
                    <li><a href="mailto:<?php echo e(get_platform_setting('support_email', 'support@magalcreator.local')); ?>">Support &amp; Inquiries</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> Magal Creator Marketplace. Proudly empowering women entrepreneurs.</p>
            <p style="color: #64748b;">Built for Women Entrepreneurs Awards &amp; Showcase</p>
        </div>
    </div>
</footer>

<!-- Core Vanilla JavaScript & Real-time Countdown Ticker -->
<script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/countdown.js"></script>
</body>
</html>

