<?php
require_once '../php/auth.php';
require_once '../php/config.php';
require_once '../php/risk_calculate.php';
startSecureSession();
requireRole('tenant');

$db      = getDB();
$user_id = $_SESSION['user_id'];
$msg     = htmlspecialchars($_GET['msg'] ?? '');
$error   = htmlspecialchars($_GET['error'] ?? '');

// Load tenant profile
$stmt = $db->prepare("SELECT * FROM tenants WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Load applications only if profile exists
$applications = [];
if ($profile) {
    $tenant_id = $profile['id'];
    $sql = "SELECT a.*, rs.weighted_score, rs.ml_probability, rs.final_risk, rs.is_first_time_renter
            FROM applications a
            LEFT JOIN risk_scores rs ON rs.application_id = a.id
            WHERE a.tenant_id = ?
            ORDER BY a.applied_date DESC";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $tenant_id);
    $stmt->execute();
    $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Count stats without arrow functions
$count_approved = 0;
$count_pending  = 0;
$count_rejected = 0;
foreach ($applications as $a) {
    if ($a['status'] === 'approved') $count_approved++;
    if ($a['status'] === 'pending')  $count_pending++;
    if ($a['status'] === 'rejected') $count_rejected++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Dashboard</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<?php include '../php/navbar.php'; ?>
<div class="container">
    <div class="dashboard-header">
        <h2><img src="/rental_risk/images/user.png" class="heading-icon"> Tenant Dashboard</h2>
        <p>Welcome, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></p>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

    <?php if (!$profile): ?>
        <div class="alert alert-info">
            <img src="/rental_risk/images/warning.png" class="inline-icon">
            You have not completed your profile yet.
            <a href="tenant_profile.php?new=1" class="btn btn-primary" style="margin-left:10px">Complete Profile Now</a>
        </div>
    <?php else: ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-num"><?= count($applications) ?></div>
            <div>My Applications</div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-num"><?= $count_approved ?></div>
            <div>Approved</div>
        </div>
        <div class="stat-card stat-yellow">
            <div class="stat-num"><?= $count_pending ?></div>
            <div>Pending</div>
        </div>
        <div class="stat-card stat-red">
            <div class="stat-num"><?= $count_rejected ?></div>
            <div>Rejected</div>
        </div>
    </div>

    <!-- Profile Summary -->
    <div class="card">
        <div class="card-header">
            <h3>My Profile</h3>
            <a href="tenant_profile.php" class="btn btn-sm btn-secondary">
                <img src="/rental_risk/images/pencil.png" class="inline-icon"> Edit
            </a>
        </div>
        <div class="info-grid">
            <div><strong>Full Name:</strong> <?= htmlspecialchars($profile['full_name']) ?></div>
            <div><strong>Age:</strong> <?= $profile['age'] ?></div>
            <div><strong>Contact:</strong> <?= htmlspecialchars($profile['contact']) ?></div>
            <div><strong>Monthly Income:</strong> NPR <?= number_format($profile['monthly_income'], 2) ?></div>
            <div><strong>Employment:</strong> <?= ucfirst(str_replace('_', ' ', $profile['employment_status'])) ?></div>
            <div><strong>Rental History:</strong> <?= $profile['rental_history_months'] ?> months</div>
        </div>
        <div style="margin-top:12px">
            <strong>Documents: </strong>
            <?php
            $docs = [
                'id_proof_path'         => 'ID Proof',
                'income_proof_path'     => 'Income Proof',
                'employment_proof_path' => 'Employment Letter',
            ];
            foreach ($docs as $col => $label):
            ?>
                <?php if (!empty($profile[$col])): ?>
                    <a href="../<?= htmlspecialchars($profile[$col]) ?>" target="_blank" class="btn btn-sm btn-outline">
                        <img src="/rental_risk/images/document.png" class="inline-icon"> <?= $label ?>
                    </a>
                <?php else: ?>
                    <span class="badge badge-warning">
                        <img src="/rental_risk/images/warning.png" class="inline-icon"> <?= $label ?> Missing
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <h3>Quick Actions</h3>
        <div class="btn-group">
            <a href="browse_properties.php" class="btn btn-primary">
                <img src="/rental_risk/images/building.png" class="inline-icon"> Browse Properties
            </a>
            <a href="tenant_preferences.php" class="btn btn-secondary">
                <img src="/rental_risk/images/search.png" class="inline-icon"> Set Preferences
            </a>
        </div>
    </div>

    <!-- Applications Table -->
    <div class="card">
        <h3>My Applications</h3>
        <?php if (empty($applications)): ?>
            <p class="text-muted">No applications yet.
                <a href="browse_properties.php">Browse properties and apply now.</a>
            </p>
        <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th><th>Property</th><th>Rent (NPR)</th>
                    <th>Status</th><th>Risk Score</th><th>Applied</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($applications as $i => $app):
                $final_risk = $app['final_risk'];
                $risk_info  = ($final_risk !== null) ? getRiskLabel($final_risk) : null;
            ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($app['property_note'] ?? '—') ?></td>
                    <td><?= number_format($app['monthly_rent'], 2) ?></td>
                    <td>
                        <span class="badge status-<?= $app['status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $app['status'])) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($risk_info): ?>
                            <span class="risk-badge <?= $risk_info['class'] ?>">
                                <?= number_format($final_risk, 3) ?> — <?= $risk_info['label'] ?>
                            </span>
                            <?php if ($app['is_first_time_renter']): ?>
                                <br><small class="warning-text">
                                    <img src="/rental_risk/images/warning.png" class="inline-icon"> First-time renter
                                </small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d M Y', strtotime($app['applied_date'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>
<script src="../js/script.js"></script>
</body>
</html>
