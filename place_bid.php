<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auction_id = intval($_POST['auction_id']);
    $bid_amount = floatval($_POST['bid_amount']);

    // Get current highest bid
    $result = $conn->query("SELECT MAX(bid_amount) AS max_bid FROM bids WHERE auction_id = $auction_id");
    $row = $result->fetch_assoc();
    $current_max = $row['max_bid'] ?? 0;

    // If no bids, get starting price
    if ($current_max == 0) {
        $result = $conn->query("SELECT start_price FROM auctions WHERE id = $auction_id");
        $row = $result->fetch_assoc();
        $current_max = $row['start_price'];
    }

    if ($bid_amount <= $current_max) {
        echo "lowbid";
        exit;
    }

    // Insert the new bid
    $stmt = $conn->prepare("INSERT INTO bids (auction_id, bid_amount) VALUES (?, ?)");
    $stmt->bind_param("id", $auction_id, $bid_amount);
    $stmt->execute();
    echo "success";
}
?>
