<?php
require_once '../php/auth.php';
require_once '../php/config.php';
startSecureSession();
requireRole('tenant');

$db = getDB();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';
$is_new = isset($_GET['new']);

// Load existing profile if any
$stmt = $db->prepare("SELECT * FROM tenants WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name         = trim($_POST['full_name'] ?? '');
    $age               = intval($_POST['age'] ?? 0);
    $contact           = trim($_POST['contact'] ?? '');
    $address           = trim($_POST['address'] ?? '');
    $monthly_income    = floatval($_POST['monthly_income'] ?? 0);
    $employment_status = $_POST['employment_status'] ?? '';
    $rental_history    = intval($_POST['rental_history_months'] ?? 0);
    $reference_text    = trim($_POST['reference_text'] ?? '');
    $guarantor_info    = trim($_POST['guarantor_info'] ?? '');

    $valid_emp = ['employed','self_employed','unemployed','student','student_funded'];

    if (empty($full_name) || $age < 15 || empty($contact) || empty($address) || $monthly_income <= 0 || !in_array($employment_status, $valid_emp)) {
        $error = 'Please fill all required fields correctly. Age must be 15+.';
    } else {
        // Create uploads folder if needed
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

        // Handle file uploads
        $id_proof_path         = $profile['id_proof_path'] ?? null;
        $income_proof_path     = $profile['income_proof_path'] ?? null;
        $employment_proof_path = $profile['employment_proof_path'] ?? null;

        $allowed_types = ['image/jpeg','image/png','application/pdf'];
        $max_size = 2 * 1024 * 1024; // 2MB

        foreach (['id_proof','income_proof','employment_proof'] as $field) {
            if (!empty($_FILES[$field]['name'])) {
                $file = $_FILES[$field];
                if ($file['size'] > $max_size) {
                    $error = "File $field exceeds 2MB limit.";
                    break;
                }
                if (!in_array($file['type'], $allowed_types)) {
                    $error = "File $field must be JPG, PNG or PDF.";
                    break;
                }
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $fname = $user_id . '_' . $field . '_' . time() . '.' . $ext;
                $dest = UPLOAD_DIR . $fname;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    ${$field . '_path'} = UPLOAD_URL . $fname;
                } else {
                    $error = "Failed to upload $field.";
                    break;
                }
            }
        }

        if (empty($error)) {
            if ($profile) {
                // Update existing profile
                $stmt = $db->prepare(
                    "UPDATE tenants SET full_name=?, age=?, contact=?, address=?, monthly_income=?,
                     employment_status=?, rental_history_months=?, reference_text=?, guarantor_info=?,
                     id_proof_path=?, income_proof_path=?, employment_proof_path=?
                     WHERE user_id=?"
                );
                $stmt->bind_param("siisdssisssi",
                    $full_name, $age, $contact, $address, $monthly_income,
                    $employment_status, $rental_history, $reference_text, $guarantor_info,
                    $id_proof_path, $income_proof_path, $employment_proof_path, $user_id
                );
            } else {
                // Insert new profile
                $stmt = $db->prepare(
                    "INSERT INTO tenants (user_id, full_name, age, contact, address, monthly_income,
                     employment_status, rental_history_months, reference_text, guarantor_info,
                     id_proof_path, income_proof_path, employment_proof_path)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
                );
                $stmt->bind_param("isiisdsisssss",
                    $user_id, $full_name, $age, $contact, $address, $monthly_income,
                    $employment_status, $rental_history, $reference_text, $guarantor_info,
                    $id_proof_path, $income_proof_path, $employment_proof_path
                );
            }
            if ($stmt->execute()) {
                $stmt->close();
                header('Location: tenant_dashboard.php?msg=Profile+saved+successfully');
                exit;
            }
            $error = 'Failed to save profile: ' . $db->error;
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Tenant</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<?php include '../php/navbar.php'; ?>
<div class="container">
    <h2><?= $is_new ? '<img src="/rental_risk/images/user.png" class="heading-icon"> Welcome! Complete Your Profile' : '<img src="/rental_risk/images/pencil.png" class="heading-icon"> Edit My Profile' ?></h2>
    <?php if ($is_new): ?>
        <div class="alert alert-info">Please fill in your details to apply for rental properties.</div>
    <?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="profileForm">
        <div class="form-section">
            <h3>Personal Information</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($profile['full_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Age *</label>
                    <input type="number" name="age" min="15" max="100" value="<?= $profile['age'] ?? '' ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Contact Number *</label>
                    <input type="text" name="contact" value="<?= htmlspecialchars($profile['contact'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Monthly Income (NPR) *</label>
                    <input type="number" name="monthly_income" min="1" step="0.01"
                           value="<?= $profile['monthly_income'] ?? '' ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Address *</label>
                <textarea name="address" rows="2" required><?= htmlspecialchars($profile['address'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h3>Employment & Rental History</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Employment Status *</label>
                    <select name="employment_status" required>
                        <option value="">-- Select --</option>
                        <?php foreach([
                            'employed'       => 'Employed',
                            'self_employed'  => 'Self-Employed',
                            'student'        => 'Student (self-funded)',
                            'student_funded' => 'Student (Parent/Guardian funded)',
                            'unemployed'     => 'Unemployed',
                        ] as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= ($profile['employment_status'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Rental History (months) *</label>
                    <input type="number" name="rental_history_months" min="0"
                           value="<?= $profile['rental_history_months'] ?? 0 ?>" required>
                    <small>Enter 0 if you are a first-time renter.</small>
                </div>
            </div>
            <div class="form-group">
                <label>Previous Landlord Reference</label>
                <textarea name="reference_text" rows="3" placeholder="Name, contact number, brief note..."><?= htmlspecialchars($profile['reference_text'] ?? '') ?></textarea>
            </div>
            <div class="form-group" id="guarantorField">
                <label>Guarantor Information <span style="color:#e74c3c">*</span></label>
                <textarea name="guarantor_info" rows="3"
                    placeholder="Parent/Guardian full name, contact number, relationship e.g. Father - Ram Sharma, 9841000000"><?= htmlspecialchars($profile['guarantor_info'] ?? '') ?></textarea>
                <small>Required if you selected <strong>Student (Parent/Guardian funded)</strong>. The guarantor agrees to cover rent if you cannot pay.</small>
            </div>
        </div>

        <div class="form-section">
            <h3>Document Upload (JPG, PNG, PDF — max 2MB each)</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>ID Proof (Citizenship/Passport)</label>
                    <?php if (!empty($profile['id_proof_path'])): ?>
                        <p><a href="../<?= htmlspecialchars($profile['id_proof_path']) ?>" target="_blank"><img src="/rental_risk/images/document.png" class="inline-icon"> View current</a></p>
                    <?php endif; ?>
                    <input type="file" name="id_proof" accept=".jpg,.jpeg,.png,.pdf">
                </div>
                <div class="form-group">
                    <label>Income Proof (Salary slip / Bank statement)</label>
                    <?php if (!empty($profile['income_proof_path'])): ?>
                        <p><a href="../<?= htmlspecialchars($profile['income_proof_path']) ?>" target="_blank"><img src="/rental_risk/images/document.png" class="inline-icon"> View current</a></p>
                    <?php endif; ?>
                    <input type="file" name="income_proof" accept=".jpg,.jpeg,.png,.pdf">
                </div>
                <div class="form-group">
                    <label>Employment Letter</label>
                    <?php if (!empty($profile['employment_proof_path'])): ?>
                        <p><a href="../<?= htmlspecialchars($profile['employment_proof_path']) ?>" target="_blank"><img src="/rental_risk/images/document.png" class="inline-icon"> View current</a></p>
                    <?php endif; ?>
                    <input type="file" name="employment_proof" accept=".jpg,.jpeg,.png,.pdf">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><img src="/rental_risk/images/save.png" class="inline-icon"> Save Profile</button>
            <a href="tenant_dashboard.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
<script src="../js/script.js"></script>
</body>
</html>
