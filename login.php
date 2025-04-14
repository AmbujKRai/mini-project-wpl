<?php
require 'db.php';
session_start();

// Processing form when submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_type = $_POST['form_type'];
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    if ($form_type === 'register') {
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $check = $conn->query("SELECT id FROM users WHERE username = '$username' OR email = '$email'");
        if ($check->num_rows > 0) {
            $message = "⚠️ User already exists!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $conn->query("INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$hashed_password')");
            $message = "Registration successful! You can now log in.";
        }
    } elseif ($form_type === 'login') {
        $result = $conn->query("SELECT * FROM users WHERE username = '$username'");
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_type'] = $user['user_type'];
                header("Location: auctions.php");
                exit;
            }
        }
        $message = "Invalid login credentials!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login / Register</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: sans-serif;
            background: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .auth-box {
            background: white;
            padding: 30px;
            width: 350px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border-radius: 12px;
        }

        .auth-box h2 {
            text-align: center;
        }

        .auth-box form {
            display: flex;
            flex-direction: column;
        }

        .auth-box input {
            padding: 10px;
            margin: 10px 0;
        }

        .auth-box button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            border-radius: 6px;
        }

        .toggle-link {
            text-align: center;
            margin-top: 10px;
            cursor: pointer;
            color: #007bff;
        }

        .message {
            text-align: center;
            margin-top: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="auth-box">
    <h2 id="form-title">Login</h2>

    <?php if (isset($message)): ?>
        <div class="message"><?= $message ?></div>
    <?php endif; ?>

    <form id="authForm" method="POST">
        <input type="hidden" name="form_type" id="formType" value="login">

        <input type="text" name="username" id="username" placeholder="Username" required>
        <input type="email" name="email" id="email" placeholder="Email" style="display: none;">
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Submit</button>
    </form>

    <div class="toggle-link" onclick="toggleForm()">Don't have an account? Register</div>
</div>

<script>
    function toggleForm() {
        const formTitle = document.getElementById('form-title');
        const formType = document.getElementById('formType');
        const emailInput = document.getElementById('email');
        const toggleLink = document.querySelector('.toggle-link');

        if (formType.value === 'login') {
            formTitle.innerText = 'Register';
            formType.value = 'register';
            emailInput.style.display = 'block';
            toggleLink.innerText = 'Already have an account? Login';
        } else {
            formTitle.innerText = 'Login';
            formType.value = 'login';
            emailInput.style.display = 'none';
            toggleLink.innerText = "Don't have an account? Register";
        }
    }

    // Keep email field visible if registration failed
    <?php if (isset($_POST['form_type']) && $_POST['form_type'] === 'register'): ?>
        toggleForm();
    <?php endif; ?>
</script>

</body>
</html>
