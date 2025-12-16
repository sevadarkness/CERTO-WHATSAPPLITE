-- Migration: user_favorites table
-- Stores user's favorite pages for quick access

CREATE TABLE IF NOT EXISTS user_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    page_url VARCHAR(255) NOT NULL,
    page_label VARCHAR(255) NOT NULL,
    page_icon VARCHAR(100) DEFAULT 'fa-star',
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_page (user_id, page_url),
    INDEX idx_user_sort (user_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
