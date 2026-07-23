<?php
require_once '../php/auth.php';
require_once '../php/config.php';
require_once '../php/risk_calculate.php';
startSecureSession();
requireAnyRole(['landlord','admin']);

$db = getDB();
$app_id = intval($_GET['id'] ?? 0);
if (!$app_id) { header('Location: landlord_dashboard.php'); exit; }

$is_admin = $_SESSION['role'] === 'admin';

$sql = "SELECT a.id AS app_id, a.property_note, a.monthly_rent, a.status, a.applied_date,
               t.*, u.username AS tenant_username, u.email AS tenant_email,
               rs.weighted_score, rs.ml_probability, rs.final_risk, rs.is_first_time_renter, rs.calculated_at
        FROM applications a
        JOIN tenants t ON t.id = a.tenant_id
        JOIN users u ON u.id = t.user_id
        LEFT JOIN risk_scores rs ON rs.application_id = a.id
        WHERE a.id = ?";

if (!$is_admin) {
    $sql .= " AND a.landlord_id = ?";
}

$stmt = $db->prepare($sql);
if ($is_admin) {
    $stmt->bind_param("i", $app_id);
} else {
    $stmt->bind_param("ii", $app_id, $_SESSION['user_id']);
}
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$app) { header('Location: landlord_dashboard.php?error=Application+not+found'); exit; }

