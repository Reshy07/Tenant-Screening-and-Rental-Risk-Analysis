<?php
require_once 'php/auth.php';
startSecureSession();

// Redirect already logged-in users
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? null;
    if ($role === 'tenant')   { header('Location: pages/tenant_dashboard.php'); exit; }
    if ($role === 'landlord') { header('Location: pages/landlord_dashboard.php'); exit; }
    if ($role === 'admin')    { header('Location: pages/admin_dashboard.php'); exit; }
}

$error = '';
$msg = '';

if (isset($_GET['error'])) $error = htmlspecialchars($_GET['error']);
if (isset($_GET['msg']))   $msg   = htmlspecialchars($_GET['msg']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter username and password.';
    } else {
        $result = loginUser($username, $password);
        if ($result['success']) {
            if ($result['role'] === 'tenant')   { header('Location: pages/tenant_dashboard.php'); exit; }
            if ($result['role'] === 'landlord') { header('Location: pages/landlord_dashboard.php'); exit; }
            if ($result['role'] === 'admin')    { header('Location: pages/admin_dashboard.php'); exit; }
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
    <title>Tenant Screening & Rental Risk Analysis System</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<div class="page-center">
    <div class="auth-box">
        <div class="auth-header">
            <h1><img src="/rental_risk/images/home.png" class="heading-icon"> Rental Risk Analysis</h1>
            <p>Tenant Screening System — Tribhuvan University CSIT Final Year Project</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($msg): ?>
            <div class="alert alert-success"><?= $msg ?></div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Login</button>
        </form>

        <div class="auth-footer">
            <p>Don't have an account?
                <a href="pages/register.php">Register as Tenant or Landlord</a>
            </p>
        </div>

        <!-- Demo credentials hint -->
        <div class="demo-box">
            <strong>Demo Credentials (password for all: <code>password</code>)</strong>
            <ul>
                <li>Admin: <code>admin</code></li>
                <li>Landlord: <code>landlord_ram</code></li>
                <li>Tenant: <code>tenant_hari</code></li>
            </ul>
        </div>
    </div>
</div>
<script src="js/script.js"></script>
</body>
</html>
