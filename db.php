<?php
$host = "localhost";
$username = "root";
$password = "";
$dbname = "auction_db";

// Try to connect to the database
$conn = new mysqli($host, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
$conn->select_db($dbname);

// Check if tables exist, if not redirect to setup
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result->num_rows == 0) {
    header("Location: setup.php");
    exit;
}

// Check if credit_score and sustainability_impact columns exist in users table
$check_columns = $conn->query("SHOW COLUMNS FROM users LIKE 'credit_score'");
if ($check_columns->num_rows == 0) {
    // Add credit_score and sustainability_impact columns
    $conn->query("ALTER TABLE users ADD COLUMN credit_score DECIMAL(10,2) DEFAULT 100.00");
    $conn->query("ALTER TABLE users ADD COLUMN sustainability_impact DECIMAL(10,2) DEFAULT 0.00");
    $conn->query("ALTER TABLE users ADD COLUMN is_new_seller BOOLEAN DEFAULT TRUE");
}

// Check if ratings table exists
$check_ratings = $conn->query("SHOW TABLES LIKE 'ratings'");
if ($check_ratings->num_rows == 0) {
    // Create ratings table
    $conn->query("CREATE TABLE ratings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        auction_id INT NOT NULL,
        rater_id INT NOT NULL,
        seller_id INT NOT NULL,
        rating INT NOT NULL,
        material_consistency INT NOT NULL,
        sustainability_rating INT NOT NULL,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE,
        FOREIGN KEY (rater_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
    )");
}

// Check if material_specializations table exists
$check_specializations = $conn->query("SHOW TABLES LIKE 'material_specializations'");
if ($check_specializations->num_rows == 0) {
    // Create material_specializations table
    $conn->query("CREATE TABLE material_specializations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        category VARCHAR(50) NOT NULL,
        specialization_count INT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
}
?>
