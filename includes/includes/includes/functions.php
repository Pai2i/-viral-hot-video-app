<?php
require_once __DIR__ . '/db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    $db = Database::getInstance();
    return $db->fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

function detectPlatform($url) {
    global $platforms;
    foreach ($platforms as $name => $config) {
        if (preg_match($config['regex'], $url)) {
            return $name;
        }
    }
    return 'other';
}

function generateSlug($string) {
    $string = preg_replace('/[^a-zA-Z0-9\s]/', '', $string);
    $string = preg_replace('/\s+/', '-', $string);
    return strtolower(trim($string, '-'));
}

function timeAgo($timestamp) {
    $diff = time() - strtotime($timestamp);
    $intervals = [
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
    ];
    
    foreach ($intervals as $secs => $unit) {
        $result = floor($diff / $secs);
        if ($result >= 1) {
            return $result . ' ' . $unit . ($result > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

function formatViews($views) {
    if ($views >= 1000000) return number_format($views / 1000000, 1) . 'M';
    if ($views >= 1000) return number_format($views / 1000, 1) . 'K';
    return number_format($views);
}

function encryptPath($path) {
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($path, 'aes-256-cbc', SECRET_KEY, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

function decryptPath($encrypted) {
    $data = base64_decode($encrypted);
    if (strpos($data, '::') === false) return false;
    list($iv, $encrypted) = explode('::', $data, 2);
    return openssl_decrypt($encrypted, 'aes-256-cbc', SECRET_KEY, 0, $iv);
}

/**
 * THE KEY FUNCTION: Download & Proxy Video
 * Uses yt-dlp to download, then re-serves through our server
 */
function processVideo($url, $userId) {
    $platform = detectPlatform($url);
    if ($platform == 'other') {
        return ['error' => 'Unsupported platform'];
    }
    
    $db = Database::getInstance();
    
    // Generate unique ID
    $videoId = uniqid('vid_', true);
    $safeId = str_replace('.', '', $videoId);
    
    // Create output directory
    $outputDir = UPLOAD_DIR . $safeId;
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $outputFile = $outputDir . '/video.mp4';
    $thumbnailFile = $outputDir . '/thumb.jpg';
    
    // Step 1: Download video using yt-dlp
    $cmd = YT_DLP_PATH . " -f 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best' ";
    $cmd .= "--output " . escapeshellarg($outputFile) . " ";
    $cmd .= "--write-thumbnail --convert-thumbnails jpg ";
    $cmd .= "--thumbnail-output " . escapeshellarg($thumbnailFile) . " ";
    $cmd .= "--no-warnings --quiet ";
    $cmd .= "--user-agent 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36' ";
    $cmd .= escapeshellarg($url) . " 2>&1";
    
    exec($cmd, $output, $returnCode);
    
    // If yt-dlp fails, try fallback methods
    if ($returnCode !== 0 || !file_exists($outputFile)) {
        // Try direct streaming proxy (without downloading full file)
        $proxyPath = encryptPath($url);
        
        // Get video info
        $infoCmd = YT_DLP_PATH . " --dump-json --no-warnings --quiet " . escapeshellarg($url) . " 2>/dev/null";
        $info = shell_exec($infoCmd);
        $videoInfo = json_decode($info, true);
        
        $title = $videoInfo['title'] ?? 'Untitled Video';
        $duration = $videoInfo['duration'] ?? 0;
        $thumbnail = $videoInfo['thumbnail'] ?? '';
        
        // Save as streaming proxy (no local file - streams directly)
        $db->insert(
            "INSERT INTO videos (user_id, title, description, source_url, proxy_path, platform, thumbnail, duration, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')",
            [$userId, $title, '', $url, 'stream:' . $proxyPath, $platform, $thumbnail, gmdate("H:i:s", $duration)]
        );
        
        return ['success' => true, 'video_id' => $db->getConnection()->lastInsertId(), 'type' => 'stream'];
    }
    
    // Step 2: File exists locally - get info
    $infoCmd = YT_DLP_PATH . " --dump-json --no-warnings --quiet " . escapeshellarg($url) . " 2>/dev/null";
    $info = shell_exec($infoCmd);
    $videoInfo = json_decode($info, true);
    
    $title = $videoInfo['title'] ?? 'Untitled Video';
    $duration = $videoInfo['duration'] ?? 0;
    
    // If no thumbnail downloaded, try to get it
    if (!file_exists($thumbnailFile)) {
        $thumbUrl = $videoInfo['thumbnail'] ?? '';
        if ($thumbUrl) {
            file_put_contents($thumbnailFile, file_get_contents($thumbUrl));
        }
    }
    
    // Ensure thumbnail exists
    $thumbDbPath = file_exists($thumbnailFile) 
        ? 'uploads/' . $safeId . '/thumb.jpg' 
        : 'assets/default-thumb.jpg';
    
    // Local file path for proxy serving
    $localPath = 'uploads/' . $safeId . '/video.mp4';
    $encryptedLocal = encryptPath($localPath);
    
    // Step 3: Save to database
    $db->insert(
        "INSERT INTO videos (user_id, title, description, source_url, proxy_path, platform, thumbnail, duration, status) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')",
        [$userId, $title, '', $url, 'local:' . $encryptedLocal, $platform, $thumbDbPath, gmdate("H:i:s", $duration)]
    );
    
    return ['success' => true, 'video_id' => $db->getConnection()->lastInsertId(), 'type' => 'local'];
}

/**
 * Stream or get video URL through proxy
 */
function getVideoProxyUrl($videoId) {
    $db = Database::getInstance();
    $video = $db->fetch("SELECT * FROM videos WHERE id = ?", [$videoId]);
    if (!$video) return null;
    
    // Increment views
    $db->query("UPDATE videos SET views = views + 1 WHERE id = ?", [$videoId]);
    
    return SITE_URL . '/includes/proxy.php?vid=' . $videoId . '&k=' . md5($video['id'] . SECRET_KEY . date('Ymd'));
}
?>
