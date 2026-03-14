<?php
/**
 * SEO Recipe Generator
 * Generates SEO-optimized recipe pages targeting popular food keywords.
 *
 * Access: https://jollygoodchef.com/seo_recipe_generator.php
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

// ─── Target keywords ─────────────────────────────────────────────────────────
$target_keywords = [
    'healthy chicken dinner',
    'high protein chicken dinner',
    'quick chicken dinner',
    'easy chicken recipes',
    'healthy vegetarian dinner',
    'vegan dinner recipes',
    'healthy indian dinner',
    'quick breakfast recipes',
    'healthy breakfast meals',
    'vegan desserts',
    'high protein snacks',
    'gluten-free cookies',
    'keto diet meals',
    'low calorie meals',
    'budget friendly recipes',
    '30 minute dinners',
    'comfort food recipes',
    'Mediterranean diet recipes',
    'high protein breakfast',
    'tuna salad recipe',
    'avocado toast recipe',
];

// ─── Recipe data library keyed by keyword ────────────────────────────────────
function get_recipe_for_keyword(string $keyword): array
{
    $library = [
        'healthy chicken dinner' => [
            'title'        => 'Healthy Baked Lemon Herb Chicken Dinner',
            'cuisine'      => 'American',
            'diet'         => 'High Protein',
            'prep_time'    => 15,
            'cook_time'    => 35,
            'servings'     => 4,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '4 boneless, skinless chicken breasts (about 6 oz each)',
                '3 tbsp olive oil, divided',
                '4 garlic cloves, minced',
                'Juice and zest of 1 lemon',
                '1 tsp dried thyme',
                '1 tsp dried rosemary',
                '1 tsp paprika',
                '½ tsp salt',
                '¼ tsp black pepper',
                '1 lb baby potatoes, halved',
                '2 cups broccoli florets',
                '1 cup cherry tomatoes',
            ],
            'instructions' => [
                'Preheat your oven to 400°F (200°C). Line a large baking sheet with parchment paper.',
                'In a small bowl, whisk together 2 tbsp olive oil, minced garlic, lemon juice, lemon zest, thyme, rosemary, paprika, salt, and pepper.',
                'Place the chicken breasts on one side of the prepared baking sheet. Brush generously with the herb marinade.',
                'Toss the halved baby potatoes with the remaining 1 tbsp olive oil and a pinch of salt. Arrange around the chicken.',
                'Roast for 20 minutes, then add the broccoli florets and cherry tomatoes to the pan.',
                'Continue roasting for another 15 minutes, or until the chicken reaches an internal temperature of 165°F (74°C).',
                'Let the chicken rest for 5 minutes before slicing. Serve immediately with the roasted vegetables.',
            ],
            'tips'         => 'For extra juicy chicken, marinate for 1-2 hours in the fridge before baking. Squeeze extra lemon juice over everything just before serving.',
            'nutrition'    => ['calories' => 380, 'protein' => 42, 'carbs' => 22, 'fat' => 13, 'fiber' => 4],
        ],

        'high protein chicken dinner' => [
            'title'        => 'High Protein Grilled Chicken with Quinoa & Greens',
            'cuisine'      => 'American',
            'diet'         => 'High Protein',
            'prep_time'    => 10,
            'cook_time'    => 25,
            'servings'     => 2,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '2 large chicken breasts (8 oz each)',
                '1 cup dry quinoa',
                '2 cups low-sodium chicken broth',
                '2 cups baby spinach',
                '1 cup edamame, shelled',
                '2 tbsp Greek yogurt',
                '1 tbsp olive oil',
                '1 tsp garlic powder',
                '1 tsp onion powder',
                '½ tsp smoked paprika',
                'Salt and pepper to taste',
            ],
            'instructions' => [
                'Rinse the quinoa under cold water. In a medium saucepan, combine quinoa and chicken broth. Bring to a boil, reduce heat, cover, and simmer for 15 minutes until fluffy.',
                'Season chicken breasts with garlic powder, onion powder, smoked paprika, salt, and pepper on both sides.',
                'Heat olive oil in a grill pan over medium-high heat. Cook chicken for 6-7 minutes per side until golden and cooked through (internal temp 165°F).',
                'Let the chicken rest for 5 minutes, then slice on the bias.',
                'Fluff the quinoa and stir in baby spinach and edamame. The residual heat will wilt the spinach.',
                'Divide the quinoa mixture between two plates, top with sliced chicken, and add a dollop of Greek yogurt.',
            ],
            'tips'         => 'Pound the chicken breasts to an even thickness before cooking for more uniform results. Add a squeeze of lemon to brighten the flavors.',
            'nutrition'    => ['calories' => 520, 'protein' => 58, 'carbs' => 40, 'fat' => 12, 'fiber' => 6],
        ],

        'quick chicken dinner' => [
            'title'        => 'Quick 20-Minute Chicken Stir-Fry Dinner',
            'cuisine'      => 'Asian',
            'diet'         => 'Low Carb',
            'prep_time'    => 10,
            'cook_time'    => 10,
            'servings'     => 4,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '1½ lbs chicken breast, thinly sliced',
                '2 tbsp vegetable oil',
                '3 garlic cloves, minced',
                '1 tbsp fresh ginger, grated',
                '2 cups broccoli florets',
                '1 red bell pepper, sliced',
                '1 cup snap peas',
                '3 tbsp low-sodium soy sauce',
                '1 tbsp oyster sauce',
                '1 tsp sesame oil',
                '1 tsp cornstarch mixed with 2 tbsp water',
                '2 green onions, sliced',
            ],
            'instructions' => [
                'Prepare all vegetables and sauce ingredients before you start cooking – this dish moves fast.',
                'Mix soy sauce, oyster sauce, and sesame oil in a small bowl. Set aside.',
                'Heat vegetable oil in a wok or large skillet over high heat until smoking.',
                'Add chicken and cook for 3-4 minutes, stirring frequently, until lightly golden. Remove and set aside.',
                'Add garlic and ginger to the pan; stir-fry for 30 seconds until fragrant.',
                'Add broccoli, bell pepper, and snap peas. Stir-fry for 3 minutes until just tender-crisp.',
                'Return chicken to the pan. Pour the sauce over everything and add the cornstarch mixture. Toss for 1 minute until the sauce thickens and coats everything.',
                'Garnish with green onions and serve immediately over rice or noodles.',
            ],
            'tips'         => 'Freeze the chicken for 15 minutes before slicing – it will be much easier to cut thinly.',
            'nutrition'    => ['calories' => 295, 'protein' => 38, 'carbs' => 14, 'fat' => 9, 'fiber' => 3],
        ],

        'easy chicken recipes' => [
            'title'        => 'Easy One-Pan Honey Garlic Chicken',
            'cuisine'      => 'American',
            'diet'         => 'Gluten-Free',
            'prep_time'    => 10,
            'cook_time'    => 25,
            'servings'     => 4,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '4 chicken thighs, bone-in skin-on',
                '3 tbsp honey',
                '4 garlic cloves, minced',
                '2 tbsp soy sauce (or tamari for gluten-free)',
                '1 tbsp apple cider vinegar',
                '1 tbsp olive oil',
                '1 tsp dried Italian herbs',
                'Salt and pepper to taste',
                'Fresh parsley for garnish',
            ],
            'instructions' => [
                'Preheat oven to 425°F (220°C).',
                'In a small bowl, mix honey, garlic, soy sauce, and apple cider vinegar to create the glaze.',
                'Pat chicken thighs dry with paper towels and season with salt, pepper, and Italian herbs.',
                'Heat olive oil in an oven-safe skillet over medium-high heat. Sear chicken thighs skin-side down for 5 minutes until golden.',
                'Flip the chicken and pour the honey garlic glaze over the top.',
                'Transfer the skillet to the preheated oven and bake for 18-20 minutes until the chicken is cooked through and the glaze is caramelized.',
                'Spoon the pan juices over the chicken and garnish with fresh parsley before serving.',
            ],
            'tips'         => 'Bone-in thighs stay juicier than chicken breasts for this recipe. The pan drippings make a wonderful sauce for drizzling over rice.',
            'nutrition'    => ['calories' => 340, 'protein' => 28, 'carbs' => 18, 'fat' => 17, 'fiber' => 0],
        ],

        'healthy vegetarian dinner' => [
            'title'        => 'Healthy Vegetarian Black Bean & Sweet Potato Bowl',
            'cuisine'      => 'Mexican',
            'diet'         => 'Vegetarian',
            'prep_time'    => 15,
            'cook_time'    => 30,
            'servings'     => 4,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '2 medium sweet potatoes, diced into 1-inch cubes',
                '1 can (15 oz) black beans, drained and rinsed',
                '1 cup dry brown rice',
                '1 red onion, diced',
                '1 red bell pepper, diced',
                '2 tbsp olive oil',
                '1 tsp cumin',
                '1 tsp chili powder',
                '½ tsp garlic powder',
                'Salt and pepper to taste',
                '1 avocado, sliced',
                'Juice of 1 lime',
                '¼ cup fresh cilantro',
                '2 tbsp Greek yogurt or sour cream',
            ],
            'instructions' => [
                'Cook brown rice according to package directions (about 30 minutes).',
                'Preheat oven to 400°F (200°C). Toss sweet potato cubes with 1 tbsp olive oil, cumin, chili powder, and a pinch of salt.',
                'Spread sweet potatoes on a baking sheet and roast for 25-30 minutes until tender and caramelized, flipping halfway.',
                'Heat remaining olive oil in a skillet over medium heat. Sauté onion and bell pepper for 5-7 minutes until softened.',
                'Add black beans, garlic powder, remaining spices, and a splash of water. Cook for 5 minutes until heated through.',
                'Assemble bowls: start with rice, then black beans, roasted sweet potatoes, and avocado slices.',
                'Squeeze lime juice over the top, garnish with cilantro, and add a dollop of Greek yogurt.',
            ],
            'tips'         => 'Meal prep friendly – store components separately and assemble when ready to eat. Add salsa or hot sauce for extra flavor.',
            'nutrition'    => ['calories' => 420, 'protein' => 14, 'carbs' => 68, 'fat' => 12, 'fiber' => 14],
        ],

        'vegan dinner recipes' => [
            'title'        => 'Creamy Vegan Coconut Chickpea Curry Dinner',
            'cuisine'      => 'Indian',
            'diet'         => 'Vegan',
            'prep_time'    => 10,
            'cook_time'    => 25,
            'servings'     => 4,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '2 cans (15 oz each) chickpeas, drained',
                '1 can (14 oz) full-fat coconut milk',
                '1 can (14 oz) diced tomatoes',
                '1 large onion, finely diced',
                '4 garlic cloves, minced',
                '1 tbsp fresh ginger, grated',
                '2 tbsp coconut oil or vegetable oil',
                '2 tbsp curry powder',
                '1 tsp garam masala',
                '1 tsp turmeric',
                '½ tsp cumin',
                'Salt to taste',
                '2 cups baby spinach',
                'Fresh cilantro and lime wedges to serve',
            ],
            'instructions' => [
                'Heat coconut oil in a large pot over medium heat. Add diced onion and cook for 8 minutes until golden and soft.',
                'Add garlic and ginger; stir for 1-2 minutes until fragrant.',
                'Add curry powder, garam masala, turmeric, and cumin. Stir the spices into the onion mixture for 1 minute to toast them.',
                'Pour in the diced tomatoes and cook for 5 minutes, stirring occasionally.',
                'Add chickpeas and coconut milk. Stir well and bring to a gentle simmer.',
                'Cook uncovered for 10 minutes, stirring occasionally, until the sauce thickens slightly.',
                'Stir in baby spinach until wilted. Season with salt to taste.',
                'Serve over basmati rice with fresh cilantro and lime wedges.',
            ],
            'tips'         => 'For a richer curry, use full-fat coconut milk. Letting the curry sit for 10 minutes after cooking deepens the flavors.',
            'nutrition'    => ['calories' => 390, 'protein' => 14, 'carbs' => 48, 'fat' => 17, 'fiber' => 12],
        ],

        'healthy indian dinner' => [
            'title'        => 'Healthy Indian Tandoori Chicken with Raita',
            'cuisine'      => 'Indian',
            'diet'         => 'High Protein',
            'prep_time'    => 20,
            'cook_time'    => 30,
            'servings'     => 4,
            'difficulty'   => 'Medium',
            'ingredients'  => [
                '4 chicken legs (thigh + drumstick), skin removed, scored',
                '1 cup low-fat plain yogurt',
                '2 tbsp tandoori masala or tikka masala spice blend',
                '1 tsp turmeric powder',
                '1 tsp cumin powder',
                '1 tsp coriander powder',
                '1 tbsp lemon juice',
                '1 tbsp ginger-garlic paste',
                'Salt to taste',
                '1 cup cucumber, grated',
                '1 cup plain yogurt (for raita)',
                '1 tsp roasted cumin powder',
                'Fresh mint and lemon wedges to serve',
            ],
            'instructions' => [
                'Score the chicken pieces with deep cuts so the marinade penetrates well.',
                'In a bowl, combine yogurt, tandoori masala, turmeric, cumin, coriander, lemon juice, ginger-garlic paste, and salt. Mix into a smooth marinade.',
                'Coat the chicken thoroughly in the marinade, ensuring it gets into the cuts. Marinate for at least 2 hours, ideally overnight in the fridge.',
                'Preheat oven to 450°F (230°C) or heat a grill to high. Line a baking tray with foil and place a wire rack on top.',
                'Arrange marinated chicken on the rack. Bake for 25-30 minutes, turning once halfway, until charred at the edges and cooked through.',
                'For the raita, squeeze excess water from grated cucumber. Mix with yogurt, roasted cumin, and a pinch of salt.',
                'Serve the tandoori chicken hot with raita, fresh mint, and lemon wedges.',
            ],
            'tips'         => 'A longer marinade time (overnight) gives the most authentic flavour. If using a grill, baste with a little butter for the char marks.',
            'nutrition'    => ['calories' => 310, 'protein' => 38, 'carbs' => 10, 'fat' => 12, 'fiber' => 1],
        ],

        'quick breakfast recipes' => [
            'title'        => 'Quick 10-Minute Veggie Breakfast Scramble',
            'cuisine'      => 'American',
            'diet'         => 'Vegetarian',
            'prep_time'    => 5,
            'cook_time'    => 10,
            'servings'     => 2,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '4 large eggs',
                '2 tbsp milk',
                '½ cup cherry tomatoes, halved',
                '½ cup baby spinach',
                '¼ cup red onion, diced',
                '¼ cup feta cheese, crumbled',
                '1 tbsp olive oil or butter',
                '½ tsp garlic powder',
                'Salt and pepper to taste',
                'Fresh chives or parsley to garnish',
            ],
            'instructions' => [
                'Crack eggs into a bowl, add milk, salt, pepper, and garlic powder. Whisk until well combined.',
                'Heat olive oil in a non-stick skillet over medium heat. Add red onion and cook for 2 minutes.',
                'Add cherry tomatoes and cook for another 2 minutes until they start to soften.',
                'Add baby spinach and stir until wilted, about 1 minute.',
                'Pour the egg mixture into the skillet. Let it sit undisturbed for 30 seconds, then gently fold with a spatula as it sets.',
                'Continue folding gently until eggs are just set but still slightly glossy. Remove from heat.',
                'Top with crumbled feta and fresh herbs. Serve immediately.',
            ],
            'tips'         => 'Remove the scramble from heat just before it looks fully done – residual heat will finish cooking it perfectly.',
            'nutrition'    => ['calories' => 245, 'protein' => 18, 'carbs' => 7, 'fat' => 16, 'fiber' => 2],
        ],

        'healthy breakfast meals' => [
            'title'        => 'Healthy Overnight Oats with Berries & Chia Seeds',
            'cuisine'      => 'American',
            'diet'         => 'Vegetarian',
            'prep_time'    => 10,
            'cook_time'    => 0,
            'servings'     => 2,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '1 cup rolled oats (not instant)',
                '1 cup unsweetened almond milk',
                '½ cup Greek yogurt',
                '2 tbsp chia seeds',
                '1 tbsp honey or maple syrup',
                '1 tsp vanilla extract',
                '1 cup mixed berries (blueberries, strawberries, raspberries)',
                '2 tbsp almond butter',
                '2 tbsp granola for topping',
            ],
            'instructions' => [
                'In a large bowl or two mason jars, combine rolled oats, almond milk, Greek yogurt, chia seeds, honey, and vanilla extract.',
                'Stir everything together until well combined.',
                'Cover and refrigerate overnight, or for at least 6 hours.',
                'In the morning, give the oats a good stir. Add a splash more almond milk if the mixture is too thick.',
                'Divide between two jars or bowls.',
                'Top each serving with mixed berries, a swirl of almond butter, and a sprinkle of granola.',
            ],
            'tips'         => 'Make a week\'s worth on Sunday for easy grab-and-go breakfasts. The oats keep well in the fridge for up to 5 days.',
            'nutrition'    => ['calories' => 385, 'protein' => 16, 'carbs' => 52, 'fat' => 13, 'fiber' => 10],
        ],

        'vegan desserts' => [
            'title'        => 'Decadent Vegan Chocolate Avocado Mousse',
            'cuisine'      => 'French',
            'diet'         => 'Vegan',
            'prep_time'    => 15,
            'cook_time'    => 0,
            'servings'     => 4,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '2 large ripe avocados',
                '¼ cup unsweetened cocoa powder',
                '¼ cup maple syrup',
                '¼ cup full-fat coconut milk',
                '1 tsp vanilla extract',
                'Pinch of sea salt',
                '½ tsp espresso powder (optional, enhances chocolate flavor)',
                'Fresh raspberries and mint leaves to serve',
                'Coconut whipped cream for topping',
            ],
            'instructions' => [
                'Halve and pit the avocados. Scoop the flesh into a food processor or high-speed blender.',
                'Add cocoa powder, maple syrup, coconut milk, vanilla extract, sea salt, and espresso powder (if using).',
                'Blend on high for 2-3 minutes, scraping down the sides as needed, until completely smooth and creamy.',
                'Taste and adjust sweetness or cocoa powder as desired.',
                'Spoon into serving glasses or ramekins.',
                'Chill in the refrigerator for at least 1 hour to firm up.',
                'Top with coconut whipped cream, fresh raspberries, and a sprig of mint before serving.',
            ],
            'tips'         => 'Use very ripe avocados for the creamiest result. You should not be able to taste the avocado at all! Store leftovers covered with plastic wrap pressed directly onto the surface to prevent browning.',
            'nutrition'    => ['calories' => 220, 'protein' => 3, 'carbs' => 22, 'fat' => 16, 'fiber' => 8],
        ],

        'high protein snacks' => [
            'title'        => 'High Protein Greek Yogurt Snack Jars with Granola',
            'cuisine'      => 'Mediterranean',
            'diet'         => 'High Protein',
            'prep_time'    => 10,
            'cook_time'    => 0,
            'servings'     => 4,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '2 cups plain Greek yogurt (2% or full-fat)',
                '¼ cup protein granola',
                '½ cup mixed berries',
                '2 tbsp nut butter (almond or peanut)',
                '2 tbsp honey',
                '2 tbsp hemp seeds',
                '1 tbsp flaxseeds',
                '¼ tsp cinnamon',
            ],
            'instructions' => [
                'Divide Greek yogurt evenly into four small jars or containers.',
                'Drizzle each with honey and swirl in a spoonful of nut butter.',
                'Top each jar with a quarter of the granola.',
                'Add a portion of mixed berries to each jar.',
                'Sprinkle with hemp seeds, flaxseeds, and a pinch of cinnamon.',
                'Serve immediately, or seal and refrigerate for up to 2 days (add granola just before eating to keep it crunchy).',
            ],
            'tips'         => 'For maximum protein, use a 0% fat Greek yogurt with over 17g protein per serving, and choose a high-protein granola.',
            'nutrition'    => ['calories' => 210, 'protein' => 19, 'carbs' => 21, 'fat' => 7, 'fiber' => 2],
        ],

        'gluten-free cookies' => [
            'title'        => 'Chewy Gluten-Free Chocolate Chip Oat Cookies',
            'cuisine'      => 'American',
            'diet'         => 'Gluten-Free',
            'prep_time'    => 15,
            'cook_time'    => 12,
            'servings'     => 24,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '2 cups certified gluten-free rolled oats',
                '1 cup almond flour',
                '½ cup coconut sugar or brown sugar',
                '¼ cup melted coconut oil',
                '2 large eggs',
                '1 tsp vanilla extract',
                '½ tsp baking soda',
                '½ tsp cinnamon',
                '¼ tsp salt',
                '1 cup dark chocolate chips',
                '½ cup chopped walnuts (optional)',
            ],
            'instructions' => [
                'Preheat oven to 350°F (175°C). Line two baking sheets with parchment paper.',
                'In a large bowl, mix together oats, almond flour, baking soda, cinnamon, and salt.',
                'In a separate bowl, whisk together coconut sugar, melted coconut oil, eggs, and vanilla extract.',
                'Pour the wet ingredients into the dry and mix until just combined – do not overmix.',
                'Fold in chocolate chips and walnuts if using.',
                'Scoop rounded tablespoons of dough onto the prepared baking sheets, spacing 2 inches apart. Flatten slightly with the back of a spoon.',
                'Bake for 10-12 minutes until the edges are golden. The centres will look slightly underdone – they firm up as they cool.',
                'Cool on the baking sheet for 5 minutes before transferring to a wire rack.',
            ],
            'tips'         => 'Chilling the dough for 30 minutes makes the cookies thicker and chewier. Store in an airtight container at room temperature for up to 5 days.',
            'nutrition'    => ['calories' => 118, 'protein' => 3, 'carbs' => 14, 'fat' => 7, 'fiber' => 2],
        ],

        'keto diet meals' => [
            'title'        => 'Keto Bacon-Wrapped Chicken with Garlic Butter Broccoli',
            'cuisine'      => 'American',
            'diet'         => 'Keto',
            'prep_time'    => 10,
            'cook_time'    => 30,
            'servings'     => 4,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '4 chicken breasts',
                '8 strips thick-cut bacon',
                '4 cups broccoli florets',
                '4 tbsp butter',
                '4 garlic cloves, minced',
                '¼ cup cream cheese',
                '¼ cup shredded cheddar cheese',
                '1 tsp garlic powder',
                '1 tsp onion powder',
                'Salt and pepper to taste',
            ],
            'instructions' => [
                'Preheat oven to 400°F (200°C).',
                'Season chicken breasts with garlic powder, onion powder, salt, and pepper.',
                'Spread a thin layer of cream cheese on top of each chicken breast.',
                'Wrap each chicken breast tightly with 2 strips of bacon, securing with toothpicks if needed.',
                'Place in an oven-safe skillet or on a baking tray. Sprinkle with shredded cheddar.',
                'Bake for 25-30 minutes until the bacon is crispy and chicken is cooked through.',
                'Meanwhile, steam broccoli until tender-crisp, about 5 minutes. Toss with butter, minced garlic, salt, and pepper.',
                'Serve the bacon-wrapped chicken immediately alongside the garlic butter broccoli.',
            ],
            'tips'         => 'This meal has under 5g net carbs per serving. Add a side salad dressed with olive oil and lemon for a complete keto dinner.',
            'nutrition'    => ['calories' => 495, 'protein' => 48, 'carbs' => 5, 'fat' => 32, 'fiber' => 2],
        ],

        'low calorie meals' => [
            'title'        => 'Low Calorie Zucchini Noodle Primavera',
            'cuisine'      => 'Italian',
            'diet'         => 'Low Calorie',
            'prep_time'    => 15,
            'cook_time'    => 10,
            'servings'     => 4,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '4 large zucchini, spiralized into noodles',
                '1 cup cherry tomatoes, halved',
                '1 yellow bell pepper, thinly sliced',
                '1 cup fresh asparagus, cut into 1-inch pieces',
                '3 garlic cloves, minced',
                '2 tbsp olive oil',
                '¼ cup fresh basil leaves',
                '2 tbsp lemon juice',
                '2 tbsp grated Parmesan cheese',
                'Salt, pepper, and red pepper flakes to taste',
            ],
            'instructions' => [
                'Spiralize the zucchini and place noodles in a colander. Sprinkle with salt and let stand for 10 minutes to draw out moisture. Pat dry with paper towels.',
                'Heat olive oil in a large skillet over medium-high heat. Add garlic and cook for 1 minute until fragrant.',
                'Add asparagus and bell pepper. Stir-fry for 3-4 minutes until just tender.',
                'Add cherry tomatoes and zucchini noodles. Toss gently for 2-3 minutes until the noodles are just heated through – do not overcook.',
                'Remove from heat and add lemon juice and fresh basil. Season with salt, pepper, and red pepper flakes.',
                'Divide among plates, top with Parmesan, and serve immediately.',
            ],
            'tips'         => 'Do not overcook zucchini noodles or they become watery. Add grilled chicken or shrimp to boost the protein content.',
            'nutrition'    => ['calories' => 125, 'protein' => 5, 'carbs' => 12, 'fat' => 7, 'fiber' => 4],
        ],

        'budget friendly recipes' => [
            'title'        => 'Budget-Friendly Bean & Vegetable Soup',
            'cuisine'      => 'American',
            'diet'         => 'Vegan',
            'prep_time'    => 10,
            'cook_time'    => 35,
            'servings'     => 6,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '2 cans (15 oz each) mixed beans (kidney, chickpea, black), drained',
                '1 can (28 oz) crushed tomatoes',
                '4 cups low-sodium vegetable broth',
                '2 medium carrots, diced',
                '3 celery stalks, diced',
                '1 large onion, diced',
                '3 garlic cloves, minced',
                '2 medium potatoes, diced',
                '2 cups kale or cabbage, chopped',
                '1 tbsp olive oil',
                '2 tsp Italian seasoning',
                '1 tsp smoked paprika',
                'Salt and pepper to taste',
                'Crusty bread to serve',
            ],
            'instructions' => [
                'Heat olive oil in a large pot over medium heat. Add onion, carrots, and celery. Cook for 8 minutes until softened.',
                'Add garlic, Italian seasoning, and smoked paprika. Stir for 1 minute.',
                'Add diced potatoes, crushed tomatoes, and vegetable broth. Bring to a boil.',
                'Reduce heat and simmer for 20 minutes until potatoes are tender.',
                'Add the drained beans and kale. Cook for another 5 minutes.',
                'Season generously with salt and pepper.',
                'Serve in deep bowls with crusty bread for dipping.',
            ],
            'tips'         => 'This soup costs around $1 per serving and freezes beautifully for up to 3 months. Swap any vegetables for whatever is on sale.',
            'nutrition'    => ['calories' => 235, 'protein' => 11, 'carbs' => 44, 'fat' => 3, 'fiber' => 12],
        ],

        '30 minute dinners' => [
            'title'        => '30-Minute Lemon Garlic Shrimp Pasta',
            'cuisine'      => 'Italian',
            'diet'         => 'Pescatarian',
            'prep_time'    => 10,
            'cook_time'    => 20,
            'servings'     => 4,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '12 oz linguine or spaghetti',
                '1 lb large shrimp, peeled and deveined',
                '6 garlic cloves, minced',
                '3 tbsp butter',
                '3 tbsp olive oil',
                'Juice and zest of 1½ lemons',
                '½ cup white wine or chicken broth',
                '¼ tsp red pepper flakes',
                '¼ cup fresh parsley, chopped',
                '¼ cup Parmesan cheese, grated',
                'Salt and pepper to taste',
            ],
            'instructions' => [
                'Cook pasta in well-salted boiling water until al dente, 1 minute less than package directions. Reserve ½ cup pasta water before draining.',
                'While pasta cooks, pat shrimp dry and season with salt and pepper.',
                'Heat olive oil and 1 tbsp butter in a large skillet over medium-high heat. Cook shrimp for 1-2 minutes per side until pink. Remove and set aside.',
                'In the same pan, add remaining butter and garlic. Cook for 1 minute until fragrant.',
                'Pour in white wine and let it reduce by half, about 2 minutes.',
                'Add lemon juice, lemon zest, and red pepper flakes. Stir to combine.',
                'Add drained pasta and toss to coat, adding pasta water as needed to create a silky sauce.',
                'Return shrimp to the pan. Toss everything together and garnish with parsley and Parmesan.',
            ],
            'tips'         => 'Don\'t overcook the shrimp – it goes rubbery very quickly. Buy frozen shrimp already peeled and deveined to save time.',
            'nutrition'    => ['calories' => 485, 'protein' => 32, 'carbs' => 52, 'fat' => 14, 'fiber' => 3],
        ],

        'comfort food recipes' => [
            'title'        => 'Ultimate Comfort Food: Creamy Mac and Cheese',
            'cuisine'      => 'American',
            'diet'         => 'Vegetarian',
            'prep_time'    => 10,
            'cook_time'    => 25,
            'servings'     => 6,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '1 lb elbow macaroni',
                '4 tbsp unsalted butter',
                '¼ cup all-purpose flour',
                '3 cups whole milk',
                '1 cup heavy cream',
                '2 cups sharp cheddar cheese, grated',
                '1 cup Gruyère cheese, grated',
                '½ cup Parmesan cheese, grated',
                '1 tsp mustard powder',
                '½ tsp onion powder',
                'Salt, pepper, and a pinch of cayenne',
                '½ cup panko breadcrumbs mixed with 2 tbsp butter (topping)',
            ],
            'instructions' => [
                'Cook macaroni in salted water until just al dente (it will cook more in the oven). Drain and set aside.',
                'Preheat oven to 375°F (190°C).',
                'Melt butter in a large saucepan over medium heat. Whisk in flour and cook for 1-2 minutes to form a roux.',
                'Gradually pour in milk and cream, whisking constantly to prevent lumps. Cook for 5 minutes until thickened.',
                'Remove from heat and stir in cheddar, Gruyère, and most of the Parmesan. Season with mustard powder, onion powder, salt, pepper, and cayenne.',
                'Add the cooked macaroni to the cheese sauce and stir to coat thoroughly.',
                'Pour into a greased 9×13 inch baking dish. Top with buttered panko and remaining Parmesan.',
                'Bake for 25 minutes until bubbly and golden brown on top. Let stand 5 minutes before serving.',
            ],
            'tips'         => 'Grate your own cheese – pre-shredded cheese has anti-caking agents that prevent it melting smoothly.',
            'nutrition'    => ['calories' => 620, 'protein' => 26, 'carbs' => 58, 'fat' => 32, 'fiber' => 2],
        ],

        'Mediterranean diet recipes' => [
            'title'        => 'Classic Mediterranean Diet Greek Salad with Grilled Fish',
            'cuisine'      => 'Mediterranean',
            'diet'         => 'Mediterranean',
            'prep_time'    => 15,
            'cook_time'    => 15,
            'servings'     => 4,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '4 white fish fillets (sea bass, cod, or tilapia)',
                '3 tbsp olive oil, divided',
                '2 tsp dried oregano',
                '1 tsp garlic powder',
                '4 medium tomatoes, cut into wedges',
                '1 English cucumber, sliced into half-moons',
                '1 red onion, thinly sliced',
                '1 cup Kalamata olives',
                '200g feta cheese, cut into cubes',
                '2 tbsp red wine vinegar',
                '1 tbsp extra-virgin olive oil',
                'Salt and pepper to taste',
                'Fresh parsley to garnish',
            ],
            'instructions' => [
                'Season fish fillets with 1 tbsp olive oil, dried oregano, garlic powder, salt, and pepper.',
                'Heat a grill pan or skillet over medium-high heat with the remaining olive oil. Cook fish for 3-4 minutes per side until it flakes easily.',
                'While the fish cooks, combine tomatoes, cucumber, red onion, and olives in a large bowl.',
                'Whisk together red wine vinegar, extra-virgin olive oil, remaining oregano, salt, and pepper to make the dressing.',
                'Pour the dressing over the salad and toss gently.',
                'Divide the salad among four plates and top with cubed feta.',
                'Serve the grilled fish alongside the salad and garnish with fresh parsley.',
            ],
            'tips'         => 'A true Mediterranean diet is about lifestyle, not just food. Use the best quality extra-virgin olive oil you can afford – it makes a big difference.',
            'nutrition'    => ['calories' => 390, 'protein' => 34, 'carbs' => 14, 'fat' => 23, 'fiber' => 4],
        ],

        'high protein breakfast' => [
            'title'        => 'High Protein Cottage Cheese Breakfast Bowl',
            'cuisine'      => 'American',
            'diet'         => 'High Protein',
            'prep_time'    => 5,
            'cook_time'    => 0,
            'servings'     => 2,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '2 cups low-fat cottage cheese',
                '2 tbsp almond butter',
                '1 banana, sliced',
                '½ cup blueberries',
                '2 tbsp honey',
                '2 tbsp sunflower seeds',
                '2 tbsp protein granola',
                '1 tsp cinnamon',
            ],
            'instructions' => [
                'Divide cottage cheese evenly between two bowls.',
                'Add a swirl of almond butter to each bowl.',
                'Arrange banana slices and blueberries on top.',
                'Drizzle each bowl with honey.',
                'Sprinkle with sunflower seeds, protein granola, and cinnamon.',
                'Serve immediately.',
            ],
            'tips'         => 'Cottage cheese has around 25g protein per cup, making this one of the highest-protein no-cook breakfasts available. Add a scoop of protein powder to the cottage cheese for even more protein.',
            'nutrition'    => ['calories' => 330, 'protein' => 28, 'carbs' => 38, 'fat' => 10, 'fiber' => 4],
        ],

        'tuna salad recipe' => [
            'title'        => 'Classic Creamy Tuna Salad with Avocado',
            'cuisine'      => 'American',
            'diet'         => 'High Protein',
            'prep_time'    => 10,
            'cook_time'    => 0,
            'servings'     => 4,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '2 cans (5 oz each) solid white albacore tuna in water, drained',
                '1 ripe avocado',
                '3 tbsp light mayonnaise',
                '2 tbsp Dijon mustard',
                '2 celery stalks, finely diced',
                '¼ red onion, finely diced',
                '2 tbsp capers, drained',
                '2 tbsp fresh dill or parsley',
                'Juice of ½ lemon',
                'Salt and pepper to taste',
                'Leafy greens or bread to serve',
            ],
            'instructions' => [
                'Drain the tuna thoroughly and flake it into a medium bowl.',
                'Mash the avocado in a separate bowl until mostly smooth.',
                'Combine the mashed avocado with mayonnaise and Dijon mustard.',
                'Add the avocado mixture to the tuna. Stir to combine.',
                'Fold in diced celery, red onion, capers, and fresh herbs.',
                'Add lemon juice and season generously with salt and pepper. Taste and adjust.',
                'Serve on top of leafy greens, in a sandwich, or with crackers.',
            ],
            'tips'         => 'The avocado replaces much of the mayo, cutting calories while adding healthy fats. Best served the same day as avocado browns quickly.',
            'nutrition'    => ['calories' => 210, 'protein' => 24, 'carbs' => 5, 'fat' => 11, 'fiber' => 3],
        ],

        'avocado toast recipe' => [
            'title'        => 'Perfect Avocado Toast with Poached Egg',
            'cuisine'      => 'American',
            'diet'         => 'Vegetarian',
            'prep_time'    => 10,
            'cook_time'    => 10,
            'servings'     => 2,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '2 thick slices of sourdough or whole-grain bread',
                '1 large ripe avocado',
                '2 large eggs',
                '1 tbsp white vinegar (for poaching)',
                '¼ tsp red pepper flakes',
                '¼ tsp everything bagel seasoning',
                '1 tsp lemon juice',
                'Flaky sea salt and black pepper to taste',
                'Microgreens or baby arugula to serve',
            ],
            'instructions' => [
                'Fill a deep saucepan with water, add white vinegar, and bring to a gentle simmer.',
                'While the water heats, toast the bread until golden and crispy.',
                'Halve and pit the avocado. Scoop into a bowl and mash with lemon juice, a pinch of salt, and black pepper. Leave it a bit chunky.',
                'Crack each egg into a small cup. Create a gentle whirlpool in the simmering water with a spoon, then carefully slide eggs in one at a time.',
                'Poach for exactly 3 minutes for a runny yolk. Remove with a slotted spoon and drain on paper towels.',
                'Spread the mashed avocado generously on each piece of toast.',
                'Top each toast with a poached egg.',
                'Garnish with red pepper flakes, everything bagel seasoning, microgreens, and a pinch of flaky sea salt.',
            ],
            'tips'         => 'The key to a good poach: use fresh eggs (they hold together better), a gentle simmer not a rolling boil, and the vinegar helps the whites set.',
            'nutrition'    => ['calories' => 340, 'protein' => 14, 'carbs' => 34, 'fat' => 18, 'fiber' => 8],
        ],
    ];

    $data = $library[$keyword] ?? null;
    if ($data === null) {
        // Fallback generic recipe
        $data = [
            'title'        => ucwords($keyword) . ' – A Delicious Recipe',
            'cuisine'      => 'International',
            'diet'         => 'Balanced',
            'prep_time'    => 15,
            'cook_time'    => 30,
            'servings'     => 4,
            'difficulty'   => 'Easy',
            'ingredients'  => [
                '2 cups main ingredient, prepared',
                '1 tbsp olive oil',
                '2 garlic cloves, minced',
                '1 tsp mixed herbs',
                'Salt and pepper to taste',
            ],
            'instructions' => [
                'Prepare all ingredients before you begin.',
                'Heat oil in a pan over medium heat.',
                'Add garlic and cook for 1 minute.',
                'Add the main ingredient and cook according to its requirements.',
                'Season to taste and serve.',
            ],
            'tips'         => 'Adjust seasonings to your taste preferences.',
            'nutrition'    => ['calories' => 300, 'protein' => 20, 'carbs' => 30, 'fat' => 10, 'fiber' => 5],
        ];
    }

    return $data;
}

/**
 * Generate a URL-friendly slug from a string.
 */
