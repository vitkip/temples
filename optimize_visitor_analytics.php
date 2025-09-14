<?php
/**
 * Visitor Analytics Performance Optimization Script
 * Manually triggers daily summary updates and performance optimizations
 */

require_once 'config/db.php';

echo "<!DOCTYPE html>\n";
echo "<html><head><title>Visitor Analytics Optimization</title>";
echo "<meta charset='utf-8'>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;} table{border-collapse:collapse;margin:10px 0;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} .performance{background:#f0f8ff;padding:15px;border-left:4px solid #007acc;margin:10px 0;}</style>";
echo "</head><body>\n";

echo "<h1>⚡ Visitor Analytics Performance Optimization</h1>\n";

$startTime = microtime(true);

try {
    // Check if we have the stored procedures
    echo "<h2>🔧 Step 1: System Check</h2>\n";
    
    $stmt = $pdo->query("SHOW PROCEDURE STATUS WHERE Name IN ('UpdateDailySummary', 'CleanupVisitorData')");
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($procedures) >= 2) {
        echo "<p class='success'>✓ Stored procedures are available</p>\n";
    } else {
        echo "<p class='warning'>⚠ Some stored procedures missing. Creating them now...</p>\n";
        
        // Create UpdateDailySummary procedure if missing
        $createProcedure = "
        DROP PROCEDURE IF EXISTS UpdateDailySummary;
        CREATE PROCEDURE UpdateDailySummary(IN target_date DATE)
        BEGIN
            DECLARE done INT DEFAULT FALSE;
            DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
            BEGIN
                ROLLBACK;
                RESIGNAL;
            END;

            START TRANSACTION;

            DELETE FROM visitor_summary WHERE summary_date = target_date;

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
        END
        ";
        
        try {
            $pdo->exec($createProcedure);
            echo "<p class='success'>✓ UpdateDailySummary procedure created</p>\n";
        } catch (PDOException $e) {
            echo "<p class='error'>✗ Failed to create UpdateDailySummary: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
    }
    
    // Get visitor data statistics
    echo "<h2>📈 Step 2: Current Data Analysis</h2>\n";
    
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_logs,
        COUNT(DISTINCT ip_address) as unique_ips,
        COUNT(DISTINCT visit_date) as unique_dates,
        MIN(visit_date) as first_visit,
        MAX(visit_date) as last_visit,
        COUNT(CASE WHEN is_bot = 1 THEN 1 END) as bot_visits,
        COUNT(CASE WHEN is_bot = 0 THEN 1 END) as human_visits
    FROM visitor_logs");
    
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<div class='performance'>\n";
    echo "<h3>📊 Database Statistics</h3>\n";
    echo "<p><strong>Total Visitor Logs:</strong> " . number_format($stats['total_logs']) . "</p>\n";
    echo "<p><strong>Unique IP Addresses:</strong> " . number_format($stats['unique_ips']) . "</p>\n";
    echo "<p><strong>Date Range:</strong> " . $stats['first_visit'] . " to " . $stats['last_visit'] . " (" . $stats['unique_dates'] . " days)</p>\n";
    echo "<p><strong>Human Visits:</strong> " . number_format($stats['human_visits']) . " (" . round(($stats['human_visits'] / $stats['total_logs']) * 100, 1) . "%)</p>\n";
    echo "<p><strong>Bot Visits:</strong> " . number_format($stats['bot_visits']) . " (" . round(($stats['bot_visits'] / $stats['total_logs']) * 100, 1) . "%)</p>\n";
    echo "</div>\n";
    
    // Check summary table status
    $stmt = $pdo->query("SELECT COUNT(*) as summary_count FROM visitor_summary");
    $summaryCount = $stmt->fetchColumn();
    
    echo "<p><strong>Existing Summaries:</strong> {$summaryCount} daily summaries</p>\n";
    
    // Step 3: Update daily summaries
    echo "<h2>🔄 Step 3: Daily Summary Updates</h2>\n";
    
    if ($stats['unique_dates'] > 0) {
        // Get dates that need summary updates
        $stmt = $pdo->query("
            SELECT DISTINCT visit_date 
            FROM visitor_logs 
            WHERE visit_date NOT IN (SELECT summary_date FROM visitor_summary)
            ORDER BY visit_date DESC
            LIMIT 30
        ");
        $datesToUpdate = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($datesToUpdate) > 0) {
            echo "<p class='info'>Found " . count($datesToUpdate) . " dates needing summary updates</p>\n";
            
            $updatedCount = 0;
            foreach ($datesToUpdate as $date) {
                try {
                    $stmt = $pdo->prepare("CALL UpdateDailySummary(?)");
                    $stmt->execute([$date]);
                    echo "<p class='success'>✓ Updated summary for {$date}</p>\n";
                    $updatedCount++;
                    
                    if (ob_get_level()) ob_flush();
                    flush();
                } catch (PDOException $e) {
                    echo "<p class='error'>✗ Failed to update {$date}: " . htmlspecialchars($e->getMessage()) . "</p>\n";
                }
            }
            
            echo "<p><strong>Summary Updates Completed:</strong> {$updatedCount}/" . count($datesToUpdate) . "</p>\n";
        } else {
            echo "<p class='success'>✓ All daily summaries are up to date</p>\n";
        }
    } else {
        echo "<p class='info'>No visitor data to summarize yet</p>\n";
    }
    
    // Step 4: Performance optimization
    echo "<h2>⚡ Step 4: Performance Optimization</h2>\n";
    
    // Analyze table sizes
    $stmt = $pdo->query("
        SELECT 
            table_name,
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)',
            table_rows
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE() 
        AND table_name LIKE 'visitor_%'
        ORDER BY (data_length + index_length) DESC
    ");
    $tableSizes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>💾 Table Sizes</h3>\n";
    echo "<table>\n";
    echo "<tr><th>Table</th><th>Size (MB)</th><th>Rows</th></tr>\n";
    foreach ($tableSizes as $table) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($table['table_name']) . "</td>";
        echo "<td>" . number_format($table['Size (MB)'], 2) . "</td>";
        echo "<td>" . number_format($table['table_rows']) . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Optimize tables
    echo "<h3>🔧 Table Optimization</h3>\n";
    $tables = ['visitor_logs', 'visitor_summary', 'visitor_countries', 'visitor_pages', 'visitor_referrers'];
    
    foreach ($tables as $table) {
        try {
            $optimizeStart = microtime(true);
            $stmt = $pdo->query("OPTIMIZE TABLE {$table}");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $optimizeTime = round((microtime(true) - $optimizeStart) * 1000, 2);
            
            if ($result && $result['Msg_text'] === 'OK') {
                echo "<p class='success'>✓ Optimized {$table} ({$optimizeTime}ms)</p>\n";
            } else {
                echo "<p class='warning'>⚠ {$table}: " . ($result['Msg_text'] ?? 'Unknown result') . "</p>\n";
            }
        } catch (PDOException $e) {
            echo "<p class='error'>✗ Failed to optimize {$table}: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
    }
    
    // Step 5: Index analysis
    echo "<h2>🗂 Step 5: Index Analysis</h2>\n";
    
    try {
        $stmt = $pdo->query("
            SELECT 
                TABLE_NAME,
                INDEX_NAME,
                COLUMN_NAME,
                CARDINALITY
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'visitor_logs'
            ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX
        ");
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>📋 Visitor Logs Indexes</h3>\n";
        echo "<table>\n";
        echo "<tr><th>Index Name</th><th>Column</th><th>Cardinality</th></tr>\n";
        foreach ($indexes as $index) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($index['INDEX_NAME']) . "</td>";
            echo "<td>" . htmlspecialchars($index['COLUMN_NAME']) . "</td>";
            echo "<td>" . number_format($index['CARDINALITY']) . "</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
    } catch (PDOException $e) {
        echo "<p class='error'>Index analysis failed: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    // Step 6: Performance recommendations
    echo "<h2>💡 Step 6: Performance Recommendations</h2>\n";
    
    $recommendations = [];
    
    if ($stats['total_logs'] > 10000) {
        $recommendations[] = "Consider implementing data archiving for logs older than 1 year";
    }
    
    if ($stats['bot_visits'] > $stats['human_visits'] * 0.5) {
        $recommendations[] = "High bot traffic detected - review bot detection rules";
    }
    
    if ($summaryCount < $stats['unique_dates'] * 0.8) {
        $recommendations[] = "Daily summaries are behind - consider running this optimization daily";
    }
    
    if (count($recommendations) > 0) {
        echo "<ul>\n";
        foreach ($recommendations as $rec) {
            echo "<li class='info'>💡 " . htmlspecialchars($rec) . "</li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p class='success'>✓ System performance looks optimal!</p>\n";
    }
    
    // Final summary
    $totalTime = round((microtime(true) - $startTime) * 1000, 2);
    
    echo "<h2>✅ Optimization Complete</h2>\n";
    echo "<div class='performance'>\n";
    echo "<h3>📊 Final Summary</h3>\n";
    echo "<p><strong>Total Execution Time:</strong> {$totalTime}ms</p>\n";
    echo "<p><strong>Visitor Logs Processed:</strong> " . number_format($stats['total_logs']) . "</p>\n";
    echo "<p><strong>Daily Summaries Available:</strong> {$summaryCount}</p>\n";
    echo "<p><strong>System Status:</strong> Optimized ✨</p>\n";
    echo "</div>\n";
    
    echo "<hr>\n";
    echo "<p><strong>Next Steps:</strong></p>\n";
    echo "<ul>\n";
    echo "<li><a href='dashboard.php'>View Optimized Dashboard</a></li>\n";
    echo "<li><a href='api/visitor_summary.php' target='_blank'>Test Summary API Performance</a></li>\n";
    echo "<li><a href='test_visitor_analytics.php'>Run Full System Test</a></li>\n";
    echo "<li>Schedule this script to run daily via cron job</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Optimization Failed</h2>\n";
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p>Please check the database connection and try again.</p>\n";
}

echo "<footer style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ccc; color: #666;'>\n";
echo "<p>Visitor Analytics Performance Optimization - " . date('Y-m-d H:i:s') . "</p>\n";
echo "</footer>\n";
echo "</body></html>\n";
?>