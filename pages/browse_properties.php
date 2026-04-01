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

// Load preferences
$prefs = null;
if ($profile) {
    $tid = $profile['id'];
    $stmt = $db->prepare("SELECT * FROM tenant_preferences WHERE tenant_id = ?");
    $stmt->bind_param("i", $tid);
    $stmt->execute();
    $prefs = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Handle application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['property_id'])) {
    if (!$profile) {
        header('Location: tenant_profile.php?new=1');
        exit;
    }

    $property_id = intval($_POST['property_id']);

    $stmt = $db->prepare("SELECT * FROM properties WHERE id = ? AND is_available = 1");
    $stmt->bind_param("i", $property_id);
    $stmt->execute();
    $property = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$property) {
        header('Location: browse_properties.php?error=Property+not+found+or+unavailable');
        exit;
    }

    // Check already applied
    $pid = $profile['id'];
    $stmt = $db->prepare("SELECT id FROM applications WHERE tenant_id = ? AND property_id = ?");
    $stmt->bind_param("ii", $pid, $property_id);
    $stmt->execute();
    $stmt->store_result();
    $already = $stmt->num_rows > 0;
    $stmt->close();

    if ($already) {
        header('Location: browse_properties.php?error=You+have+already+applied+for+this+property');
        exit;
    }

    // Insert application
    $note        = $property['title'] . ' — ' . $property['address'];
    $rent        = floatval($property['monthly_rent']);
    $tenant_id   = $profile['id'];
    $landlord_id = intval($property['landlord_id']);

    $stmt = $db->prepare(
        "INSERT INTO applications (tenant_id, landlord_id, property_id, property_note, monthly_rent, status)
         VALUES (?, ?, ?, ?, ?, 'pending')"
    );
    $stmt->bind_param("iiisd", $tenant_id, $landlord_id, $property_id, $note, $rent);
    $stmt->execute();
    $app_id = $stmt->insert_id;
    $stmt->close();

    // Calculate and save risk
    $is_first_time  = ($profile['rental_history_months'] == 0) ? 1 : 0;
    $weighted_score = calculateWeightedRiskScore($profile, $rent);
    $ml_probability = getMLProbability($profile, $rent);
    $final_risk     = calculateFinalRisk($weighted_score, $ml_probability, $is_first_time);
    saveRiskScore($app_id, $weighted_score, $ml_probability, $final_risk, $is_first_time);

    header('Location: browse_properties.php?msg=Application+submitted+successfully');
    exit;
}

// Load all available properties
$sql = "SELECT p.*, u.username AS landlord_name
        FROM properties p
        JOIN users u ON u.id = p.landlord_id
        WHERE p.is_available = 1
        ORDER BY p.created_at DESC";
$properties = $db->query($sql)->fetch_all(MYSQLI_ASSOC);

// ============================================================
// ALGORITHM D: Preference-Based Recommendation Scoring
// Score 0-100 based on how well property matches preferences
// rent=40pts, location=30pts, type=20pts, bedrooms=10pts
// ============================================================
function calculateMatchScore($property, $prefs) {
    if (!$prefs) return 0;

    $score = 0;

    // Rent match (40 pts)
    $rent    = floatval($property['monthly_rent']);
    $min     = floatval($prefs['preferred_min_rent']);
    $max     = floatval($prefs['preferred_max_rent']);
    $has_min = ($min > 0);
    $has_max = ($max > 0);

    if ($has_min && $has_max) {
        if ($rent >= $min && $rent <= $max) {
            $score += 40;
        } else if ($rent > $max) {
            $over = ($rent - $max) / $max;
            if ($over <= 0.1)      $score += 30;
            elseif ($over <= 0.25) $score += 15;
        } else {
            $score += 10;
        }
    } elseif ($has_max && $rent <= $max) {
        $score += 40;
    } elseif ($has_min && $rent >= $min) {
        $score += 40;
    } else {
        $score += 20;
    }

    // Location match (30 pts)
    $pref_area = strtolower(trim($prefs['preferred_area']));
    $prop_addr = strtolower($property['address']);
    $prop_title = strtolower($property['title']);

    if (!empty($pref_area)) {
        $areas   = explode(',', $pref_area);
        $matched = false;
        foreach ($areas as $area) {
            $area = trim($area);
            if (!empty($area) && (strpos($prop_addr, $area) !== false || strpos($prop_title, $area) !== false)) {
                $matched = true;
                break;
            }
        }
        if ($matched) $score += 30;
    } else {
        $score += 15;
    }

    // Type match (20 pts)
    $pref_type = $prefs['preferred_type'];
    if ($pref_type === 'any' || $pref_type === $property['property_type']) {
        $score += 20;
    }

    // Bedrooms match (10 pts)
    $pref_beds = intval($prefs['preferred_bedrooms']);
    $prop_beds = intval($property['bedrooms']);
    if ($pref_beds === 0) {
        $score += 10;
    } elseif ($prop_beds === $pref_beds) {
        $score += 10;
    } elseif (abs($prop_beds - $pref_beds) === 1) {
        $score += 5;
    }

    return min(100, $score);
}

// Apply match scores
for ($i = 0; $i < count($properties); $i++) {
    $properties[$i]['match_score'] = calculateMatchScore($properties[$i], $prefs);
}

// ============================================================
// QuickSort — sort properties by match_score descending
// Same algorithm used in risk_calculate.php for applications
// ============================================================
function quickSortProperties(array &$props, $low, $high) {
    if ($low < $high) {
        $pivot = partitionProperties($props, $low, $high);
        quickSortProperties($props, $low, $pivot - 1);
        quickSortProperties($props, $pivot + 1, $high);
    }
}

