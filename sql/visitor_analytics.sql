-- =============================================
-- Visitor Analytics System - Database Schema
-- =============================================
-- Created: 2024-12-19
-- Description: Complete visitor tracking and analytics system
-- Compatible with: MySQL 5.7+ / MariaDB 10.2+
-- =============================================

-- =============================================
-- 1. Main Visitor Logs Table
-- =============================================
CREATE TABLE IF NOT EXISTS `visitor_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address` VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6 address',
    `user_agent` TEXT NULL COMMENT 'Full user agent string',
    `page_url` VARCHAR(500) NULL COMMENT 'Requested page URL',
    `referrer` VARCHAR(500) NULL COMMENT 'HTTP referrer',
    `visit_date` DATE NOT NULL COMMENT 'Date of visit (for indexing)',
    `visit_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Exact timestamp',
    `session_id` VARCHAR(100) NULL COMMENT 'Session identifier',
    
    -- Enhanced tracking fields
    `country` VARCHAR(100) NULL COMMENT 'Visitor country',
    `city` VARCHAR(100) NULL COMMENT 'Visitor city',
    `device_type` ENUM('mobile', 'desktop', 'tablet') NULL COMMENT 'Device category',
    `browser` VARCHAR(50) NULL COMMENT 'Browser name',
    `browser_version` VARCHAR(20) NULL COMMENT 'Browser version',
    `os` VARCHAR(50) NULL COMMENT 'Operating system',
    `os_version` VARCHAR(20) NULL COMMENT 'OS version',
    `is_bot` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Bot detection flag',
    `screen_resolution` VARCHAR(20) NULL COMMENT 'Screen resolution (e.g., 1920x1080)',
    
    -- UTM and marketing tracking
    `utm_source` VARCHAR(100) NULL COMMENT 'UTM source parameter',
    `utm_medium` VARCHAR(100) NULL COMMENT 'UTM medium parameter',
    `utm_campaign` VARCHAR(100) NULL COMMENT 'UTM campaign parameter',
    `utm_term` VARCHAR(100) NULL COMMENT 'UTM term parameter',
    `utm_content` VARCHAR(100) NULL COMMENT 'UTM content parameter',
    
    -- Page performance tracking
    `load_time` DECIMAL(6,3) NULL COMMENT 'Page load time in seconds',
    `page_size` INT UNSIGNED NULL COMMENT 'Page size in bytes',
    
    -- Additional metadata
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    
    -- Performance indexes
    INDEX `idx_visit_date` (`visit_date`),
    INDEX `idx_ip_address` (`ip_address`),
    INDEX `idx_visit_time` (`visit_time`),
    INDEX `idx_session_id` (`session_id`),
    INDEX `idx_country` (`country`),
    INDEX `idx_device_type` (`device_type`),
    INDEX `idx_is_bot` (`is_bot`),
    INDEX `idx_page_url` (`page_url`(100)),
    
    -- Composite indexes for common queries
    INDEX `idx_date_ip` (`visit_date`, `ip_address`),
    INDEX `idx_date_bot` (`visit_date`, `is_bot`),
    INDEX `idx_date_device` (`visit_date`, `device_type`),
    INDEX `idx_date_country` (`visit_date`, `country`),
    INDEX `idx_utm_source` (`utm_source`),
    INDEX `idx_utm_campaign` (`utm_campaign`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Main visitor tracking table';

-- =============================================
-- 2. Daily Summary Table (for performance)
-- =============================================
CREATE TABLE IF NOT EXISTS `visitor_summary` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `summary_date` DATE NOT NULL COMMENT 'Date being summarized',
    `unique_visitors` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Unique IP addresses',
    `total_pageviews` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total page views',
    `unique_pageviews` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Unique page views',
    `avg_session_duration` DECIMAL(8,2) NULL COMMENT 'Average session duration in seconds',
    `bounce_rate` DECIMAL(5,2) NULL COMMENT 'Bounce rate percentage',
    
    -- Device breakdown
    `mobile_visitors` INT UNSIGNED NOT NULL DEFAULT 0,
    `desktop_visitors` INT UNSIGNED NOT NULL DEFAULT 0,
    `tablet_visitors` INT UNSIGNED NOT NULL DEFAULT 0,
    
    -- Top statistics (JSON format for flexibility)
    `top_pages` JSON NULL COMMENT 'Top pages with view counts',
    `top_referrers` JSON NULL COMMENT 'Top referrers with counts',
    `top_countries` JSON NULL COMMENT 'Top countries with visitor counts',
    `top_browsers` JSON NULL COMMENT 'Top browsers with counts',
    
    -- Performance metrics
    `avg_load_time` DECIMAL(6,3) NULL COMMENT 'Average page load time',
    `total_bots` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Bot visits filtered out',
    
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_summary_date` (`summary_date`),
    INDEX `idx_summary_date` (`summary_date`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily visitor statistics summary';

-- =============================================
-- 3. Country Statistics Table
-- =============================================
CREATE TABLE IF NOT EXISTS `visitor_countries` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `country` VARCHAR(100) NOT NULL,
    `country_code` VARCHAR(2) NULL COMMENT 'ISO country code',
    `visit_date` DATE NOT NULL,
    `unique_visitors` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_pageviews` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_country_date` (`country`, `visit_date`),
    INDEX `idx_visit_date` (`visit_date`),
    INDEX `idx_country` (`country`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Country-wise visitor statistics';

-- =============================================
-- 4. Page Statistics Table
-- =============================================
CREATE TABLE IF NOT EXISTS `visitor_pages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page_url` VARCHAR(500) NOT NULL,
    `page_title` VARCHAR(200) NULL,
    `visit_date` DATE NOT NULL,
    `unique_visitors` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_pageviews` INT UNSIGNED NOT NULL DEFAULT 0,
    `avg_time_on_page` DECIMAL(8,2) NULL COMMENT 'Average time in seconds',
    `bounce_rate` DECIMAL(5,2) NULL COMMENT 'Page bounce rate',
    `exit_rate` DECIMAL(5,2) NULL COMMENT 'Page exit rate',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    INDEX `idx_page_url` (`page_url`(100)),
    INDEX `idx_visit_date` (`visit_date`),
    INDEX `idx_page_date` (`page_url`(100), `visit_date`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Page-wise visitor statistics';

-- =============================================
-- 5. Referrer Statistics Table
-- =============================================
CREATE TABLE IF NOT EXISTS `visitor_referrers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `referrer` VARCHAR(500) NOT NULL,
    `referrer_domain` VARCHAR(200) NULL COMMENT 'Extracted domain',
    `referrer_type` ENUM('search', 'social', 'direct', 'referral', 'email', 'paid') NULL,
    `visit_date` DATE NOT NULL,
    `unique_visitors` INT UNSIGNED NOT NULL DEFAULT 0,
    `total_visits` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    INDEX `idx_referrer_domain` (`referrer_domain`),
    INDEX `idx_visit_date` (`visit_date`),
    INDEX `idx_referrer_type` (`referrer_type`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Referrer statistics';

-- =============================================
-- 6. System Settings Table
-- =============================================
CREATE TABLE IF NOT EXISTS `visitor_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT NULL,
    `description` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_setting_key` (`setting_key`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Visitor analytics system settings';

-- =============================================
-- Insert Default Settings
-- =============================================
INSERT INTO `visitor_settings` (`setting_key`, `setting_value`, `description`) VALUES
('enable_tracking', '1', 'Enable/disable visitor tracking'),
('track_bots', '0', 'Whether to track bot visits'),
('data_retention_days', '365', 'Number of days to keep visitor data'),
('enable_geolocation', '1', 'Enable country/city detection'),
('summary_update_hour', '2', 'Hour of day (0-23) to update daily summaries'),
('enable_performance_tracking', '1', 'Track page load times and performance'),
('privacy_mode', '0', 'Enhanced privacy mode (anonymize IPs)'),
('max_session_duration', '1800', 'Maximum session duration in seconds'),
('bot_detection_enabled', '1', 'Enable automatic bot detection'),
('enable_utm_tracking', '1', 'Track UTM campaign parameters')
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `updated_at` = CURRENT_TIMESTAMP;

-- =============================================
-- Performance Optimization Views
-- =============================================

-- View for quick daily statistics
CREATE OR REPLACE VIEW `v_daily_stats` AS
SELECT 
    visit_date,
    COUNT(DISTINCT ip_address) as unique_visitors,
    COUNT(*) as total_pageviews,
    COUNT(DISTINCT session_id) as unique_sessions,
    SUM(CASE WHEN device_type = 'mobile' THEN 1 ELSE 0 END) as mobile_views,
    SUM(CASE WHEN device_type = 'desktop' THEN 1 ELSE 0 END) as desktop_views,
    SUM(CASE WHEN device_type = 'tablet' THEN 1 ELSE 0 END) as tablet_views,
    AVG(load_time) as avg_load_time
FROM visitor_logs 
WHERE is_bot = 0 
GROUP BY visit_date 
ORDER BY visit_date DESC;

-- View for current month statistics
CREATE OR REPLACE VIEW `v_monthly_stats` AS
SELECT 
    DATE_FORMAT(visit_date, '%Y-%m') as month,
    COUNT(DISTINCT ip_address) as unique_visitors,
    COUNT(*) as total_pageviews,
    COUNT(DISTINCT session_id) as unique_sessions,
    AVG(load_time) as avg_load_time
FROM visitor_logs 
WHERE is_bot = 0 
    AND visit_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
GROUP BY DATE_FORMAT(visit_date, '%Y-%m')
ORDER BY month DESC;

-- View for top pages (current month)
CREATE OR REPLACE VIEW `v_top_pages` AS
SELECT 
    page_url,
    COUNT(DISTINCT ip_address) as unique_visitors,
    COUNT(*) as total_views,
    AVG(load_time) as avg_load_time
FROM visitor_logs 
WHERE is_bot = 0 
    AND visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    AND page_url IS NOT NULL
GROUP BY page_url 
ORDER BY total_views DESC 
LIMIT 50;

-- =============================================
-- Stored Procedures for Maintenance
-- =============================================

DELIMITER //

-- Procedure to update daily summaries
CREATE OR REPLACE PROCEDURE `UpdateDailySummary`(IN target_date DATE)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- Delete existing summary for the date
    DELETE FROM visitor_summary WHERE summary_date = target_date;

    -- Insert new summary
    INSERT INTO visitor_summary (
        summary_date, unique_visitors, total_pageviews, unique_pageviews,
        mobile_visitors, desktop_visitors, tablet_visitors, avg_load_time, total_bots
    )
    SELECT 
        target_date,
        COUNT(DISTINCT CASE WHEN is_bot = 0 THEN ip_address END) as unique_visitors,
        COUNT(CASE WHEN is_bot = 0 THEN 1 END) as total_pageviews,
        COUNT(DISTINCT CASE WHEN is_bot = 0 THEN CONCAT(ip_address, '-', page_url) END) as unique_pageviews,
        COUNT(DISTINCT CASE WHEN is_bot = 0 AND device_type = 'mobile' THEN ip_address END) as mobile_visitors,
        COUNT(DISTINCT CASE WHEN is_bot = 0 AND device_type = 'desktop' THEN ip_address END) as desktop_visitors,
        COUNT(DISTINCT CASE WHEN is_bot = 0 AND device_type = 'tablet' THEN ip_address END) as tablet_visitors,
        AVG(CASE WHEN is_bot = 0 THEN load_time END) as avg_load_time,
        COUNT(CASE WHEN is_bot = 1 THEN 1 END) as total_bots
    FROM visitor_logs 
    WHERE visit_date = target_date;

    COMMIT;
END //

-- Procedure to cleanup old data
CREATE OR REPLACE PROCEDURE `CleanupVisitorData`(IN retention_days INT)
BEGIN
    DECLARE cutoff_date DATE;
    DECLARE done INT DEFAULT FALSE;
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    SET cutoff_date = DATE_SUB(CURDATE(), INTERVAL retention_days DAY);

    START TRANSACTION;

    -- Delete old visitor logs
    DELETE FROM visitor_logs WHERE visit_date < cutoff_date;
    
    -- Delete old summaries
    DELETE FROM visitor_summary WHERE summary_date < cutoff_date;
    
    -- Delete old country stats
    DELETE FROM visitor_countries WHERE visit_date < cutoff_date;
    
    -- Delete old page stats
    DELETE FROM visitor_pages WHERE visit_date < cutoff_date;
    
    -- Delete old referrer stats
    DELETE FROM visitor_referrers WHERE visit_date < cutoff_date;

    COMMIT;

    -- Optimize tables after cleanup
    OPTIMIZE TABLE visitor_logs, visitor_summary, visitor_countries, visitor_pages, visitor_referrers;
END //

DELIMITER ;

-- =============================================
-- Create Events for Automatic Maintenance
-- =============================================

-- Event to update daily summaries (runs daily at 2 AM)  
CREATE EVENT IF NOT EXISTS `evt_update_daily_summary`
ON SCHEDULE EVERY 1 DAY 
STARTS (TIMESTAMP(CURRENT_DATE) + INTERVAL 1 DAY + INTERVAL 2 HOUR)
DO
BEGIN
    CALL UpdateDailySummary(CURDATE() - INTERVAL 1 DAY);
END;

-- Event to cleanup old data (runs weekly on Sunday at 3 AM)
CREATE EVENT IF NOT EXISTS `evt_cleanup_visitor_data`
ON SCHEDULE EVERY 1 WEEK 
STARTS (TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)) + INTERVAL 1 WEEK + INTERVAL 3 HOUR)
DO
BEGIN
    DECLARE retention_days INT DEFAULT 365;
    
    -- Get retention setting
    SELECT CAST(setting_value AS UNSIGNED) INTO retention_days 
    FROM visitor_settings 
    WHERE setting_key = 'data_retention_days' 
    AND setting_value REGEXP '^[0-9]+$'
    LIMIT 1;
    
    -- Use default if setting not found or invalid
    IF retention_days IS NULL OR retention_days < 30 THEN
        SET retention_days = 365;
    END IF;
    
    CALL CleanupVisitorData(retention_days);
END;

-- =============================================
-- Enable Event Scheduler (if not already enabled)
-- =============================================
SET GLOBAL event_scheduler = ON;

-- =============================================
-- Success Message
-- =============================================
SELECT 'Visitor Analytics System - Database Schema Created Successfully!' as STATUS;
SELECT COUNT(*) as TABLES_CREATED FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name LIKE 'visitor_%';

-- Display settings
SELECT 'Default Settings:' as INFO;
SELECT setting_key, setting_value, description 
FROM visitor_settings 
ORDER BY setting_key;
