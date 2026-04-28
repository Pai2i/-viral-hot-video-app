<?php require_once 'includes/functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?> - Watch Videos Free</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <!-- Navigation -->
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
                <?php else: ?>
                    <a href="/login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                    <a href="/register.php"><i class="fas fa-user-plus"></i> Register</a>
                <?php endif; ?>
            </div>
            <form class="search-bar" action="/search.php" method="GET">
                <input type="text" name="q" placeholder="Search videos..." required>
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </nav>

    <!-- Categories -->
    <div class="categories-bar">
        <div class="container">
            <?php
            $db = Database::getInstance();
            $categories = $db->fetchAll("SELECT * FROM categories ORDER BY name");
            foreach ($categories as $cat): ?>
                <a href="/category.php?slug=<?= $cat['slug'] ?>" class="category-tag">
                    <i class="fas <?= $cat['icon'] ?>"></i> <?= $cat['name'] ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Main Content -->
    <main class="container">
        <section class="featured-section">
            <h2><i class="fas fa-star"></i> Featured Videos</h2>
            <div class="video-grid">
                <?php
                $videos = $db->fetchAll(
                    "SELECT v.*, u.username FROM videos v 
                     JOIN users u ON v.user_id = u.id 
                     WHERE v.status = 'active' 
                     ORDER BY v.views DESC LIMIT 12"
                );
                
                foreach ($videos as $video): 
                    $proxyUrl = SITE_URL . '/includes/proxy.php?vid=' . $video['id'] . '&k=' . md5($video['id'] . SECRET_KEY . date('Ymd'));
                ?>
                <div class="video-card">
                    <a href="/watch.php?id=<?= $video['id'] ?>">
                        <div class="video-thumbnail" style="background-image: url('<?= SITE_URL . '/' . $video['thumbnail'] ?>');">
                            <div class="video-duration"><?= $video['duration'] ?></div>
                            <div class="play-overlay"><i class="fas fa-play"></i></div>
                        </div>
                    </a>
                    <div class="video-info">
                        <h3><a href="/watch.php?id=<?= $video['id'] ?>"><?= htmlspecialchars($video['title']) ?></a></h3>
                        <div class="video-meta">
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($video['username']) ?></span>
                            <span><i class="fas fa-eye"></i> <?= formatViews($video['views']) ?></span>
                            <span><i class="fas fa-clock"></i> <?= timeAgo($video['created_at']) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <script src="assets/script.js"></script>
</body>
</html>
