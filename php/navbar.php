<?php
// Shared navigation bar — included in all pages
startSecureSession();
$role     = $_SESSION['role'] ?? '';
$username = $_SESSION['username'] ?? '';
?>
<nav class="navbar">
    <div class="nav-brand">
        <img src="/rental_risk/images/home.png" class="nav-icon"> RentalRisk
    </div>
    <div class="nav-links">
        <?php if ($role === 'tenant'): ?>
            <a href="tenant_dashboard.php">Dashboard</a>
            <a href="browse_properties.php">Browse Properties</a>
            <a href="tenant_preferences.php">My Preferences</a>
            <a href="tenant_profile.php">My Profile</a>
        <?php elseif ($role === 'landlord'): ?>
            <a href="landlord_dashboard.php">Dashboard</a>
            <a href="manage_properties.php">My Properties</a>
        <?php elseif ($role === 'admin'): ?>
            <a href="admin_dashboard.php">Admin Panel</a>
        <?php endif; ?>
    </div>
    <div class="nav-user">
        <?php if ($username): ?>
            <span><img src="/rental_risk/images/user.png" class="nav-icon">
                <?= htmlspecialchars($username) ?> (<?= ucfirst($role) ?>)
            </span>
            <a href="/rental_risk/logout.php" class="btn btn-sm btn-danger">Logout</a>
        <?php endif; ?>
    </div>
</nav>
