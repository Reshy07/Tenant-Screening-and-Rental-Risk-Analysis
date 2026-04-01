<?php
// ============================================================
// Admin Account Creator
// USE THIS ONCE to create your own admin, then DELETE this file
// Visit: http://localhost/rental_risk/genhash.php
// ============================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'php/config.php';

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } else {
        $db   = getDB();
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Check if username or email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username=? OR email=?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'Username or email already exists.';
            $stmt->close();
        } else {
            $stmt->close();
            $stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
            $stmt->bind_param("sss", $username, $email, $hash);
            if ($stmt->execute()) {
                $success = "Admin account created! Username: <strong>$username</strong><br>You can now <a href='index.php'>login here</a>.<br><br><strong style='color:red'>DELETE this file now: C:\\xampp\\htdocs\\rental_risk\\genhash.php</strong>";
            } else {
                $error = 'Failed to create account: ' . $db->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Admin Account</title>
    <style>
        body { font-family: Arial; max-width: 420px; margin: 60px auto; padding: 20px; }
        input { width: 100%; padding: 8px; margin-bottom: 12px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #2980b9; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 15px; }
        .error   { background: #fdecea; color: #c0392b; padding: 10px; border-radius: 4px; margin-bottom: 12px; }
        .success { background: #eafaf1; color: #1e8449; padding: 12px; border-radius: 4px; margin-bottom: 12px; }
        label { font-weight: bold; font-size: 13px; display: block; margin-bottom: 4px; }
        h2 { color: #1a3a5c; }
        .warning { background: #fef9e7; border: 1px solid #f39c12; padding: 10px; border-radius: 4px; margin-bottom: 16px; font-size: 13px; }
    </style>
</head>
<body>
    <h2>Create Admin Account</h2>
    <div class="warning">
        <strong>Security Notice:</strong> Delete this file immediately after creating your admin account.
    </div>

    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="success"><?= $success ?></div>
    <?php else: ?>
    <form method="POST">
        <label>Username</label>
        <input type="text" name="username" required>
        <label>Email</label>
        <input type="email" name="email" required>
        <label>Password</label>
        <input type="password" name="password" minlength="6" required>
        <button type="submit">Create Admin Account</button>
    </form>
    <?php endif; ?>
</body>
</html>
