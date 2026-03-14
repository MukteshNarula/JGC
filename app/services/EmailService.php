<?php

/**
 * EmailService – Jolly Good Chef
 *
 * Handles all transactional and marketing emails sent by the platform.
 * Uses PHP's native mail() function with full RFC 2822 headers.
 *
 * Usage:
 *   $emailService = new EmailService($pdo);
 *   $emailService->sendWelcome($userId, $email, $name);
 */

class EmailService
{
    /** @var PDO */
    private PDO $pdo;

    /** @var string Sender name shown in the "From" header */
    private string $fromName = 'Jolly Good Chef';

    /** @var string Sender address */
    private string $fromEmail = 'noreply@jollygoodchef.com';

    /** @var string Reply-to address */
    private string $replyTo = 'support@jollygoodchef.com';

    /** @var string Base URL of the app */
    private string $appUrl = 'https://app.jollygoodchef.com';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // =========================================================================
    // PUBLIC SEND METHODS
    // =========================================================================

    /**
     * Welcome email sent immediately after registration.
     */
    public function sendWelcome(int $userId, string $email, string $name): bool
    {
        $subject = 'Welcome to Jolly Good Chef! 🍳 Your account is ready';
        $body    = $this->getWelcomeTemplate($name);
        return $this->send($email, $subject, $body, $userId, 'welcome');
    }

    /**
     * Payment / subscription confirmation.
     *
     * @param array $details Keys: plan, amount, order_id, payment_id
     */
    public function sendPaymentConfirmation(int $userId, string $email, string $name, array $details): bool
    {
        $subject = 'Payment Confirmed – Welcome to ' . ($details['plan'] ?? 'Premium') . '! 🎉';
        $body    = $this->getPaymentConfirmationTemplate($name, $details);
        return $this->send($email, $subject, $body, $userId, 'payment_confirmation');
    }

    /**
     * Meal plan sharing / WhatsApp order summary email.
     *
     * @param array $mealPlan Keys: week_label, meals (array of day => [breakfast, lunch, dinner])
     */
    public function sendMealPlanEmail(int $userId, string $email, string $name, array $mealPlan): bool
    {
        $subject = 'Your Meal Plan is Ready! 📅';
        $body    = $this->getMealPlanTemplate($name, $mealPlan);
        return $this->send($email, $subject, $body, $userId, 'meal_plan');
    }

    /**
     * Order confirmation (Razorpay / marketplace orders).
     *
     * @param array $order Keys: order_id, items (array), total, address
     */
    public function sendOrderConfirmationEmail(int $userId, string $email, string $name, array $order): bool
    {
        $subject = 'Order Confirmed! Your ingredients are on their way 🛒';
        $body    = $this->getOrderConfirmationTemplate($name, $order);
        return $this->send($email, $subject, $body, $userId, 'order_confirmation');
    }

    /**
     * Recipe generation success notification.
     *
     * @param array $recipe Keys: title, cuisine, servings, url
     */
    public function sendRecipeGenerationEmail(int $userId, string $email, string $name, array $recipe): bool
    {
        $subject = 'Your AI Recipe is Ready: ' . ($recipe['title'] ?? 'New Recipe') . ' 🍽️';
        $body    = $this->getRecipeGenerationTemplate($name, $recipe);
        return $this->send($email, $subject, $body, $userId, 'recipe_generation');
    }

    /**
     * Grocery list ready notification.
     *
     * @param array $groceryList Keys: week_label, items (array), total_estimate
     */
    public function sendGroceryListEmail(int $userId, string $email, string $name, array $groceryList): bool
    {
        $subject = 'Your Smart Grocery List is Ready! 🛍️';
        $body    = $this->getGroceryListTemplate($name, $groceryList);
        return $this->send($email, $subject, $body, $userId, 'grocery_list');
    }

    /**
     * Monthly newsletter sent to all users on the 1st of each month.
     *
     * @param array $data Keys: top_recipes, user_stats, community_highlights, new_features, tips
     */
    public function sendMonthlyNewsletter(int $userId, string $email, string $name, array $data): bool
    {
        $month   = date('F Y');
        $subject = "Your {$month} Jolly Good Chef Newsletter 📰";
        $body    = $this->getMonthlyNewsletterTemplate($name, $data);
        return $this->send($email, $subject, $body, $userId, 'monthly_newsletter');
    }

