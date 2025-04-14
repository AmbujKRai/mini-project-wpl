<?php
$host = "localhost";
$username = "root";
$password = "";
$dbname = "auction_db";

// Connect to the database server
$conn = new mysqli($host, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Setting up database...</h2>";

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql)) {
    echo "<p>Database created successfully or already exists</p>";
} else {
    echo "<p>Error creating database: " . $conn->error . "</p>";
    exit;
}

// Select the database
$conn->select_db($dbname);

// Create users table
$sql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('user', 'seller', 'admin') DEFAULT 'user',
    company_name VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    credit_score DECIMAL(10,2) DEFAULT 100.00,
    sustainability_impact DECIMAL(10,2) DEFAULT 0.00,
    is_new_seller BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "<p>Users table created successfully</p>";
} else {
    echo "<p>Error creating users table: " . $conn->error . "</p>";
}

// Create auctions table
$sql = "CREATE TABLE IF NOT EXISTS auctions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT,
    title VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(50) DEFAULT 'Industrial Waste',
    quantity FLOAT NOT NULL,
    unit VARCHAR(20) NOT NULL,
    start_price DECIMAL(10,2) NOT NULL,
    end_time DATETIME NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL
)";

if ($conn->query($sql)) {
    echo "<p>Auctions table created successfully</p>";
} else {
    echo "<p>Error creating auctions table: " . $conn->error . "</p>";
}

// Create bids table
$sql = "CREATE TABLE IF NOT EXISTS bids (
    id INT AUTO_INCREMENT PRIMARY KEY,
    auction_id INT NOT NULL,
    bidder_id INT,
    bid_amount DECIMAL(10,2) NOT NULL,
    bid_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE CASCADE,
    FOREIGN KEY (bidder_id) REFERENCES users(id) ON DELETE SET NULL
)";

if ($conn->query($sql)) {
    echo "<p>Bids table created successfully</p>";
} else {
    echo "<p>Error creating bids table: " . $conn->error . "</p>";
}

// Create messages table
$sql = "CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    auction_id INT,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (auction_id) REFERENCES auctions(id) ON DELETE SET NULL
)";

if ($conn->query($sql)) {
    echo "<p>Messages table created successfully</p>";
} else {
    echo "<p>Error creating messages table: " . $conn->error . "</p>";
}

// Create ratings table
$sql = "CREATE TABLE IF NOT EXISTS ratings (
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
)";

if ($conn->query($sql)) {
    echo "<p>Ratings table created successfully</p>";
} else {
    echo "<p>Error creating ratings table: " . $conn->error . "</p>";
}

// Create material specialization table
$sql = "CREATE TABLE IF NOT EXISTS material_specializations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category VARCHAR(50) NOT NULL,
    specialization_count INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql)) {
    echo "<p>Material specialization table created successfully</p>";
} else {
    echo "<p>Error creating material specialization table: " . $conn->error . "</p>";
}

// Create admin user if not exists
$admin_username = "admin";
$admin_email = "admin@example.com";
$admin_password = password_hash("admin123", PASSWORD_DEFAULT);

// Check if admin exists
$result = $conn->query("SELECT id FROM users WHERE username = 'admin'");
if ($result->num_rows == 0) {
    $sql = "INSERT INTO users (username, email, password, user_type) 
            VALUES ('$admin_username', '$admin_email', '$admin_password', 'admin')";
    
    if ($conn->query($sql)) {
        echo "<p>Admin user created successfully (Username: admin, Password: admin123)</p>";
    } else {
        echo "<p>Error creating admin user: " . $conn->error . "</p>";
    }
} else {
    echo "<p>Admin user already exists</p>";
}

// Create uploads directory if it doesn't exist
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
    echo "<p>Uploads directory created</p>";
}

echo "<p style='color: green; font-weight: bold;'>Setup completed successfully!</p>";
echo "<p><a href='index.html'>Go to homepage</a></p>";

$conn->close();
?> 