function generate_slug(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Generate SEO-optimized meta title (50-60 chars).
 */
function generate_meta_title(string $title, string $keyword): string
{
    $base = $title . ' | Jolly Good Chef';
    if (strlen($base) <= 60) {
        return $base;
    }
    // Truncate the recipe title part
    $suffix    = ' | Jolly Good Chef';
    $max_title = 60 - strlen($suffix);
    return substr($title, 0, $max_title) . $suffix;
}

/**
 * Generate SEO meta description (150-160 chars).
 */
function generate_meta_description(array $recipe_data, string $keyword): string
{
    $title      = $recipe_data['title'];
    $prep       = $recipe_data['prep_time'];
    $cook       = $recipe_data['cook_time'];
    $total      = $prep + $cook;
    $servings   = $recipe_data['servings'];
    $calories   = $recipe_data['nutrition']['calories'] ?? 0;

    $desc = "Make this easy {$keyword} at home in {$total} minutes. "
          . "Serving {$servings} people at {$calories} calories each. "
          . "Step-by-step recipe from Jolly Good Chef.";

    if (strlen($desc) > 160) {
        $desc = substr($desc, 0, 157) . '...';
    }
    return $desc;
}

// ─── Handle publish action ───────────────────────────────────────────────────
$publish_message = '';
$publish_error   = '';

if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'publish') {
        $keyword         = trim($_POST['keyword'] ?? '');
        $title           = trim($_POST['title'] ?? '');
        $cuisine         = trim($_POST['cuisine'] ?? '');
        $diet            = trim($_POST['diet'] ?? '');
        $ingredients_raw = $_POST['ingredients'] ?? '';
        $instructions_raw = $_POST['instructions'] ?? '';
        $tips            = trim($_POST['tips'] ?? '');
        $nutrition_json  = trim($_POST['nutrition_json'] ?? '{}');
        $meta_desc       = trim($_POST['meta_description'] ?? '');
        $prep_time       = (int)($_POST['prep_time'] ?? 15);
        $cook_time       = (int)($_POST['cook_time'] ?? 30);
        $servings        = (int)($_POST['servings'] ?? 4);
        $difficulty      = trim($_POST['difficulty'] ?? 'Easy');

        if (empty($title) || empty($keyword)) {
            $publish_error = 'Title and keyword are required.';
        } else {
            $base_slug = generate_slug($title);
            $slug      = $base_slug;

            // Check for slug collision and append counter if needed
            $counter = 1;
            do {
                $check = $pdo->prepare('SELECT id FROM recipes WHERE slug = ?');
                $check->execute([$slug]);
                if ($check->fetch()) {
                    $slug = $base_slug . '-' . $counter++;
                } else {
                    break;
                }
            } while ($counter < 100);

            // Parse ingredients and instructions (newline separated)
            $ingredients_arr  = array_filter(array_map('trim', explode("\n", $ingredients_raw)));
            $instructions_arr = array_filter(array_map('trim', explode("\n", $instructions_raw)));

            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO recipes
                        (user_id, title, cuisine, diet, ingredients_json, instructions_json,
                         tips, nutrition_json, slug, is_seo_generated, seo_keyword,
                         meta_description, is_public, created_at)
                     VALUES
                        (0, :title, :cuisine, :diet, :ingredients_json, :instructions_json,
                         :tips, :nutrition_json, :slug, 1, :seo_keyword,
                         :meta_description, 0, NOW())'
                );
                $stmt->execute([
                    ':title'            => $title,
                    ':cuisine'          => $cuisine,
                    ':diet'             => $diet,
                    ':ingredients_json' => json_encode(array_values($ingredients_arr)),
                    ':instructions_json' => json_encode(array_values($instructions_arr)),
                    ':tips'             => $tips,
                    ':nutrition_json'   => $nutrition_json,
                    ':slug'             => $slug,
                    ':seo_keyword'      => $keyword,
                    ':meta_description' => $meta_desc,
                ]);

                $new_id          = $pdo->lastInsertId();
                $publish_message = "✅ Recipe <strong>" . htmlspecialchars($title) . "</strong> published successfully! "
                                 . "<a href=\"seo_recipes_admin.php\">View in admin →</a>";
            } catch (PDOException $e) {
                error_log('SEO Recipe Generator publish error: ' . $e->getMessage());
                $publish_error = 'Database error. Please try again.';
            }
        }
    }
}

