<?php
require 'db.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Auctions</title>
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

    echo "
    <div class='auction-item'>
        <img src='$image_path' alt='Auction Image'>
        <h3>$title</h3>
        <p>$description</p>
        <p><strong>Starting Price:</strong> ₹$start_price</p>
        <p><strong>Time Left:</strong> <span class='timer' data-end_time='$end_time' id='timer-$id'>Loading...</span></p>
        <a href='#' class='btn'>Bid Now</a>
    </div>
    ";
}
?>

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
</script>

</body>
</html>
