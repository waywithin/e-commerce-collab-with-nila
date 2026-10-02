<?php
/**
 * Magal Creator Multi-Vendor Marketplace
 * Manager Dynamic Categories Management
 * Location: /manager/categories.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$pageTitle = 'Category Management';
include __DIR__ . '/includes/header.php';

$editCat = null;
if (isset($_GET['edit'])) {
    $cId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $cId]);
    $editCat = $stmt->fetch();
}

// Handle Add / Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $name = sanitize_string($_POST['name'] ?? '');
        $description = sanitize_textarea($_POST['description'] ?? '');
        $iconClass = sanitize_string($_POST['icon_class'] ?? 'fa-tag');
        $status = sanitize_string($_POST['status'] ?? 'active');
        $catId = (int)($_POST['category_id'] ?? 0);

        if (empty($name)) {
            set_flash('danger', 'Category name cannot be empty.');
        } else {
            $slug = slugify($name);

            if ($catId > 0) {
                // Update
                $up = $db->prepare("
                    UPDATE categories SET name = :name, slug = :slug, description = :desc, icon_class = :icon, status = :st, updated_at = NOW()
                    WHERE id = :id
                ");
                $up->execute([
                    'name' => $name,
                    'slug' => $slug,
                    'desc' => $description,
                    'icon' => $iconClass,
                    'st'   => $status,
                    'id'   => $catId
                ]);
                set_flash('success', 'Category "' . htmlspecialchars($name) . '" updated successfully.');
            } else {
                // Insert
                $ins = $db->prepare("
                    INSERT INTO categories (name, slug, description, icon_class, status)
                    VALUES (:name, :slug, :desc, :icon, :st)
                ");
                $ins->execute([
                    'name' => $name,
                    'slug' => $slug,
                    'desc' => $description,
                    'icon' => $iconClass,
                    'st'   => $status
                ]);
                set_flash('success', 'New category "' . htmlspecialchars($name) . '" created.');
            }
            header("Location: " . BASE_URL . "/manager/categories.php");
            exit;
        }
    }
}

// Handle Safe Deletion
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    
    // Check if products exist in this category
    $pCheck = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = :cid");
    $pCheck->execute(['cid' => $delId]);
    if ($pCheck->fetchColumn() > 0) {
        set_flash('danger', 'Cannot delete this category because active offerings are currently assigned to it. Deactivate it instead.');
    } else {
        $del = $db->prepare("DELETE FROM categories WHERE id = :id");
        $del->execute(['id' => $delId]);
        set_flash('success', 'Category deleted successfully.');
    }
    header("Location: " . BASE_URL . "/manager/categories.php");
    exit;
}

// Fetch all categories with listing counts
$categories = $db->query("
    SELECT c.*, COUNT(p.id) AS total_items
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    GROUP BY c.id
    ORDER BY c.name ASC
")->fetchAll();
?>

<div style="margin-bottom: 24px;">
    <h1 style="font-size: 26px; color: var(--secondary); margin-bottom: 4px;">Dynamic Service Categories</h1>
    <p style="font-size: 14px; color: #64748b;">Add, edit, and organize marketplace categories dynamically without editing code.</p>
</div>

<div style="display: grid; grid-template-columns: 1fr 1.6fr; gap: 30px; align-items: flex-start;">
    <!-- Add / Edit Category Form -->
    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 26px; box-shadow: var(--shadow-sm);">
        <h3 style="font-size: 16px; margin-bottom: 16px; color: var(--text-heading); border-bottom: 1px solid var(--border-color); padding-bottom: 10px;">
            <?php echo $editCat ? 'Edit Category' : 'Create New Category'; ?>
        </h3>

        <form action="<?php echo BASE_URL; ?>/manager/categories.php" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="category_id" value="<?php echo (int)($editCat['id'] ?? 0); ?>">

            <div class="form-group">
                <label class="form-label" for="catName">Category Name *</label>
                <input type="text" id="catName" name="name" class="form-control" required placeholder="e.g. Handmade Pottery &amp; Ceramics" value="<?php echo e($editCat['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="catIcon">FontAwesome Icon Class</label>
                <input type="text" id="catIcon" name="icon_class" class="form-control" placeholder="e.g. fa-palette, fa-cake-candles" value="<?php echo e($editCat['icon_class'] ?? 'fa-tag'); ?>">
                <div class="form-hint">Icon class from FontAwesome 6 (e.g. fa-shirt, fa-gem, fa-spa)</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="catDesc">Description</label>
                <textarea id="catDesc" name="description" class="form-control" placeholder="Brief summary of what this category encompasses..."><?php echo e($editCat['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="catStatus">Status</label>
                <select id="catStatus" name="status" class="form-control">
                    <option value="active" <?php echo (($editCat['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo (($editCat['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 16px;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <i class="fa-solid fa-save"></i> <?php echo $editCat ? 'Update Category' : 'Save Category'; ?>
                </button>
                <?php if ($editCat): ?>
                    <a href="<?php echo BASE_URL; ?>/manager/categories.php" class="btn btn-outline">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Category List Table -->
    <div class="data-table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50px;">Icon</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Offerings</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td>
                            <div style="width: 34px; height: 34px; border-radius: 50%; background: #F7EEEB; color: #8C3A27; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                <i class="fa-solid <?php echo e($cat['icon_class'] ?: 'fa-tag'); ?>"></i>
                            </div>
                        </td>
                        <td><strong><?php echo e($cat['name']); ?></strong></td>
                        <td><code><?php echo e($cat['slug']); ?></code></td>
                        <td><strong><?php echo (int)$cat['total_items']; ?></strong></td>
                        <td>
                            <?php if ($cat['status'] === 'active'): ?>
                                <span class="badge" style="background: #d1fae5; color: #065f46;">Active</span>
                            <?php else: ?>
                                <span class="badge" style="background: #f1f5f9; color: #64748b;">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                <a href="<?php echo BASE_URL; ?>/manager/categories.php?edit=<?php echo (int)$cat['id']; ?>" class="btn btn-outline btn-sm">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <?php if ((int)$cat['total_items'] === 0): ?>
                                    <a href="<?php echo BASE_URL; ?>/manager/categories.php?delete=<?php echo (int)$cat['id']; ?>" class="btn btn-outline btn-sm" style="color: #ef4444;" data-confirm="Delete this unused category?">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