// ─── Generate recipe preview ─────────────────────────────────────────────────
$preview      = null;
$preview_slug = '';
$meta_title   = '';
$meta_desc_generated = '';

if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    $keyword     = trim($_POST['keyword'] ?? '');
    if ($keyword) {
        $recipe_data        = get_recipe_for_keyword($keyword);
        $preview            = $recipe_data;
        $preview['keyword'] = $keyword;
        $preview_slug       = generate_slug($recipe_data['title']);
        $meta_title         = generate_meta_title($recipe_data['title'], $keyword);
        $meta_desc_generated = generate_meta_description($recipe_data, $keyword);
    }
}

// ─── Get keyword generation stats ───────────────────────────────────────────
$keyword_stats = [];
if ($is_admin) {
    try {
        $stmt = $pdo->query(
            "SELECT seo_keyword, COUNT(*) as count
             FROM recipes
             WHERE is_seo_generated = 1
             GROUP BY seo_keyword
             ORDER BY count DESC"
        );
        foreach ($stmt->fetchAll() as $row) {
            $keyword_stats[$row['seo_keyword']] = (int)$row['count'];
        }
    } catch (PDOException $e) {
        // Columns may not exist yet – silently ignore
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Recipe Generator | Jolly Good Chef</title>
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
            background: linear-gradient(135deg, #e8490f 0%, #c73d0a 100%);
            color: #fff;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,.2);
        }
        .header h1 { font-size: 1.4rem; font-weight: 700; }
        .header .subtitle { font-size: .85rem; opacity: .9; }
        .header-links { display: flex; gap: 1rem; align-items: center; }
        .header-links a, .btn-logout {
            color: #fff;
            text-decoration: none;
            font-size: .85rem;
            padding: .4rem .9rem;
            background: rgba(255,255,255,.2);
            border-radius: 4px;
            border: 1px solid rgba(255,255,255,.3);
            cursor: pointer;
        }
        .header-links a:hover, .btn-logout:hover { background: rgba(255,255,255,.35); }

        /* ── Layout ── */
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 1.5rem; }

        /* ── Card ── */
        .card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .card h2 { font-size: 1.2rem; color: #e8490f; margin-bottom: 1.5rem; }

        /* ── Forms ── */
        .form-group { margin-bottom: 1.2rem; }
        label { display: block; font-weight: 600; font-size: .875rem; margin-bottom: .4rem; color: #444; }
        input[type=text], input[type=password], select, textarea {
            width: 100%;
            padding: .65rem .9rem;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: .9rem;
            transition: border-color .2s;
            font-family: inherit;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #e8490f;
        }
        textarea { resize: vertical; }

        /* ── Buttons ── */
        .btn {
            padding: .65rem 1.4rem;
            border: none;
            border-radius: 6px;
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .15s, transform .1s;
            text-decoration: none;
            display: inline-block;
        }
        .btn:active { transform: scale(.98); }
        .btn-primary { background: #e8490f; color: #fff; }
        .btn-primary:hover { opacity: .9; }
        .btn-success { background: #22c55e; color: #fff; }
        .btn-success:hover { opacity: .9; }
        .btn-secondary { background: #6b7280; color: #fff; }

        /* ── Alerts ── */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            font-size: .9rem;
        }
        .alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #22c55e; }
        .alert-error   { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .alert-info    { background: #dbeafe; color: #1e40af; border-left: 4px solid #3b82f6; }

        /* ── Grid ── */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; }
        @media (max-width: 768px) {
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
        }

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
        .login-card h2 { text-align: center; color: #e8490f; margin-bottom: 1.5rem; font-size: 1.3rem; }

        /* ── Preview ── */
        .preview-section { border: 2px dashed #e8490f; border-radius: 10px; padding: 1.5rem; }
        .preview-section h3 { color: #e8490f; margin-bottom: 1rem; }

        .seo-metrics { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .metric-badge {
            padding: .3rem .8rem;
            border-radius: 20px;
            font-size: .78rem;
            font-weight: 600;
        }
        .metric-good  { background: #dcfce7; color: #166534; }
        .metric-warn  { background: #fef9c3; color: #854d0e; }
        .metric-bad   { background: #fee2e2; color: #991b1b; }

        .recipe-preview h1 { font-size: 1.5rem; color: #1a1a2e; margin-bottom: .75rem; }
        .recipe-preview p.desc { color: #555; font-size: .9rem; line-height: 1.6; margin-bottom: 1rem; }
        .recipe-meta-row { display: flex; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1.2rem; font-size: .85rem; color: #666; }
        .recipe-meta-row span strong { color: #1a1a2e; }

        .recipe-columns { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 1.5rem; }
        @media (max-width: 640px) { .recipe-columns { grid-template-columns: 1fr; } }

        .recipe-preview h2 { font-size: 1rem; color: #e8490f; margin-bottom: .6rem; }
        .recipe-preview ul, .recipe-preview ol { padding-left: 1.3rem; }
        .recipe-preview li { margin-bottom: .4rem; font-size: .88rem; line-height: 1.5; }

        .nutrition-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: .75rem; }
        @media (max-width: 480px) { .nutrition-grid { grid-template-columns: repeat(2, 1fr); } }
        .nutrition-item { text-align: center; background: #f8f9fa; border-radius: 8px; padding: .75rem .5rem; }
        .nutrition-item .value { font-size: 1.2rem; font-weight: 700; color: #e8490f; }
        .nutrition-item .label { font-size: .72rem; color: #666; margin-top: .2rem; }

        .json-ld-preview {
            background: #1e1e2e;
            color: #a8d8a8;
            border-radius: 6px;
            padding: 1rem;
            font-size: .75rem;
            font-family: monospace;
            overflow-x: auto;
            max-height: 200px;
        }

        /* ── Keywords list ── */
        .keyword-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: .75rem; }
        .keyword-item {
            padding: .75rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background: #f9fafb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: .85rem;
        }
        .keyword-item.generated { border-color: #22c55e; background: #f0fdf4; }
        .keyword-badge { font-size: .7rem; background: #22c55e; color: #fff; padding: .2rem .5rem; border-radius: 10px; }

        /* ── Tabs ── */
        .tabs { display: flex; gap: 0; margin-bottom: 0; border-bottom: 2px solid #e0e0e0; }
        .tab {
            padding: .75rem 1.5rem;
            cursor: pointer;
            font-weight: 600;
            font-size: .9rem;
            color: #666;
            border: none;
            background: none;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: color .2s, border-color .2s;
        }
        .tab.active, .tab:hover { color: #e8490f; border-bottom-color: #e8490f; }

        .tab-content { display: none; padding-top: 1.5rem; }
        .tab-content.active { display: block; }

        /* ── Progress bar ── */
        #bulk-progress { display: none; margin-top: 1rem; }
        .progress-bar-wrap { background: #e0e0e0; border-radius: 10px; height: 12px; overflow: hidden; }
        .progress-bar-fill { height: 100%; background: #e8490f; width: 0%; transition: width .3s; }
        .progress-log {
            background: #1e1e2e;
            color: #cdd6f4;
            border-radius: 6px;
            padding: 1rem;
            font-family: monospace;
            font-size: .78rem;
            max-height: 180px;
            overflow-y: auto;
            margin-top: .75rem;
        }
    </style>
</head>
<body>

<?php if (!$is_admin): ?>
<!-- ════════════ LOGIN ════════════ -->
<div class="login-wrap">
    <div class="login-card">
        <div class="logo">🍳</div>
        <h2>SEO Recipe Generator</h2>
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
<!-- ════════════ MAIN APP ════════════ -->

<div class="header">
    <div>
        <h1>🍳 SEO Recipe Generator</h1>
        <div class="subtitle">Generate SEO-optimized recipe pages targeting popular food keywords</div>
    </div>
    <div class="header-links">
        <a href="seo_recipes_admin.php">📋 Admin Dashboard</a>
        <form method="POST" style="margin:0">
            <input type="hidden" name="admin_logout" value="1">
            <button type="submit" class="btn-logout">Logout</button>
        </form>
    </div>
</div>

<div class="container">

    <?php if ($publish_message): ?>
        <div class="alert alert-success"><?= $publish_message ?></div>
    <?php endif; ?>
    <?php if ($publish_error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($publish_error) ?></div>
    <?php endif; ?>

    <!-- ── Tabs ─────────────────────────────────── -->
    <div class="card" style="padding-bottom:0">
        <div class="tabs">
            <button class="tab active" onclick="showTab('generate')">⚡ Generate Recipe</button>
            <button class="tab" onclick="showTab('bulk')">📦 Bulk Generate</button>
            <button class="tab" onclick="showTab('keywords')">🎯 Target Keywords</button>
        </div>

        <!-- ── Single Generate ── -->
        <div id="tab-generate" class="tab-content active">
            <form method="POST" id="generator-form">
                <input type="hidden" name="action" value="generate">
                <div class="grid-2">
                    <div class="form-group">
                        <label for="keyword">Target Keyword *</label>
                        <select id="keyword" name="keyword" required onchange="document.getElementById('custom-keyword').style.display=this.value==='__custom'?'block':'none'">
                            <option value="">— Select a keyword —</option>
                            <?php foreach ($target_keywords as $kw): ?>
                                <option value="<?= htmlspecialchars($kw) ?>"
                                    <?= (isset($preview['keyword']) && $preview['keyword'] === $kw) ? 'selected' : '' ?>
                                    <?= isset($keyword_stats[$kw]) ? '' : '' ?>>
                                    <?= htmlspecialchars($kw) ?>
                                    <?= isset($keyword_stats[$kw]) ? ' (' . $keyword_stats[$kw] . ' generated)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__custom">+ Custom keyword…</option>
                        </select>
                    </div>
                    <div class="form-group" id="custom-keyword" style="display:none">
                        <label for="custom_keyword_input">Custom Keyword</label>
                        <input type="text" id="custom_keyword_input" name="custom_keyword" placeholder="e.g., easy pasta recipes">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">⚡ Generate Recipe Preview</button>
            </form>
        </div>

        <!-- ── Bulk Generate ── -->
        <div id="tab-bulk" class="tab-content">
            <div class="alert alert-info">
                Bulk generation will create one recipe per keyword and save them as drafts (unpublished).
                You can then review and publish them from the <a href="seo_recipes_admin.php">Admin Dashboard</a>.
            </div>
            <div class="form-group">
                <label>Select Keywords to Generate</label>
                <div class="keyword-grid" id="bulk-keyword-list">
                    <?php foreach ($target_keywords as $kw): ?>
                        <label class="keyword-item <?= isset($keyword_stats[$kw]) ? 'generated' : '' ?>">
                            <span>
                                <input type="checkbox" name="bulk_keywords[]" value="<?= htmlspecialchars($kw) ?>" checked>
                                <?= htmlspecialchars($kw) ?>
                            </span>
                            <?php if (isset($keyword_stats[$kw])): ?>
                                <span class="keyword-badge"><?= $keyword_stats[$kw] ?>×</span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="button" class="btn btn-primary" onclick="runBulkGenerate()">📦 Bulk Generate Selected</button>

            <div id="bulk-progress">
                <p style="font-weight:600;margin-bottom:.5rem">Generation Progress</p>
                <div class="progress-bar-wrap"><div class="progress-bar-fill" id="progress-fill"></div></div>
                <div class="progress-log" id="progress-log"></div>
            </div>
        </div>

        <!-- ── Target Keywords ── -->
        <div id="tab-keywords" class="tab-content">
            <p style="margin-bottom:1rem;color:#555;font-size:.9rem">
                These 21 keywords are pre-programmed into the generator. Click any keyword to generate a recipe.
            </p>
            <div class="keyword-grid">
                <?php foreach ($target_keywords as $kw): ?>
                    <div class="keyword-item <?= isset($keyword_stats[$kw]) ? 'generated' : '' ?>">
                        <span><?= htmlspecialchars($kw) ?></span>
                        <button type="button" onclick="quickGenerate(<?= htmlspecialchars(json_encode($kw), ENT_QUOTES) ?>)"
                                class="btn btn-primary" style="padding:.25rem .7rem;font-size:.78rem">
                            Generate
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── Preview ──────────────────────────────── -->
    <?php if ($preview): ?>
    <div class="card preview-section">
        <h3>👁️ Recipe Preview</h3>

        <!-- SEO Metrics -->
        <?php
            $mt_len   = strlen($meta_title);
            $md_len   = strlen($meta_desc_generated);
            $mt_class = ($mt_len >= 50 && $mt_len <= 60) ? 'metric-good' : (($mt_len < 50) ? 'metric-warn' : 'metric-bad');
            $md_class = ($md_len >= 150 && $md_len <= 160) ? 'metric-good' : (($md_len < 150) ? 'metric-warn' : 'metric-bad');
        ?>
        <div class="seo-metrics">
            <span class="metric-badge <?= $mt_class ?>">Meta Title: <?= $mt_len ?> chars <?= $mt_class === 'metric-good' ? '✓' : '⚠️' ?></span>
            <span class="metric-badge <?= $md_class ?>">Meta Desc: <?= $md_len ?> chars <?= $md_class === 'metric-good' ? '✓' : '⚠️' ?></span>
            <span class="metric-badge metric-good">✓ Schema Markup</span>
            <span class="metric-badge metric-good">✓ Keyword in H1</span>
            <span class="metric-badge metric-good">✓ Slug Generated</span>
        </div>

        <!-- Recipe preview -->
        <div class="recipe-preview">
            <h1><?= htmlspecialchars($preview['title']) ?></h1>
            <p class="desc"><?= htmlspecialchars($meta_desc_generated) ?></p>

            <div class="recipe-meta-row">
                <span>🕐 Prep: <strong><?= $preview['prep_time'] ?> min</strong></span>
                <span>🔥 Cook: <strong><?= $preview['cook_time'] ?> min</strong></span>
                <span>⏱️ Total: <strong><?= $preview['prep_time'] + $preview['cook_time'] ?> min</strong></span>
                <span>👥 Serves: <strong><?= $preview['servings'] ?></strong></span>
                <span>📊 Difficulty: <strong><?= htmlspecialchars($preview['difficulty']) ?></strong></span>
                <span>🌍 Cuisine: <strong><?= htmlspecialchars($preview['cuisine']) ?></strong></span>
            </div>

            <div class="recipe-columns">
                <div>
                    <h2>Ingredients</h2>
                    <ul>
                        <?php foreach ($preview['ingredients'] as $ing): ?>
                            <li><?= htmlspecialchars($ing) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h2>Instructions</h2>
                    <ol>
                        <?php foreach ($preview['instructions'] as $step): ?>
                            <li><?= htmlspecialchars($step) ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>

            <!-- Nutrition -->
            <div style="margin-bottom:1.5rem">
                <h2 style="margin-bottom:.75rem">Nutrition (per serving)</h2>
                <div class="nutrition-grid">
                    <div class="nutrition-item">
                        <div class="value"><?= $preview['nutrition']['calories'] ?></div>
                        <div class="label">Calories</div>
                    </div>
                    <div class="nutrition-item">
                        <div class="value"><?= $preview['nutrition']['protein'] ?>g</div>
                        <div class="label">Protein</div>
                    </div>
                    <div class="nutrition-item">
                        <div class="value"><?= $preview['nutrition']['carbs'] ?>g</div>
                        <div class="label">Carbs</div>
                    </div>
                    <div class="nutrition-item">
                        <div class="value"><?= $preview['nutrition']['fat'] ?>g</div>
                        <div class="label">Fat</div>
                    </div>
                    <div class="nutrition-item">
                        <div class="value"><?= $preview['nutrition']['fiber'] ?>g</div>
                        <div class="label">Fiber</div>
                    </div>
                </div>
            </div>

            <!-- JSON-LD preview -->
            <details>
                <summary style="cursor:pointer;font-weight:600;color:#e8490f;margin-bottom:.5rem">🔧 JSON-LD Structured Data Preview</summary>
                <div class="json-ld-preview">
                    <?php
                        $jld_preview = [
                            '@context' => 'https://schema.org',
                            '@type'    => 'Recipe',
                            'name'     => $preview['title'],
                            'description' => $meta_desc_generated,
                            'prepTime' => 'PT' . $preview['prep_time'] . 'M',
                            'cookTime' => 'PT' . $preview['cook_time'] . 'M',
                            'nutrition' => [
                                '@type'    => 'NutritionInformation',
                                'calories' => $preview['nutrition']['calories'] . ' calories',
                            ],
                            'keywords' => $preview['keyword'],
                        ];
                        echo htmlspecialchars(json_encode($jld_preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    ?>
                </div>
            </details>
        </div>

        <!-- Publish form (editable) -->
        <hr style="margin: 1.5rem 0; border-color: #e0e0e0">
        <h3 style="margin-bottom:1rem">✏️ Edit &amp; Publish</h3>
        <form method="POST">
            <input type="hidden" name="action" value="publish">
            <input type="hidden" name="keyword" value="<?= htmlspecialchars($preview['keyword']) ?>">
            <input type="hidden" name="nutrition_json" value="<?= htmlspecialchars(json_encode($preview['nutrition'])) ?>">
            <input type="hidden" name="prep_time" value="<?= (int)$preview['prep_time'] ?>">
            <input type="hidden" name="cook_time" value="<?= (int)$preview['cook_time'] ?>">
            <input type="hidden" name="servings" value="<?= (int)$preview['servings'] ?>">
            <input type="hidden" name="difficulty" value="<?= htmlspecialchars($preview['difficulty']) ?>">

            <div class="form-group">
                <label>SEO Title *
                    <span style="font-size:.75rem;color:#999;font-weight:400">(<?= strlen($preview['title']) ?> chars – aim for 50-60)</span>
                </label>
                <input type="text" name="title" value="<?= htmlspecialchars($preview['title']) ?>" required maxlength="100">
            </div>

            <div class="form-group">
                <label>Meta Description
                    <span style="font-size:.75rem;color:#999;font-weight:400">(aim for 150-160 chars)</span>
                </label>
                <textarea name="meta_description" rows="3" maxlength="200"><?= htmlspecialchars($meta_desc_generated) ?></textarea>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Cuisine</label>
                    <input type="text" name="cuisine" value="<?= htmlspecialchars($preview['cuisine']) ?>">
                </div>
                <div class="form-group">
                    <label>Diet Type</label>
                    <input type="text" name="diet" value="<?= htmlspecialchars($preview['diet']) ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label>Ingredients (one per line)</label>
                    <textarea name="ingredients" rows="8"><?= htmlspecialchars(implode("\n", $preview['ingredients'])) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Instructions (one step per line)</label>
                    <textarea name="instructions" rows="8"><?= htmlspecialchars(implode("\n", $preview['instructions'])) ?></textarea>
                </div>
            </div>

            <div class="form-group">
                <label>Chef's Tips (optional)</label>
                <textarea name="tips" rows="2"><?= htmlspecialchars($preview['tips'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-success">🚀 Publish to Database</button>
            <span style="margin-left:1rem;font-size:.83rem;color:#666">Slug will be: <code>/recipes/<?= htmlspecialchars($preview_slug) ?></code></span>
        </form>
    </div>
    <?php endif; ?>

</div><!-- /container -->

<script>
function showTab(name) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.target.classList.add('active');
}

function quickGenerate(keyword) {
    const form = document.getElementById('generator-form');
    document.getElementById('keyword').value = keyword;
    form.submit();
}

async function runBulkGenerate() {
    const checked = Array.from(document.querySelectorAll('input[name="bulk_keywords[]"]:checked'))
                         .map(el => el.value);
    if (!checked.length) {
        alert('Please select at least one keyword.');
        return;
    }

    const progressWrap = document.getElementById('bulk-progress');
    const fill         = document.getElementById('progress-fill');
    const log          = document.getElementById('progress-log');
    progressWrap.style.display = 'block';
    log.innerHTML = '';

    let done = 0;

    for (const keyword of checked) {
        const fd = new FormData();
        fd.append('action', 'bulk_generate_single');
        fd.append('keyword', keyword);

        try {
            const res  = await fetch(window.location.href, { method: 'POST', body: fd });
            const text = await res.text();
            const ok   = text.includes('"success":true');
            log.innerHTML += `<div style="color:${ok ? '#a6e3a1' : '#f38ba8'}">${ok ? '✓' : '✗'} ${keyword}</div>`;
        } catch (e) {
            log.innerHTML += `<div style="color:#f38ba8">✗ ${keyword} (network error)</div>`;
        }

        done++;
        fill.style.width = Math.round((done / checked.length) * 100) + '%';
        log.scrollTop = log.scrollHeight;
    }

    log.innerHTML += '<div style="color:#89b4fa;margin-top:.5rem">✅ Bulk generation complete! <a href="seo_recipes_admin.php" style="color:#89dceb">View in Admin →</a></div>';
}
</script>

<?php
// Handle AJAX bulk generate single
if ($is_admin && isset($_POST['action']) && $_POST['action'] === 'bulk_generate_single') {
    $kw   = trim($_POST['keyword'] ?? '');
    $resp = ['success' => false, 'message' => ''];

    if ($kw) {
        $data        = get_recipe_for_keyword($kw);
        $base_slug   = generate_slug($data['title']);
        $slug        = $base_slug;
        $counter     = 1;
        do {
            $check = $pdo->prepare('SELECT id FROM recipes WHERE slug = ?');
            $check->execute([$slug]);
            if ($check->fetch()) {
                $slug = $base_slug . '-' . $counter++;
            } else {
                break;
            }
        } while ($counter < 100);

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO recipes
                    (user_id, title, cuisine, diet, ingredients_json, instructions_json,
                     tips, nutrition_json, slug, is_seo_generated, seo_keyword,
                     meta_description, is_public, created_at)
                 VALUES
                    (0, :title, :cuisine, :diet, :ingredients_json, :instructions_json,
                     :tips, :nutrition_json, :slug, 1, :seo_keyword,
                     :meta_description, 0, NOW())'
            );
            $stmt->execute([
                ':title'             => $data['title'],
                ':cuisine'           => $data['cuisine'],
                ':diet'              => $data['diet'],
                ':ingredients_json'  => json_encode($data['ingredients']),
                ':instructions_json' => json_encode($data['instructions']),
                ':tips'              => $data['tips'] ?? '',
                ':nutrition_json'    => json_encode($data['nutrition']),
                ':slug'              => $slug,
                ':seo_keyword'       => $kw,
                ':meta_description'  => generate_meta_description($data, $kw),
            ]);
            $resp = ['success' => true, 'id' => $pdo->lastInsertId(), 'slug' => $slug];
        } catch (PDOException $e) {
            error_log('Bulk generate error: ' . $e->getMessage());
            $resp = ['success' => false, 'message' => 'DB error'];
        }
    }

    header('Content-Type: application/json');
    echo json_encode($resp);
    exit;
}
?>

<?php endif; ?>
</body>
</html>
