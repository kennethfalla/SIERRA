<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Deliberately old schema: migration must fill in the missing fields/tables.
return <<<'SQL'
CREATE TABLE users (id INT PRIMARY KEY, first_name VARCHAR(80), last_name VARCHAR(80), email VARCHAR(254), contact_number VARCHAR(30));
CREATE TABLE categories (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80), icon_class VARCHAR(50));
CREATE TABLE barangays (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80));
CREATE TABLE reports (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id INT, category_id INT, barangay_id INT,
 title VARCHAR(255), description TEXT, status VARCHAR(32), risk_level VARCHAR(20),
 created_at DATETIME, verification_count INT DEFAULT 0
);
CREATE TABLE report_images (id INT AUTO_INCREMENT PRIMARY KEY, report_id INT);
CREATE TABLE announcements (id INT AUTO_INCREMENT PRIMARY KEY, barangay_id INT, is_active TINYINT DEFAULT 1, is_public TINYINT DEFAULT 1);
CREATE TABLE activity_logs (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT, actor_name VARCHAR(191), actor_role VARCHAR(50),
 target_module VARCHAR(50), action VARCHAR(100), description VARCHAR(500), ip_address VARCHAR(64), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
SQL;
