-- Migration: Add SEO columns to recipes table
-- Run this once to add the required columns for SEO recipe generation

ALTER TABLE recipes
    ADD COLUMN IF NOT EXISTS slug VARCHAR(255) UNIQUE AFTER diet_used,
    ADD COLUMN IF NOT EXISTS is_seo_generated TINYINT(1) NOT NULL DEFAULT 0 AFTER slug,
    ADD COLUMN IF NOT EXISTS seo_keyword VARCHAR(255) DEFAULT NULL AFTER is_seo_generated,
    ADD COLUMN IF NOT EXISTS meta_description TEXT DEFAULT NULL AFTER seo_keyword,
    ADD COLUMN IF NOT EXISTS is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER meta_description,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER is_public;

-- Index for SEO-generated recipe lookups
CREATE INDEX IF NOT EXISTS idx_is_seo_generated ON recipes (is_seo_generated);
CREATE INDEX IF NOT EXISTS idx_slug ON recipes (slug);
CREATE INDEX IF NOT EXISTS idx_seo_keyword ON recipes (seo_keyword);
