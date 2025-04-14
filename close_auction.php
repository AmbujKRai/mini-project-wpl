<?php
require 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $auction_id = intval($_POST['auction_id']);
    $seller_id = intval($_POST['seller_id']);
    $rating = intval($_POST['rating']);
    $material_consistency = intval($_POST['material_consistency']);
    $sustainability_rating = intval($_POST['sustainability_rating']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    
    // Insert rating
    $stmt = $conn->prepare("INSERT INTO ratings (auction_id, rater_id, seller_id, rating, material_consistency, sustainability_rating, comment) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiiiss", $auction_id, $user_id, $seller_id, $rating, $material_consistency, $sustainability_rating, $comment);
    
    if ($stmt->execute()) {
        // Update seller's credit score based on rating
        $credit_increase = ($rating + $material_consistency + $sustainability_rating) * 2; // Simple formula for credit increase
        
        // Update sustainability impact based on rating and auction details
        $auction_query = $conn->query("SELECT quantity FROM auctions WHERE id = $auction_id");
        $auction_data = $auction_query->fetch_assoc();
        $sustainability_impact = $sustainability_rating * ($auction_data['quantity'] / 10); // Simple calculation
        
        // Update the seller's credit score and sustainability impact
        $conn->query("UPDATE users SET 
                      credit_score = credit_score + $credit_increase,
                      sustainability_impact = sustainability_impact + $sustainability_impact
                      WHERE id = $seller_id");
        
        // Update material specialization
        $material_query = $conn->query("SELECT category FROM auctions WHERE id = $auction_id");
        $material_data = $material_query->fetch_assoc();
        $category = $material_data['category'];
        
        // Check if specialization already exists
        $spec_query = $conn->query("SELECT id FROM material_specializations 
                                   WHERE user_id = $seller_id AND category = '$category'");
        
        if ($spec_query->num_rows > 0) {
            // Increment the count
            $conn->query("UPDATE material_specializations 
                         SET specialization_count = specialization_count + 1 
                         WHERE user_id = $seller_id AND category = '$category'");
        } else {
            // Create new specialization
            $conn->query("INSERT INTO material_specializations (user_id, category) 
                         VALUES ($seller_id, '$category')");
        }
        
        // Update auction status
        $conn->query("UPDATE auctions SET status = 'completed' WHERE id = $auction_id");
        
        // Redirect back to profile
        header("Location: profile.php?tab=my-bids&status=rated");
        exit;
    } else {
        header("Location: profile.php?error=rating_failed");
        exit;
    }
}

// Mark auction as completed without rating
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_completed'])) {
    $auction_id = intval($_POST['auction_id']);
    
    // Verify the user is the seller of this auction
    $verify = $conn->query("SELECT * FROM auctions WHERE id = $auction_id AND seller_id = $user_id");
    
    if ($verify->num_rows > 0) {
        // Update auction status
        $conn->query("UPDATE auctions SET status = 'completed' WHERE id = $auction_id");
        
        // Get the winning bidder if there is one
        $bid_result = $conn->query("
            SELECT b.bidder_id, b.bid_amount, u.email, u.username, a.title, a.seller_id
            FROM bids b
            JOIN users u ON b.bidder_id = u.id
            JOIN auctions a ON b.auction_id = a.id
            WHERE b.auction_id = $auction_id
            ORDER BY b.bid_amount DESC
            LIMIT 1
        ");
        
        if ($bid_result->num_rows > 0) {
            $winner = $bid_result->fetch_assoc();
            
            // Here you could implement email notification to the winner
            // Example:
            // mail($winner['email'], "You won the auction: " . $winner['title'], 
            //     "Congratulations! You've won the auction with your bid of ₹" . $winner['bid_amount']);
            
            // Give a small automatic credit boost to the seller for completed auction
            $conn->query("UPDATE users SET credit_score = credit_score + 5 WHERE id = ".$winner['seller_id']);
            
            // Set is_new_seller to FALSE after first successful auction
            $conn->query("UPDATE users SET is_new_seller = FALSE WHERE id = ".$winner['seller_id']." AND is_new_seller = TRUE");
        }
        
        // Redirect back to profile
        header("Location: profile.php?tab=my-auctions&status=closed");
        exit;
    } else {
        header("Location: profile.php?error=unauthorized");
        exit;
    }
} 

// Rate auction form
if (isset($_GET['rate']) && isset($_GET['auction_id'])) {
    $auction_id = intval($_GET['auction_id']);
    
    // Get auction details
    $auction_query = $conn->query("SELECT a.*, u.username AS seller_name, u.id AS seller_id 
                                 FROM auctions a 
                                 JOIN users u ON a.seller_id = u.id 
                                 WHERE a.id = $auction_id");
    
    if ($auction_query->num_rows === 0) {
        header("Location: profile.php?error=auction_not_found");
        exit;
    }
    
    $auction = $auction_query->fetch_assoc();
    
    // Check if the user has already rated this auction
    $check_rating = $conn->query("SELECT id FROM ratings 
                                WHERE auction_id = $auction_id AND rater_id = $user_id");
    
    if ($check_rating->num_rows > 0) {
        header("Location: profile.php?tab=my-bids&error=already_rated");
        exit;
    }
    
    // Display rating form
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Rate Seller - WasteBidder</title>
        <link rel="stylesheet" href="styles.css">
        <style>
            .rating-container {
                max-width: 600px;
                margin: 30px auto;
                background: white;
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .rating-field {
                margin-bottom: 20px;
            }
            
            .rating-field h3 {
                margin-bottom: 10px;
            }
            
            .star-rating {
                display: flex;
                gap: 10px;
            }
            
            .star-rating input {
                display: none;
            }
            
            .star-rating label {
                cursor: pointer;
                font-size: 30px;
                color: #ddd;
            }
            
            .star-rating label:hover,
            .star-rating label:hover ~ label,
            .star-rating input:checked ~ label {
                color: #ffc107;
            }
            
            textarea {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 5px;
                resize: vertical;
                min-height: 100px;
            }
            
            button {
                background: #28a745;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
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
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </header>
        
        <div class="rating-container">
            <h2>Rate Your Experience with <?= htmlspecialchars($auction['seller_name']) ?></h2>
            <p>Auction: <?= htmlspecialchars($auction['title']) ?></p>
            
            <form method="POST" action="close_auction.php">
                <input type="hidden" name="auction_id" value="<?= $auction_id ?>">
                <input type="hidden" name="seller_id" value="<?= $auction['seller_id'] ?>">
                
                <div class="rating-field">
                    <h3>Overall Rating</h3>
                    <div class="star-rating">
                        <input type="radio" id="star5" name="rating" value="5" required>
                        <label for="star5">★</label>
                        <input type="radio" id="star4" name="rating" value="4">
                        <label for="star4">★</label>
                        <input type="radio" id="star3" name="rating" value="3">
                        <label for="star3">★</label>
                        <input type="radio" id="star2" name="rating" value="2">
                        <label for="star2">★</label>
                        <input type="radio" id="star1" name="rating" value="1">
                        <label for="star1">★</label>
                    </div>
                </div>
                
                <div class="rating-field">
                    <h3>Material Consistency</h3>
                    <p>How well did the material match the description?</p>
                    <div class="star-rating">
                        <input type="radio" id="mc5" name="material_consistency" value="5" required>
                        <label for="mc5">★</label>
                        <input type="radio" id="mc4" name="material_consistency" value="4">
                        <label for="mc4">★</label>
                        <input type="radio" id="mc3" name="material_consistency" value="3">
                        <label for="mc3">★</label>
                        <input type="radio" id="mc2" name="material_consistency" value="2">
                        <label for="mc2">★</label>
                        <input type="radio" id="mc1" name="material_consistency" value="1">
                        <label for="mc1">★</label>
                    </div>
                </div>
                
                <div class="rating-field">
                    <h3>Sustainability Impact</h3>
                    <p>How much environmental benefit did this transaction provide?</p>
                    <div class="star-rating">
                        <input type="radio" id="sr5" name="sustainability_rating" value="5" required>
                        <label for="sr5">★</label>
                        <input type="radio" id="sr4" name="sustainability_rating" value="4">
                        <label for="sr4">★</label>
                        <input type="radio" id="sr3" name="sustainability_rating" value="3">
                        <label for="sr3">★</label>
                        <input type="radio" id="sr2" name="sustainability_rating" value="2">
                        <label for="sr2">★</label>
                        <input type="radio" id="sr1" name="sustainability_rating" value="1">
                        <label for="sr1">★</label>
                    </div>
                </div>
                
                <div class="rating-field">
                    <h3>Comments</h3>
                    <textarea name="comment" placeholder="Share your experience with this seller..."></textarea>
                </div>
                
                <button type="submit" name="submit_rating">Submit Rating</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
} else {
    header("Location: profile.php");
    exit;
}
?> 