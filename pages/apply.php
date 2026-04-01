<?php
require_once '../php/auth.php';
require_once '../php/config.php';
require_once '../php/risk_calculate.php';
startSecureSession();
requireRole('tenant');

$db = getDB();
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tenant_dashboard.php');
    exit;
}

// Get tenant profile
$stmt = $db->prepare("SELECT * FROM tenants WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
    header('Location: tenant_dashboard.php?error=Please+complete+your+profile+first');
    exit;
}

$property_note = trim($_POST['property_note'] ?? '');
$monthly_rent  = floatval($_POST['monthly_rent'] ?? 0);

if (empty($property_note) || $monthly_rent <= 0) {
    header('Location: tenant_dashboard.php?error=Invalid+application+data');
    exit;
}

// Insert application (no specific landlord targeted — landlords see all)
$stmt = $db->prepare("INSERT INTO applications (tenant_id, property_note, monthly_rent, status) VALUES (?,?,?,'pending')");
$stmt->bind_param("isd", $profile['id'], $property_note, $monthly_rent);
$stmt->execute();
$app_id = $stmt->insert_id;
$stmt->close();

// ---------------------------------------------------------------
// Run Risk Calculation immediately on submission
// ---------------------------------------------------------------
$is_first_time = ($profile['rental_history_months'] == 0) ? 1 : 0;

// Algorithm A: Weighted rule-based score
$weighted_score = calculateWeightedRiskScore($profile, $monthly_rent);

// Algorithm B: ML probability via Python
$ml_probability = getMLProbability($profile, $monthly_rent);

// Final combined risk
$final_risk = calculateFinalRisk($weighted_score, $ml_probability, $is_first_time);

// Save to risk_scores table
saveRiskScore($app_id, $weighted_score, $ml_probability, $final_risk, $is_first_time);

header('Location: tenant_dashboard.php?msg=Application+submitted+successfully');
exit;
?>
