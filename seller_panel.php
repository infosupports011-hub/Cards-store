<?php
session_start();
require_once 'db.php';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_seller'])) {
    $name = trim($_POST['name']);
    $nickname = trim($_POST['nickname']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);

    try {
        $stmt = $pdo->prepare("INSERT INTO sellers (name, nickname, username, email, rating_score, status, is_approved, created_at) VALUES (?, ?, ?, ?, 3.00, 'active', 0, NOW())");
        $stmt->execute([$name, $nickname, $username, $email]);
        $msg = "Seller registration submitted! Your nickname and ratings will be displayed to buyers upon approval.";
    } catch (PDOException $e) {
        $msg = "Username or Email already taken.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Seller Registration</title>
    <style>
        body { background: #0d1b2a; color: #fff; font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: #1b263b; padding: 30px; border-radius: 8px; width: 380px; border: 1px solid #415a77; }
        input { width: 100%; padding: 10px; margin: 10px 0; background: #0d1b2a; border: 1px solid #415a77; color: #fff; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #38b000; border: none; color: white; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #2d8a00; }
        .msg { color: #ffb703; font-size: 13px; text-align: center; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Seller Registration</h2>
        <?php if (!empty($msg)) echo "<div class='msg'>$msg</div>"; ?>
        <form method="POST">
            <input type="text" name="name" placeholder="Real Full Name (Hidden)" required>
            <input type="text" name="nickname" placeholder="Public Display Nickname" required>
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email Address" required>
            <button type="submit" name="register_seller">Register as Seller</button>
        </form>
    </div>
</body>
</html>
