<?php
/**
 * Dynamic Sitemap
 * Outputs a valid XML sitemap for Google Search Console.
 * Configure your web server to serve this file at /sitemap.xml
 *
 * Apache .htaccess:
 *   RewriteRule ^sitemap\.xml$ sitemap.php [L,NC]
 *
 * Then submit https://jollygoodchef.com/sitemap.xml to Google Search Console.
 */

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=86400');

require_once __DIR__ . '/config/db.php';

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

    <!-- Static Pages -->
    <url>
        <loc>https://jollygoodchef.com/</loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>1.0</priority>
    </url>

    <url>
        <loc>https://jollygoodchef.com/index.php?page=community</loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>

    <url>
        <loc>https://jollygoodchef.com/index.php?page=upgrade</loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>

    <url>
        <loc>https://jollygoodchef.com/index.php?page=privacy</loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>

    <url>
        <loc>https://jollygoodchef.com/index.php?page=terms</loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>

    <!-- Community & SEO Recipes -->
    <?php
    try {
        $stmt = $pdo->prepare("
            SELECT id, slug, updated_at
            FROM recipes
            WHERE is_public = 1
            ORDER BY updated_at DESC
            LIMIT 1000
        ");
        $stmt->execute();
        $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($recipes as $recipe):
            $lastmod    = date('Y-m-d', strtotime($recipe['updated_at'] ?? 'now'));
            $loc        = 'https://jollygoodchef.com/recipes/' . rawurlencode($recipe['slug']);
            $loc_escaped = htmlspecialchars($loc, ENT_XML1);
    ?>
    <url>
        <loc><?= $loc_escaped ?></loc>
        <lastmod><?= $lastmod ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
    <?php
        endforeach;
    } catch (Exception $e) {
        error_log('Sitemap generation error: ' . $e->getMessage());
    }
    ?>

</urlset>
