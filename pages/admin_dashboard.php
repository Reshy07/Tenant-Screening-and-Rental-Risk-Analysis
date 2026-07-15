<?php
require_once '../php/auth.php';
require_once '../php/config.php';
require_once '../php/risk_calculate.php';
startSecureSession();
requireRole('admin');

$db    = getDB();
$msg   = htmlspecialchars($_GET['msg'] ?? '');
$error = htmlspecialchars($_GET['error'] ?? '');

// Stats
$stats = $db->query("
    SELECT
        (SELECT COUNT(*) FROM users WHERE role='tenant')   AS total_tenants,
        (SELECT COUNT(*) FROM users WHERE role='landlord') AS total_landlords,
        (SELECT COUNT(*) FROM applications)                AS total_applications,
        (SELECT AVG(final_risk) FROM risk_scores)          AS avg_risk
")->fetch_assoc();

// Toggle user active/inactive
if (isset($_GET['toggle_user'])) {
    $uid = intval($_GET['toggle_user']);
    if ($uid !== $_SESSION['user_id']) {
        $db->query("UPDATE users SET is_active = 1 - is_active WHERE id = $uid AND role != 'admin'");
    }
    header('Location: admin_dashboard.php?msg=User+status+updated');
    exit;
}

// Insert sample data
if (isset($_GET['insert_sample'])) {
    include '../php/insert_sample.php';
    if (!empty($insertSampleResult)) {
        $summary = "Sample data ready: "
                 . "users +" . intval($insertSampleResult['created_users']) . ", "
                 . "tenant profiles +" . intval($insertSampleResult['created_tenants']) . ", "
                 . "applications +" . intval($insertSampleResult['created_applications']) . ", "
                 . "scores recalculated " . intval($insertSampleResult['recalculated_scores']) . ". "
                 . "Updated existing: users " . intval($insertSampleResult['updated_users'])
                 . ", tenants " . intval($insertSampleResult['updated_tenants'])
                 . ", applications " . intval($insertSampleResult['updated_applications']) . ".";
        header('Location: admin_dashboard.php?msg=' . urlencode($summary));
    } else {
        header('Location: admin_dashboard.php?error=Sample+data+insertion+did+not+return+a+result');
    }
    exit;
}

// Recalculate risk scores for all existing applications
if (isset($_GET['recalculate_all'])) {
    $rows = $db->query(
        "SELECT a.id AS app_id, a.monthly_rent,
                t.monthly_income, t.employment_status, t.rental_history_months,
                t.reference_text, t.age, t.guarantor_info
         FROM applications a
         JOIN tenants t ON t.id = a.tenant_id"
    );

    $updated = 0;
    if ($rows) {
        while ($row = $rows->fetch_assoc()) {
            $rent = floatval($row['monthly_rent']);
            $is_first_time = (intval($row['rental_history_months']) === 0) ? 1 : 0;

            $weighted_score = calculateWeightedRiskScore($row, $rent);
            $ml_probability = getMLProbability($row, $rent);
            $final_risk     = calculateFinalRisk($weighted_score, $ml_probability, $is_first_time);

            if (saveRiskScore(intval($row['app_id']), $weighted_score, $ml_probability, $final_risk, $is_first_time)) {
                $updated++;
            }
        }
        $rows->free();
    }

    header('Location: admin_dashboard.php?msg=' . urlencode("Recalculated risk scores for {$updated} applications"));
    exit;
}

// All applications with risk — sorted by QuickSort
$sql = "SELECT a.id AS app_id, a.property_note, a.monthly_rent, a.status, a.applied_date,
               t.full_name, t.employment_status, t.rental_history_months,
               u.username AS tenant_username,
               ul.username AS landlord_username,
               p.title AS property_title,
               rs.weighted_score, rs.ml_probability, rs.final_risk, rs.is_first_time_renter
        FROM applications a
        JOIN tenants t   ON t.id = a.tenant_id
        JOIN users u     ON u.id = t.user_id
        LEFT JOIN users ul    ON ul.id = a.landlord_id
        LEFT JOIN properties p ON p.id = a.property_id
        LEFT JOIN risk_scores rs ON rs.application_id = a.id";
$result       = $db->query($sql);
$applications = $result->fetch_all(MYSQLI_ASSOC);
$applications = sortApplicationsByRisk($applications);

// All users
$all_users = $db->query("SELECT * FROM users ORDER BY role, created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="/rental_risk/css/styles.css?v=<?= filemtime(__DIR__ . '/../css/styles.css') ?>">
</head>
<body>
<?php include '../php/navbar.php'; ?>
<div class="container">
    <div class="dashboard-header">
        <h2><img src="/rental_risk/images/settings.png" class="heading-icon"> Admin Dashboard</h2>
        <p>Full system overview and management</p>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card"><div class="stat-num"><?= $stats['total_tenants'] ?></div><div>Tenants</div></div>
        <div class="stat-card"><div class="stat-num"><?= $stats['total_landlords'] ?></div><div>Landlords</div></div>
        <div class="stat-card"><div class="stat-num"><?= $stats['total_applications'] ?></div><div>Applications</div></div>
        <div class="stat-card <?= floatval($stats['avg_risk'] ?? 0) > 0.5 ? 'stat-red' : 'stat-green' ?>">
            <div class="stat-num"><?= $stats['avg_risk'] !== null ? formatRiskPercent($stats['avg_risk']) : 'N/A' ?></div>
            <div>Avg Risk</div>
        </div>
    </div>

    <!-- Demo tools -->
    <div class="card">
        <h3><img src="/rental_risk/images/test.png" class="heading-icon"> Demo Tools</h3>
        <a href="admin_dashboard.php?insert_sample=1" class="btn btn-secondary"
           onclick="return confirm('Insert sample test data?')">
            <img src="/rental_risk/images/chart.png" class="inline-icon"> Insert Sample Test Data
        </a>
        <a href="admin_dashboard.php?recalculate_all=1" class="btn btn-primary"
           onclick="return confirm('Recalculate risk scores for all applications?')"
           style="margin-left:8px">
            <img src="/rental_risk/images/settings.png" class="inline-icon"> Recalculate All Risk Scores
        </a>
    </div>

    <!-- All Applications -->
    <div class="card">
        <div class="card-header">
            <h3>All Applications</h3>
            <small class="text-muted">Sorted by risk — QuickSort Algorithm</small>
        </div>
        <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th><th>Tenant</th><th>Property</th><th>Rent</th>
                    <th>Landlord</th><th>Weighted</th><th>ML</th>
                    <th>Final Risk</th><th>Status</th><th>View</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($applications as $i => $app):
                $risk_info = ($app['final_risk'] !== null) ? getRiskLabel($app['final_risk']) : null;
            ?>
                <tr class="<?= $risk_info ? $risk_info['class'].'-row' : '' ?>">
                    <td><?= $i + 1 ?></td>
                    <td>
                        <?= htmlspecialchars($app['full_name']) ?><br>
                        <small><?= htmlspecialchars($app['tenant_username']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($app['property_title'] ?? $app['property_note'] ?? '—') ?></td>
                    <td><?= number_format($app['monthly_rent'], 0) ?></td>
                    <td><?= htmlspecialchars($app['landlord_username'] ?? '—') ?></td>
                    <td><?= ($app['weighted_score'] !== null) ? formatRiskPercent($app['weighted_score']) : '—' ?></td>
                    <td><?= ($app['ml_probability'] !== null) ? formatRiskPercent($app['ml_probability']) : '—' ?></td>
                    <td>
                        <?php if ($risk_info): ?>
                            <span class="risk-badge <?= $risk_info['class'] ?>\"><?= formatRiskPercent($app['final_risk']) ?></span>
                            <?php if ($app['is_first_time_renter']): ?>
                                <br><small class="warning-text">
                                    <img src="/rental_risk/images/warning.png" class="inline-icon"> First-time
                                </small>
                            <?php endif; ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <span class="badge status-<?= $app['status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $app['status'])) ?>
                        </span>
                    </td>
                    <td>
                        <a href="view_application.php?id=<?= $app['app_id'] ?>" class="btn btn-sm btn-primary">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- User Management -->
    <div class="card">
        <h3><img src="/rental_risk/images/user.png" class="heading-icon"> Manage Users</h3>
        <table class="data-table">
            <thead>
                <tr><th>#</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Action</th></tr>
            </thead>
            <tbody>
            <?php foreach ($all_users as $i => $u): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge role-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                    <td>
                        <?php if ($u['role'] !== 'admin' && $u['id'] !== $_SESSION['user_id']): ?>
                            <a href="admin_dashboard.php?toggle_user=<?= $u['id'] ?>"
                               class="btn btn-sm <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?>"
                               onclick="return confirm('Toggle user status?')">
                                <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="/rental_risk/js/script.js"></script>
</body>
</html>
