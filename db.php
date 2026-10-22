<?php
$host = 'localhost';
$dbname = 'cardszoneclub';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS buyers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        username VARCHAR(255) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL UNIQUE,
        private_key VARCHAR(36) NOT NULL UNIQUE,
        is_activated TINYINT DEFAULT 0,
        balance_usd DECIMAL(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS sellers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        nickname VARCHAR(100) NOT NULL,
        username VARCHAR(255) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL UNIQUE,
        rating_score DECIMAL(3,2) DEFAULT 3.00,
        status ENUM('active', 'suspended', 'blacklisted') DEFAULT 'active',
        is_approved TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seller_id INT NOT NULL,
        category VARCHAR(100) NOT NULL,
        bins VARCHAR(20),
        card_type VARCHAR(50),
        subtype VARCHAR(50),
        expiry VARCHAR(10),
        holder_name VARCHAR(100),
        country VARCHAR(50),
        state VARCHAR(50),
        full_address TEXT,
        extra_info TEXT,
        zip VARCHAR(20),
        bank VARCHAR(100),
        bank_pin_code VARCHAR(50),
        email_password VARCHAR(255),
        price DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS deposits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        buyer_id INT NOT NULL,
        amount_usd DECIMAL(10,2) NOT NULL,
        crypto_type VARCHAR(20) NOT NULL,
        tx_hash VARCHAR(255) DEFAULT NULL,
        status ENUM('pending', 'confirmed', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $stmt = $pdo->prepare("DELETE FROM buyers WHERE is_activated = 0 AND created_at < NOW() - INTERVAL 5 DAY");
    $stmt->execute();

    $pdo->exec("UPDATE sellers SET status = 'blacklisted' WHERE rating_score < 1.50 AND status != 'blacklisted'");

} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}
?>
