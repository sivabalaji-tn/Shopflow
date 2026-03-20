-- ============================================================
-- ShopFlow — Super Admin Tables
-- Run this in phpMyAdmin AFTER shopflow_schema.sql
-- ============================================================

USE shopflow_db;

-- ── Super Admins ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS super_admins (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120) NOT NULL,
    email      VARCHAR(180) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Platform Settings ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS platform_settings (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(80)  NOT NULL UNIQUE,
    setting_value TEXT         DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default platform settings
INSERT IGNORE INTO platform_settings (setting_key, setting_value) VALUES
    ('site_name',           'ShopFlow'),
    ('contact_email',       'admin@shopflow.com'),
    ('maintenance_mode',    '0'),
    ('maintenance_message', 'We are under maintenance. Back soon!'),
    ('registration_open',   '1');

-- ── Default Super Admin Account ──────────────────────────────
-- Email:    admin@shopflow.com
-- Password: admin123  (CHANGE THIS IMMEDIATELY AFTER FIRST LOGIN)
INSERT IGNORE INTO super_admins (name, email, password) VALUES (
    'Super Admin',
    'admin@shopflow.com',
    '$2y$10$TKh8H1.PfuAi8a8n4bHgP.4Mk2VXn9RO.Z7jCqBnf6V6v6YWfK4K2'
);

-- Add is_suspended column to owners if not exists
ALTER TABLE owners ADD COLUMN IF NOT EXISTS is_suspended TINYINT(1) DEFAULT 0;
ALTER TABLE shops  ADD COLUMN IF NOT EXISTS is_suspended TINYINT(1) DEFAULT 0;
ALTER TABLE shops  ADD COLUMN IF NOT EXISTS approved_at  TIMESTAMP  DEFAULT NULL;
