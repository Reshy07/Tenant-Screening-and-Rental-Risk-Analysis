<?php
require_once 'php/auth.php';
startSecureSession();

if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? null;
    if ($role === 'tenant')   { header('Location: pages/tenant_dashboard.php'); exit; }
    if ($role === 'landlord') { header('Location: pages/landlord_dashboard.php'); exit; }
    if ($role === 'admin')    { header('Location: pages/admin_dashboard.php'); exit; }
}

$msg = '';
if (isset($_GET['msg'])) {
    $msg = htmlspecialchars($_GET['msg']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrueTenant</title>
    <link rel="stylesheet" href="/rental_risk/css/styles.css?v=<?= filemtime(__DIR__ . '/css/styles.css') ?>">
</head>
<body>
<div class="landing-shell">
    <div class="landing-card">
        <div class="landing-copy">
            <div class="landing-badge">TrueTenant</div>
            <div class="landing-kicker">Rental screening made clear and organized</div>
            <h1>Manage tenant screening from one structured workspace.</h1>
            <p>
                TrueTenant helps landlords review applications and helps tenants keep their profile, preferences,
                and applications in one place. Start from the dashboard that fits your role.
            </p>

            <?php if ($msg): ?>
                <div class="alert alert-success"><?= $msg ?></div>
            <?php endif; ?>

            <div class="landing-actions">
                <a href="login.php" class="btn btn-primary">Login</a>
                <a href="pages/register.php" class="btn btn-outline">Create Account</a>
            </div>

            <div class="landing-stats">
                <div class="landing-stat">
                    <strong>Tenant Profiles</strong>
                    <span>Store screening details and preferences in one place.</span>
                </div>
                <div class="landing-stat">
                    <strong>Application Tracking</strong>
                    <span>Follow each rental application from review to final decision.</span>
                </div>
                <div class="landing-stat">
                    <strong>Risk-Based Decisions</strong>
                    <span>Use system insights to support approval and rejection decisions.</span>
                </div>
            </div>

            <div class="landing-points">
                <div class="landing-point">
                    <h2>For Landlords</h2>
                    <p>Review applicants, compare risk results, and manage property approvals with a cleaner workflow.</p>
                </div>
                <div class="landing-point">
                    <h2>For Tenants</h2>
                    <p>Create a profile, browse available properties, and keep track of every submitted application.</p>
                </div>
            </div>
        </div>

        <div class="landing-side">
            <div class="landing-panel">
                <h2>Get Started</h2>
                <p>Choose the next step that matches your access level.</p>
                <div class="landing-steps">
                    <div class="landing-step">
                        <span>1</span>
                        <div>
                            <strong>Sign in</strong>
                            <p>Access your tenant, landlord, or admin workspace.</p>
                        </div>
                    </div>
                    <div class="landing-step">
                        <span>2</span>
                        <div>
                            <strong>Complete your setup</strong>
                            <p>Create your account if you are new to TrueTenant.</p>
                        </div>
                    </div>
                </div>
                <a href="login.php" class="btn btn-primary btn-full">Go to Login</a>
                <a href="pages/register.php" class="btn btn-secondary btn-full">Register Now</a>
            </div>

            <div class="landing-panel landing-panel-soft">
                <h2>Demo Access</h2>
                <p>Password for every demo account: <code>password</code></p>
                <ul class="landing-demo-list">
                    <li><strong>Admin:</strong> admin</li>
                    <li><strong>Landlord:</strong> landlord_ram</li>
                    <li><strong>Tenant:</strong> tenant_hari</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<script src="/rental_risk/js/script.js"></script>
</body>
</html>
