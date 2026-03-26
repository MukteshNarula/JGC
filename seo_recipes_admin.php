<?php
/**
 * SEO Recipes Admin Dashboard
 * Manage, review, approve and export SEO-generated recipe pages.
 *
 * Access: https://jollygoodchef.com/seo_recipes_admin.php
 */

session_start();
require_once __DIR__ . '/config/db.php';

// ─── Admin authentication ────────────────────────────────────────────────────
// Set the SEO_ADMIN_PASS environment variable on your server before use.
$admin_password_env = getenv('SEO_ADMIN_PASS');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    if (!$admin_password_env) {
        $login_error = 'Admin password not configured. Set the SEO_ADMIN_PASS environment variable on your server.';
    } elseif (hash_equals($admin_password_env, $_POST['admin_password'] ?? '')) {
        $_SESSION['seo_admin'] = true;
    } else {
        $login_error = 'Incorrect password.';
    }
}
if (isset($_POST['admin_logout'])) {
    unset($_SESSION['seo_admin']);
}
$is_admin = !empty($_SESSION['seo_admin']);

// ─── Actions (require admin) ─────────────────────────────────────────────────
$action_message = '';
$action_error   = '';

if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Approve / publish
    if ($action === 'approve' && !empty($_POST['recipe_id'])) {
        $id = (int)$_POST['recipe_id'];
        try {
            $pdo->prepare('UPDATE recipes SET is_public = 1 WHERE id = ? AND is_seo_generated = 1')
                ->execute([$id]);
            $action_message = 'Recipe approved and published successfully.';
        } catch (PDOException $e) {
            error_log('Admin approve error: ' . $e->getMessage());
            $action_error = 'Could not approve recipe.';
        }
    }

    // Reject / unpublish
    if ($action === 'reject' && !empty($_POST['recipe_id'])) {
        $id = (int)$_POST['recipe_id'];
        try {
            $pdo->prepare('UPDATE recipes SET is_public = 0 WHERE id = ? AND is_seo_generated = 1')
                ->execute([$id]);
            $action_message = 'Recipe unpublished.';
        } catch (PDOException $e) {
            error_log('Admin reject error: ' . $e->getMessage());
            $action_error = 'Could not unpublish recipe.';
        }
    }

    // Delete
    if ($action === 'delete' && !empty($_POST['recipe_id'])) {
        $id = (int)$_POST['recipe_id'];
        try {
            $pdo->prepare('DELETE FROM recipes WHERE id = ? AND is_seo_generated = 1')
                ->execute([$id]);
            $action_message = 'Recipe deleted.';
        } catch (PDOException $e) {
            error_log('Admin delete error: ' . $e->getMessage());
            $action_error = 'Could not delete recipe.';
        }
    }

    // Bulk delete
    if ($action === 'bulk_delete' && !empty($_POST['recipe_ids'])) {
        $ids = array_map('intval', (array)$_POST['recipe_ids']);
        if ($ids) {
            try {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("DELETE FROM recipes WHERE id IN ({$placeholders}) AND is_seo_generated = 1")
                    ->execute($ids);
                $action_message = count($ids) . ' recipe(s) deleted.';
            } catch (PDOException $e) {
                error_log('Admin bulk delete error: ' . $e->getMessage());
                $action_error = 'Bulk delete failed.';
            }
        }
    }

    // Bulk approve
    if ($action === 'bulk_approve' && !empty($_POST['recipe_ids'])) {
        $ids = array_map('intval', (array)$_POST['recipe_ids']);
        if ($ids) {
            try {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("UPDATE recipes SET is_public = 1 WHERE id IN ({$placeholders}) AND is_seo_generated = 1")
                    ->execute($ids);
                $action_message = count($ids) . ' recipe(s) approved and published.';
            } catch (PDOException $e) {
                error_log('Admin bulk approve error: ' . $e->getMessage());
                $action_error = 'Bulk approve failed.';
            }
        }
    }

    // Edit / save
    if ($action === 'save_edit' && !empty($_POST['recipe_id'])) {
        $id           = (int)$_POST['recipe_id'];
        $title        = trim($_POST['title']       ?? '');
        $cuisine      = trim($_POST['cuisine']     ?? '');
        $diet         = trim($_POST['diet']        ?? '');
        $tips         = trim($_POST['tips']        ?? '');
        $meta_desc    = trim($_POST['meta_description'] ?? '');
        $ingredients  = array_filter(array_map('trim', explode("\n", $_POST['ingredients'] ?? '')));
        $instructions = array_filter(array_map('trim', explode("\n", $_POST['instructions'] ?? '')));

        if (empty($title)) {
            $action_error = 'Title is required.';
        } else {
            try {
                $pdo->prepare(
                    'UPDATE recipes
                     SET title = :title, cuisine = :cuisine, diet = :diet,
                         ingredients_json = :ingredients_json,
                         instructions_json = :instructions_json,
                         tips = :tips, meta_description = :meta_description
                     WHERE id = :id AND is_seo_generated = 1'
                )->execute([
                    ':title'             => $title,
                    ':cuisine'           => $cuisine,
                    ':diet'              => $diet,
                    ':ingredients_json'  => json_encode(array_values($ingredients)),
                    ':instructions_json' => json_encode(array_values($instructions)),
                    ':tips'              => $tips,
                    ':meta_description'  => $meta_desc,
                    ':id'               => $id,
                ]);
                $action_message = 'Recipe updated successfully.';
            } catch (PDOException $e) {
                error_log('Admin save edit error: ' . $e->getMessage());
                $action_error = 'Could not save changes.';
            }
        }
    }
}