function partitionProperties(array &$props, $low, $high) {
    $pivot = intval($props[$high]['match_score']);
    $i     = $low - 1;
    for ($j = $low; $j < $high; $j++) {
        // Descending — swap if current is GREATER than pivot
        if (intval($props[$j]['match_score']) > $pivot) {
            $i++;
            $tmp      = $props[$i];
            $props[$i] = $props[$j];
            $props[$j] = $tmp;
        }
    }
    $tmp          = $props[$i + 1];
    $props[$i + 1] = $props[$high];
    $props[$high]  = $tmp;
    return $i + 1;
}

// Sort by match score descending if preferences exist
if ($prefs && count($properties) > 1) {
    quickSortProperties($properties, 0, count($properties) - 1);
}

// Properties already applied to
$already_applied = [];
if ($profile) {
    $pid2 = $profile['id'];
    $stmt = $db->prepare("SELECT property_id FROM applications WHERE tenant_id = ? AND property_id IS NOT NULL");
    $stmt->bind_param("i", $pid2);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as $row) {
        $already_applied[] = $row['property_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Properties</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<?php include '../php/navbar.php'; ?>
<div class="container">

    <div class="dashboard-header">
        <h2><img src="/rental_risk/images/building.png" class="heading-icon"> Browse Properties</h2>
        <?php if ($prefs): ?>
            <p>Showing best matches for your preferences first.
               <a href="tenant_preferences.php">Change Preferences</a>
            </p>
        <?php else: ?>
            <p>
                <a href="tenant_preferences.php" class="btn btn-sm btn-primary">
                    <img src="/rental_risk/images/search.png" class="inline-icon"> Set Preferences for Better Matches
                </a>
            </p>
        <?php endif; ?>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

    <?php if (!$profile): ?>
        <div class="alert alert-info">
            <img src="/rental_risk/images/warning.png" class="inline-icon">
            Please complete your profile before applying.
            <a href="tenant_profile.php?new=1" class="btn btn-primary" style="margin-left:10px">Complete Profile</a>
        </div>
    <?php endif; ?>

    <?php if ($prefs): ?>
    <div class="prefs-bar">
        <strong>Your preferences:</strong>
        <?php if ($prefs['preferred_min_rent'] > 0 || $prefs['preferred_max_rent'] > 0): ?>
            NPR <?= number_format($prefs['preferred_min_rent'], 0) ?> – <?= number_format($prefs['preferred_max_rent'], 0) ?>/mo &nbsp;|&nbsp;
        <?php endif; ?>
        <?php if (!empty($prefs['preferred_area'])): ?>
            Area: <?= htmlspecialchars($prefs['preferred_area']) ?> &nbsp;|&nbsp;
        <?php endif; ?>
        Type: <?= ucfirst($prefs['preferred_type']) ?> &nbsp;|&nbsp;
        Bedrooms: <?= ($prefs['preferred_bedrooms'] > 0) ? $prefs['preferred_bedrooms'] : 'Any' ?>
        &nbsp;<a href="tenant_preferences.php" style="font-size:12px">Edit</a>
    </div>
    <?php endif; ?>

    <?php if (empty($properties)): ?>
        <div class="card"><p class="text-muted">No properties available right now. Check back later.</p></div>
    <?php else: ?>
    <div class="property-grid">
        <?php foreach ($properties as $p):
            $applied     = in_array($p['id'], $already_applied);
            $match       = intval($p['match_score']);
            $match_class = ($match >= 70) ? 'match-high' : (($match >= 40) ? 'match-mid' : 'match-low');
        ?>
        <div class="property-card">
            <div class="property-card-header">
                <span class="property-type-badge"><?= ucfirst($p['property_type']) ?></span>
                <span class="property-rent">NPR <?= number_format($p['monthly_rent'], 0) ?>/mo</span>
            </div>

            <?php if ($prefs): ?>
            <div class="match-bar-wrap">
                <div class="match-bar <?= $match_class ?>" style="width:<?= $match ?>%"></div>
            </div>
            <span class="match-label <?= $match_class ?>"><?= $match ?>% match</span>
            <?php endif; ?>

            <h3 class="property-title"><?= htmlspecialchars($p['title']) ?></h3>
            <p class="property-address">
                <img src="/rental_risk/images/building.png" class="inline-icon">
                <?= htmlspecialchars($p['address']) ?>
            </p>

            <?php if (!empty($p['description'])): ?>
                <p class="property-desc">
                    <?= htmlspecialchars(substr($p['description'], 0, 130)) ?><?= strlen($p['description']) > 130 ? '...' : '' ?>
                </p>
            <?php endif; ?>

            <div class="property-meta">
                <span><?= $p['bedrooms'] ?> Bedroom<?= $p['bedrooms'] != 1 ? 's' : '' ?></span>
                <span><img src="/rental_risk/images/user.png" class="inline-icon"> <?= htmlspecialchars($p['landlord_name']) ?></span>
            </div>

            <div class="property-actions">
                <?php if ($applied): ?>
                    <span class="badge badge-success" style="display:block;text-align:center;padding:8px">
                        <img src="/rental_risk/images/check.png" class="inline-icon"> Already Applied
                    </span>
                <?php elseif ($profile): ?>
                    <form method="POST">
                        <input type="hidden" name="property_id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-primary btn-full"
                                onclick="return confirm('Apply for this property?')">
                            <img src="/rental_risk/images/email.png" class="inline-icon"> Apply Now
                        </button>
                    </form>
                <?php else: ?>
                    <a href="tenant_profile.php?new=1" class="btn btn-secondary btn-full">Complete Profile to Apply</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<script src="../js/script.js"></script>
</body>
</html>