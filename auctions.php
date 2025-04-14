<?php
require 'db.php';
session_start();

// Get filter parameters
$category = isset($_GET['category']) ? $_GET['category'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter_by = isset($_GET['filter_by']) ? $_GET['filter_by'] : '';

// Build the query
$query = "SELECT a.*, u.username as seller_name, u.id as seller_id, u.credit_score, u.sustainability_impact, u.is_new_seller  
          FROM auctions a 
          LEFT JOIN users u ON a.seller_id = u.id 
          WHERE a.status = 'active'";

if (!empty($category)) {
    $category = mysqli_real_escape_string($conn, $category);
    $query .= " AND a.category = '$category'";
}

if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND (a.title LIKE '%$search%' OR a.description LIKE '%$search%')";
}

// Sorting
switch ($sort) {
    case 'price_low':
        $query .= " ORDER BY a.start_price ASC";
        break;
    case 'price_high':
        $query .= " ORDER BY a.start_price DESC";
        break;
    case 'ending_soon':
        $query .= " ORDER BY a.end_time ASC";
        break;
    case 'credit_high':
        $query .= " ORDER BY u.credit_score DESC";
        break;
    case 'sustainability':
        $query .= " ORDER BY u.sustainability_impact DESC";
        break;
    case 'new_sellers':
        $query .= " ORDER BY u.is_new_seller DESC, a.created_at DESC";
        break;
    case 'latest':
    default:
        $query .= " ORDER BY a.created_at DESC";
        break;
}

$result = $conn->query($query);

