<?php
require_once '../php/auth.php';
require_once '../php/config.php';
startSecureSession();
requireRole('landlord');

$db = getDB();
$landlord_id = $_SESSION['user_id'];
$msg   = htmlspecialchars($_GET['msg'] ?? '');
$error = '';

// ---- Handle DELETE ----
if (isset($_GET['delete'])) {
    $pid = intval($_GET['delete']);
    $stmt = $db->prepare("DELETE FROM properties WHERE id=? AND landlord_id=?");
    $stmt->bind_param("ii", $pid, $landlord_id);
    $stmt->execute();
    $stmt->close();
    header('Location: manage_properties.php?msg=Property+deleted');
    exit;
}

// ---- Handle ADD / EDIT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid         = intval($_POST['property_id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $rent        = floatval($_POST['monthly_rent'] ?? 0);
    $bedrooms    = intval($_POST['bedrooms'] ?? 1);
    $type        = $_POST['property_type'] ?? 'flat';
    $available   = isset($_POST['is_available']) ? 1 : 0;

    $valid_types = ['room','flat','house','office'];

    if (empty($title) || empty($address) || $rent <= 0 || !in_array($type, $valid_types)) {
        $error = 'Please fill all required fields with valid values.';
    } else {
        if ($pid > 0) {
            // Update existing
            $stmt = $db->prepare(
                "UPDATE properties SET title=?, description=?, address=?, monthly_rent=?,
                 bedrooms=?, property_type=?, is_available=?
                 WHERE id=? AND landlord_id=?"
            );
            $stmt->bind_param("sssdiisii",
                $title, $description, $address, $rent,
                $bedrooms, $type, $available, $pid, $landlord_id
            );
        } else {
            // Insert new
            $stmt = $db->prepare(
                "INSERT INTO properties (landlord_id, title, description, address, monthly_rent, bedrooms, property_type, is_available)
                 VALUES (?,?,?,?,?,?,?,?)"
            );
            $stmt->bind_param("isssdiis",
                $landlord_id, $title, $description, $address, $rent, $bedrooms, $type, $available
            );
        }
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: manage_properties.php?msg=' . ($pid > 0 ? 'Property+updated' : 'Property+added+successfully'));
            exit;
        }
        $error = 'Failed to save: ' . $db->error;
        $stmt->close();
    }
}

// ---- Load property for editing ----
$editing = null;
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM properties WHERE id=? AND landlord_id=?");
    $stmt->bind_param("ii", $eid, $landlord_id);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ---- Load all landlord properties with application count ----
$sql = "SELECT p.*, COUNT(a.id) AS application_count
        FROM properties p
        LEFT JOIN applications a ON a.property_id = p.id
        WHERE p.landlord_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$properties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Properties — Landlord</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<?php include '../php/navbar.php'; ?>
<div class="container">
    <div class="dashboard-header">
        <h2><img src="/rental_risk/images/building.png" class="heading-icon"> My Properties</h2>
        <p>List and manage your rental properties</p>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- ADD / EDIT FORM -->
    <div class="card">
        <h3><?= $editing ? '<img src="/rental_risk/images/pencil.png" class="heading-icon"> Edit Property' : '<img src="/rental_risk/images/building.png" class="heading-icon"> Add New Property' ?></h3>

        <form method="POST" id="propertyForm">
            <?php if ($editing): ?>
                <input type="hidden" name="property_id" value="<?= $editing['id'] ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label>Property Title *</label>
                    <input type="text" name="title" placeholder="e.g. Cozy 2BHK Flat in Thamel"
                           value="<?= htmlspecialchars($editing['title'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Property Type *</label>
                    <select name="property_type" required>
                        <?php foreach(['room'=>'Room','flat'=>'Flat/Apartment','house'=>'Full House','office'=>'Office Space'] as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= ($editing['property_type'] ?? 'flat') === $v ? 'selected':'' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Monthly Rent (NPR) *</label>
                    <input type="number" name="monthly_rent" min="1" step="0.01"
                           value="<?= $editing['monthly_rent'] ?? '' ?>" required>
                </div>
                <div class="form-group">
                    <label>Bedrooms</label>
                    <input type="number" name="bedrooms" min="1" max="20"
                           value="<?= $editing['bedrooms'] ?? 1 ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Address *</label>
                <input type="text" name="address" placeholder="e.g. Flat 3A, Sunrise Apartment, Thamel, Kathmandu"
                       value="<?= htmlspecialchars($editing['address'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"
                    placeholder="Describe the property — facilities, nearby landmarks, rules, etc."><?= htmlspecialchars($editing['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_available" value="1"
                           <?= ($editing['is_available'] ?? 1) ? 'checked' : '' ?>>
                    &nbsp;Mark as Available (tenants can see and apply)
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <img src="/rental_risk/images/save.png" class="inline-icon">
                    <?= $editing ? 'Update Property' : 'Add Property' ?>
                </button>
                <?php if ($editing): ?>
                    <a href="manage_properties.php" class="btn btn-secondary">Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- PROPERTY LISTINGS TABLE -->
    <div class="card">
        <h3>My Listed Properties (<?= count($properties) ?>)</h3>

        <?php if (empty($properties)): ?>
            <p class="text-muted">You haven't listed any properties yet. Add one above.</p>
        <?php else: ?>
        <div class="table-scroll">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Address</th>
                    <th>Rent (NPR)</th>
                    <th>Bedrooms</th>
                    <th>Status</th>
                    <th>Applications</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($properties as $i => $p): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($p['title']) ?></strong></td>
                    <td><?= ucfirst($p['property_type']) ?></td>
                    <td><?= htmlspecialchars($p['address']) ?></td>
                    <td><?= number_format($p['monthly_rent'], 2) ?></td>
                    <td><?= $p['bedrooms'] ?></td>
                    <td>
                        <?php if ($p['is_available']): ?>
                            <span class="badge badge-success">Available</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Not Available</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="landlord_dashboard.php?property=<?= $p['id'] ?>" class="btn btn-sm btn-outline">
                            <?= $p['application_count'] ?> application(s)
                        </a>
                    </td>
                    <td>
                        <a href="manage_properties.php?edit=<?= $p['id'] ?>" class="btn btn-sm btn-secondary">
                            <img src="/rental_risk/images/pencil.png" class="inline-icon"> Edit
                        </a>
                        <a href="manage_properties.php?delete=<?= $p['id'] ?>"
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Delete this property? All its applications will also be removed.')">
                            <img src="/rental_risk/images/cross.png" class="inline-icon"> Delete
                        </a>
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