$risk_info = $app['final_risk'] !== null ? getRiskLabel($app['final_risk']) : null;
$back_url = ($_SESSION['role'] === 'admin') ? 'admin_dashboard.php' : 'landlord_dashboard.php';
$risk_breakdown = null;
$final_formula = null;
if ($is_admin) {
    $risk_breakdown = calculateWeightedRiskBreakdown($app, $app['monthly_rent']);
    $final_formula = $app['is_first_time_renter']
        ? '0.7 x weighted + 0.3 x ML probability'
        : '0.6 x weighted + 0.4 x ML probability';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Details</title>
    <link rel="stylesheet" href="/rental_risk/css/styles.css?v=<?= filemtime(__DIR__ . '/../css/styles.css') ?>">
</head>
<body>
<?php include '../php/navbar.php'; ?>
<div class="container">
    <div class="page-actions">
        <a href="<?= $back_url ?>" class="btn btn-secondary">← Back</a>
        <h2>Application #<?= $app_id ?> Details</h2>
    </div>

    <!-- Risk Score Banner -->
    <?php if ($risk_info): ?>
    <div class="risk-banner <?= $risk_info['class'] ?>-banner">
        <div class="risk-banner-left">
            <div class="risk-score-big"><?= formatRiskPercent($app['final_risk']) ?></div>
            <div class="risk-label-big"><?= $risk_info['label'] ?></div>
        </div>
        <div class="risk-banner-details">
            <div><strong>Weighted Score (Algorithm A):</strong> <?= formatRiskPercent($app['weighted_score'], 2) ?></div>
            <div><strong>ML Probability (Algorithm B):</strong> <?= formatRiskPercent($app['ml_probability'], 2) ?></div>
            <div><strong>Final Combined Risk:</strong> <?= formatRiskPercent($app['final_risk'], 2) ?></div>
            <div><strong>Calculated:</strong> <?= $app['calculated_at'] ?? 'N/A' ?></div>
        </div>
        <?php if ($app['is_first_time_renter']): ?>
        <div class="first-time-warning">
            <img src="/rental_risk/images/warning.png" class="inline-icon"> <strong>First-time Renter — Limited rental history.</strong><br>
            Rely on income proof, employment status and references.<br>
            Consider requesting a higher security deposit or a guarantor.
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($is_admin): ?>
    <div class="card">
        <div class="card-header">
            <h3><img src="/rental_risk/images/ai.png" class="heading-icon"> Admin Risk Breakdown</h3>
        </div>

        <div class="two-col">
            <div>
                <table class="detail-table">
                    <tr><th>Income</th><td>NPR <?= number_format($risk_breakdown['monthly_income'], 2) ?></td></tr>
                    <tr><th>Rent Used</th><td>NPR <?= number_format($risk_breakdown['monthly_rent'], 2) ?></td></tr>
                    <tr><th>Income/Rent Ratio</th><td><?= number_format($risk_breakdown['income_rent_ratio'], 2) ?>x</td></tr>
                    <tr><th>Employment Status</th><td><?= ucfirst(str_replace('_', ' ', $app['employment_status'])) ?></td></tr>
                    <tr><th>Rental History</th><td><?= intval($app['rental_history_months']) ?> months</td></tr>
                    <tr><th>Reference Detected</th><td><?= $risk_breakdown['has_reference'] ? 'Yes' : 'No' ?></td></tr>
                    <tr><th>Guarantor Detected</th><td><?= $risk_breakdown['has_guarantor'] ? 'Yes' : 'No' ?></td></tr>
                </table>
            </div>

            <div>
                <table class="detail-table">
                    <tr><th>Income Ratio Risk</th><td><?= formatRiskPercent($risk_breakdown['income_ratio_risk'], 2) ?></td></tr>
                    <tr><th>Employment Risk</th><td><?= formatRiskPercent($risk_breakdown['employment_risk'], 2) ?></td></tr>
                    <tr><th>Reference Risk</th><td><?= formatRiskPercent($risk_breakdown['reference_risk'], 2) ?></td></tr>
                    <tr><th>History Risk</th><td><?= formatRiskPercent($risk_breakdown['history_risk'], 2) ?></td></tr>
                    <tr><th>Weighted Formula</th><td>(0.4 x <?= number_format($risk_breakdown['income_ratio_risk'], 2) ?>) + (0.3 x <?= number_format($risk_breakdown['employment_risk'], 2) ?>) + (0.2 x <?= number_format($risk_breakdown['reference_risk'], 2) ?>) + (0.1 x <?= number_format($risk_breakdown['history_risk'], 2) ?>)</td></tr>
                    <tr><th>Weighted Score</th><td><?= formatRiskPercent($risk_breakdown['weighted_score'], 2) ?></td></tr>
                    <tr><th>ML Probability</th><td><?= $app['ml_probability'] !== null ? formatRiskPercent($app['ml_probability'], 2) : 'Not available' ?></td></tr>
                    <tr><th>Final Formula</th><td><?= htmlspecialchars($final_formula) ?></td></tr>
                    <tr><th>Final Risk</th><td><?= $app['final_risk'] !== null ? formatRiskPercent($app['final_risk'], 2) : 'Not available' ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="two-col">
        <!-- Personal Info -->
        <div class="card">
            <h3><img src="/rental_risk/images/user.png" class="heading-icon"> Personal Information</h3>
            <table class="detail-table">
                <tr><th>Full Name</th><td><?= htmlspecialchars($app['full_name']) ?></td></tr>
                <tr><th>Username</th><td><?= htmlspecialchars($app['tenant_username']) ?></td></tr>
                <tr><th>Email</th><td><?= htmlspecialchars($app['tenant_email']) ?></td></tr>
                <tr><th>Age</th><td><?= $app['age'] ?></td></tr>
                <tr><th>Contact</th><td><?= htmlspecialchars($app['contact']) ?></td></tr>
                <tr><th>Address</th><td><?= htmlspecialchars($app['address']) ?></td></tr>
            </table>
        </div>

        <!-- Financial Info -->
        <div class="card">
            <h3><img src="/rental_risk/images/chart.png" class="heading-icon"> Financial & Employment</h3>
            <table class="detail-table">
                <tr><th>Monthly Income</th><td>NPR <?= number_format($app['monthly_income'], 2) ?></td></tr>
                <tr><th>Monthly Rent</th><td>NPR <?= number_format($app['monthly_rent'], 2) ?></td></tr>
                <tr><th>Income/Rent Ratio</th>
                    <td><?= $app['monthly_rent'] > 0 ? number_format($app['monthly_income'] / $app['monthly_rent'], 2) : 'N/A' ?>x</td></tr>
                <tr><th>Employment</th><td><?= ucfirst(str_replace('_',' ',$app['employment_status'])) ?></td></tr>
                <tr><th>Rental History</th><td><?= $app['rental_history_months'] ?> months</td></tr>
                <tr><th>Property Note</th><td><?= htmlspecialchars($app['property_note']) ?></td></tr>
                <tr><th>Applied On</th><td><?= date('d M Y H:i', strtotime($app['applied_date'])) ?></td></tr>
                <?php if (!empty($app['guarantor_info'])): ?>
                <tr style="background:#eafaf1">
                    <th>Guarantor</th>
                    <td><strong><?= nl2br(htmlspecialchars($app['guarantor_info'])) ?></strong>
                    <br><small style="color:#27ae60">Guarantor declared — will cover rent if tenant defaults</small></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- Reference -->
    <div class="card">
        <h3><img src="/rental_risk/images/clipboard.png" class="heading-icon"> Landlord Reference</h3>
        <?php if (!empty($app['reference_text'])): ?>
            <p><?= nl2br(htmlspecialchars($app['reference_text'])) ?></p>
        <?php else: ?>
            <p class="text-muted">No reference provided.</p>
        <?php endif; ?>
    </div>

    <!-- Documents -->
    <div class="card">
        <h3><img src="/rental_risk/images/attach.png" class="heading-icon"> Uploaded Documents</h3>
        <div class="doc-row">
            <?php
            $docs = [
                'id_proof_path'         => 'ID Proof',
                'income_proof_path'     => 'Income Proof',
                'employment_proof_path' => 'Employment Letter',
            ];
            foreach ($docs as $col => $label):
            ?>
                <div class="doc-item">
                    <strong><?= $label ?></strong><br>
                    <?php if (!empty($app[$col])): ?>
                        <a href="../<?= htmlspecialchars($app[$col]) ?>" target="_blank" class="btn btn-sm btn-outline"><img src="/rental_risk/images/document.png" class="inline-icon"> View / Download</a>
                    <?php else: ?>
                        <span class="badge badge-warning">Not uploaded</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Recommendation -->
    <div class="card">
        <h3><img src="/rental_risk/images/ai.png" class="heading-icon"> System Recommendation</h3>
        <?php if ($risk_info): ?>
            <?php
            $score = floatval($app['final_risk']);
            if ($score < 0.4) {
                echo '<div class="recommendation recommendation-green">
                    <strong><img src="/rental_risk/images/check.png" style="width:16px;vertical-align:middle"> LOW RISK — Recommended for Approval</strong><br>
                    This applicant shows strong financial standing, stable employment, and good rental history.
                    Likely to be a reliable tenant.
                </div>';
            } elseif ($score <= 0.7) {
                echo '<div class="recommendation recommendation-yellow">
                    <strong><img src="/rental_risk/images/warning.png" style="width:16px;vertical-align:middle"> MEDIUM RISK — Proceed with Caution</strong><br>
                    Some risk factors present. Consider requiring a security deposit (1–2 months rent),
                    verifying all documents carefully, and checking references before approval.
                </div>';
            } else {
                echo '<div class="recommendation recommendation-red">
                    <strong><img src="/rental_risk/images/cross.png" style="width:16px;vertical-align:middle"> HIGH RISK — Not Recommended</strong><br>
                    Significant risk factors detected. If proceeding, require a guarantor,
                    larger deposit (3+ months), and thorough document verification.
                </div>';
            }
            ?>
        <?php else: ?>
            <p class="text-muted">Risk score not yet calculated.</p>
        <?php endif; ?>
    </div>

    <!-- Status Update -->
    <?php if ($_SESSION['role'] === 'landlord'): ?>
    <div class="card">
        <h3>Update Application Status</h3>
        <div class="btn-group">
            <a href="update_status.php?id=<?= $app_id ?>&status=approved&redirect=view"
               class="btn btn-success" onclick="return confirm('Approve this application?')">
               <img src="/rental_risk/images/check.png" class="inline-icon"> Approve</a>
            <a href="update_status.php?id=<?= $app_id ?>&status=rejected&redirect=view"
               class="btn btn-danger" onclick="return confirm('Reject this application?')">
               <img src="/rental_risk/images/cross.png" class="inline-icon"> Reject</a>
            <a href="update_status.php?id=<?= $app_id ?>&status=under_review&redirect=view"
               class="btn btn-secondary">
               <img src="/rental_risk/images/search.png" class="inline-icon"> Under Review</a>
        </div>
    </div>
    <?php endif; ?>
</div>
<script src="/rental_risk/js/script.js"></script>
</body>
</html>
