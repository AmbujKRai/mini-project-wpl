<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $start_price = $_POST['start_price'];
    $end_time = $_POST['end_time']; // renamed for clarity

    // Handle image upload
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true); // Create directory if it doesn't exist
    }

    $image_name = basename($_FILES["image"]["name"]);
    $target_file = $target_dir . $image_name;

    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO auctions (title, description, start_price, end_time, image_path) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdss", $title, $description, $start_price, $end_time, $target_file);

        if ($stmt->execute()) {
            echo "<p style='color: green;'>Auction created successfully!</p>";
        } else {
            echo "<p style='color: red;'>Database error: " . $stmt->error . "</p>";
        }

        $stmt->close();
    } else {
        echo "<p style='color: red;'>Error uploading image.</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Auction</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<header>
    <div class="logo">AuctionHub</div>
    <nav>
        <ul>
            <li><a href="index.html">Home</a></li>
            <li><a href="auctions.php">Auctions</a></li>
            <li><a href="create_auction.php">Create Auction</a></li>
            <li><a href="profile.html">Profile</a></li>
            <li><a href="login.html">Login/Register</a></li>
            <li><a href="admin.html">Admin Panel</a></li>
        </ul>
    </nav>
</header>

<h2>Create a New Auction</h2>
<form action="create_auction.php" method="POST" enctype="multipart/form-data">
    <input type="text" name="title" placeholder="Auction Title" required>
    <textarea name="description" placeholder="Description" required></textarea>
    <input type="number" name="start_price" placeholder="Starting Price" step="0.01" required>
    <input type="datetime-local" name="end_time" required>
    <input type="file" name="image" accept="image/*" required>
    <button type="submit">Create Auction</button>
</form>

</body>
</html>