// ─── Export as CSV ────────────────────────────────────────────────────────────
if ($is_admin && isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $rows = $pdo->query(
            "SELECT id, title, seo_keyword, cuisine, diet, slug, is_public,
                    meta_description, created_at
             FROM recipes
             WHERE is_seo_generated = 1
             ORDER BY created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="seo_recipes_' . date('Y-m-d') . '.csv"');
        $fh = fopen('php://output', 'w');
        fputcsv($fh, ['ID', 'Title', 'Keyword', 'Cuisine', 'Diet', 'Slug', 'Published', 'Meta Description', 'Created At']);
        foreach ($rows as $row) {
            $row['is_public'] = $row['is_public'] ? 'Yes' : 'No';
            fputcsv($fh, $row);
        }
        fclose($fh);
        exit;
    } catch (PDOException $e) {
        error_log('CSV export error: ' . $e->getMessage());
    }
}

// ─── Fetch recipes for listing ───────────────────────────────────────────────
$recipes      = [];
$total_count  = 0;
$pub_count    = 0;
$draft_count  = 0;
$keyword_stats = [];
$edit_recipe  = null;

$filter_status  = $_GET['status']  ?? 'all';
$filter_keyword = $_GET['keyword'] ?? '';
$page_num       = max(1, (int)($_GET['p'] ?? 1));
$per_page       = 20;

