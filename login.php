<?php
// Start the session
session_start();

// Database connection details
$host = 'localhost';
$dbname = 'ashtiric_precedence';
$username = 'ashtiric_pre_user';
$password = 'Precedence@2025';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle login form submission
if (isset($_POST['login'])) {
    $username = htmlspecialchars(trim($_POST['username']));  // Trim whitespace
    $password = trim($_POST['password']);  // Trim whitespace

    // Fetch user data from the database
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if user exists and password is correct
    if ($user && $password === $user['password']) {
        // Set session variable to track logged-in user
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];

        // Redirect to admin page after successful login
        header("Location: admin.php");
        exit;
    } else {
        $errorMessage = "Invalid username or password.";
    }
}

// If the user is already logged in, redirect to admin page
if (isset($_SESSION['user_id'])) {
    header("Location: admin.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f4f6f7;
            margin: 0;
        }
        .login-container {
            background-color: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 6px 24px rgba(0, 0, 0, 0.1);
            width: 300px;
        }
        .login-container h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .login-container label {
            display: block;
            margin-bottom: 8px;
        }
        .login-container input {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        .login-container button {
            width: 100%;
            padding: 12px;
            background-color: #27ae60;
            color: #fff;
            font-size: 16px;
            border: none;
            cursor: pointer;
            border-radius: 8px;
        }
        .login-container button:hover {
            background-color: #2ecc71;
        }
        .error-message {
            color: #e74c3c;
            background-color: #f8d7da;
            padding: 15px;
            text-align: center;
            margin-bottom: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>

    <div class="login-container">
        <h2>Login</h2>

        <?php if (isset($errorMessage)): ?>
            <div class="error-message"><?= $errorMessage ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>

            <button type="submit" name="login">Login</button>
        </form>
    </div>

</body>
</html>
