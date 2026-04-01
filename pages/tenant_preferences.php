<?php
// ============================================================
// Tenant Preferences Page
// Tenant fills in what kind of property they are looking for.
// These preferences are used by the recommendation algorithm
// in browse_properties.php to rank listings for this tenant.
// ============================================================
require_once '../php/auth.php';
require_once '../php/config.php';
startSecureSession();
requireRole('tenant');

$db      = getDB();
$user_id = $_SESSION['user_id'];
$msg     = '';
$error   = '';

// Get tenant profile id
$stmt = $db->prepare("SELECT id FROM tenants WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tenant = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tenant) {
    header('Location: tenant_profile.php?new=1');
    exit;
}

$tenant_id = $tenant['id'];

// Load existing preferences
$stmt = $db->prepare("SELECT * FROM tenant_preferences WHERE tenant_id = ?");
$stmt->bind_param("i", $tenant_id);
$stmt->execute();
$prefs = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Save preferences
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $min_rent   = floatval($_POST['preferred_min_rent'] ?? 0);
    $max_rent   = floatval($_POST['preferred_max_rent'] ?? 0);
    $area       = trim($_POST['preferred_area'] ?? '');
    $type       = $_POST['preferred_type'] ?? 'any';
    $bedrooms   = intval($_POST['preferred_bedrooms'] ?? 0);

    $valid_types = ['any','room','flat','house','office'];
    if (!in_array($type, $valid_types)) $type = 'any';
    if ($max_rent > 0 && $min_rent > $max_rent) {
        $error = 'Minimum rent cannot be greater than maximum rent.';
    } else {
        if ($prefs) {
            $stmt = $db->prepare(
                "UPDATE tenant_preferences
                 SET preferred_min_rent=?, preferred_max_rent=?, preferred_area=?,
                     preferred_type=?, preferred_bedrooms=?
                 WHERE tenant_id=?"
            );
            $stmt->bind_param("ddssii", $min_rent, $max_rent, $area, $type, $bedrooms, $tenant_id);
        } else {
            $stmt = $db->prepare(
                "INSERT INTO tenant_preferences
                 (tenant_id, preferred_min_rent, preferred_max_rent, preferred_area, preferred_type, preferred_bedrooms)
                 VALUES (?,?,?,?,?,?)"
            );
            $stmt->bind_param("iddssi", $tenant_id, $min_rent, $max_rent, $area, $type, $bedrooms);
        }
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: browse_properties.php?msg=Preferences+saved+Showing+best+matches+for+you');
            exit;
        }
        $error = 'Failed to save preferences.';
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Preferences</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<?php include '../php/navbar.php'; ?>
<div class="container">
    <div class="dashboard-header">
        <h2><img src="/rental_risk/images/search.png" class="heading-icon"> My Rental Preferences</h2>
        <p>Tell us what you are looking for — we will show the best matching properties first.</p>
    </div>

    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($prefs): ?>
        <div class="alert alert-info">
            You have saved preferences. Browse Properties will now show best matches for you at the top.
            <a href="browse_properties.php" class="btn btn-sm btn-primary" style="margin-left:10px">Browse Now</a>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" id="prefsForm">

            <div class="form-section">
                <h3>Budget</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Minimum Rent (NPR)</label>
                        <input type="number" name="preferred_min_rent" min="0" placeholder="e.g. 5000"
                               value="<?= $prefs['preferred_min_rent'] ?? '' ?>">
                        <small>Leave 0 for no minimum</small>
                    </div>
                    <div class="form-group">
                        <label>Maximum Rent (NPR)</label>
                        <input type="number" name="preferred_max_rent" min="0" placeholder="e.g. 20000"
                               value="<?= $prefs['preferred_max_rent'] ?? '' ?>">
                        <small>Leave 0 for no maximum</small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Location & Type</h3>
                <div class="form-group">
                    <label>Preferred Area / Location</label>
                    <input type="text" name="preferred_area"
                           placeholder="e.g. Thamel, Lalitpur, Baneshwor"
                           value="<?= htmlspecialchars($prefs['preferred_area'] ?? '') ?>">
                    <small>Enter a neighbourhood, area or city name. Partial matches also work.</small>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Property Type</label>
                        <select name="preferred_type">
                            <?php foreach(['any'=>'Any Type','room'=>'Room','flat'=>'Flat/Apartment','house'=>'Full House','office'=>'Office Space'] as $v=>$l): ?>
                                <option value="<?= $v ?>" <?= ($prefs['preferred_type'] ?? 'any') === $v ? 'selected':'' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Number of Bedrooms</label>
                        <input type="number" name="preferred_bedrooms" min="0" max="20"
                               value="<?= $prefs['preferred_bedrooms'] ?? 0 ?>">
                        <small>Enter 0 for no preference</small>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <img src="/rental_risk/images/save.png" class="inline-icon"> Save Preferences
                </button>
                <a href="browse_properties.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <!-- How scoring works -->
    <div class="card">
        <h3>How Matching Works</h3>
        <table class="detail-table">
            <tr><th>Criteria</th><th>Points if matched</th></tr>
            <tr><td>Rent within your budget range</td><td>40 points</td></tr>
            <tr><td>Location matches your preferred area</td><td>30 points</td></tr>
            <tr><td>Property type matches</td><td>20 points</td></tr>
            <tr><td>Number of bedrooms matches</td><td>10 points</td></tr>
        </table>
        <p style="margin-top:10px;font-size:13px;color:#7f8c8d">
            Properties are sorted from highest match score to lowest.
            Properties with no preferences set are shown in date order.
        </p>
    </div>
</div>
<script src="../js/script.js"></script>
</body>
</html>
