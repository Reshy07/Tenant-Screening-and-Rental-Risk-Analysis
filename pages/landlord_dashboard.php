<?php
require_once '../php/auth.php';
require_once '../php/config.php';
require_once '../php/risk_calculate.php';
startSecureSession();
requireRole('landlord');

$db          = getDB();
$landlord_id = $_SESSION['user_id'];
$msg         = htmlspecialchars($_GET['msg'] ?? '');
$error       = htmlspecialchars($_GET['error'] ?? '');

// Load ALL applications (landlords see everyone so they can screen)
$sql = "SELECT a.id AS app_id, a.property_note, a.monthly_rent, a.status, a.applied_date,
               t.full_name, t.age, t.monthly_income, t.employment_status, t.rental_history_months,
               t.reference_text, t.id_proof_path, t.income_proof_path, t.employment_proof_path,
               u.username AS tenant_username,
               p.title AS property_title,
               rs.weighted_score, rs.ml_probability, rs.final_risk, rs.is_first_time_renter
        FROM applications a
        JOIN tenants t ON t.id = a.tenant_id
        JOIN users u   ON u.id = t.user_id
        LEFT JOIN properties p ON p.id = a.property_id
        LEFT JOIN risk_scores rs ON rs.application_id = a.id
        ORDER BY a.applied_date DESC";

$result      = $db->query($sql);
$applications = $result->fetch_all(MYSQLI_ASSOC);

// ALGORITHM C: QuickSort — sort by final_risk descending
$applications = sortApplicationsByRisk($applications);

// Stats — no arrow functions, works on all PHP versions
$total       = count($applications);
$high_risk   = 0;
$medium_risk = 0;
$low_risk    = 0;
foreach ($applications as $a) {
    $r = floatval($a['final_risk'] ?? 0);
    if ($a['final_risk'] !== null) {
        if ($r > 0.7)      $high_risk++;
        elseif ($r >= 0.4) $medium_risk++;
        else               $low_risk++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landlord Dashboard</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<?php include '../php/navbar.php'; ?>
<div class="container">
    <div class="dashboard-header">
        <h2><img src="/rental_risk/images/building.png" class="heading-icon"> Landlord Dashboard</h2>
        <p>Welcome, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong></p>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card"><div class="stat-num"><?= $total ?></div><div>Total Applications</div></div>
        <div class="stat-card stat-red"><div class="stat-num"><?= $high_risk ?></div><div>High Risk</div></div>
        <div class="stat-card stat-yellow"><div class="stat-num"><?= $medium_risk ?></div><div>Medium Risk</div></div>
        <div class="stat-card stat-green"><div class="stat-num"><?= $low_risk ?></div><div>Low Risk</div></div>
    </div>

    <!-- Applications Table -->
    <div class="card">
        <div class="card-header">
            <h3>All Tenant Applications</h3>
            <small class="text-muted">Sorted highest risk first — QuickSort Algorithm</small>
        </div>

        <?php if (empty($applications)): ?>
            <p class="text-muted">No applications yet. Add properties so tenants can apply.</p>
        <?php else: ?>
        <div class="table-scroll">
        <table class="data-table" id="applicationsTable">
            <thead>
                <tr>
                    <th>#</th><th>Applicant</th><th>Property</th>
                    <th>Rent</th><th>Income</th><th>Employment</th>
                    <th>Weighted</th><th>ML Prob.</th><th>Final Risk ▼</th>
                    <th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($applications as $i => $app):
                $risk_info = $app['final_risk'] !== null ? getRiskLabel($app['final_risk']) : null;
                $row_class = $risk_info ? $risk_info['class'].'-row' : '';
            ?>
                <tr class="<?= $row_class ?>">
                    <td><?= $i+1 ?></td>
                    <td>
                        <strong><?= htmlspecialchars($app['full_name']) ?></strong><br>
                        <small><?= htmlspecialchars($app['tenant_username']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($app['property_title'] ?? $app['property_note'] ?? '—') ?></td>
                    <td>NPR <?= number_format($app['monthly_rent'],0) ?></td>
                    <td>NPR <?= number_format($app['monthly_income'],0) ?></td>
                    <td><?= ucfirst(str_replace('_',' ',$app['employment_status'])) ?></td>
                    <td><?= $app['weighted_score'] !== null ? number_format($app['weighted_score'],3) : '—' ?></td>
                    <td><?= $app['ml_probability'] !== null ? number_format($app['ml_probability'],3) : '—' ?></td>
                    <td>
                        <?php if ($risk_info): ?>
                            <span class="risk-badge <?= $risk_info['class'] ?>"><?= number_format($app['final_risk'],3) ?></span>
                            <br><small><?= $risk_info['label'] ?></small>
                            <?php if ($app['is_first_time_renter']): ?>
                                <br><small class="warning-text">
                                    <img src="/rental_risk/images/warning.png" class="inline-icon"> First-time
                                </small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge status-<?= $app['status'] ?>"><?= ucfirst(str_replace('_',' ',$app['status'])) ?></span>
                    </td>
                    <td>
                        <a href="view_application.php?id=<?= $app['app_id'] ?>" class="btn btn-sm btn-primary">View</a>
                        <a href="update_status.php?id=<?= $app['app_id'] ?>&status=approved"
                           class="btn btn-sm btn-success"
                           onclick="return confirm('Approve?')">Approve</a>
                        <a href="update_status.php?id=<?= $app['app_id'] ?>&status=rejected"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Reject?')">Reject</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<script src="../js/script.js"></script>
</body>
</html>
