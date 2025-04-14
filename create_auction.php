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
        .auction-form {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #455a64;
        }
        
        input[type="text"],
        input[type="number"],
        input[type="datetime-local"],
        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            background-color: #f8f9fa;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="datetime-local"]:focus,
        select:focus,
        textarea:focus {
            border-color: #28a745;
            outline: none;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.25);
            background-color: #fff;
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        input[type="file"] {
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            border: 1px dashed #ccc;
            width: 100%;
            cursor: pointer;
        }
        
        button[type="submit"] {
            background: #28a745;
            color: white;
            padding: 14px 24px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: background 0.3s, transform 0.2s;
            margin-top: 10px;
            width: 100%;
        }
        
        button[type="submit"]:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
        }
        
        small {
            display: block;
            color: #78909c;
            margin-top: 5px;
            font-size: 0.85rem;
        }
        
        .success-message {
            padding: 15px;
            background-color: #e8f5e9;
            border-left: 4px solid #28a745;
            color: #218838;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .error-message {
            padding: 15px;
            background-color: #ffebee;
            border-left: 4px solid #dc3545;
            color: #c62828;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .auction-form {
                padding: 20px;
            }
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
    <h1>Create a New Waste Auction</h1>
    
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <?php if (isset($stmt) && isset($stmt->affected_rows) && $stmt->affected_rows > 0): ?>
            <div class="success-message">
                <p>Auction created successfully! Your waste material is now listed for bidding.</p>
                <p><a href="auctions.php">View all auctions</a> or <a href="profile.php">go to your profile</a> to manage your listings.</p>
            </div>
        <?php elseif (isset($uploadOk) && $uploadOk == 0): ?>
            <div class="error-message">
                <p>Error creating auction. Please check the form and try again.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="auction-form">
        <form action="create_auction.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Auction Title</label>
                <input type="text" id="title" name="title" placeholder="E.g., 500kg Metal Scrap from Manufacturing Process" required>
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
                    <input type="number" id="quantity" name="quantity" step="0.01" min="0.01" placeholder="Amount" required>
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
                <textarea id="description" name="description" placeholder="Detailed description of the waste material including composition, origin, condition, and any other relevant information that might be useful for potential buyers." rows="5" required></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="start_price">Starting Price (₹)</label>
                    <input type="number" id="start_price" name="start_price" placeholder="Minimum bid amount" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="end_time">Auction End Time</label>
                    <input type="datetime-local" id="end_time" name="end_time" required>
                    <small>Set when the auction will close. Must be at least 1 hour from now.</small>
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
                <small>Upload a clear image of the waste material. Maximum file size: 5MB. Supported formats: JPG, JPEG, PNG, GIF.</small>
            </div>
            
            <button type="submit">Create Auction</button>
        </form>
    </div>
</div>

</body>
</html>