    // =========================================================================
    // CORE SEND METHOD
    // =========================================================================

    /**
     * Sends an HTML email and logs it to the email_logs table.
     *
     * @param string $to        Recipient email address
     * @param string $subject   Email subject
     * @param string $body      HTML body
     * @param int    $userId    User ID for logging
     * @param string $emailType Identifier stored in email_logs.email_type
     */
    public function send(string $to, string $subject, string $body, int $userId, string $emailType): bool
    {
        // Validate recipient address before attempting send
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("[EmailService] Invalid email address: {$to}");
            return false;
        }

        $boundary = md5(uniqid((string) rand(), true));
        $headers  = implode("\r\n", [
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "From: {$this->fromName} <{$this->fromEmail}>",
            "Reply-To: {$this->replyTo}",
            "X-Mailer: PHP/" . PHP_VERSION,
            "X-Email-Type: {$emailType}",
        ]);

        $plainText = $this->htmlToPlainText($body);

        $message = "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($plainText) . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($body) . "\r\n\r\n"
            . "--{$boundary}--";

        $sent = mail($to, $subject, $message, $headers);

        $this->logEmail($userId, $to, $subject, $emailType, $sent);

        if (!$sent) {
            error_log("[EmailService] mail() failed for {$to} (type={$emailType})");
        }

