-- Chon database da tao tren hosting trong phpMyAdmin truoc khi import file nay.
-- File nay tao schema cho comments.php, khong chua thong tin dang nhap.

CREATE TABLE IF NOT EXISTS guest_comments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    message VARCHAR(1000) NOT NULL,
    side VARCHAR(10) NOT NULL DEFAULT 'groom',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rsvps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    relationship VARCHAR(40) NOT NULL,
    attendance VARCHAR(20) NOT NULL,
    guest_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    wishes VARCHAR(1000) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