if ($is_admin) {
    try {
        // Stats
        $stat_row   = $pdo->query("SELECT COUNT(*) as total, SUM(is_public) as pub FROM recipes WHERE is_seo_generated = 1")->fetch();
        $total_count = (int)($stat_row['total'] ?? 0);
        $pub_count   = (int)($stat_row['pub']   ?? 0);
        $draft_count = $total_count - $pub_count;

        // Keyword stats
        $kw_rows = $pdo->query(
            "SELECT seo_keyword, COUNT(*) as count, SUM(is_public) as published
             FROM recipes WHERE is_seo_generated = 1
             GROUP BY seo_keyword ORDER BY count DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($kw_rows as $kw) {
            $keyword_stats[$kw['seo_keyword']] = [
                'count'     => (int)$kw['count'],
                'published' => (int)$kw['published'],
            ];
        }

        // Build query with filters
        $where_parts = ['r.is_seo_generated = 1'];
        $params      = [];

        if ($filter_status === 'published') {
            $where_parts[] = 'r.is_public = 1';
        } elseif ($filter_status === 'draft') {
            $where_parts[] = 'r.is_public = 0';
        }

        if ($filter_keyword) {
            $where_parts[] = 'r.seo_keyword = :kw';
            $params[':kw'] = $filter_keyword;
        }

        $where  = implode(' AND ', $where_parts);
        $offset = ($page_num - 1) * $per_page;

        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM recipes r WHERE {$where}");
        $count_stmt->execute($params);
        $filtered_total = (int)$count_stmt->fetchColumn();

        $params[':limit']  = $per_page;
        $params[':offset'] = $offset;

        $stmt = $pdo->prepare(
            "SELECT r.id, r.title, r.cuisine, r.diet, r.seo_keyword, r.slug,
                    r.is_public, r.meta_description, r.created_at,
                    r.ingredients_json, r.instructions_json, r.tips
             FROM recipes r
             WHERE {$where}
             ORDER BY r.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        // Bind integer params explicitly for PDO
        foreach ($params as $key => $val) {
            if (in_array($key, [':limit', ':offset'], true)) {
                $stmt->bindValue($key, $val, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $val);
            }
        }
        $stmt->execute();
        $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_pages = (int)ceil($filtered_total / $per_page);

        // Editing a single recipe
        if (!empty($_GET['edit'])) {
            $edit_id = (int)$_GET['edit'];
            $row     = $pdo->prepare(
                'SELECT * FROM recipes WHERE id = ? AND is_seo_generated = 1'
            );
            $row->execute([$edit_id]);
            $edit_recipe = $row->fetch(PDO::FETCH_ASSOC) ?: null;
        }

    } catch (PDOException $e) {
        error_log('Admin dashboard error: ' . $e->getMessage());
        $action_error = 'Database error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Recipes Admin | Jolly Good Chef</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f4f6f9;
            color: #1a1a2e;
            min-height: 100vh;
        }

        /* ── Header ── */
        .header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,.3);
        }
        .header h1 { font-size: 1.3rem; font-weight: 700; }
        .header-links { display: flex; gap: .75rem; align-items: center; }
        .header-links a, .btn-logout {
            color: #fff;
            text-decoration: none;
            font-size: .83rem;
            padding: .35rem .85rem;
            background: rgba(255,255,255,.15);
            border-radius: 4px;
            border: 1px solid rgba(255,255,255,.25);
            cursor: pointer;
        }
        .header-links a:hover, .btn-logout:hover { background: rgba(255,255,255,.3); }

        /* ── Container ── */
        .container { max-width: 1280px; margin: 0 auto; padding: 1.5rem; }

        /* ── Cards ── */
        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,.07);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .card h2 { font-size: 1.1rem; color: #1a1a2e; margin-bottom: 1.2rem; }

        /* ── Stats ── */
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; }
        .stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
            border-left: 4px solid #e8490f;
        }
        .stat-card.green  { border-left-color: #22c55e; }
        .stat-card.blue   { border-left-color: #3b82f6; }
        .stat-card.orange { border-left-color: #f59e0b; }
        .stat-card .value { font-size: 2rem; font-weight: 800; color: #1a1a2e; }
        .stat-card .label { font-size: .8rem; color: #666; margin-top: .2rem; }

        /* ── Table ── */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: .85rem; }
        thead tr { background: #f8f9fa; }
        th, td { padding: .75rem 1rem; text-align: left; border-bottom: 1px solid #e8e8e8; white-space: nowrap; }
        th { font-weight: 700; color: #444; font-size: .78rem; text-transform: uppercase; letter-spacing: .5px; }
        tr:hover td { background: #f9fafb; }
        td.wrap { white-space: normal; max-width: 280px; }

        /* ── Badges ── */
        .badge {
            display: inline-block;
            padding: .2rem .6rem;
            border-radius: 12px;
            font-size: .73rem;
            font-weight: 600;
        }
        .badge-green  { background: #dcfce7; color: #166534; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-red    { background: #fee2e2; color: #991b1b; }
        .badge-blue   { background: #dbeafe; color: #1e40af; }

        /* ── Buttons ── */
        .btn {
            padding: .4rem .9rem;
            border: none;
            border-radius: 5px;
            font-size: .8rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .15s;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover { opacity: .85; }
        .btn-primary { background: #e8490f; color: #fff; }
        .btn-success { background: #22c55e; color: #fff; }
        .btn-warning { background: #f59e0b; color: #fff; }
        .btn-danger  { background: #ef4444; color: #fff; }
        .btn-secondary { background: #6b7280; color: #fff; }
        .btn-sm { padding: .28rem .65rem; font-size: .75rem; }

        /* ── Alerts ── */
        .alert {
            padding: .85rem 1.1rem;
            border-radius: 6px;
            margin-bottom: 1.2rem;
            font-size: .88rem;
        }
        .alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #22c55e; }
        .alert-error   { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }

        /* ── Login ── */
        .login-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f0f4f8 0%, #e8edf3 100%);
        }
        .login-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.12);
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
        }
        .login-card .logo { font-size: 2rem; text-align: center; margin-bottom: .5rem; }
        .login-card h2 { text-align: center; color: #1a1a2e; margin-bottom: 1.5rem; font-size: 1.3rem; }

        /* ── Forms ── */
        .form-group { margin-bottom: 1.1rem; }
        label { display: block; font-weight: 600; font-size: .85rem; margin-bottom: .35rem; color: #444; }
        input[type=text], input[type=password], select, textarea {
            width: 100%;
            padding: .6rem .85rem;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: .88rem;
            transition: border-color .2s;
            font-family: inherit;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #e8490f;
        }
        textarea { resize: vertical; }

        /* ── Filters ── */
        .filter-bar { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; margin-bottom: 1.2rem; }
        .filter-bar .form-group { margin-bottom: 0; }
        .filter-bar select, .filter-bar input { min-width: 160px; }

        /* ── Grid ── */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; }
        @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }

        /* ── Pagination ── */
        .pagination { display: flex; gap: .5rem; align-items: center; margin-top: 1rem; }
        .pagination a, .pagination span {
            padding: .35rem .75rem;
            border-radius: 5px;
            font-size: .83rem;
            text-decoration: none;
            border: 1px solid #e0e0e0;
            background: #fff;
            color: #444;
        }
        .pagination a:hover { background: #f0f0f0; }
        .pagination .current { background: #e8490f; color: #fff; border-color: #e8490f; }

        /* ── Keyword performance ── */
        .kw-table td:last-child { width: 200px; }
        .kw-bar-wrap { background: #e0e0e0; border-radius: 6px; height: 8px; }
        .kw-bar { background: #e8490f; height: 8px; border-radius: 6px; min-width: 4px; }

        /* ── Edit modal ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.5);
            z-index: 1000;
            align-items: flex-start;
            justify-content: center;
            padding: 2rem 1rem;
            overflow-y: auto;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 40px rgba(0,0,0,.2);
            padding: 2rem;
            width: 100%;
            max-width: 760px;
        }
        .modal h2 { margin-bottom: 1.5rem; font-size: 1.2rem; }
        .modal-close {
            float: right;
            font-size: 1.4rem;
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            line-height: 1;
        }
    </style>
</head>
<body>

<?php if (!$is_admin): ?>
<!-- ════════════ LOGIN ════════════ -->
<div class="login-wrap">
    <div class="login-card">
        <div class="logo">📋</div>
        <h2>Admin Dashboard</h2>
        <?php if (!empty($login_error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($login_error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="admin_login" value="1">
            <div class="form-group">
                <label for="admin_password">Admin Password</label>
                <input type="password" id="admin_password" name="admin_password" required placeholder="Enter admin password">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">Login →</button>
        </form>
        <p style="text-align:center;margin-top:1rem;font-size:.8rem;color:#999">
            Jolly Good Chef · Admin Area
        </p>
    </div>
</div>

<?php else: ?>
<!-- ════════════ MAIN ADMIN ════════════ -->

<div class="header">
    <div>
        <h1>📋 SEO Recipes Admin Dashboard</h1>
    </div>
    <div class="header-links">
        <a href="seo_recipe_generator.php">⚡ Generator</a>
        <a href="?export=csv">⬇ Export CSV</a>
        <form method="POST" style="margin:0">
            <input type="hidden" name="admin_logout" value="1">
            <button type="submit" class="btn-logout">Logout</button>
        </form>
    </div>
</div>

<div class="container">

    <?php if ($action_message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($action_message) ?></div>
    <?php endif; ?>
    <?php if ($action_error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($action_error) ?></div>
    <?php endif; ?>

    <!-- ── Stats ── -->
    <div class="stats-row" style="margin-bottom:1.5rem">
        <div class="stat-card">
            <div class="value"><?= $total_count ?></div>
            <div class="label">Total SEO Recipes</div>
        </div>
        <div class="stat-card green">
            <div class="value"><?= $pub_count ?></div>
            <div class="label">Published</div>
        </div>
        <div class="stat-card orange">
            <div class="value"><?= $draft_count ?></div>
            <div class="label">Drafts</div>
        </div>
        <div class="stat-card blue">
            <div class="value"><?= count($keyword_stats) ?></div>
            <div class="label">Keywords Covered</div>
        </div>
    </div>

    <!-- ── Keyword Performance ── -->
    <?php if (!empty($keyword_stats)): ?>
    <div class="card">
        <h2>🎯 Keyword Performance</h2>
        <div class="table-wrap">
            <table class="kw-table">
                <thead>
                    <tr>
                        <th>Keyword</th>
                        <th>Recipes Generated</th>
                        <th>Published</th>
                        <th>Progress</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $max_count = max(array_column($keyword_stats, 'count')) ?: 1;
                    foreach ($keyword_stats as $kw => $stat):
                        $bar_width = round(($stat['count'] / $max_count) * 100);
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($kw) ?></strong></td>
                        <td><?= $stat['count'] ?></td>
                        <td>
                            <span class="badge <?= $stat['published'] > 0 ? 'badge-green' : 'badge-yellow' ?>">
                                <?= $stat['published'] ?> published
                            </span>
                        </td>
                        <td>
                            <div class="kw-bar-wrap">
                                <div class="kw-bar" style="width:<?= $bar_width ?>%"></div>
                            </div>
                        </td>
                        <td>
                            <a href="?keyword=<?= urlencode($kw) ?>" class="btn btn-secondary btn-sm">Filter</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Recipes List ── -->
    <div class="card">
        <h2>📄 SEO-Generated Recipes</h2>

        <!-- Filters -->
        <form method="GET" class="filter-bar">
            <div class="form-group">
                <label>Status</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="all"       <?= $filter_status === 'all'       ? 'selected' : '' ?>>All</option>
                    <option value="published" <?= $filter_status === 'published' ? 'selected' : '' ?>>Published</option>
                    <option value="draft"     <?= $filter_status === 'draft'     ? 'selected' : '' ?>>Drafts</option>
                </select>
            </div>
            <div class="form-group">
                <label>Keyword</label>
                <select name="keyword" onchange="this.form.submit()">
                    <option value="">All Keywords</option>
                    <?php foreach (array_keys($keyword_stats) as $kw): ?>
                        <option value="<?= htmlspecialchars($kw) ?>" <?= $filter_keyword === $kw ? 'selected' : '' ?>>
                            <?= htmlspecialchars($kw) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">Filter</button>
            <a href="?" class="btn btn-secondary">Clear</a>
        </form>

        <!-- Bulk actions -->
        <form method="POST" id="bulk-form">
            <div style="display:flex;gap:.75rem;margin-bottom:1rem;flex-wrap:wrap">
                <button type="submit" name="action" value="bulk_approve" class="btn btn-success btn-sm"
                        onclick="return confirmBulk('approve')">✅ Approve Selected</button>
                <button type="submit" name="action" value="bulk_delete" class="btn btn-danger btn-sm"
                        onclick="return confirmBulk('delete')">🗑️ Delete Selected</button>
                <label style="font-size:.82rem;display:flex;align-items:center;gap:.4rem;margin-left:auto">
                    <input type="checkbox" id="select-all" onchange="toggleAll(this)"> Select All
                </label>
            </div>

            <?php if (empty($recipes)): ?>
                <div style="text-align:center;padding:2rem;color:#999">
                    No SEO-generated recipes found.
                    <a href="seo_recipe_generator.php">Generate some →</a>
                </div>
            <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:30px"><input type="checkbox" id="select-all-top" onchange="toggleAll(this)"></th>
                            <th>#</th>
                            <th>Title</th>
                            <th>Keyword</th>
                            <th>Cuisine</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recipes as $recipe): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="recipe_ids[]" value="<?= $recipe['id'] ?>">
                            </td>
                            <td><?= $recipe['id'] ?></td>
                            <td class="wrap">
                                <strong><?= htmlspecialchars($recipe['title']) ?></strong>
                                <?php if ($recipe['slug']): ?>
                                    <br><small style="color:#999">/recipes/<?= htmlspecialchars($recipe['slug']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-blue"><?= htmlspecialchars($recipe['seo_keyword'] ?? '—') ?></span>
                            </td>
                            <td><?= htmlspecialchars($recipe['cuisine'] ?? '—') ?></td>
                            <td>
                                <?php if ($recipe['is_public']): ?>
                                    <span class="badge badge-green">Published</span>
                                <?php else: ?>
                                    <span class="badge badge-yellow">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d M Y', strtotime($recipe['created_at'])) ?></td>
                            <td>
                                <div style="display:flex;gap:.3rem;flex-wrap:wrap">
                                    <!-- Edit -->
                                    <button type="button"
                                            class="btn btn-warning btn-sm"
                                            onclick="openEdit(<?= htmlspecialchars(json_encode($recipe), ENT_QUOTES) ?>)">
                                        ✏️ Edit
                                    </button>

                                    <!-- Approve / Unpublish -->
                                    <?php if (!$recipe['is_public']): ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="recipe_id" value="<?= $recipe['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm">✅ Publish</button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="recipe_id" value="<?= $recipe['id'] ?>">
                                        <button type="submit" class="btn btn-secondary btn-sm">⏸ Unpublish</button>
                                    </form>
                                    <?php endif; ?>

                                    <!-- Delete -->
                                    <form method="POST" style="display:inline"
                                          onsubmit="return confirm('Delete this recipe?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="recipe_id" value="<?= $recipe['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </form>

        <!-- Pagination -->
        <?php if (isset($total_pages) && $total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page_num > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['p' => $page_num - 1])) ?>">‹ Prev</a>
            <?php endif; ?>

            <?php for ($i = max(1, $page_num - 2); $i <= min($total_pages, $page_num + 2); $i++): ?>
                <?php if ($i === $page_num): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['p' => $i])) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page_num < $total_pages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['p' => $page_num + 1])) ?>">Next ›</a>
            <?php endif; ?>

            <span style="margin-left:auto;color:#666;font-size:.8rem">
                <?= isset($filtered_total) ? $filtered_total : $total_count ?> total
            </span>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /container -->

<!-- ── Edit Modal ── -->
<div class="modal-overlay" id="edit-modal">
    <div class="modal">
        <button class="modal-close" onclick="closeEdit()">×</button>
        <h2>✏️ Edit Recipe</h2>
        <form method="POST" id="edit-form">
            <input type="hidden" name="action" value="save_edit">
            <input type="hidden" name="recipe_id" id="edit-recipe-id">

            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" id="edit-title" required maxlength="255">
            </div>

            <div class="form-group">
                <label>Meta Description</label>
                <textarea name="meta_description" id="edit-meta-desc" rows="3" maxlength="200"></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Cuisine</label>
                    <input type="text" name="cuisine" id="edit-cuisine" maxlength="100">
                </div>
                <div class="form-group">
                    <label>Diet</label>
                    <input type="text" name="diet" id="edit-diet" maxlength="100">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Ingredients (one per line)</label>
                    <textarea name="ingredients" id="edit-ingredients" rows="8"></textarea>
                </div>
                <div class="form-group">
                    <label>Instructions (one step per line)</label>
                    <textarea name="instructions" id="edit-instructions" rows="8"></textarea>
                </div>
            </div>

            <div class="form-group">
                <label>Tips</label>
                <textarea name="tips" id="edit-tips" rows="2"></textarea>
            </div>

            <div style="display:flex;gap:.75rem">
                <button type="submit" class="btn btn-success">💾 Save Changes</button>
                <button type="button" class="btn btn-secondary" onclick="closeEdit()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAll(master) {
    document.querySelectorAll('input[name="recipe_ids[]"]').forEach(cb => { cb.checked = master.checked; });
    // Sync both checkboxes
    document.querySelectorAll('#select-all, #select-all-top').forEach(cb => { cb.checked = master.checked; });
}

function confirmBulk(action) {
    const count = document.querySelectorAll('input[name="recipe_ids[]"]:checked').length;
    if (!count) { alert('Please select at least one recipe.'); return false; }
    return confirm(`Are you sure you want to ${action} ${count} recipe(s)?`);
}

function openEdit(recipe) {
    const ingredients  = JSON.parse(recipe.ingredients_json  || '[]');
    const instructions = JSON.parse(recipe.instructions_json || '[]');

    document.getElementById('edit-recipe-id').value   = recipe.id;
    document.getElementById('edit-title').value        = recipe.title     || '';
    document.getElementById('edit-meta-desc').value    = recipe.meta_description || '';
    document.getElementById('edit-cuisine').value      = recipe.cuisine   || '';
    document.getElementById('edit-diet').value         = recipe.diet      || '';
    document.getElementById('edit-tips').value         = recipe.tips      || '';
    document.getElementById('edit-ingredients').value  = ingredients.join('\n');
    document.getElementById('edit-instructions').value = instructions.join('\n');

    document.getElementById('edit-modal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeEdit() {
    document.getElementById('edit-modal').classList.remove('open');
    document.body.style.overflow = '';
}

// Close modal on overlay click
document.getElementById('edit-modal').addEventListener('click', function(e) {
    if (e.target === this) { closeEdit(); }
});
</script>

<?php endif; ?>
</body>
</html>
