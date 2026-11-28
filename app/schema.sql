-- 电脑小百科 · 技术笔记 — MySQL 8 / 5.7 结构
-- 导入方式：phpMyAdmin → 选中数据库 → 导入本文件；或 mysql -u user -p db < schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS posts (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug            VARCHAR(120) NOT NULL,
  title           VARCHAR(200) NOT NULL,
  seo_title       VARCHAR(240) NOT NULL DEFAULT '',
  description     VARCHAR(400) NOT NULL DEFAULT '',
  eyebrow         VARCHAR(160) NOT NULL DEFAULT '',
  section         VARCHAR(60)  NOT NULL DEFAULT '',
  category_id     INT UNSIGNED NULL,
  tags            VARCHAR(240) NOT NULL DEFAULT '',
  project         VARCHAR(120) NOT NULL DEFAULT '',
  published_at    DATE NULL,
  updated_on      DATE NULL,
  word_count      INT UNSIGNED NOT NULL DEFAULT 0,
  reading_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  keywords        VARCHAR(240) NOT NULL DEFAULT '',
  body_html       MEDIUMTEXT NOT NULL,
  status          ENUM('draft','published') NOT NULL DEFAULT 'draft',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_posts_slug (slug),
  KEY idx_posts_status_date (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(60) NOT NULL,
  slug        VARCHAR(80) NOT NULL,
  description VARCHAR(400) NOT NULL DEFAULT '',
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_categories_slug (slug),
  UNIQUE KEY uk_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS works (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug                 VARCHAR(120) NOT NULL,
  title                VARCHAR(200) NOT NULL,
  seo_title            VARCHAR(240) NOT NULL DEFAULT '',
  description          VARCHAR(400) NOT NULL DEFAULT '',
  eyebrow              VARCHAR(160) NOT NULL DEFAULT '',
  en                   VARCHAR(160) NOT NULL DEFAULT '',
  lede                 VARCHAR(500) NOT NULL DEFAULT '',
  meta_chips           TEXT NULL,              -- JSON: ["v2.1 · 已发布","C# / WPF", ...]
  specs                TEXT NULL,              -- JSON: [{"label":"起始","value":"..."}]
  points               TEXT NULL,              -- JSON: ["C# / WPF", ...]
  status_label         VARCHAR(80) NOT NULL DEFAULT '',
  status_kind          ENUM('','live','wip') NOT NULL DEFAULT '',
  period               VARCHAR(80) NOT NULL DEFAULT '',
  metric               VARCHAR(100) NOT NULL DEFAULT '',
  mark_key             VARCHAR(40) NOT NULL DEFAULT 'generic',
  cover_image          VARCHAR(255) NOT NULL DEFAULT '',
  body_html            MEDIUMTEXT NOT NULL,
  related_html         TEXT NULL,
  app_type             VARCHAR(40) NOT NULL DEFAULT 'SoftwareApplication',
  application_category VARCHAR(60) NOT NULL DEFAULT '',
  operating_system     VARCHAR(120) NOT NULL DEFAULT '',
  version              VARCHAR(40) NOT NULL DEFAULT '',
  sort_order           INT NOT NULL DEFAULT 0,
  is_published         TINYINT(1) NOT NULL DEFAULT 1,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_works_slug (slug),
  KEY idx_works_order (is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS memos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(120) NOT NULL DEFAULT '',
  content     TEXT NOT NULL,
  pinned      TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_memos_pinned (pinned, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
