<?php
require 'db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=profile.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user data
$user_query = $conn->query("SELECT * FROM users WHERE id = $user_id");
$user = $user_query->fetch_assoc();

// Update profile info
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    
    // Update user info
    $update = $conn->query("UPDATE users SET 
        company_name = '$company_name', 
        phone = '$phone', 
        address = '$address' 
        WHERE id = $user_id");
    
    if ($update) {
        $message = "<p style='color: green;'>Profile updated successfully!</p>";
        // Refresh user data
        $user_query = $conn->query("SELECT * FROM users WHERE id = $user_id");
        $user = $user_query->fetch_assoc();
    } else {
        $message = "<p style='color: red;'>Error updating profile: " . $conn->error . "</p>";
    }
}

// Get created auctions (for sellers)
$auctions_query = $conn->query("
    SELECT a.*, 
           (SELECT MAX(bid_amount) FROM bids WHERE auction_id = a.id) as highest_bid,
           (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count
    FROM auctions a 
    WHERE a.seller_id = $user_id
    ORDER BY a.created_at DESC
");

// Get bids placed by the user
$bids_query = $conn->query("
    SELECT b.*, a.title, a.end_time, a.status,
           (SELECT MAX(bid_amount) FROM bids WHERE auction_id = b.auction_id) as current_highest
    FROM bids b
    JOIN auctions a ON b.auction_id = a.id
    WHERE b.bidder_id = $user_id
    ORDER BY b.bid_time DESC
");

// Get winning bids
$winning_bids_query = $conn->query("
    SELECT a.*, b.bid_amount
    FROM auctions a
    JOIN bids b ON a.id = b.auction_id
    WHERE b.bidder_id = $user_id
    AND b.bid_amount = (SELECT MAX(bid_amount) FROM bids WHERE auction_id = a.id)
    AND a.end_time < NOW()
    AND a.status = 'active'
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .user-info {
            margin-left: 20px;
        }
        
        .tabs {
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            outline: none;
        }
        
        .tab-btn.active {
            border-bottom: 3px solid #007bff;
            color: #007bff;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .auction-list {
            margin-top: 20px;
        }
        
        .auction-item {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            padding: 15px;
            display: flex;
        }
        
        .auction-item img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        .auction-details {
            margin-left: 15px;
            flex: 1;
        }
        
        .bid-status {
            font-weight: bold;
        }
        
        .winning {
            color: green;
        }
        
        .outbid {
            color: red;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            color: white;
        }
        
        .status-active {
            background-color: #28a745;
        }
        
        .status-completed {
            background-color: #6c757d;
        }
        
        .status-cancelled {
            background-color: #dc3545;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .credit-display {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        
        .credit-score-badge, .sustainability-badge {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 5px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .credit-score-badge {
            border-left: 4px solid #28a745;
        }
        
        .sustainability-badge {
            border-left: 4px solid #17a2b8;
        }
        
        .score {
            font-size: 1.5em;
            font-weight: bold;
            color: #333;
        }
        
        .score-label {
            font-size: 0.8em;
            color: #666;
        }
        
        .new-seller-badge {
            background: #17a2b8;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            display: inline-block;
        }
        
        .credit-score-overview {
            display: flex;
            justify-content: space-around;
            text-align: center;
            margin: 20px 0;
        }
        
        .score-metric {
            padding: 15px;
        }
        
        .metric-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #28a745;
        }
        
        .metric-label {
            font-size: 0.9em;
            color: #666;
        }
        
        .specialization-list {
            margin-top: 20px;
        }
        
        .specialization-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .spec-category {
            flex: 0 0 150px;
            font-weight: bold;
        }
        
        .spec-level {
            flex: 1;
            height: 20px;
            background: #eee;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .spec-bar {
            height: 100%;
            background: #28a745;
            border-radius: 10px;
        }
        
        .spec-count {
            flex: 0 0 80px;
            text-align: right;
            color: #666;
        }
        
        .ratings-list {
            margin-top: 20px;
        }
        
        .rating-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .rating-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .rating-auction {
            font-weight: bold;
        }
        
        .rating-date {
            color: #666;
            font-size: 0.9em;
        }
        
        .rating-stars {
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        .star {
            color: #ddd;
        }
        
        .star.filled {
            color: #ffc107;
        }
        
        .rating-comment {
            font-style: italic;
            margin-bottom: 10px;
            color: #555;
        }
        
        .rating-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.9em;
        }
        
        .rating-metrics {
            display: flex;
            gap: 15px;
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

<div class="profile-container">
    <div class="profile-header">
        <div class="user-avatar">
            <img src="https://via.placeholder.com/100" alt="User Avatar" style="border-radius: 50%;">
        </div>
        <div class="user-info">
            <h1><?= htmlspecialchars($user['username']) ?></h1>
            <p>Account type: <?= ucfirst($user['user_type']) ?></p>
            <p>Email: <?= htmlspecialchars($user['email']) ?></p>
            <p>Member since: <?= date('F j, Y', strtotime($user['created_at'])) ?></p>
            <?php if ($user['user_type'] == 'seller'): ?>
                <div class="credit-display">
                    <div class="credit-score-badge">
                        <span class="score"><?= round($user['credit_score']) ?></span>
                        <span class="score-label">Credit Score</span>
                    </div>
                    <div class="sustainability-badge">
                        <span class="score"><?= round($user['sustainability_impact']) ?></span>
                        <span class="score-label">Sustainability Impact</span>
                    </div>
                    <?php if ($user['is_new_seller']): ?>
                        <div class="new-seller-badge">New Seller</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?= $message ?>
    
    <div class="tabs">
        <button class="tab-btn active" onclick="openTab('profile')">Profile Info</button>
        <button class="tab-btn" onclick="openTab('my-auctions')">My Auctions</button>
        <button class="tab-btn" onclick="openTab('my-bids')">My Bids</button>
        <button class="tab-btn" onclick="openTab('won-auctions')">Won Auctions</button>
        <?php if ($user['user_type'] == 'seller'): ?>
            <button class="tab-btn" onclick="openTab('credit-score')">Credit & Ratings</button>
        <?php endif; ?>
    </div>
    
    <div id="profile" class="tab-content active">
        <div class="card">
            <h2>Profile Information</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="company_name">Company Name</label>
                    <input type="text" id="company_name" name="company_name" value="<?= htmlspecialchars($user['company_name'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                </div>
                
                <button type="submit" name="update_profile">Update Profile</button>
            </form>
        </div>
    </div>
    
    <div id="my-auctions" class="tab-content">
        <h2>My Auctions</h2>
        
        <?php if($auctions_query->num_rows > 0): ?>
            <div class="auction-list">
                <?php while($auction = $auctions_query->fetch_assoc()): ?>
                    <div class="auction-item">
                        <img src="<?= htmlspecialchars($auction['image_path']) ?>" alt="Auction Image">
                        <div class="auction-details">
                            <h3><?= htmlspecialchars($auction['title']) ?></h3>
                            <p>
                                <span class="status-badge status-<?= $auction['status'] ?>"><?= ucfirst($auction['status']) ?></span>
                                <?= $auction['quantity'] ?> <?= htmlspecialchars($auction['unit']) ?>
                            </p>
                            <p>Starting Price: ₹<?= $auction['start_price'] ?></p>
                            <p>Current Highest Bid: ₹<?= $auction['highest_bid'] ?? $auction['start_price'] ?></p>
                            <p>Bids: <?= $auction['bid_count'] ?></p>
                            <p>End Time: <?= date('F j, Y, g:i a', strtotime($auction['end_time'])) ?></p>
                            
                            <?php if(strtotime($auction['end_time']) < time()): ?>
                                <p>Auction has ended</p>
                                <form method="POST" action="close_auction.php">
                                    <input type="hidden" name="auction_id" value="<?= $auction['id'] ?>">
                                    <button type="submit" name="mark_completed">Mark as Completed</button>
                                </form>
                            <?php else: ?>
                                <p>Time Remaining: <span class="timer" data-end_time="<?= $auction['end_time'] ?>">Loading...</span></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>You haven't created any auctions yet</h3>
                <p>Get started by <a href="create_auction.php">creating your first auction</a></p>
            </div>
        <?php endif; ?>
    </div>
    
    <div id="my-bids" class="tab-content">
        <h2>My Bids</h2>
        
        <?php if($bids_query->num_rows > 0): ?>
            <div class="auction-list">
                <?php while($bid = $bids_query->fetch_assoc()): ?>
                    <div class="auction-item">
                        <div class="auction-details">
                            <h3><?= htmlspecialchars($bid['title']) ?></h3>
                            <p>Your Bid: ₹<?= $bid['bid_amount'] ?></p>
                            <p>Current Highest Bid: ₹<?= $bid['current_highest'] ?></p>
                            <p>Bid Time: <?= date('F j, Y, g:i a', strtotime($bid['bid_time'])) ?></p>
                            
                            <?php if($bid['bid_amount'] == $bid['current_highest']): ?>
                                <p class="bid-status winning">You are the highest bidder</p>
                            <?php else: ?>
                                <p class="bid-status outbid">You have been outbid</p>
                            <?php endif; ?>
                            
                            <?php if(strtotime($bid['end_time']) > time() && $bid['status'] == 'active'): ?>
                                <p>Auction ends: <?= date('F j, Y, g:i a', strtotime($bid['end_time'])) ?></p>
                                <a href="auctions.php?auction_id=<?= $bid['auction_id'] ?>" class="btn">View Auction</a>
                            <?php else: ?>
                                <p>Auction has ended</p>
                                <?php 
                                // Check if this user won the auction (highest bidder)
                                if ($bid['bid_amount'] == $bid['current_highest'] && $bid['status'] == 'active') {
                                    // Check if user has already rated this auction
                                    $check_rating = $conn->query("SELECT id FROM ratings WHERE auction_id = " . $bid['auction_id'] . " AND rater_id = $user_id");
                                    if ($check_rating->num_rows == 0) {
                                        echo '<a href="close_auction.php?rate=1&auction_id=' . $bid['auction_id'] . '" class="btn">Rate Seller</a>';
                                    } else {
                                        echo '<p>You have already rated this seller</p>';
                                    }
                                }
                                ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>You haven't placed any bids yet</h3>
                <p>Check out <a href="auctions.php">available auctions</a> to start bidding</p>
            </div>
        <?php endif; ?>
    </div>
    
    <div id="won-auctions" class="tab-content">
        <h2>Won Auctions</h2>
        
        <?php if($winning_bids_query->num_rows > 0): ?>
            <div class="auction-list">
                <?php while($won = $winning_bids_query->fetch_assoc()): ?>
                    <div class="auction-item">
                        <img src="<?= htmlspecialchars($won['image_path']) ?>" alt="Auction Image">
                        <div class="auction-details">
                            <h3><?= htmlspecialchars($won['title']) ?></h3>
                            <p>Category: <?= htmlspecialchars($won['category']) ?></p>
                            <p><?= $won['quantity'] ?> <?= htmlspecialchars($won['unit']) ?></p>
                            <p>Winning Bid: ₹<?= $won['bid_amount'] ?></p>
                            <p>Auction ended: <?= date('F j, Y, g:i a', strtotime($won['end_time'])) ?></p>
                            
                            <!-- Contact seller button -->
                            <button onclick="contactSeller(<?= $won['seller_id'] ?>, <?= $won['id'] ?>)">Contact Seller</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>You haven't won any auctions yet</h3>
                <p>Keep bidding to win industrial waste materials!</p>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($user['user_type'] == 'seller'): ?>
        <div id="credit-score" class="tab-content">
            <h2>Seller Ratings & Specializations</h2>
            
            <div class="card">
                <h3>Credit Score Overview</h3>
                <div class="credit-score-overview">
                    <div class="score-metric">
                        <div class="metric-value"><?= round($user['credit_score']) ?></div>
                        <div class="metric-label">Total Credit Score</div>
                    </div>
                    <div class="score-metric">
                        <div class="metric-value"><?= round($user['sustainability_impact']) ?></div>
                        <div class="metric-label">Sustainability Impact</div>
                    </div>
                    <div class="score-metric">
                        <?php 
                        // Count total specializations
                        $spec_query = $conn->query("SELECT COUNT(*) as total, SUM(specialization_count) as sum 
                                                  FROM material_specializations 
                                                  WHERE user_id = $user_id");
                        $spec_data = $spec_query->fetch_assoc();
                        ?>
                        <div class="metric-value"><?= $spec_data['total'] ?? 0 ?></div>
                        <div class="metric-label">Material Specializations</div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <h3>Material Specializations</h3>
                <?php
                $specializations = $conn->query("SELECT category, specialization_count 
                                               FROM material_specializations 
                                               WHERE user_id = $user_id 
                                               ORDER BY specialization_count DESC");
                
                if ($specializations->num_rows > 0):
                ?>
                    <div class="specialization-list">
                        <?php while($spec = $specializations->fetch_assoc()): ?>
                            <div class="specialization-item">
                                <div class="spec-category"><?= htmlspecialchars($spec['category']) ?></div>
                                <div class="spec-level">
                                    <div class="spec-bar" style="width: <?= min(100, $spec['specialization_count'] * 10) ?>%"></div>
                                </div>
                                <div class="spec-count"><?= $spec['specialization_count'] ?> sales</div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p>You haven't specialized in any material categories yet. Complete more sales to build your specializations.</p>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h3>Recent Ratings</h3>
                <?php
                $ratings = $conn->query("SELECT r.*, a.title as auction_title, u.username as rater_name 
                                       FROM ratings r
                                       JOIN auctions a ON r.auction_id = a.id
                                       JOIN users u ON r.rater_id = u.id
                                       WHERE r.seller_id = $user_id
                                       ORDER BY r.created_at DESC
                                       LIMIT 5");
                
                if ($ratings->num_rows > 0):
                ?>
                    <div class="ratings-list">
                        <?php while($rating = $ratings->fetch_assoc()): ?>
                            <div class="rating-item">
                                <div class="rating-header">
                                    <div class="rating-auction"><?= htmlspecialchars($rating['auction_title']) ?></div>
                                    <div class="rating-date"><?= date('M j, Y', strtotime($rating['created_at'])) ?></div>
                                </div>
                                <div class="rating-stars">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rating['rating']) {
                                            echo '<span class="star filled">★</span>';
                                        } else {
                                            echo '<span class="star">☆</span>';
                                        }
                                    }
                                    ?>
                                </div>
                                <?php if (!empty($rating['comment'])): ?>
                                    <div class="rating-comment">"<?= htmlspecialchars($rating['comment']) ?>"</div>
                                <?php endif; ?>
                                <div class="rating-details">
                                    <div class="rating-user">by <?= htmlspecialchars($rating['rater_name']) ?></div>
                                    <div class="rating-metrics">
                                        <span>Material: <?= $rating['material_consistency'] ?>/5</span>
                                        <span>Sustainability: <?= $rating['sustainability_rating'] ?>/5</span>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p>You haven't received any ratings yet.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    function openTab(tabName) {
        const tabs = document.getElementsByClassName('tab-content');
        for (let i = 0; i < tabs.length; i++) {
            tabs[i].classList.remove('active');
        }
        
        const buttons = document.getElementsByClassName('tab-btn');
        for (let i = 0; i < buttons.length; i++) {
            buttons[i].classList.remove('active');
        }
        
        document.getElementById(tabName).classList.add('active');
        event.currentTarget.classList.add('active');
    }
    
    function contactSeller(sellerId, auctionId) {
        // You can implement messaging functionality here
        alert('Messaging functionality will be implemented soon!');
    }
    
    // Countdown function
    function startCountdown(timerElement, end_timeStr) {
        const end_time = new Date(end_timeStr).getTime();

        function updateTimer() {
            const now = new Date().getTime();
            const diff = end_time - now;

            if (diff <= 0) {
                timerElement.innerText = 'Auction ended';
                timerElement.style.color = 'red';
                clearInterval(intervalId);
                return;
            }

            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

            let timeText = '';
            if (days > 0) {
                timeText = `${days}d ${hours}h`;
            } else if (hours > 0) {
                timeText = `${hours}h ${minutes}m`;
            } else {
                timeText = `${minutes}m ${seconds}s`;
                timerElement.style.color = diff < 300000 ? 'red' : ''; // Red for last 5 minutes
            }

            timerElement.innerText = timeText;
        }

        updateTimer();
        const intervalId = setInterval(updateTimer, 1000);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const timers = document.querySelectorAll('.timer');
        timers.forEach(timer => {
            const end_time = timer.getAttribute('data-end_time');
            if (end_time) {
                startCountdown(timer, end_time);
            }
        });
    });
</script>

</body>
</html> 