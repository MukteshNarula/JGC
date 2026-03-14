<?php
/**
 * Recipe Display Template
 * Used by the SEO recipe pages for structured, SEO-optimized output.
 * Expects $recipe array with all recipe fields from the database.
 */

$title        = htmlspecialchars($recipe['title'] ?? '');
$ingredients  = json_decode($recipe['ingredients_json'] ?? '[]', true) ?? [];
$instructions = json_decode($recipe['instructions_json'] ?? '[]', true) ?? [];
$nutrition    = json_decode($recipe['nutrition_json'] ?? '[]', true) ?? [];

$description     = $recipe['meta_description'] ?? substr(strip_tags(implode(' ', $instructions)), 0, 160);
$slug            = $recipe['slug'] ?? '';
$url             = 'https://jollygoodchef.com/recipes/' . rawurlencode($slug);
$keyword         = $recipe['seo_keyword'] ?? '';
$cuisine         = htmlspecialchars($recipe['cuisine'] ?? '');
$diet            = htmlspecialchars($recipe['diet'] ?? '');
$image_url       = htmlspecialchars($recipe['image_url'] ?? 'https://jollygoodchef.com/assets/images/default-recipe.jpg');
$prep_time       = $recipe['prep_time'] ?? 15;
$cook_time       = $recipe['cook_time'] ?? 30;
$total_time      = $prep_time + $cook_time;
$servings        = $recipe['servings'] ?? 4;
$difficulty      = $recipe['difficulty'] ?? 'Easy';

// Nutrition values
$calories = $nutrition['calories'] ?? 0;
$protein  = $nutrition['protein']  ?? 0;
$carbs    = $nutrition['carbs']    ?? 0;
$fat      = $nutrition['fat']      ?? 0;
$fiber    = $nutrition['fiber']    ?? 0;

// JSON-LD Structured Data (Schema.org Recipe)
$json_ld = [
    '@context'           => 'https://schema.org',
    '@type'              => 'Recipe',
    'name'               => $title,
    'description'        => $description,
    'url'                => $url,
    'image'              => $image_url,
    'author'             => [
        '@type' => 'Organization',
        'name'  => 'Jolly Good Chef',
        'url'   => 'https://jollygoodchef.com',
    ],
    'datePublished'      => date('Y-m-d', strtotime($recipe['created_at'] ?? 'now')),
    'prepTime'           => 'PT' . $prep_time . 'M',
    'cookTime'           => 'PT' . $cook_time . 'M',
    'totalTime'          => 'PT' . $total_time . 'M',
    'recipeYield'        => $servings . ' servings',
    'recipeCategory'     => $cuisine ?: 'Main Course',
    'recipeCuisine'      => $cuisine ?: 'International',
    'keywords'           => $keyword ?: $title,
    'recipeIngredient'   => $ingredients,
    'recipeInstructions' => array_map(function ($step, $index) {
        if (is_array($step)) {
            $text = $step['text'] ?? (is_string(reset($step)) ? reset($step) : '');
        } else {
            $text = (string)$step;
        }
        return [
            '@type' => 'HowToStep',
            'name'  => 'Step ' . ($index + 1),
            'text'  => $text,
        ];
    }, $instructions, array_keys($instructions)),
    'nutrition'          => [
        '@type'          => 'NutritionInformation',
        'calories'       => $calories . ' calories',
        'proteinContent' => $protein . 'g',
        'carbohydrateContent' => $carbs . 'g',
        'fatContent'     => $fat . 'g',
        'fiberContent'   => $fiber . 'g',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> | Jolly Good Chef</title>
    <meta name="description" content="<?= htmlspecialchars($description) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($keyword) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($url) ?>">

    <!-- Open Graph -->
    <meta property="og:title" content="<?= htmlspecialchars($title) ?> | Jolly Good Chef">
    <meta property="og:description" content="<?= htmlspecialchars($description) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($image_url) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($url) ?>">
    <meta property="og:type" content="article">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
        <?= json_encode($json_ld, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
    </script>
</head>
<body>
    <article class="recipe-page" itemscope itemtype="https://schema.org/Recipe">
        <h1 itemprop="name"><?= $title ?></h1>
        <p itemprop="description"><?= htmlspecialchars($description) ?></p>

        <img src="<?= htmlspecialchars($image_url) ?>"
             alt="<?= htmlspecialchars($title) ?> - <?= htmlspecialchars($keyword) ?>"
             itemprop="image"
             loading="lazy">

        <div class="recipe-meta">
            <?php if ($prep_time): ?>
            <span>Prep: <?= (int)$prep_time ?> min</span>
            <?php endif; ?>
            <?php if ($cook_time): ?>
            <span>Cook: <?= (int)$cook_time ?> min</span>
            <?php endif; ?>
            <span>Serves: <?= (int)$servings ?></span>
            <span>Difficulty: <?= htmlspecialchars($difficulty) ?></span>
        </div>

        <?php if (!empty($ingredients)): ?>
        <section class="ingredients">
            <h2>Ingredients</h2>
            <ul>
                <?php foreach ($ingredients as $ingredient): ?>
                <li itemprop="recipeIngredient"><?= htmlspecialchars(is_array($ingredient) ? ($ingredient['text'] ?? '') : $ingredient) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if (!empty($instructions)): ?>
        <section class="instructions">
            <h2>Instructions</h2>
            <ol>
                <?php foreach ($instructions as $i => $step): ?>
                <li itemprop="recipeInstructions" itemscope itemtype="https://schema.org/HowToStep">
                    <span itemprop="text"><?= htmlspecialchars(is_array($step) ? ($step['text'] ?? '') : $step) ?></span>
                </li>
                <?php endforeach; ?>
            </ol>
        </section>
        <?php endif; ?>

        <?php if (!empty($nutrition) && $calories > 0): ?>
        <section class="nutrition" itemprop="nutrition" itemscope itemtype="https://schema.org/NutritionInformation">
            <h2>Nutrition Information <small>(per serving)</small></h2>
            <dl>
                <dt>Calories</dt><dd itemprop="calories"><?= (int)$calories ?></dd>
                <dt>Protein</dt><dd itemprop="proteinContent"><?= (int)$protein ?>g</dd>
                <dt>Carbohydrates</dt><dd itemprop="carbohydrateContent"><?= (int)$carbs ?>g</dd>
                <dt>Fat</dt><dd itemprop="fatContent"><?= (int)$fat ?>g</dd>
                <?php if ($fiber): ?>
                <dt>Fiber</dt><dd itemprop="fiberContent"><?= (int)$fiber ?>g</dd>
                <?php endif; ?>
            </dl>
        </section>
        <?php endif; ?>
    </article>
</body>
</html>
