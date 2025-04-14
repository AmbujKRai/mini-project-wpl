<?php
require 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=create_auction.php");
    exit;
}

// Check if user is a seller or admin
$user_id = $_SESSION['user_id'];
$user_check = $conn->query("SELECT user_type FROM users WHERE id = $user_id");
$user_data = $user_check->fetch_assoc();

if ($user_data['user_type'] != 'seller' && $user_data['user_type'] != 'admin') {
    // Update user to seller status if they're creating an auction
    $conn->query("UPDATE users SET user_type = 'seller' WHERE id = $user_id");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $quantity = floatval($_POST['quantity']);
    $unit = mysqli_real_escape_string($conn, $_POST['unit']);
    $start_price = floatval($_POST['start_price']);
    $end_time = $_POST['end_time'];

    // Handle image upload
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true); // Create directory if it doesn't exist
    }

    $image_name = time() . "_" . basename($_FILES["image"]["name"]);
    $target_file = $target_dir . $image_name;
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // Check if image file is an actual image
    $check = getimagesize($_FILES["image"]["tmp_name"]);
    if($check === false) {
        echo "<p style='color: red;'>File is not an image.</p>";
        $uploadOk = 0;
    }

    // Check file size (limit to 5MB)
    if ($_FILES["image"]["size"] > 5000000) {
        echo "<p style='color: red;'>Sorry, your file is too large.</p>";
        $uploadOk = 0;
    }
    
    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        echo "<p style='color: red;'>Sorry, only JPG, JPEG, PNG & GIF files are allowed.</p>";
        $uploadOk = 0;
    }

    if ($uploadOk == 1 && move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO auctions (seller_id, title, description, category, quantity, unit, start_price, end_time, image_path, status) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("isssdsdss", $user_id, $title, $description, $category, $quantity, $unit, $start_price, $end_time, $target_file);

        if ($stmt->execute()) {
            echo "<p style='color: green;'>Auction created successfully!</p>";
            
            // Add or update material specialization for this seller
            $spec_check = $conn->query("SELECT * FROM material_specializations 
                                       WHERE user_id = $user_id AND category = '$category'");
                                       
            if ($spec_check->num_rows > 0) {
                // Update existing specialization
                $conn->query("UPDATE material_specializations 
                              SET specialization_count = specialization_count + 1 
                              WHERE user_id = $user_id AND category = '$category'");
            } else {
                // Create new specialization entry
                $conn->query("INSERT INTO material_specializations (user_id, category, specialization_count) 
                              VALUES ($user_id, '$category', 1)");
            }
            
            // Check if seller is new (less than 10 auctions)
            $auction_count = $conn->query("SELECT COUNT(*) as count FROM auctions WHERE seller_id = $user_id");
            $count_data = $auction_count->fetch_assoc();
            
            if ($count_data['count'] <= 10) {
                $conn->query("UPDATE users SET is_new_seller = 1 WHERE id = $user_id");
            } else {
                $conn->query("UPDATE users SET is_new_seller = 0 WHERE id = $user_id");
            }
        } else {
            echo "<p style='color: red;'>Database error: " . $stmt->error . "</p>";
        }

        $stmt->close();
    } else {
        echo "<p style='color: red;'>Error uploading image.</p>";
    }
}

// Get waste categories
$categories = ['Industrial Scrap', 'Chemical Waste', 'Textile Waste', 'Plastic Waste', 'Metal Scrap', 'Electronic Waste', 'Paper Waste', 'Other'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Auction - Industrial Waste</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .form-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .form-group {
            flex: 1;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<header>
    <div class="logo">WasteBidder</div>
    <nav>
        <ul>
            <li><a href="index.html">Home</a></li>
            <li><a href="auctions.php">Auctions</a></li>
            <li><a href="create_auction.php">Create Auction</a></li>
            <li><a href="profile.php">Profile</a></li>
            <?php if(isset($_SESSION['user_id'])): ?>
                <li><a href="logout.php">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php">Login/Register</a></li>
            <?php endif; ?>
            <?php if(isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin'): ?>
                <li><a href="admin.php">Admin Panel</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<div class="container">
    <h2>Create a New Waste Auction</h2>
    <form action="create_auction.php" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" placeholder="Auction Title" required>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="category">Waste Category</label>
                <select id="category" name="category" required>
                    <?php foreach($categories as $category): ?>
                        <option value="<?= $category ?>"><?= $category ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="quantity">Quantity</label>
                <input type="number" id="quantity" name="quantity" step="0.01" min="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="unit">Unit</label>
                <select id="unit" name="unit" required>
                    <option value="kg">Kilograms (kg)</option>
                    <option value="ton">Tons</option>
                    <option value="liter">Liters</option>
                    <option value="cubic_meter">Cubic Meters</option>
                    <option value="piece">Pieces</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" placeholder="Detailed description of the waste material, composition, origin, etc." rows="5" required></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="start_price">Starting Price (₹)</label>
                <input type="number" id="start_price" name="start_price" placeholder="Starting Price" step="0.01" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="end_time">Auction End Time</label>
                <input type="datetime-local" id="end_time" name="end_time" required>
                <script>
                    // Set minimum end time to 1 hour from now
                    const now = new Date();
                    now.setHours(now.getHours() + 1);
                    document.getElementById('end_time').min = now.toISOString().slice(0, 16);
                </script>
            </div>
        </div>
        
        <div class="form-group">
            <label for="image">Upload Image</label>
            <input type="file" id="image" name="image" accept="image/*" required>
            <small>Upload an image of the waste material (max 5MB)</small>
        </div>
        
        <button type="submit">Create Auction</button>
    </form>
</div>

</body>
</html>
