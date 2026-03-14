# Jolly Good Chef (JGC)

A production-ready PHP application that generates SEO-optimized recipe pages for [Jolly Good Chef](https://jollygoodchef.com), targeting popular food keywords to improve Google rankings.

---

## Project Structure

```
JGC/
├── config/
│   └── db.example.php          # Database config template (copy to db.php)
├── migrations/
│   └── add_seo_columns.sql     # Database migration script
├── public_html/
│   └── templates/
│       └── recipe-template.php # Recipe display template with JSON-LD
├── seo_recipe_generator.php    # ⚡ Main SEO recipe generator
├── seo_recipes_admin.php       # 📋 Admin dashboard
├── sitemap.php                 # Dynamic XML sitemap
└── .gitignore
```

---

## Quick Start

### 1. Database Setup

Copy the config example and add your credentials:
```bash
cp config/db.example.php config/db.php
# Edit config/db.php with your database credentials
```

Run the migration to add SEO columns to the `recipes` table:
```sql
-- Run in your MySQL client or phpMyAdmin
SOURCE migrations/add_seo_columns.sql;
```

Or manually:
```sql
ALTER TABLE recipes
    ADD COLUMN IF NOT EXISTS slug VARCHAR(255) UNIQUE,
    ADD COLUMN IF NOT EXISTS is_seo_generated TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS seo_keyword VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS meta_description TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS is_public TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
```

### 2. Web Server Configuration

Add to your `.htaccess` for clean URLs and sitemap routing:
```apache
RewriteEngine On

# Route sitemap.xml to sitemap.php
RewriteRule ^sitemap\.xml$ sitemap.php [L,NC]

# Route recipe slugs
RewriteRule ^recipes/([a-z0-9-]+)/?$ index.php?page=recipe&slug=$1 [L,QSA,NC]
```

### 3. Set Admin Password

The `SEO_ADMIN_PASS` environment variable **must be set** before the admin tools can be used. There is no default fallback password.

```bash
# Apache (add to VirtualHost or .htaccess)
SetEnv SEO_ADMIN_PASS "your_secure_password_here"

# Or in your server's environment
export SEO_ADMIN_PASS="your_secure_password_here"
```

If the variable is not set, the login form will show a configuration error message.

---

## Tools

### SEO Recipe Generator
**URL:** `https://jollygoodchef.com/seo_recipe_generator.php`

- Generates SEO-optimized recipes for 21 target keywords
- Live preview before publishing
- Editable title, meta description, ingredients, and instructions
- One-click publish to database
- Bulk generate all keywords at once

### Admin Dashboard
**URL:** `https://jollygoodchef.com/seo_recipes_admin.php`

- View all SEO-generated recipes
- Approve / unpublish individual recipes
- Edit recipe content
- Bulk approve or delete
- Keyword performance tracking with progress bars
- Export recipes as CSV
- Pagination for large datasets

### Sitemap
**URL:** `https://jollygoodchef.com/sitemap.xml`

- Auto-includes all published recipes
- Submit to Google Search Console

---

## Target Keywords

The generator includes pre-built recipes for these 21 high-value keywords:

| # | Keyword |
|---|---------|
| 1 | healthy chicken dinner |
| 2 | high protein chicken dinner |
| 3 | quick chicken dinner |
| 4 | easy chicken recipes |
| 5 | healthy vegetarian dinner |
| 6 | vegan dinner recipes |
| 7 | healthy indian dinner |
| 8 | quick breakfast recipes |
| 9 | healthy breakfast meals |
| 10 | vegan desserts |
| 11 | high protein snacks |
| 12 | gluten-free cookies |
| 13 | keto diet meals |
| 14 | low calorie meals |
| 15 | budget friendly recipes |
| 16 | 30 minute dinners |
| 17 | comfort food recipes |
| 18 | Mediterranean diet recipes |
| 19 | high protein breakfast |
| 20 | tuna salad recipe |
| 21 | avocado toast recipe |

---

## SEO Features

- ✅ Meta titles (50-60 characters)
- ✅ Meta descriptions (150-160 characters)
- ✅ H1 tags with target keywords
- ✅ Schema.org Recipe JSON-LD structured data
- ✅ Canonical URLs
- ✅ Open Graph meta tags
- ✅ Alt text for recipe images
- ✅ Clean URL slugs
- ✅ XML sitemap

---

## Database Schema

The `recipes` table requires these columns (added via migration):

| Column | Type | Description |
|--------|------|-------------|
| `slug` | VARCHAR(255) UNIQUE | URL-friendly recipe identifier |
| `is_seo_generated` | TINYINT(1) | Flag: 1 = SEO-generated recipe |
| `seo_keyword` | VARCHAR(255) | Keyword used to generate the recipe |
| `meta_description` | TEXT | SEO meta description |
| `is_public` | TINYINT(1) | Flag: 1 = published/visible |
| `updated_at` | TIMESTAMP | Last update timestamp (for sitemap) |
