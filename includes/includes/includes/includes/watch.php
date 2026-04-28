<?php 
require_once 'includes/functions.php';

$videoId = $_GET['id'] ?? 0;
$db = Database::getInstance();
$video = $db->fetch(
    "SELECT v.*, u.username, u.avatar FROM videos v 
     JOIN users u ON v.user_id = u.id 
     WHERE v.id = ? AND v.status = 'active'", 
    [$videoId]
);

if (!$video) {
    header('Location: /');
    exit;
}

// Get proxy URL
$proxyUrl = getVideoProxyUrl($videoId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($video['title']) ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
    /* Custom video player styles */
    .video-player-wrapper {
        position: relative;
        width: 100%;
        background: #000;
        border-radius: 12px;
        overflow: hidden;
    }
    
    video {
        width: 100%;
        max-height: 70vh;
        display: block;
    }
    
    .custom-controls {
        display: flex;
        align-items: center;
        padding: 10px 15px;
        background: rgba(0,0,0,0.9);
        gap: 15px;
    }
    
    .custom-controls button {
        background: none;
        border: none;
        color: white;
        font-size: 18px;
        cursor: pointer;
        padding: 5px;
    }
    
    .progress-bar {
        flex: 1;
        height: 4px;
        background: #333;
        border-radius: 2px;
        cursor: pointer;
        position: relative;
    }
    
    .progress-fill {
        height: 100%;
        background: #ff4444;
        border-radius: 2px;
        width: 0%;
    }
    
    .time-display {
        color: white;
        font-size: 13px;
        font-family: monospace;
    }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="/" class="logo"><?= SITE_NAME ?></a>
            <div class="nav-links">
                <a href="/"><i class="fas fa-home"></i> Home</a>
                <a href="/trending.php"><i class="fas fa-fire"></i> Trending</a>
                <?php if (isLoggedIn()): ?>
                    <a href="/upload.php"><i class="fas fa-upload"></i> Upload</a>
                    <a href="/profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="container watch-page">
        <div class="video-player-wrapper">
            <!-- ═══ THE PROXIED VIDEO ═══ -->
            <!-- Browser sees src="yoursite.com/..." — never the original source -->
            <video id="mainVideo" controls autoplay>
                <source src="<?= $proxyUrl ?>" type="video/mp4">
                Your browser does not support video playback.
            </video>
        </div>
        
        <div class="video-details-section">
            <h1 class="video-title"><?= htmlspecialchars($video['title']) ?></h1>
            
            <div class="video-actions">
                <div class="video-stats">
                    <span><i class="fas fa-eye"></i> <?= formatViews($video['views']) ?> views</span>
                    <span><i class="fas fa-clock"></i> <?= timeAgo($video['created_at']) ?></span>
                    <span class="platform-badge platform-<?= $video['platform'] ?>">
                        <i class="fab fa-<?= $video['platform'] ?>"></i> <?= ucfirst($video['platform']) ?>
                    </span>
                </div>
                
                <?php if (isLoggedIn()): ?>
                <div class="interaction-buttons">
                    <button class="btn btn-like" data-video="<?= $video['id'] ?>">
                        <i class="fas fa-thumbs-up"></i> <span id="likeCount"><?= $video['likes'] ?></span>
                    </button>
                    <button class="btn btn-share" onclick="copyLink()">
                        <i class="fas fa-share"></i> Share
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="uploader-info">
                <img src="<?= $video['avatar'] ? 'uploads/avatars/'.$video['avatar'] : 'assets/default-avatar.png' ?>" 
                     alt="" class="avatar">
                <div>
                    <strong><?= htmlspecialchars($video['username']) ?></strong>
                </div>
            </div>
            
            <div class="video-description">
                <p><?= nl2br(htmlspecialchars($video['description'] ?: 'No description')) ?></p>
            </div>
        </div>
    </main>

    <script>
    function copyLink() {
        navigator.clipboard.writeText(window.location.href)
            .then(() => alert('Link copied!'))
            .catch(() => prompt('Copy this link:', window.location.href));
    }
    </script>
</body>
</html>
