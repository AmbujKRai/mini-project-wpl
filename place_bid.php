<?php
require 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "login_required";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auction_id = intval($_POST['auction_id']);
    $bid_amount = floatval($_POST['bid_amount']);
    $user_id = $_SESSION['user_id'];

    // Verify auction exists and is still active
    $auction_query = $conn->query("SELECT * FROM auctions WHERE id = $auction_id AND status = 'active' AND end_time > NOW()");
    if ($auction_query->num_rows === 0) {
        echo "auction_closed";
        exit;
    }

    $auction = $auction_query->fetch_assoc();
    
    // Check if current user is the seller
    if ($auction['seller_id'] == $user_id) {
        echo "seller_cannot_bid";
        exit;
    }

    // Get current highest bid
    $result = $conn->query("SELECT MAX(bid_amount) AS max_bid FROM bids WHERE auction_id = $auction_id");
    $row = $result->fetch_assoc();
    $current_max = $row['max_bid'] ?? 0;

    // If no bids, get starting price
    if ($current_max == 0) {
        $current_max = $auction['start_price'];
    }

    if ($bid_amount <= $current_max) {
        echo "lowbid";
        exit;
    }

    // Insert the new bid
    $stmt = $conn->prepare("INSERT INTO bids (auction_id, bidder_id, bid_amount) VALUES (?, ?, ?)");
    $stmt->bind_param("iid", $auction_id, $user_id, $bid_amount);
    
    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }
    
    $stmt->close();
}
?>
