<?php
/**
 * ════════════════════════════════════════════════════════════
 * UNISTREAM VIDEO PROXY ENGINE
 * ────────────────────────────────────────────────────────────
 * This is the CORE file. It fetches video from the original
 * source but serves it through OUR server. The browser NEVER
 * knows the original source. All traffic appears to come
 * from our domain.
 * ════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

// Security check
$videoId = $_GET['vid'] ?? 0;
$key = $_GET['k'] ?? '';

$db = Database::getInstance();
$video = $db->fetch("SELECT * FROM videos WHERE id = ?", [$videoId]);

if (!$video || $video['status'] != 'active') {
    http_response_code(404);
    die('Video not found');
}

// Verify key (simple check)
$expectedKey = md5($video['id'] . SECRET_KEY . date('Ymd'));
if ($key !== $expectedKey && $key !== md5($video['id'] . SECRET_KEY . date('Ymd', strtotime('-1 day')))) {
    http_response_code(403);
    die('Invalid access');
}

// ──── METHOD 1: Local File ────
if (strpos($video['proxy_path'], 'local:') === 0) {
    $encryptedPath = substr($video['proxy_path'], 6);
    $realPath = decryptPath($encryptedPath);
    $fullPath = __DIR__ . '/../' . $realPath;
    
    if (!$realPath || !file_exists($fullPath)) {
        http_response_code(404);
        die('File not found');
    }
    
    $fileSize = filesize($fullPath);
    $mimeType = mime_content_type($fullPath);
    
    // Handle range requests (for seeking)
    $start = 0;
    $end = $fileSize - 1;
    $contentLength = $fileSize;
    
    if (isset($_SERVER['HTTP_RANGE'])) {
        preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches);
        $start = intval($matches[1]);
        $end = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : $fileSize - 1;
        $contentLength = $end - $start + 1;
        
        header('HTTP/1.1 206 Partial Content');
        header("Content-Range: bytes $start-$end/$fileSize");
    }
    
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $contentLength);
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=86400');
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
    header('X-Content-Type-Options: nosniff');
    
    // Output file with range support
    $fp = fopen($fullPath, 'rb');
    fseek($fp, $start);
    $chunkSize = 1024 * 1024; // 1MB chunks
    $bytesSent = 0;
    
    while (!feof($fp) && $bytesSent < $contentLength && !connection_aborted()) {
        $remaining = $contentLength - $bytesSent;
        $readSize = min($chunkSize, $remaining);
        echo fread($fp, $readSize);
        $bytesSent += $readSize;
        flush();
        
        if (connection_aborted()) break;
    }
    fclose($fp);
    exit;
}
    
// ──── METHOD 2: Stream Proxy (No local file) ────
if (strpos($video['proxy_path'], 'stream:') === 0) {
    $encryptedUrl = substr($video['proxy_path'], 7);
    $originalUrl = decryptPath($encryptedUrl);
    
    if (!$originalUrl) {
        http_response_code(500);
        die('Invalid stream');
    }
    
    // Fetch and proxy the video stream from original source
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $originalUrl,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_REFERER => 'https://www.google.com/',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_BUFFERSIZE => 524288, // 512KB buffer
        CURLOPT_FILE => fopen('php://output', 'w'),
    ]);
    
    // Forward range header if present
    if (isset($_SERVER['HTTP_RANGE'])) {
        curl_setopt($ch, CURLOPT_RANGE, $_SERVER['HTTP_RANGE']);
    }
    
    // Get headers from the source and forward them
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $headerLine) {
        $header = trim($headerLine);
        $allowedHeaders = ['Content-Type:', 'Content-Length:', 'Accept-Ranges:', 'Content-Range:'];
        
        foreach ($allowedHeaders as $allowed) {
            if (stripos($header, $allowed) === 0) {
                header($header);
                break;
            }
        }
        return strlen($headerLine);
    });
    
    // Set our own headers
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    
    curl_exec($ch);
    curl_close($ch);
    exit;
}

// ──── METHOD 3: yt-dlp direct stream ────
// Fallback: use yt-dlp to get the direct video URL, then proxy it
$cmd = YT_DLP_PATH . " -g -f 'best[ext=mp4]/best' --no-warnings --quiet " . escapeshellarg($video['source_url']) . " 2>/dev/null";
$directUrl = trim(shell_exec($cmd));

if ($directUrl && filter_var($directUrl, FILTER_VALIDATE_URL)) {
    // Redirect internally (not HTTP redirect - stream through us)
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $directUrl,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_FILE => fopen('php://output', 'w'),
    ]);
    
    if (isset($_SERVER['HTTP_RANGE'])) {
        curl_setopt($ch, CURLOPT_RANGE, $_SERVER['HTTP_RANGE']);
    }
    
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $headerLine) {
        $header = trim($headerLine);
        $allowed = ['Content-Type:', 'Content-Length:', 'Accept-Ranges:', 'Content-Range:'];
        foreach ($allowed as $a) {
            if (stripos($header, $a) === 0) { header($header); break; }
        }
        return strlen($headerLine);
    });
    
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    
    curl_exec($ch);
    curl_close($ch);
    exit;
}

http_response_code(500);
die('Unable to stream video');
?>
