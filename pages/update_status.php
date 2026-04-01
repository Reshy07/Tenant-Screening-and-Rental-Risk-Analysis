<?php
require_once '../php/auth.php';
require_once '../php/config.php';
startSecureSession();
requireAnyRole(['landlord','admin']);

$db = getDB();
$app_id  = intval($_GET['id'] ?? 0);
$status  = $_GET['status'] ?? '';
$redirect_view = isset($_GET['redirect']) && $_GET['redirect'] === 'view';

$allowed = ['pending','approved','rejected','under_review'];
if (!$app_id || !in_array($status, $allowed)) {
    header('Location: landlord_dashboard.php?error=Invalid+request');
    exit;
}

$stmt = $db->prepare("UPDATE applications SET status=?, landlord_id=? WHERE id=?");
$stmt->bind_param("sii", $status, $_SESSION['user_id'], $app_id);
$stmt->execute();
$stmt->close();

if ($redirect_view) {
    header("Location: view_application.php?id=$app_id&msg=Status+updated");
} elseif ($_SESSION['role'] === 'admin') {
    header("Location: admin_dashboard.php?msg=Status+updated");
} else {
    header("Location: landlord_dashboard.php?msg=Application+status+updated");
}
exit;
?>
