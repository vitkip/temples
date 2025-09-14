<?php
/**
 * SQL Import Script for Visitor Analytics System
 * Run this script once to set up the database schema
 */

require_once 'config/db.php';

echo "<!DOCTYPE html>\n";
echo "<html><head><title>Visitor Analytics Setup</title></head><body>\n";
echo "<h1>Visitor Analytics Database Setup</h1>\n";

try {
    // Read the SQL file
    $sqlFile = 'sql/visitor_analytics.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: {$sqlFile}");
    }
    
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new Exception("Could not read SQL file");
    }
    
    echo "<p>Reading SQL file... ✓</p>\n";
    echo "<p>File size: " . number_format(strlen($sql)) . " bytes</p>\n";
    
    // Remove comments and split by delimiters
    $sql = preg_replace('/--.*$/m', '', $sql); // Remove line comments
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Remove block comments
    
    // Split by semicolons but preserve DELIMITER blocks
    $statements = [];
    $current = '';
    $lines = explode("\n", $sql);
    $delimiter = ';';
    $inDelimiterBlock = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Check for DELIMITER changes
        if (preg_match('/^DELIMITER\s+(.+)$/i', $line, $matches)) {
            $delimiter = trim($matches[1]);
            $inDelimiterBlock = ($delimiter !== ';');
            continue;
        }
        
        $current .= $line . "\n";
        
        // Check if statement ends with current delimiter
        if (substr($line, -strlen($delimiter)) === $delimiter) {
            if ($inDelimiterBlock && $delimiter !== ';') {
                // This is a procedure/function definition
                $statements[] = trim(substr($current, 0, -strlen($delimiter)));
            } else {
                // Regular statement
                $statements[] = trim(substr($current, 0, -1));
            }
            $current = '';
        }
    }
    
    // Add any remaining content
    if (trim($current)) {
        $statements[] = trim($current);
    }
    
    echo "<p>Parsed " . count($statements) . " SQL statements</p>\n";
    echo "<hr>\n";
    
    // Execute each statement
    $success = 0;
    $errors = 0;
    
    foreach ($statements as $i => $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        try {
            $stmt = $pdo->prepare($statement);
            $result = $stmt->execute();
            
            if ($result) {
                $success++;
                // Show first few words of successful statements
                $preview = substr($statement, 0, 60);
                echo "<p style='color: green;'>✓ Statement " . ($i+1) . ": " . htmlspecialchars($preview) . "...</p>\n";
            } else {
                $errors++;
                $errorInfo = $stmt->errorInfo();
                echo "<p style='color: red;'>✗ Statement " . ($i+1) . " failed: " . htmlspecialchars($errorInfo[2]) . "</p>\n";
            }
        } catch (PDOException $e) {
            $errors++;
            $preview = substr($statement, 0, 60);
            echo "<p style='color: orange;'>⚠ Statement " . ($i+1) . " warning: " . htmlspecialchars($e->getMessage()) . "<br>";
            echo "Statement: " . htmlspecialchars($preview) . "...</p>\n";
        }
        
        // Flush output for real-time feedback
        if (ob_get_level()) ob_flush();
        flush();
    }
    
    echo "<hr>\n";
    echo "<h2>Summary</h2>\n";
    echo "<p><strong>Successful statements:</strong> {$success}</p>\n";
    echo "<p><strong>Errors/Warnings:</strong> {$errors}</p>\n";
    
    // Verify tables were created
    echo "<h3>Verifying Tables</h3>\n";
    $tables = ['visitor_logs', 'visitor_summary', 'visitor_countries', 'visitor_pages', 'visitor_referrers', 'visitor_settings'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
            $count = $stmt->fetchColumn();
            echo "<p style='color: green;'>✓ Table '{$table}' exists with {$count} records</p>\n";
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ Table '{$table}' not found or error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
        }
    }
    
    // Check if settings were inserted
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM visitor_settings");
        $settingsCount = $stmt->fetchColumn();
        echo "<p><strong>Default settings inserted:</strong> {$settingsCount}</p>\n";
        
        if ($settingsCount > 0) {
            echo "<h4>Current Settings:</h4>\n";
            $stmt = $pdo->query("SELECT setting_key, setting_value, description FROM visitor_settings ORDER BY setting_key");
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
            echo "<tr><th>Key</th><th>Value</th><th>Description</th></tr>\n";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['setting_key']) . "</td>";
                echo "<td>" . htmlspecialchars($row['setting_value']) . "</td>";
                echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>Could not check settings: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    echo "<h2>✅ Database Setup Complete!</h2>\n";
    echo "<p>The visitor analytics system is now ready to use.</p>\n";
    echo "<p><a href='dashboard.php'>Go to Dashboard</a> | <a href='api/visitor_summary.php'>Test Summary API</a> | <a href='api/visitor_stats.php'>Test Stats API</a></p>\n";
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>❌ Setup Failed</h2>\n";
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "<p>Please check the error and try again.</p>\n";
}

echo "</body></html>\n";
?>