<?php
require_once '../php/auth.php';
startSecureSession();
if (isLoggedIn()) { header('Location: /rental_risk/index.php'); exit; }

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $role     = $_POST['role'] ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $error = 'All fields are required.';
    } elseif (!in_array($role, ['tenant', 'landlord'])) {
        $error = 'Invalid role selected.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        $result = registerUser($username, $email, $password, $role);
        if ($result['success']) {
            if ($role === 'tenant') {
                // Auto-login and redirect to fill profile
                $_SESSION['user_id']  = $result['user_id'];
                $_SESSION['username'] = $username;
                $_SESSION['role']     = $role;
                header('Location: tenant_profile.php?new=1');
                exit;
            } else {
                $success = 'Landlord account created! You can now log in.';
            }
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Rental Risk System</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<div class="page-center">
    <div class="auth-box">
        <div class="auth-header">
            <h1><img src="/rental_risk/images/user.png" class="heading-icon"> Create Account</h1>
            <p>Register as a Tenant or Landlord</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" id="registerForm">
            <div class="form-group">
                <label>Register As</label>
                <div class="role-select">
                    <label class="role-option">
                        <input type="radio" name="role" value="tenant"
                               <?= ($_POST['role'] ?? '') === 'tenant' ? 'checked' : '' ?>>
                        <span>🧑 Tenant</span>
                    </label>
                    <label class="role-option">
                        <input type="radio" name="role" value="landlord"
                               <?= ($_POST['role'] ?? '') === 'landlord' ? 'checked' : '' ?>>
                        <span>🏘️ Landlord</span>
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" minlength="6" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Create Account</button>
        </form>

        <div class="auth-footer">
            <p>Already have an account? <a href="../index.php">Login here</a></p>
        </div>
    </div>
</div>
<script src="../js/script.js"></script>
</body>
</html>