// Get categories for filter
$categoriesQuery = $conn->query("SELECT DISTINCT category FROM auctions ORDER BY category");
$categories = [];
while ($cat = $categoriesQuery->fetch_assoc()) {
    $categories[] = $cat['category'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Industrial Waste Auctions</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .filter-section {
            background: #f0f0f0;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        .filter-form select, .filter-form input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .filter-form button {
            padding: 8px 15px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .auction-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .auction-item {
            position: relative;
        }
        
        .auction-details {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }
        
        .auction-meta {
            font-size: 0.9em;
            color: #666;
        }
        
        .quantity-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            padding: 20px;
            box-sizing: border-box;
        }
        
        .modal-content {
            background-color: #fff;
            margin: 0 auto;
            max-width: 700px;
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.2);
            position: relative;
            top: 50%;
            transform: translateY(-50%);
            padding: 25px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-content img {
            width: 100%;
            max-height: 300px;
            object-fit: contain;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            transition: 0.3s;
        }
        
        .close:hover,
        .close:focus {
            color: #333;
            text-decoration: none;
            cursor: pointer;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .modal-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .no-results {
            text-align: center;
            padding: 40px;
            font-size: 1.2em;
            color: #666;
        }
        
        .new-seller-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #17a2b8;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            z-index: 2;
        }
        
        .seller-info {
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9em;
        }
        
        .credit-score {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .credit-stars {
            display: flex;
        }
        
        .star {
            color: #ddd;
        }
        
        .star.filled {
            color: #ffc107;
        }
        
        .seller-details {
            display: flex;
            justify-content: space-between;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin: 15px 0;
        }
        
        .seller-metrics {
            text-align: center;
        }
        
        .metric-value {
            font-size: 1.2em;
            font-weight: bold;
            color: #28a745;
        }
        
        .metric-label {
            font-size: 0.8em;
            color: #666;
        }

        /* Mobile responsive adjustments */
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                padding: 15px;
                margin: 0 auto;
            }
            
            .modal-info {
                grid-template-columns: 1fr;
            }
            
            .seller-details {
                flex-direction: column;
                gap: 10px;
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
    <h1>Industrial Waste Auctions</h1>
    
    <div class="filter-section">
        <form class="filter-form" method="GET">
            <div>
                <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
            </div>
            
            <div>
                <select name="category">
                    <option value="">All Categories</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $category == $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <select name="sort">
                    <option value="latest" <?= $sort == 'latest' ? 'selected' : '' ?>>Latest</option>
                    <option value="ending_soon" <?= $sort == 'ending_soon' ? 'selected' : '' ?>>Ending Soon</option>
                    <option value="price_low" <?= $sort == 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price_high" <?= $sort == 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                    <option value="credit_high" <?= $sort == 'credit_high' ? 'selected' : '' ?>>Highest Seller Rating</option>
                    <option value="sustainability" <?= $sort == 'sustainability' ? 'selected' : '' ?>>Most Sustainable</option>
                    <option value="new_sellers" <?= $sort == 'new_sellers' ? 'selected' : '' ?>>New Sellers</option>
                </select>
            </div>
            
            <div>
                <select name="filter_by">
                    <option value="" <?= $filter_by == '' ? 'selected' : '' ?>>All Sellers</option>
                    <option value="top_rated" <?= $filter_by == 'top_rated' ? 'selected' : '' ?>>Top Rated Sellers</option>
                    <option value="new_seller" <?= $filter_by == 'new_seller' ? 'selected' : '' ?>>New Sellers</option>
                    <option value="sustainable" <?= $filter_by == 'sustainable' ? 'selected' : '' ?>>Highest Sustainability</option>
                </select>
            </div>
            
            <button type="submit">Filter</button>
        </form>
    </div>

    <?php if ($result->num_rows > 0): ?>
        <div class="auction-grid">
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                $id = $row['id'];
                $title = htmlspecialchars($row['title']);
                $description = htmlspecialchars($row['description']);
                $start_price = $row['start_price'];
                $end_time = $row['end_time'];
                $image_path = htmlspecialchars($row['image_path']);
                $category = htmlspecialchars($row['category']);
                $quantity = $row['quantity'];
                $unit = htmlspecialchars($row['unit']);
                $seller_name = htmlspecialchars($row['seller_name']);

                // Get highest bid
                $bid_result = $conn->query("SELECT MAX(bid_amount) AS highest_bid, COUNT(*) as bid_count FROM bids WHERE auction_id = $id");
                $bid_data = $bid_result->fetch_assoc();
                $highest_bid = $bid_data['highest_bid'] ? $bid_data['highest_bid'] : $start_price;
                $bid_count = $bid_data['bid_count'];
                ?>

                <div class="auction-item" 
                    data-id="<?= $id ?>"
                    data-title="<?= $title ?>" 
                    data-description="<?= $description ?>" 
                    data-start_price="<?= $start_price ?>" 
                    data-highest_bid="<?= $highest_bid ?>" 
                    data-end_time="<?= $end_time ?>" 
                    data-image="<?= $image_path ?>"
                    data-category="<?= $category ?>"
                    data-quantity="<?= $quantity ?>"
                    data-unit="<?= $unit ?>"
                    data-seller="<?= $seller_name ?>"
                    data-seller_id="<?= $row['seller_id'] ?>"
                    data-credit_score="<?= $row['credit_score'] ?>"
                    data-sustainability="<?= $row['sustainability_impact'] ?>"
                    data-bid_count="<?= $bid_count ?>"
                    onclick="openModal(this)">
                    
                    <img src="<?= $image_path ?>" alt="<?= $title ?>">
                    <div class="quantity-badge"><?= $quantity ?> <?= $unit ?></div>
                    
                    <?php if($row['is_new_seller']): ?>
                        <div class="new-seller-badge">New Seller</div>
                    <?php endif; ?>
                    
                    <h3><?= $title ?></h3>
                    <p><?= substr($description, 0, 100) ?>...</p>
                    
                    <div class="auction-details">
                        <div>
                            <p><strong>Current Bid:</strong> ₹<span id="card-bid-<?= $id ?>"><?= $highest_bid ?></span></p>
                            <p class="auction-meta"><?= $bid_count ?> bids</p>
                        </div>
                        <div>
                            <p><span class="timer" data-end_time="<?= $end_time ?>" id="timer-<?= $id ?>">Loading...</span></p>
                            <p class="auction-meta"><?= $category ?></p>
                        </div>
                    </div>
                    
                    <div class="seller-info">
                        <div class="credit-score">
                            <span class="credit-label">Seller Rating:</span>
                            <div class="credit-stars">
                                <?php 
                                $stars = min(5, floor($row['credit_score'] / 20)); // Convert credit score to stars (0-5)
                                for ($i = 0; $i < 5; $i++) {
                                    if ($i < $stars) {
                                        echo '<span class="star filled">★</span>';
                                    } else {
                                        echo '<span class="star">☆</span>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="no-results">
            <h3>No auctions found matching your criteria</h3>
            <p>Try adjusting your filters or check back later for new listings</p>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Popup -->
<div id="auctionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle"></h2>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        
        <img id="modalImage" src="" alt="Auction Image" />
        
        <div class="modal-info">
            <div>
                <p><strong>Category:</strong> <span id="modalCategory"></span></p>
                <p><strong>Quantity:</strong> <span id="modalQuantity"></span> <span id="modalUnit"></span></p>
                <p><strong>Seller:</strong> <span id="modalSeller"></span></p>
            </div>
            <div>
                <p><strong>Starting Price:</strong> ₹<span id="modalPrice"></span></p>
                <p><strong>Current Highest Bid:</strong> ₹<span id="modalHighestBid"></span></p>
                <p><strong>Total Bids:</strong> <span id="modalBidCount"></span></p>
                <p><strong>Ends:</strong> <span id="modalDeadline"></span></p>
            </div>
        </div>
        
        <div class="seller-details">
            <div class="seller-metrics">
                <div class="metric-value" id="modalCreditScore">--</div>
                <div class="metric-label">Seller Rating</div>
            </div>
            <div class="seller-metrics">
                <div class="metric-value" id="modalSustainability">--</div>
                <div class="metric-label">Sustainability Impact</div>
            </div>
            <div class="seller-metrics">
                <div class="metric-value" id="modalSpecialization">--</div>
                <div class="metric-label">Material Expertise</div>
            </div>
        </div>
        
        <div>
            <h3>Description</h3>
            <p id="modalDescription"></p>
        </div>
        
        <?php if(isset($_SESSION['user_id'])): ?>
            <div>
                <h3>Place Your Bid</h3>
                <p>Enter an amount higher than the current bid</p>
                <div style="display: flex; gap: 10px;">
                    <input type="number" id="bidAmount" placeholder="Enter your bid" style="flex: 1;">
                    <button onclick="placeBid()">Place Bid</button>
                </div>
                <p id="bidMessage"></p>
            </div>
        <?php else: ?>
            <div>
                <p>Please <a href="login.php">login</a> to place a bid</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Countdown function
    function startCountdown(timerElement, end_timeStr) {
        const end_time = new Date(end_timeStr).getTime();
        const auctionId = timerElement.id.replace('timer-', '');

        function updateTimer() {
            const now = new Date().getTime();
            const diff = end_time - now;

            if (diff <= 0) {
                timerElement.innerText = 'Auction ended';
                timerElement.style.color = 'red';
                clearInterval(intervalId);

                // Disable bidding if modal is open for this auction
                const bidInput = document.getElementById('bidAmount');
                if (bidInput && bidInput.dataset.auction_id === auctionId) {
                    bidInput.disabled = true;
                    document.querySelector('.modal-content button').disabled = true;
                    document.getElementById('bidMessage').innerText = 'This auction has ended';
                    document.getElementById('bidMessage').style.color = 'red';
                }
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

    function openModal(element) {
        const endTime = new Date(element.dataset.end_time).getTime();
        const now = new Date().getTime();

        document.getElementById('modalTitle').innerText = element.dataset.title;
        document.getElementById('modalDescription').innerText = element.dataset.description;
        document.getElementById('modalPrice').innerText = element.dataset.start_price;
        document.getElementById('modalHighestBid').innerText = element.dataset.highest_bid;
        document.getElementById('modalDeadline').innerText = new Date(element.dataset.end_time).toLocaleString();
        document.getElementById('modalImage').src = element.dataset.image;
        document.getElementById('modalCategory').innerText = element.dataset.category;
        document.getElementById('modalQuantity').innerText = element.dataset.quantity;
        document.getElementById('modalUnit').innerText = element.dataset.unit;
        document.getElementById('modalSeller').innerText = element.dataset.seller;
        document.getElementById('modalBidCount').innerText = element.dataset.bid_count;
        document.getElementById('modalCreditScore').innerText = parseInt(element.dataset.credit_score);
        document.getElementById('modalSustainability').innerText = parseFloat(element.dataset.sustainability).toFixed(1);
        
        // Fetch specialization data
        const sellerId = element.dataset.seller_id;
        const category = element.dataset.category;
        
        // You could make an AJAX call here to get the seller's specialization level
        // For simplicity, we'll just use a placeholder value
        document.getElementById('modalSpecialization').innerText = "Medium";

        const bidInput = document.getElementById('bidAmount');
        if (bidInput) {
            const bidButton = document.querySelector('.modal-content button');
            const bidMessage = document.getElementById('bidMessage');

            bidInput.value = '';
            bidInput.dataset.auction_id = element.dataset.id;
            bidInput.min = parseFloat(element.dataset.highest_bid) + 0.01;

            if (now > endTime) {
                bidInput.disabled = true;
                bidButton.disabled = true;
                bidMessage.innerText = 'This auction has ended';
                bidMessage.style.color = 'red';
            } else {
                bidInput.disabled = false;
                bidButton.disabled = false;
                bidMessage.innerText = '';
            }
        }

        document.getElementById('auctionModal').style.display = 'block';
    }

    function closeModal() {
        document.getElementById('auctionModal').style.display = 'none';
    }

    function placeBid() {
        const bidInput = document.getElementById('bidAmount');
        const bid = parseFloat(bidInput.value);
        const auctionId = bidInput.dataset.auction_id;
        const currentHighest = parseFloat(document.getElementById('modalHighestBid').innerText);
        const message = document.getElementById('bidMessage');

        if (bidInput.disabled) {
            message.innerText = 'Bidding is closed.';
            message.style.color = 'red';
            return;
        }

        if (isNaN(bid) || bid <= currentHighest) {
            message.innerText = 'Bid must be higher than current bid!';
            message.style.color = 'red';
            return;
        }

        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'place_bid.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        xhr.onload = function () {
            if (xhr.status === 200) {
                const response = xhr.responseText;
                
                if (response === "success") {
                    message.innerText = 'Bid placed successfully!';
                    message.style.color = 'green';
                    document.getElementById('modalHighestBid').innerText = bid.toFixed(2);
                    document.getElementById('card-bid-' + auctionId).innerText = bid.toFixed(2);
                    
                    // Increment bid count
                    const bidCountElement = document.getElementById('modalBidCount');
                    bidCountElement.innerText = parseInt(bidCountElement.innerText) + 1;
                    
                } else if (response === "login_required") {
                    message.innerText = 'You must be logged in to place a bid';
                    message.style.color = 'red';
                    setTimeout(() => {
                        window.location.href = 'login.php?redirect=auctions.php';
                    }, 2000);
                } else if (response === "lowbid") {
                    message.innerText = 'Someone placed a higher bid just now. Try again!';
                    message.style.color = 'red';
                } else if (response === "seller_cannot_bid") {
                    message.innerText = 'You cannot bid on your own auction';
                    message.style.color = 'red';
                } else if (response === "auction_closed") {
                    message.innerText = 'This auction has ended';
                    message.style.color = 'red';
                    bidInput.disabled = true;
                } else {
                    message.innerText = 'An error occurred. Please try again.';
                    message.style.color = 'red';
                }
            }
        };

        xhr.onerror = function() {
            message.innerText = 'Network error. Please try again.';
            message.style.color = 'red';
        };

        xhr.send(`auction_id=${auctionId}&bid_amount=${bid}`);
    }

    // Close the modal when clicking outside of it
    window.onclick = function(event) {
        const modal = document.getElementById('auctionModal');
        if (event.target === modal) {
            closeModal();
        }
    }
</script>

</body>
</html>
