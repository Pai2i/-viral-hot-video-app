CREATE DATABASE IF NOT EXISTS unistream;
USE unistream;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    avatar VARCHAR(255) DEFAULT 'default.png',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    icon VARCHAR(50) DEFAULT 'fa-folder'
);

CREATE TABLE videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT,
    source_url TEXT NOT NULL,
    proxy_path VARCHAR(255) NOT NULL,
    platform ENUM('youtube', 'tiktok', 'instagram', 'facebook', 'other') DEFAULT 'other',
    thumbnail VARCHAR(255),
    duration VARCHAR(20),
    views INT DEFAULT 0,
    likes INT DEFAULT 0,
    status ENUM('processing', 'active', 'blocked') DEFAULT 'processing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    type ENUM('like', 'dislike') DEFAULT 'like',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (user_id, video_id)
);

-- Insert default categories
INSERT INTO categories (name, slug, icon) VALUES
('Entertainment', 'entertainment', 'fa-film'),
('Music', 'music', 'fa-music'),
('Gaming', 'gaming', 'fa-gamepad'),
('Education', 'education', 'fa-graduation-cap'),
('Sports', 'sports', 'fa-futbol'),
('News', 'news', 'fa-newspaper'),
('Technology', 'technology', 'fa-microchip'),
('Comedy', 'comedy', 'fa-face-laugh');

-- Admin user (password: admin123)
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@unistream.com', '$2y$10$YourHashHere', 'admin');
