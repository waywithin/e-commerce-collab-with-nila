<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

require_role(['provider', 'manager']);

$db = getDB();
$userId = current_user_id();
$userRole = current_user_role();

if ($userRole === 'provider') {
  require_provider();
}

// Handle new post submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_post') {
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Security token invalid. Refresh the page and try again.');
  }

  if ($userRole === 'provider') {
        $content = trim($_POST['content'] ?? '');
        $category = $_POST['category'] ?? 'Discussion';
        $product_link = trim($_POST['product_link'] ?? '');

        if (!empty($content)) {
            $stmt = $db->prepare("INSERT INTO magal_circle_posts (provider_id, category, content, product_link) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $category, $content, $product_link]);
            header('Location: magal-circle.php?success=1');
            exit;
        }
    }
}

// Fetch posts with provider info
$stmt = $db->query(" 
    SELECT p.*, u.full_name AS provider_name, sp.business_name, sp.logo_image AS profile_image
    FROM magal_circle_posts p 
    JOIN users u ON p.provider_id = u.id
    LEFT JOIN service_providers sp ON sp.user_id = u.id
    ORDER BY p.created_at DESC
");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container my-4">
  <div class="d-flex align-items-center gap-3 p-4 rounded-3 mb-4" style="background-color: var(--color-support-light); border-left: 5px solid var(--color-support);">
    <span class="magal-circle-logo">
      <img src="../assets/images/branding/Magal_Circle.png" alt="Magal Circle Logo">
    </span>
    <div>
      <h2 class="fw-bold mb-1" style="color: var(--color-primary);">Magal Circle</h2>
      <p class="text-muted mb-0">Grow together. Support each other. A private space for women entrepreneurs.</p>
    </div>
  </div>

  <?php if ($userRole === 'provider'): ?>
    <!-- Post Creation Form -->
    <div class="card border-0 shadow-sm p-4 mb-4" style="background-color: var(--color-card-bg);">
      <h5 class="fw-bold mb-3" style="color: var(--color-primary);">Share with the Community</h5>
      <form method="POST" action="magal-circle.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="create_post">
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <select name="category" class="form-select">
              <option value="Discussion">Discussion</option>
              <option value="Business Tip">Business Tip</option>
              <option value="Milestone">Milestone Celebration</option>
              <option value="Resource">Resource</option>
              <option value="Announcement">Announcement</option>
            </select>
          </div>
          <div class="col-md-8">
            <input type="url" name="product_link" class="form-control" placeholder="Optional: Add link to your marketplace item (https://...)">
          </div>
        </div>
        <div class="mb-3">
          <textarea name="content" class="form-control" rows="3" placeholder="What's on your mind? Share tips, ask questions, or celebrate wins..." required></textarea>
        </div>
        <button type="submit" class="btn px-4 text-white font-weight-bold" style="background-color: var(--color-primary);">Post to Circle</button>
      </form>
    </div>
  <?php endif; ?>

  <!-- Feed Section -->
  <div class="community-feed">
    <?php if (empty($posts)): ?>
      <div class="text-center py-5 bg-white rounded shadow-sm">
        <p class="text-muted">No posts in Magal Circle yet. Be the first to share an update!</p>
      </div>
    <?php else: ?>
      <?php foreach ($posts as $post): ?>
        <div class="card border-0 shadow-sm mb-3 p-3" style="background-color: var(--color-card-bg);">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="d-flex align-items-center gap-2">
              <img src="<?php echo e(get_image_url($post['profile_image'] ?? '', 'provider')); ?>" alt="" class="rounded-circle" width="40" height="40" style="object-fit: cover;">
              <div>
                <h6 class="mb-0 fw-bold" style="color: var(--color-text);"><?php echo e($post['business_name'] ?? $post['provider_name']); ?></h6>
                <small class="text-muted"><?php echo e(date('M d, Y h:i A', strtotime($post['created_at']))); ?></small>
              </div>
            </div>
            <span class="badge px-3 py-2" style="background-color: var(--color-support-light); color: var(--color-support);"><?php echo e($post['category']); ?></span>
          </div>
          <p class="mb-2" style="color: var(--color-text);"><?php echo nl2br(e($post['content'])); ?></p>
          
          <?php if (!empty($post['product_link'])): ?>
            <div class="mt-2 pt-2 border-top">
              <a href="<?php echo e($post['product_link']); ?>" class="small text-decoration-none fw-semibold" style="color: var(--color-accent);" target="_blank" rel="noopener noreferrer">
                🔗 View Featured Offering on Marketplace
              </a>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php include '../includes/footer.php'; ?>