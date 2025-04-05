<?php
require 'db.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Auctions</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 10;
            padding-top: 60px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 30px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 10px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .modal-content img {
            max-width: 100%;
            border-radius: 10px;
        }

        .modal-content input {
            margin-top: 10px;
            padding: 8px;
            width: 100%;
        }

        .modal-content button {
            margin-top: 10px;
            padding: 10px;
            width: 100%;
        }

        .auction-item {
            cursor: pointer;
        }
    </style>
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

<h2 style="text-align: center;">Live Auctions</h2>
<div class="auction-grid">

<?php
$result = $conn->query("SELECT * FROM auctions ORDER BY created_at DESC");

while ($row = $result->fetch_assoc()) {
    $id = $row['id'];
    $title = htmlspecialchars($row['title']);
    $description = htmlspecialchars($row['description']);
    $start_price = $row['start_price'];
    $end_time = $row['end_time'];
    $image_path = htmlspecialchars($row['image_path']);

    // Get highest bid
    $bid_result = $conn->query("SELECT MAX(bid_amount) AS highest_bid FROM bids WHERE auction_id = $id");
    $highest_bid = $bid_result && $bid_result->num_rows > 0 ? $bid_result->fetch_assoc()['highest_bid'] : null;
    $highest_bid = $highest_bid ? $highest_bid : $start_price;

    echo "
    <div class='auction-item' 
         data-id='$id'
         data-title=\"$title\"
         data-description=\"$description\"
         data-start_price=\"$start_price\"
         data-highest_bid=\"$highest_bid\"
         data-end_time=\"$end_time\"
         data-image=\"$image_path\"
         onclick='openModal(this)'>
        <img src='$image_path' alt='Auction Image'>
        <h3>$title</h3>
        <p>$description</p>
        <p><strong>Starting Price:</strong> ₹$start_price</p>
        <p><strong>Current Bid:</strong> ₹<span id='card-bid-$id'>$highest_bid</span></p>
        <p><strong>Time Left:</strong> <span class='timer' data-end_time='$end_time' id='timer-$id'>Loading...</span></p>
        <a href='#' class='btn'>Bid Now</a>
    </div>
    ";
}
?>

</div>

<!-- Modal Popup -->
<div id="auctionModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <img id="modalImage" src="" alt="Auction Image" />
        <h2 id="modalTitle"></h2>
        <p id="modalDescription"></p>
        <p><strong>Starting Price:</strong> ₹<span id="modalPrice"></span></p>
        <p><strong>Current Highest Bid:</strong> ₹<span id="modalHighestBid"></span></p>
        <p><strong>Ends on:</strong> <span id="modalDeadline"></span></p>
        
        <input type="number" id="bidAmount" placeholder="Enter your bid" />
        <button onclick="placeBid()">Place Bid</button>
        <p id="bidMessage"></p>
    </div>
</div>

<script>
    function startCountdown(timerElement, end_timeStr) {
        const end_time = new Date(end_timeStr).getTime();

        function updateTimer() {
            const now = new Date().getTime();
            const diff = end_time - now;

            if (diff <= 0) {
                timerElement.innerText = 'Auction has ended';
                clearInterval(intervalId);
                return;
            }

            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

            timerElement.innerText =
                `${days}d ${hours}h ${minutes}m ${seconds}s`;
        }

        updateTimer(); // initial run
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
        document.getElementById('modalTitle').innerText = element.dataset.title;
        document.getElementById('modalDescription').innerText = element.dataset.description;
        document.getElementById('modalPrice').innerText = element.dataset.start_price;
        document.getElementById('modalHighestBid').innerText = element.dataset.highest_bid;
        document.getElementById('modalDeadline').innerText = new Date(element.dataset.end_time).toLocaleString();
        document.getElementById('modalImage').src = element.dataset.image;

        document.getElementById('bidAmount').value = '';
        document.getElementById('bidMessage').innerText = '';
        document.getElementById('bidAmount').dataset.auction_id = element.dataset.id;

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
            if (xhr.responseText === "success") {
                message.innerText = 'Bid placed successfully!';
                message.style.color = 'green';
                document.getElementById('modalHighestBid').innerText = bid.toFixed(2);
                const cardBidSpan = document.getElementById('card-bid-' + auctionId);
                if (cardBidSpan) {
                    cardBidSpan.innerText = bid.toFixed(2);
                }

            } else if (xhr.responseText === "lowbid") {
                message.innerText = 'Your bid must be higher than the current bid!';
                message.style.color = 'red';
            } else {
                message.innerText = 'Something went wrong.';
                message.style.color = 'red';
            }
        }
    };

    xhr.send(`auction_id=${auctionId}&bid_amount=${bid}`);
    }

</script>

</body>
</html>