        return $sent;
    }

    // =========================================================================
    // EMAIL TEMPLATES
    // =========================================================================

    private function getWelcomeTemplate(string $name): string
    {
        $dashboardUrl  = $this->appUrl . '/app/index.php?page=dashboard';
        $generatorUrl  = $this->appUrl . '/app/index.php?page=generator';
        $prefsUrl      = $this->appUrl . '/app/index.php?page=email-preferences';

        return $this->wrapLayout("Welcome to Jolly Good Chef! 🎉", "
            <p>Hi {$name}! 👋</p>
            <p>Welcome to <strong>Jolly Good Chef</strong> – your personal AI-powered cooking assistant!</p>
            <p>Here's what you can do right away:</p>
            <ul>
                <li>🤖 <strong>Generate your first AI recipe</strong> – tell the AI what you have at home</li>
                <li>📅 <strong>Plan your meals</strong> – organise breakfast, lunch &amp; dinner for the week</li>
                <li>🛒 <strong>Get a smart grocery list</strong> – auto-generated from your meal plan</li>
                <li>🌍 <strong>Explore the community</strong> – discover thousands of shared recipes</li>
            </ul>
            <p style='text-align:center; margin: 28px 0;'>
                <a href='{$generatorUrl}' style='{$this->btnStyle('#667eea')}'>Generate Your First Recipe 🍳</a>
            </p>
            <p>If you have any questions, just reply to this email – we're always happy to help.</p>
            <p>Happy Cooking!<br>The Jolly Good Chef Team 🍴</p>
        ", $prefsUrl);
    }

    private function getPaymentConfirmationTemplate(string $name, array $details): string
    {
        $plan       = htmlspecialchars($details['plan']      ?? 'Premium', ENT_QUOTES, 'UTF-8');
        $amount     = htmlspecialchars($details['amount']    ?? '', ENT_QUOTES, 'UTF-8');
        $orderId    = htmlspecialchars($details['order_id']  ?? '', ENT_QUOTES, 'UTF-8');
        $paymentId  = htmlspecialchars($details['payment_id'] ?? '', ENT_QUOTES, 'UTF-8');
        $dashUrl    = $this->appUrl . '/app/index.php?page=dashboard';
        $prefsUrl   = $this->appUrl . '/app/index.php?page=email-preferences';

        return $this->wrapLayout("Payment Confirmed! 🎉", "
            <p>Hi {$name}! 🎉</p>
            <p>Your payment has been confirmed and your <strong>{$plan}</strong> subscription is now active.</p>
            <table style='width:100%; border-collapse:collapse; margin: 20px 0; font-size:14px;'>
                <tr style='background:#f8f9ff;'>
                    <td style='padding:12px 16px; border:1px solid #e5e7eb; font-weight:600;'>Plan</td>
                    <td style='padding:12px 16px; border:1px solid #e5e7eb;'>{$plan}</td>
                </tr>
                <tr>
                    <td style='padding:12px 16px; border:1px solid #e5e7eb; font-weight:600;'>Amount Paid</td>
                    <td style='padding:12px 16px; border:1px solid #e5e7eb;'>{$amount}</td>
                </tr>
                <tr style='background:#f8f9ff;'>
                    <td style='padding:12px 16px; border:1px solid #e5e7eb; font-weight:600;'>Order ID</td>
                    <td style='padding:12px 16px; border:1px solid #e5e7eb;'>{$orderId}</td>
                </tr>
                <tr>
                    <td style='padding:12px 16px; border:1px solid #e5e7eb; font-weight:600;'>Payment ID</td>
                    <td style='padding:12px 16px; border:1px solid #e5e7eb;'>{$paymentId}</td>
                </tr>
            </table>
            <p>You now have access to <strong>unlimited AI recipe generation</strong>, full meal planning, smart grocery lists, and more. 🚀</p>
            <p style='text-align:center; margin: 28px 0;'>
                <a href='{$dashUrl}' style='{$this->btnStyle('#27ae60')}'>Go to Your Dashboard</a>
            </p>
            <p>Keep this email as your payment receipt. If you have any billing queries, reply here or email <a href='mailto:support@jollygoodchef.com'>support@jollygoodchef.com</a>.</p>
            <p>Happy Cooking!<br>The Jolly Good Chef Team 🍴</p>
        ", $prefsUrl);
    }

    private function getMealPlanTemplate(string $name, array $mealPlan): string
    {
        $weekLabel = htmlspecialchars($mealPlan['week_label'] ?? 'This Week', ENT_QUOTES, 'UTF-8');
        $meals     = $mealPlan['meals'] ?? [];
        $planUrl   = $this->appUrl . '/app/index.php?page=meal-planner';
        $prefsUrl  = $this->appUrl . '/app/index.php?page=email-preferences';

        $mealsHtml = '';
        foreach ($meals as $day => $dayMeals) {
            $day      = htmlspecialchars((string) $day, ENT_QUOTES, 'UTF-8');
            $bfast    = htmlspecialchars($dayMeals['breakfast'] ?? '–', ENT_QUOTES, 'UTF-8');
            $lunch    = htmlspecialchars($dayMeals['lunch']     ?? '–', ENT_QUOTES, 'UTF-8');
            $dinner   = htmlspecialchars($dayMeals['dinner']    ?? '–', ENT_QUOTES, 'UTF-8');
            $mealsHtml .= "
            <tr>
                <td style='padding:10px 14px; border:1px solid #e5e7eb; font-weight:600; background:#f8f9ff;'>{$day}</td>
                <td style='padding:10px 14px; border:1px solid #e5e7eb;'>{$bfast}</td>
                <td style='padding:10px 14px; border:1px solid #e5e7eb;'>{$lunch}</td>
                <td style='padding:10px 14px; border:1px solid #e5e7eb;'>{$dinner}</td>
            </tr>";
        }

        $tableHtml = $mealsHtml
            ? "<table style='width:100%; border-collapse:collapse; font-size:13px; margin:20px 0;'>
                <thead>
                    <tr style='background: linear-gradient(135deg,#667eea,#764ba2); color:#fff;'>
                        <th style='padding:10px 14px; text-align:left;'>Day</th>
                        <th style='padding:10px 14px; text-align:left;'>Breakfast</th>
                        <th style='padding:10px 14px; text-align:left;'>Lunch</th>
                        <th style='padding:10px 14px; text-align:left;'>Dinner</th>
                    </tr>
                </thead>
                <tbody>{$mealsHtml}</tbody>
               </table>"
            : '<p style="color:#999;">No meals planned yet – head to the meal planner to get started!</p>';

        return $this->wrapLayout("Your Meal Plan for {$weekLabel} 📅", "
            <p>Hi {$name}! 📅</p>
            <p>Here's your meal plan for <strong>{$weekLabel}</strong>:</p>
            {$tableHtml}
            <p style='text-align:center; margin: 28px 0;'>
                <a href='{$planUrl}' style='{$this->btnStyle('#667eea')}'>View Full Meal Plan</a>
            </p>
            <p>Tip: Your grocery list has been updated automatically based on this plan. 🛒</p>
            <p>Happy Cooking!<br>The Jolly Good Chef Team 🍴</p>
        ", $prefsUrl);
    }

    private function getOrderConfirmationTemplate(string $name, array $order): string
    {
        $orderId  = htmlspecialchars($order['order_id'] ?? '', ENT_QUOTES, 'UTF-8');
        $total    = htmlspecialchars($order['total']    ?? '', ENT_QUOTES, 'UTF-8');
        $address  = htmlspecialchars($order['address']  ?? '', ENT_QUOTES, 'UTF-8');
        $items    = $order['items'] ?? [];
        $prefsUrl = $this->appUrl . '/app/index.php?page=email-preferences';

        $itemsHtml = '';
        foreach ($items as $item) {
            $itemName = htmlspecialchars($item['name']     ?? '', ENT_QUOTES, 'UTF-8');
            $qty      = htmlspecialchars((string)($item['quantity'] ?? ''), ENT_QUOTES, 'UTF-8');
            $price    = htmlspecialchars($item['price']    ?? '', ENT_QUOTES, 'UTF-8');
            $itemsHtml .= "
            <tr>
                <td style='padding:10px 14px; border:1px solid #e5e7eb;'>{$itemName}</td>
                <td style='padding:10px 14px; border:1px solid #e5e7eb; text-align:center;'>{$qty}</td>
                <td style='padding:10px 14px; border:1px solid #e5e7eb; text-align:right;'>{$price}</td>
            </tr>";
        }

        $itemsTable = $itemsHtml
            ? "<table style='width:100%; border-collapse:collapse; font-size:13px; margin:20px 0;'>
                <thead>
                    <tr style='background: linear-gradient(135deg,#667eea,#764ba2); color:#fff;'>
                        <th style='padding:10px 14px; text-align:left;'>Item</th>
                        <th style='padding:10px 14px; text-align:center;'>Qty</th>
                        <th style='padding:10px 14px; text-align:right;'>Price</th>
                    </tr>
                </thead>
                <tbody>
                    {$itemsHtml}
                    <tr style='background:#f8f9ff; font-weight:700;'>
                        <td colspan='2' style='padding:10px 14px; border:1px solid #e5e7eb;'>Total</td>
                        <td style='padding:10px 14px; border:1px solid #e5e7eb; text-align:right;'>{$total}</td>
                    </tr>
                </tbody>
               </table>"
            : '';

        $addressHtml = $address
            ? "<p><strong>Delivery address:</strong> {$address}</p>"
            : '';

        return $this->wrapLayout("Order Confirmed! 🛒", "
            <p>Hi {$name}! 🛒</p>
            <p>Your order <strong>#{$orderId}</strong> has been confirmed.</p>
            {$itemsTable}
            {$addressHtml}
            <p>We'll notify you when your order is on its way. If you have questions, reply to this email.</p>
            <p>Happy Cooking!<br>The Jolly Good Chef Team 🍴</p>
        ", $prefsUrl);
    }

    private function getRecipeGenerationTemplate(string $name, array $recipe): string
    {
        $title    = htmlspecialchars($recipe['title']    ?? 'Your Recipe', ENT_QUOTES, 'UTF-8');
        $cuisine  = htmlspecialchars($recipe['cuisine']  ?? '', ENT_QUOTES, 'UTF-8');
        $servings = htmlspecialchars((string)($recipe['servings'] ?? ''), ENT_QUOTES, 'UTF-8');
        $url      = htmlspecialchars($recipe['url']      ?? ($this->appUrl . '/app/index.php?page=history'), ENT_QUOTES, 'UTF-8');
        $prefsUrl = $this->appUrl . '/app/index.php?page=email-preferences';

        $meta = '';
        if ($cuisine)  $meta .= "<li>🌍 Cuisine: <strong>{$cuisine}</strong></li>";
        if ($servings) $meta .= "<li>👥 Servings: <strong>{$servings}</strong></li>";

        return $this->wrapLayout("Your Recipe is Ready: {$title} 🍽️", "
            <p>Hi {$name}! 🍽️</p>
            <p>Your AI-generated recipe <strong>\"{$title}\"</strong> is ready!</p>
            " . ($meta ? "<ul style='margin:16px 0;'>{$meta}</ul>" : "") . "
            <p style='text-align:center; margin: 28px 0;'>
                <a href='{$url}' style='{$this->btnStyle('#667eea')}'>View Your Recipe</a>
            </p>
            <p>Enjoy cooking, and don't forget to add it to your meal plan! 📅</p>
            <p>Happy Cooking!<br>The Jolly Good Chef Team 🍴</p>
        ", $prefsUrl);
    }

    private function getGroceryListTemplate(string $name, array $groceryList): string
    {
        $weekLabel     = htmlspecialchars($groceryList['week_label']    ?? 'This Week', ENT_QUOTES, 'UTF-8');
        $totalEstimate = htmlspecialchars($groceryList['total_estimate'] ?? '', ENT_QUOTES, 'UTF-8');
        $items         = $groceryList['items'] ?? [];
        $listUrl       = $this->appUrl . '/app/index.php?page=grocery-list';
        $prefsUrl      = $this->appUrl . '/app/index.php?page=email-preferences';

        $itemsHtml = '';
        foreach ($items as $item) {
            $itemName = htmlspecialchars($item['name']     ?? '', ENT_QUOTES, 'UTF-8');
            $qty      = htmlspecialchars((string)($item['quantity'] ?? ''), ENT_QUOTES, 'UTF-8');
            $unit     = htmlspecialchars($item['unit']     ?? '', ENT_QUOTES, 'UTF-8');
            $itemsHtml .= "<li style='padding:6px 0; border-bottom:1px solid #f3f4f6;'>{$itemName}"
                . ($qty ? " – <strong>{$qty} {$unit}</strong>" : '') . '</li>';
        }

        $listBlock = $itemsHtml
            ? "<ul style='list-style:none; padding:0; margin:20px 0; font-size:14px;'>{$itemsHtml}</ul>"
            : '<p style="color:#999;">Your grocery list is empty – plan some meals first!</p>';

        $totalBlock = $totalEstimate
            ? "<p style='background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px 16px; font-weight:700;'>💰 Estimated Total: {$totalEstimate}</p>"
            : '';

        return $this->wrapLayout("Your Grocery List for {$weekLabel} 🛍️", "
            <p>Hi {$name}! 🛍️</p>
            <p>Here's your smart grocery list for <strong>{$weekLabel}</strong>:</p>
            {$listBlock}
            {$totalBlock}
            <p style='text-align:center; margin: 28px 0;'>
                <a href='{$listUrl}' style='{$this->btnStyle('#667eea')}'>View Full List</a>
            </p>
            <p>Happy Shopping &amp; Happy Cooking!<br>The Jolly Good Chef Team 🍴</p>
        ", $prefsUrl);
    }

    private function getMonthlyNewsletterTemplate(string $name, array $data): string
    {
        $month              = date('F Y');
        $topRecipes         = $data['top_recipes']         ?? [];
        $userStats          = $data['user_stats']          ?? [];
        $communityHighlights = $data['community_highlights'] ?? [];
        $newFeatures        = $data['new_features']        ?? [];
        $tips               = $data['tips']                ?? [];
        $prefsUrl           = $this->appUrl . '/app/index.php?page=email-preferences';

        // --- Top Recipes ---
        $recipesHtml = '';
        foreach ($topRecipes as $recipe) {
            $title  = htmlspecialchars($recipe['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $likes  = (int)($recipe['likes'] ?? 0);
            $id     = (int)($recipe['id']    ?? 0);
            $recipeUrl = $this->appUrl . "/app/index.php?page=recipe&id={$id}";
            $recipesHtml .= "
            <tr>
                <td style='padding:10px 14px; border-bottom:1px solid #f3f4f6;'>
                    <strong>{$title}</strong><br>
                    <span style='color:#9ca3af; font-size:12px;'>❤️ {$likes} likes</span>
                </td>
                <td style='padding:10px 14px; border-bottom:1px solid #f3f4f6; text-align:right;'>
                    <a href='{$recipeUrl}' style='background:#667eea; color:#fff; padding:5px 14px; border-radius:6px; font-size:12px; text-decoration:none; font-weight:600;'>View</a>
                </td>
            </tr>";
        }
        $recipesSection = $recipesHtml
            ? "<table style='width:100%; border-collapse:collapse; font-size:14px;'><tbody>{$recipesHtml}</tbody></table>"
            : '<p style="color:#9ca3af; text-align:center;">No trending recipes this month – be the first to share one!</p>';

        // --- User Stats ---
        $recipesGenerated = (int)($userStats['recipes_generated'] ?? 0);
        $mealsPlanned     = (int)($userStats['meals_planned']     ?? 0);
        $favourites       = (int)($userStats['favorite_recipes']  ?? 0);
        $statsSection = "
        <table style='width:100%; border-collapse:collapse; text-align:center;'>
            <tr>
                <td style='padding:16px; background:#f8f9ff; border-radius:8px;'>
                    <div style='font-size:28px; font-weight:800; color:#667eea;'>{$recipesGenerated}</div>
                    <div style='font-size:12px; color:#9ca3af; margin-top:4px;'>Recipes Generated</div>
                </td>
                <td style='width:12px;'></td>
                <td style='padding:16px; background:#f0fdf4; border-radius:8px;'>
                    <div style='font-size:28px; font-weight:800; color:#27ae60;'>{$mealsPlanned}</div>
                    <div style='font-size:12px; color:#9ca3af; margin-top:4px;'>Meals Planned</div>
                </td>
                <td style='width:12px;'></td>
                <td style='padding:16px; background:#fffbeb; border-radius:8px;'>
                    <div style='font-size:28px; font-weight:800; color:#f59e0b;'>{$favourites}</div>
                    <div style='font-size:12px; color:#9ca3af; margin-top:4px;'>Favourite Recipes</div>
                </td>
            </tr>
        </table>";

        // --- Community Highlights ---
        $communityHtml = '';
        foreach ($communityHighlights as $highlight) {
            $hTitle  = htmlspecialchars($highlight['title']       ?? '', ENT_QUOTES, 'UTF-8');
            $hDesc   = htmlspecialchars($highlight['description'] ?? '', ENT_QUOTES, 'UTF-8');
            $hAuthor = htmlspecialchars($highlight['author']      ?? 'Community Member', ENT_QUOTES, 'UTF-8');
            $communityHtml .= "
            <div style='padding:14px 16px; background:#f8f9ff; border-left:4px solid #667eea; margin-bottom:10px; border-radius:0 8px 8px 0;'>
                <strong style='color:#1a1a2e;'>{$hTitle}</strong><br>
                <span style='font-size:13px; color:#6b7280;'>{$hDesc}</span><br>
                <small style='color:#9ca3af;'>by {$hAuthor}</small>
            </div>";
        }
        if (!$communityHtml) {
            $communityUrl  = $this->appUrl . '/app/index.php?page=community';
            $communityHtml = "<p style='color:#9ca3af; text-align:center;'>Explore the community and discover amazing recipes! "
                . "<a href='{$communityUrl}' style='color:#667eea;'>Visit now →</a></p>";
        }

        // --- New Features ---
        $featuresHtml = '';
        foreach ($newFeatures as $feature) {
            $fTitle = htmlspecialchars($feature['title']       ?? '', ENT_QUOTES, 'UTF-8');
            $fDesc  = htmlspecialchars($feature['description'] ?? '', ENT_QUOTES, 'UTF-8');
            $featuresHtml .= "
            <li style='margin-bottom:12px;'>
                <strong>{$fTitle}</strong><br>
                <span style='font-size:13px; color:#6b7280;'>{$fDesc}</span>
            </li>";
        }
        $featuresSection = $featuresHtml
            ? "<ul style='padding-left:20px; margin:0;'>{$featuresHtml}</ul>"
            : '<p style="color:#9ca3af;">Stay tuned – exciting updates are on the way!</p>';

        // --- Tips & Tricks ---
        $tipsHtml = '';
        foreach ($tips as $tip) {
            $tTitle = htmlspecialchars($tip['title']       ?? '', ENT_QUOTES, 'UTF-8');
            $tDesc  = htmlspecialchars($tip['description'] ?? '', ENT_QUOTES, 'UTF-8');
            $tipsHtml .= "
            <div style='padding:12px 14px; background:#fffbeb; border-left:3px solid #f59e0b; margin-bottom:10px; border-radius:0 8px 8px 0;'>
                💡 <strong>{$tTitle}</strong><br>
                <span style='font-size:13px; color:#6b7280;'>{$tDesc}</span>
            </div>";
        }
        $tipsSection = $tipsHtml ?: '<p style="color:#9ca3af;">Check back next month for more tips!</p>';

        $generatorUrl = $this->appUrl . '/app/index.php?page=generator';
        $historyUrl   = $this->appUrl . '/app/index.php?page=history';

        return "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<title>Jolly Good Chef – {$month} Newsletter</title>
</head>
<body style='margin:0; padding:0; background:#f3f4f6; font-family:\"Segoe UI\",Arial,sans-serif; color:#1a1a2e; line-height:1.6;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#f3f4f6; padding:32px 16px;'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' style='max-width:600px; width:100%; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.08);'>

  <!-- HEADER -->
  <tr>
    <td style='background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); padding:36px 32px; text-align:center;'>
      <h1 style='margin:0; color:#fff; font-size:26px; font-weight:800; letter-spacing:-0.5px;'>🍳 Jolly Good Chef</h1>
      <p style='margin:8px 0 0; color:rgba(255,255,255,.85); font-size:15px;'>{$month} Newsletter</p>
    </td>
  </tr>

  <!-- GREETING -->
  <tr>
    <td style='padding:28px 32px 20px;'>
      <p>Hi <strong>{$name}</strong>! 👋</p>
      <p style='color:#6b7280; margin:8px 0 0;'>Welcome to your monthly round-up! Here's what's been cooking at Jolly Good Chef this month. 🚀</p>
    </td>
  </tr>

  <!-- YOUR ACTIVITY -->
  <tr>
    <td style='padding:8px 32px 28px;'>
      <h2 style='margin:0 0 16px; font-size:18px; color:#667eea; border-bottom:2px solid #e5e7eb; padding-bottom:10px;'>📊 Your Activity This Month</h2>
      {$statsSection}
      <p style='text-align:center; margin:20px 0 0;'>
        <a href='{$historyUrl}' style='{$this->btnStyle('#667eea')}'>View Full History</a>
      </p>
    </td>
  </tr>

  <!-- DIVIDER -->
  <tr><td style='padding:0 32px;'><hr style='border:none; border-top:1px solid #f3f4f6; margin:0;'></td></tr>

  <!-- TOP TRENDING RECIPES -->
  <tr>
    <td style='padding:28px 32px;'>
      <h2 style='margin:0 0 8px; font-size:18px; color:#667eea; border-bottom:2px solid #e5e7eb; padding-bottom:10px;'>🔥 Top Trending Recipes</h2>
      <p style='color:#6b7280; font-size:14px; margin:0 0 16px;'>The most loved recipes from our community this month:</p>
      {$recipesSection}
      <p style='text-align:center; margin:20px 0 0;'>
        <a href='{$this->appUrl}/app/index.php?page=community' style='{$this->btnStyle('#667eea')}'>Explore More Recipes</a>
      </p>
    </td>
  </tr>

  <!-- DIVIDER -->
  <tr><td style='padding:0 32px;'><hr style='border:none; border-top:1px solid #f3f4f6; margin:0;'></td></tr>

  <!-- COMMUNITY HIGHLIGHTS -->
  <tr>
    <td style='padding:28px 32px;'>
      <h2 style='margin:0 0 8px; font-size:18px; color:#667eea; border-bottom:2px solid #e5e7eb; padding-bottom:10px;'>⭐ Community Highlights</h2>
      <p style='color:#6b7280; font-size:14px; margin:0 0 16px;'>Inspiring creations from the Jolly Good Chef community:</p>
      {$communityHtml}
    </td>
  </tr>

  <!-- DIVIDER -->
  <tr><td style='padding:0 32px;'><hr style='border:none; border-top:1px solid #f3f4f6; margin:0;'></td></tr>

  <!-- NEW FEATURES -->
  <tr>
    <td style='padding:28px 32px;'>
      <h2 style='margin:0 0 8px; font-size:18px; color:#667eea; border-bottom:2px solid #e5e7eb; padding-bottom:10px;'>✨ What's New This Month</h2>
      {$featuresSection}
      <p style='text-align:center; margin:20px 0 0;'>
        <a href='{$this->appUrl}/app/index.php?page=dashboard' style='{$this->btnStyle('#667eea')}'>Check It Out</a>
      </p>
    </td>
  </tr>

  <!-- DIVIDER -->
  <tr><td style='padding:0 32px;'><hr style='border:none; border-top:1px solid #f3f4f6; margin:0;'></td></tr>

  <!-- TIPS & TRICKS -->
  <tr>
    <td style='padding:28px 32px;'>
      <h2 style='margin:0 0 8px; font-size:18px; color:#667eea; border-bottom:2px solid #e5e7eb; padding-bottom:10px;'>💡 Tips &amp; Tricks</h2>
      <p style='color:#6b7280; font-size:14px; margin:0 0 16px;'>Quick cooking tips to make your life easier:</p>
      {$tipsSection}
    </td>
  </tr>

  <!-- CTA BAND -->
  <tr>
    <td style='padding:28px 32px; background:linear-gradient(135deg,#f0f4ff,#faf0ff); text-align:center;'>
      <h3 style='margin:0 0 8px; font-size:18px;'>Ready to create amazing recipes? 🎯</h3>
      <p style='color:#6b7280; margin:0 0 20px; font-size:14px;'>Generate personalised recipes with our AI, plan your meals, and get smart grocery lists – all in one place!</p>
      <a href='{$generatorUrl}' style='{$this->btnStyle('#27ae60')}'>Start Generating Now 🍳</a>
    </td>
  </tr>

  <!-- FOOTER -->
  <tr>
    <td style='background:#f8f9fa; padding:24px 32px; text-align:center; font-size:12px; color:#9ca3af;'>
      <p style='margin:0 0 8px;'>© " . date('Y') . " Jolly Good Chef. All rights reserved.</p>
      <p style='margin:0;'>
        <a href='{$prefsUrl}' style='color:#667eea; text-decoration:none;'>Manage Email Preferences</a>
        &nbsp;·&nbsp;
        <a href='{$this->appUrl}/app/index.php?page=privacy' style='color:#667eea; text-decoration:none;'>Privacy Policy</a>
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>";
    }

    // =========================================================================
    // LAYOUT WRAPPER
    // =========================================================================

    /**
     * Wraps a content block in the standard transactional email shell.
     */
    private function wrapLayout(string $title, string $content, string $prefsUrl): string
    {
        $year = date('Y');
        return "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<title>{$title}</title>
</head>
<body style='margin:0; padding:0; background:#f3f4f6; font-family:\"Segoe UI\",Arial,sans-serif; color:#1a1a2e; line-height:1.6;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#f3f4f6; padding:32px 16px;'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' style='max-width:600px; width:100%; background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.08);'>

  <!-- HEADER -->
  <tr>
    <td style='background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); padding:28px 32px; text-align:center;'>
      <h1 style='margin:0; color:#fff; font-size:22px; font-weight:800;'>🍳 Jolly Good Chef</h1>
    </td>
  </tr>

  <!-- CONTENT -->
  <tr>
    <td style='padding:32px;'>
      {$content}
    </td>
  </tr>

  <!-- FOOTER -->
  <tr>
    <td style='background:#f8f9fa; padding:20px 32px; text-align:center; font-size:12px; color:#9ca3af;'>
      <p style='margin:0 0 6px;'>© {$year} Jolly Good Chef. All rights reserved.</p>
      <p style='margin:0;'>
        <a href='{$prefsUrl}' style='color:#667eea; text-decoration:none;'>Manage Email Preferences</a>
        &nbsp;·&nbsp;
        <a href='{$this->appUrl}/app/index.php?page=privacy' style='color:#667eea; text-decoration:none;'>Privacy Policy</a>
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>";
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Returns an inline CSS string for a solid-colour CTA button.
     */
    private function btnStyle(string $bg): string
    {
        return "display:inline-block; background:{$bg}; color:#fff; padding:12px 28px;"
            . " border-radius:8px; text-decoration:none; font-weight:700; font-size:14px;";
    }

    /**
     * Very light HTML-to-plain-text converter for the multipart plain-text part.
     */
    private function htmlToPlainText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
        $text = preg_replace('/<\/p>/i', "\n\n", $text) ?? $text;
        $text = preg_replace('/<\/tr>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/h[1-6]>/i', "\n\n", $text) ?? $text;
        $text = preg_replace('/<\/li>/i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        return trim(preg_replace('/\n{3,}/', "\n\n", $text) ?? $text);
    }

    /**
     * Inserts a row into the email_logs table (fails silently so it never
     * interrupts a transaction or user-facing request).
     */
    private function logEmail(int $userId, string $email, string $subject, string $emailType, bool $success): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO email_logs (user_id, email, subject, email_type, status, sent_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $email,
                $subject,
                $emailType,
                $success ? 'sent' : 'failed',
            ]);
        } catch (Throwable $e) {
            error_log('[EmailService] Failed to log email: ' . $e->getMessage());
        }
    }
}
