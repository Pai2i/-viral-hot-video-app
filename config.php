<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'unistream');

// Site
define('SITE_NAME', 'UniStream');
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST']);
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_VIDEO_SIZE', 500 * 1024 * 1024); // 500MB

// yt-dlp path
define('YT_DLP_PATH', '/usr/local/bin/yt-dlp');

// Encryption key for internal URLs
define('SECRET_KEY', 'YourSuperSecretKeyChangeThis123!');

// Platform configurations
$platforms = [
    'youtube' => [
        'regex' => '/^(https?:\/\/)?(www\.)?(youtube\.com|youtu\.be)/',
        'enabled' => true
    ],
    'tiktok' => [
        'regex' => '/^(https?:\/\/)?(www\.|vm\.|m\.)?tiktok\.com/',
        'enabled' => true
    ],
    'instagram' => [
        'regex' => '/^(https?:\/\/)?(www\.)?(instagram\.com|instagr\.am)/',
        'enabled' => true
    ],
    'facebook' => [
        'regex' => '/^(https?:\/\/)?(www\.)?(facebook\.com|fb\.com|fb\.watch)/',
        'enabled' => true
    ]
];
?>
