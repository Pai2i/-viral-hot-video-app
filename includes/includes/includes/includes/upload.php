<?php 
require_once 'includes/functions.php';
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}
$user = getCurrentUser();

// Handle form submission
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['video_url'])) {
    $url = trim($_POST['video_url']);
    $platform = detectPlatform($url);
    
    if ($platform === 'other') {
        $error = 'Please enter a valid YouTube, TikTok, Instagram, or Facebook video URL';
    } else {
        $result = processVideo($url, $user['id']);
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $message = 'Video imported successfully!';
            $newVideoId = $result['video_id'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Video - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a href="/" class="logo"><?= SITE_NAME ?></a>
            <div class="nav-links">
                <a href="/"><i class="fas fa-home"></i> Home</a>
                <a href="/upload.php" class="active"><i class="fas fa-upload"></i> Upload</a>
                <a href="/profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <main class="container upload-page">
        <div class="upload-container">
            <h1><i class="fas fa-cloud-upload-alt"></i> Import Video</h1>
            <p class="subtitle">Paste a link from YouTube, TikTok, Instagram, or Facebook</p>
            
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $message ?>
                    <?php if (isset($newVideoId)): ?>
                        <br><a href="/watch.php?id=<?= $newVideoId ?>" class="btn btn-sm">Watch Now →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="upload-form" id="uploadForm">
                <div class="form-group">
                    <label for="video_url">Video URL</label>
                    <div class="url-input-wrapper">
                        <input type="url" id="video_url" name="video_url" 
                               placeholder="https://www.youtube.com/watch?v=... or https://www.tiktok.com/@..." 
                               required class="url-input">
                        <button type="submit" class="btn btn-primary" id="importBtn">
                            <i class="fas fa-download"></i> Import Video
                        </button>
                    </div>
                </div>
                
                <div class="supported-platforms">
                    <p><strong>Supported Platforms:</strong></p>
                    <div class="platform-icons">
                        <span><i class="fab fa-youtube"></i> YouTube</span>
                        <span><i class="fab fa-tiktok"></i> TikTok</span>
                        <span><i class="fab fa-instagram"></i> Instagram</span>
                        <span><i class="fab fa-facebook"></i> Facebook</span>
                    </div>
                </div>
            </form>
            
            <div class="loading-overlay" id="loadingOverlay" style="display:none;">
                <div class="spinner"></div>
                <p>Fetching video... This may take a moment.</p>
            </div>
        </div>
    </main>

    <script>
    document.getElementById('uploadForm').addEventListener('submit', function() {
        document.getElementById('loadingOverlay').style.display = 'flex';
        document.getElementById('importBtn').disabled = true;
        document.getElementById('importBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';
    });
    </script>
</body>
</html>
