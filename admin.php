<?php
require 'db.php';
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=admin.php");
    exit;
}

// Verify admin status
$user_id = $_SESSION['user_id'];
$admin_check = $conn->query("SELECT user_type FROM users WHERE id = $user_id");
$user_data = $admin_check->fetch_assoc();

if ($user_data['user_type'] !== 'admin') {
    header("Location: index.html");
    exit;
}

// Handle form submissions
$message = '';

// Delete auction
if (isset($_POST['delete_auction'])) {
    $auction_id = intval($_POST['auction_id']);
    // Delete associated bids first
    $conn->query("DELETE FROM bids WHERE auction_id = $auction_id");
    // Then delete the auction
    if ($conn->query("DELETE FROM auctions WHERE id = $auction_id")) {
        $message = "<p class='success'>Auction deleted successfully</p>";
    } else {
        $message = "<p class='error'>Error deleting auction: " . $conn->error . "</p>";
    }
}

// Ban user
if (isset($_POST['ban_user'])) {
    $ban_user_id = intval($_POST['user_id']);
    // Here you could implement a ban system, for now we'll just delete the user
    // Delete user's bids first
    $conn->query("UPDATE bids SET bidder_id = NULL WHERE bidder_id = $ban_user_id");
    // Delete user's auctions
    $conn->query("UPDATE auctions SET seller_id = NULL WHERE seller_id = $ban_user_id");
    // Then delete the user
    if ($conn->query("DELETE FROM users WHERE id = $ban_user_id")) {
        $message = "<p class='success'>User removed successfully</p>";
    } else {
        $message = "<p class='error'>Error removing user: " . $conn->error . "</p>";
    }
}

// Get stats
$stats = [
    'users' => $conn->query("SELECT COUNT(*) AS count FROM users")->fetch_assoc()['count'],
    'auctions' => $conn->query("SELECT COUNT(*) AS count FROM auctions")->fetch_assoc()['count'],
    'active_auctions' => $conn->query("SELECT COUNT(*) AS count FROM auctions WHERE status = 'active'")->fetch_assoc()['count'],
    'completed_auctions' => $conn->query("SELECT COUNT(*) AS count FROM auctions WHERE status = 'completed'")->fetch_assoc()['count'],
    'bids' => $conn->query("SELECT COUNT(*) AS count FROM bids")->fetch_assoc()['count'],
    'total_value' => $conn->query("SELECT SUM(bid_amount) AS total FROM bids WHERE bid_amount = (SELECT MAX(bid_amount) FROM bids b WHERE b.auction_id = bids.auction_id)")->fetch_assoc()['total'] ?? 0
];

// Get recent auctions
$recent_auctions = $conn->query("
    SELECT a.*, u.username as seller_name 
    FROM auctions a 
    LEFT JOIN users u ON a.seller_id = u.id 
    ORDER BY a.created_at DESC 
    LIMIT 10
");

// Get recent users
$recent_users = $conn->query("
    SELECT * FROM users
    WHERE user_type != 'admin'
    ORDER BY created_at DESC
    LIMIT 10
");

// Get latest bids
$latest_bids = $conn->query("
    SELECT b.*, a.title as auction_title, u.username as bidder_name
    FROM bids b
    JOIN auctions a ON b.auction_id = a.id
    LEFT JOIN users u ON b.bidder_id = u.id
    ORDER BY b.bid_time DESC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel - WasteBidder</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0;
            color: #666;
        }
        
        .stat-card .value {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
            margin: 10px 0;
        }
        
        .data-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .data-section h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        table th {
            background: #f8f9fa;
        }
        
        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            color: white;
        }
        
        .delete-btn {
            background: #dc3545;
        }
        
        .view-btn {
            background: #17a2b8;
        }
        
        .ban-btn {
            background: #dc3545;
        }
        
        .success {
            color: green;
            font-weight: bold;
        }
        
        .error {
            color: red;
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
            <li><a href="logout.php">Logout</a></li>
            <li><a href="admin.php">Admin Panel</a></li>
        </ul>
    </nav>
</header>

<div class="admin-container">
    <h1>Admin Panel</h1>
    
    <?= $message ?>
    
    <div class="dashboard">
        <div class="stat-card">
            <h3>Total Users</h3>
            <div class="value"><?= $stats['users'] ?></div>
        </div>
        
        <div class="stat-card">
            <h3>Total Auctions</h3>
            <div class="value"><?= $stats['auctions'] ?></div>
        </div>
        
        <div class="stat-card">
            <h3>Active Auctions</h3>
            <div class="value"><?= $stats['active_auctions'] ?></div>
        </div>
        
        <div class="stat-card">
            <h3>Completed Auctions</h3>
            <div class="value"><?= $stats['completed_auctions'] ?></div>
        </div>
        
        <div class="stat-card">
            <h3>Total Bids</h3>
            <div class="value"><?= $stats['bids'] ?></div>
        </div>
        
        <div class="stat-card">
            <h3>Total Value</h3>
            <div class="value">₹<?= number_format($stats['total_value'], 2) ?></div>
        </div>
    </div>
    
    <div class="data-section">
        <h2>Recent Auctions</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Seller</th>
                    <th>Category</th>
                    <th>Start Price</th>
                    <th>Created</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($auction = $recent_auctions->fetch_assoc()): ?>
                    <tr>
                        <td><?= $auction['id'] ?></td>
                        <td><?= htmlspecialchars($auction['title']) ?></td>
                        <td><?= htmlspecialchars($auction['seller_name'] ?? 'Unknown') ?></td>
                        <td><?= htmlspecialchars($auction['category']) ?></td>
                        <td>₹<?= $auction['start_price'] ?></td>
                        <td><?= date('Y-m-d', strtotime($auction['created_at'])) ?></td>
                        <td><?= ucfirst($auction['status']) ?></td>
                        <td>
                            <form method="POST" style="display: inline">
                                <input type="hidden" name="auction_id" value="<?= $auction['id'] ?>">
                                <button type="submit" name="delete_auction" class="action-btn delete-btn">Delete</button>
                            </form>
                            <a href="auctions.php?auction_id=<?= $auction['id'] ?>" class="action-btn view-btn">View</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <div class="data-section">
        <h2>Recent Users</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Type</th>
                    <th>Company</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $recent_users->fetch_assoc()): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= ucfirst($user['user_type']) ?></td>
                        <td><?= htmlspecialchars($user['company_name'] ?? 'N/A') ?></td>
                        <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                        <td>
                            <form method="POST" style="display: inline">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <button type="submit" name="ban_user" class="action-btn ban-btn" onclick="return confirm('Are you sure you want to remove this user?')">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    
    <div class="data-section">
        <h2>Recent Bids</h2>
        <table>
            <thead>
                <tr>
                    <th>Auction</th>
                    <th>Bidder</th>
                    <th>Amount</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($bid = $latest_bids->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($bid['auction_title']) ?></td>
                        <td><?= htmlspecialchars($bid['bidder_name'] ?? 'Unknown') ?></td>
                        <td>₹<?= $bid['bid_amount'] ?></td>
                        <td><?= date('Y-m-d H:i:s', strtotime($bid['bid_time'])) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html> 