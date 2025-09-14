<?php
/**
 * Enhanced Visitor Tracking System
 * Compatible with new SQL schema
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Helper Functions
function isBot($userAgent) {
    $bots = [
        'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
        'yandexbot', 'facebookexternalhit', 'twitterbot', 'whatsapp',
        'crawler', 'spider', 'bot', 'scrapy', 'curl', 'wget'
    ];
    
    foreach ($bots as $bot) {
        if (stripos($userAgent, $bot) !== false) {
            return true;
        }
    }
    return false;
}

function getDeviceType($userAgent) {
    if (preg_match('/iPad/i', $userAgent)) {
        return 'tablet';
    } elseif (preg_match('/Mobile|Android|iPhone|iPod|BlackBerry|Windows Phone/i', $userAgent)) {
        return 'mobile';
    }
    return 'desktop';
}

function getBrowserName($userAgent) {
    if (preg_match('/Chrome/i', $userAgent)) return 'Chrome';
    if (preg_match('/Firefox/i', $userAgent)) return 'Firefox';
    if (preg_match('/Safari/i', $userAgent)) return 'Safari';
    if (preg_match('/Edge/i', $userAgent)) return 'Edge';
    if (preg_match('/Opera/i', $userAgent)) return 'Opera';
    return 'Unknown';
}

function getOperatingSystem($userAgent) {
    if (preg_match('/Windows NT 10/i', $userAgent)) return 'Windows 10';
    if (preg_match('/Windows NT/i', $userAgent)) return 'Windows';
    if (preg_match('/Mac OS X/i', $userAgent)) return 'macOS';
    if (preg_match('/Linux/i', $userAgent)) return 'Linux';
    if (preg_match('/Android/i', $userAgent)) return 'Android';
    if (preg_match('/iOS|iPhone|iPad/i', $userAgent)) return 'iOS';
    return 'Unknown';
}

function getRealIP() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
        'HTTP_X_FORWARDED',          // Proxy
        'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
        'HTTP_CLIENT_IP',            // Proxy
        'REMOTE_ADDR'                // Standard
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            $ip = trim($ips[0]);
            
            // Validate IP and exclude private ranges for public tracking
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function getCountryFromIP($ip) {
    // Simple country detection - ในการใช้งานจริงอาจใช้ GeoIP API
    if (strpos($ip, '192.168.') === 0 || strpos($ip, '127.') === 0) {
        return 'Laos'; // Default for local testing
    }
    
    // ตัวอย่างการใช้ API ฟรี (uncomment เมื่อใช้งานจริง)
    /*
    try {
        $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=country");
        if ($response) {
            $data = json_decode($response, true);
            return $data['country'] ?? 'Unknown';
        }
    } catch (Exception $e) {
        error_log('GeoIP error: ' . $e->getMessage());
    }
    */
    
    return 'Unknown';
}

// Main tracking logic
try {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = getRealIP();
    $currentPage = $_SERVER['REQUEST_URI'] ?? '/';
    $currentDate = date('Y-m-d');
    $sessionKey = 'visitor_tracked_' . $currentDate . '_' . md5($ip);
    
    // Skip tracking for bots
    if (isBot($userAgent)) {
        return;
    }
    
    // Skip if already tracked today for this IP
    if (isset($_SESSION[$sessionKey])) {
        return;
    }
    
    // Check if this IP was already tracked today
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) FROM visitor_logs 
        WHERE ip_address = ? AND visit_date = CURDATE()
    ");
    $checkStmt->execute([$ip]);
    
    if ($checkStmt->fetchColumn() == 0) {
        // Get additional information
        $deviceType = getDeviceType($userAgent);
        $browserName = getBrowserName($userAgent);
        $os = getOperatingSystem($userAgent);
        $country = getCountryFromIP($ip);
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        $sessionId = session_id();
        $isMobile = in_array($deviceType, ['mobile', 'tablet']) ? 1 : 0;
        
        // Parse UTM parameters
        $utmSource = $_GET['utm_source'] ?? null;
        $utmMedium = $_GET['utm_medium'] ?? null;
        $utmCampaign = $_GET['utm_campaign'] ?? null;
        
        // Get browser language
        $language = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
        
        // Insert visitor log
        $stmt = $pdo->prepare("
            INSERT INTO visitor_logs (
                ip_address, user_agent, page_url, visit_date, visit_time, 
                session_id, referrer, device_type, browser_name, operating_system,
                is_bot, is_mobile, country, utm_source, utm_medium, utm_campaign, language
            ) VALUES (?, ?, ?, CURDATE(), NOW(), ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $ip, $userAgent, $currentPage, $sessionId, $referrer,
            $deviceType, $browserName, $os, $isMobile, $country,
            $utmSource, $utmMedium, $utmCampaign, $language
        ]);
        
        // Update daily summary
        updateDailySummary($pdo, $currentDate);
    }
    
    // Mark as tracked in session
    $_SESSION[$sessionKey] = true;
    
} catch (Exception $e) {
    error_log('Visitor tracking error: ' . $e->getMessage());
}

function updateDailySummary($pdo, $date) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO visitor_summary (
                summary_date, unique_visitors, total_pageviews, unique_sessions,
                mobile_visitors, desktop_visitors, tablet_visitors, new_visitors
            )
            SELECT 
                DATE(visit_time) as date,
                COUNT(DISTINCT ip_address) as unique_visitors,
                COUNT(*) as total_pageviews,
                COUNT(DISTINCT IFNULL(session_id, CONCAT(ip_address, DATE(visit_time)))) as unique_sessions,
                SUM(CASE WHEN device_type = 'mobile' THEN 1 ELSE 0 END) as mobile_visitors,
                SUM(CASE WHEN device_type = 'desktop' THEN 1 ELSE 0 END) as desktop_visitors,
                SUM(CASE WHEN device_type = 'tablet' THEN 1 ELSE 0 END) as tablet_visitors,
                COUNT(DISTINCT ip_address) as new_visitors
            FROM visitor_logs 
            WHERE DATE(visit_time) = ? AND is_bot = 0
            GROUP BY DATE(visit_time)
            ON DUPLICATE KEY UPDATE
                unique_visitors = VALUES(unique_visitors),
                total_pageviews = VALUES(total_pageviews),
                unique_sessions = VALUES(unique_sessions),
                mobile_visitors = VALUES(mobile_visitors),
                desktop_visitors = VALUES(desktop_visitors),
                tablet_visitors = VALUES(tablet_visitors),
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$date]);
    } catch (Exception $e) {
        error_log('Summary update error: ' . $e->getMessage());
    }
}
?>
