<?php

/**
 * Monthly Newsletter Cron Job – Jolly Good Chef
 *
 * Sends a comprehensive monthly newsletter to all eligible registered users
 * on the 1st of each month.
 *
 * Recommended cron schedule (Hostinger / cPanel):
 *   0 9 1 * * /usr/bin/php /home/u346881956/public_html/app/cron/send_newsletter.php
 *
 * Manual test:
 *   https://app.jollygoodchef.com/app/cron/send_newsletter.php
 *   (Remove the date guard below, or run via CLI: php send_newsletter.php --force)
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

define('LOG_FILE', __DIR__ . '/../../newsletter_cron.log');

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function log_msg(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    if (PHP_SAPI === 'cli') {
        echo $msg . "\n";
    }
}

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

log_msg('=== Monthly Newsletter Cron Job Started ===');

// Allow --force flag from CLI to bypass date check (useful for testing)
$force = in_array('--force', $argv ?? [], true);

if (!$force && date('d') !== '01') {
    log_msg('Not the 1st of the month – skipping. Use --force to override.');
    log_msg('=== Cron Job Ended (skipped) ===');
    exit(0);
}

try {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../services/EmailService.php';
    log_msg('✓ Database connection and EmailService loaded');
} catch (Throwable $e) {
    log_msg('❌ FATAL: Could not load dependencies – ' . $e->getMessage());
    exit(1);
}

$emailService = new EmailService($pdo);

// ---------------------------------------------------------------------------
// Collect newsletter content
// ---------------------------------------------------------------------------

$lastMonthStart = date('Y-m-01', strtotime('-1 month'));
$lastMonthEnd   = date('Y-m-t', strtotime('-1 month'));

// 1. Top trending recipes last month (by likes)
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.title, COUNT(l.id) AS likes
        FROM recipes r
        LEFT JOIN likes l
               ON r.id = l.recipe_id
              AND l.created_at BETWEEN :start AND :end
        WHERE r.is_public = 1
        GROUP BY r.id, r.title
        ORDER BY likes DESC
        LIMIT 5
    ");
    $stmt->execute([':start' => $lastMonthStart, ':end' => $lastMonthEnd]);
    $topRecipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    log_msg('✓ Fetched ' . count($topRecipes) . ' trending recipe(s)');
} catch (Throwable $e) {
    log_msg('⚠ Could not fetch trending recipes: ' . $e->getMessage());
    $topRecipes = [];
}

// 2. Community highlights – top 3 most-liked public recipes overall
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.title, r.description, u.full_name AS author,
               COUNT(l.id) AS likes
        FROM recipes r
        LEFT JOIN users  u ON r.user_id = u.id
        LEFT JOIN likes  l ON r.id = l.recipe_id
        WHERE r.is_public = 1
        GROUP BY r.id, r.title, r.description, u.full_name
        ORDER BY likes DESC
        LIMIT 3
    ");
    $stmt->execute();
    $communityHighlights = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $communityHighlights[] = [
            'title'       => $row['title'],
            'description' => mb_strimwidth($row['description'] ?? '', 0, 120, '…'),
            'author'      => $row['author'] ?? 'Community Member',
        ];
    }
    log_msg('✓ Fetched ' . count($communityHighlights) . ' community highlight(s)');
} catch (Throwable $e) {
    log_msg('⚠ Could not fetch community highlights: ' . $e->getMessage());
    $communityHighlights = [];
}

// 3. New features (update this array each month)
$newFeatures = [
    [
        'title'       => 'Enhanced AI Recipe Search',
        'description' => 'Find recipes faster with smarter AI filters and personalised recommendations.',
    ],
    [
        'title'       => 'Meal Plan Sync',
        'description' => 'Sync your meal plans across devices and share them with family members.',
    ],
    [
        'title'       => 'Smart Grocery Optimiser',
        'description' => 'Auto-consolidate your grocery list and get price estimates before you shop.',
    ],
];

// 4. Monthly tips & tricks (rotate or update each month)
$tips = [
    [
        'title'       => 'Prep Like a Pro',
        'description' => 'Batch-cook proteins and vegetables on Sunday to make weekday meals effortless.',
    ],
    [
        'title'       => 'Save Money on Groceries',
        'description' => 'Buy seasonal produce and use our price estimation feature to compare stores before you shop.',
    ],
    [
        'title'       => 'Make It Your Own',
        'description' => 'Use the recipe customisation panel to swap ingredients and adjust seasoning to match your taste.',
    ],
    [
        'title'       => 'Stay Hydrated',
        'description' => 'Include water-rich foods like cucumber, watermelon, and leafy greens in your weekly meals.',
    ],
];

log_msg('✓ Newsletter content prepared');

// ---------------------------------------------------------------------------
// Fetch eligible users
// ---------------------------------------------------------------------------

try {
    // Exclude admins and users who already received a newsletter today
    $stmt = $pdo->query("
        SELECT u.id, u.email, u.full_name
        FROM users u
        WHERE u.role != 'admin'
          AND u.id NOT IN (
              SELECT user_id
              FROM email_logs
              WHERE email_type = 'monthly_newsletter'
                AND DATE(sent_at) = CURDATE()
          )
        ORDER BY u.id ASC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    log_msg('✓ Found ' . count($users) . ' eligible user(s)');
} catch (Throwable $e) {
    log_msg('❌ FATAL: Could not fetch users – ' . $e->getMessage());
    exit(1);
}

// ---------------------------------------------------------------------------
// Send newsletters
// ---------------------------------------------------------------------------

$sent   = 0;
$failed = 0;

foreach ($users as $user) {
    $userId    = (int) $user['id'];
    $userEmail = $user['email'];
    $userName  = $user['full_name'] ?: 'there';

    try {
        // Per-user stats for last month
        $statStmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT r.id)  AS recipes_generated,
                COUNT(DISTINCT m.id)  AS meals_planned,
                COUNT(DISTINCT f.id)  AS favorite_recipes
            FROM users u
            LEFT JOIN recipes          r ON u.id = r.user_id
                                       AND r.created_at BETWEEN :s1 AND :e1
            LEFT JOIN meal_plan_items  m ON u.id = m.user_id
                                       AND m.created_at BETWEEN :s2 AND :e2
            LEFT JOIN likes            f ON u.id = f.user_id
                                       AND f.created_at BETWEEN :s3 AND :e3
            WHERE u.id = :uid
        ");
        $statStmt->execute([
            ':s1'  => $lastMonthStart,
            ':e1'  => $lastMonthEnd,
            ':s2'  => $lastMonthStart,
            ':e2'  => $lastMonthEnd,
            ':s3'  => $lastMonthStart,
            ':e3'  => $lastMonthEnd,
            ':uid' => $userId,
        ]);
        $userStats = $statStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $result = $emailService->sendMonthlyNewsletter($userId, $userEmail, $userName, [
            'top_recipes'          => $topRecipes,
            'user_stats'           => $userStats,
            'community_highlights' => $communityHighlights,
            'new_features'         => $newFeatures,
            'tips'                 => $tips,
        ]);

        if ($result) {
            $sent++;
            log_msg("✓ Sent to {$userEmail}");
        } else {
            $failed++;
            log_msg("✗ mail() returned false for {$userEmail}");
        }

    } catch (Throwable $e) {
        $failed++;
        log_msg("✗ Exception for {$userEmail}: " . $e->getMessage());
    }

    // Small delay to avoid flooding the mail server
    usleep(100000); // 100 ms
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

log_msg('');
log_msg("✅ Newsletter cron completed: {$sent} sent, {$failed} failed");
log_msg('=== Monthly Newsletter Cron Job Ended ===');
log_msg('');

exit($failed > 0 ? 1 : 0);
