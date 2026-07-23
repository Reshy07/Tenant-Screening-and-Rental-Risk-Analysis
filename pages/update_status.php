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

$sql = "UPDATE applications SET status=?, landlord_id=? WHERE id=?";
if ($_SESSION['role'] !== 'admin') {
    $sql .= " AND landlord_id = ?";
}

$stmt = $db->prepare($sql);
if ($_SESSION['role'] === 'admin') {
    $stmt->bind_param("sii", $status, $_SESSION['user_id'], $app_id);
} else {
    $stmt->bind_param("siii", $status, $_SESSION['user_id'], $app_id, $_SESSION['user_id']);
}
$stmt->execute();

if ($stmt->affected_rows === 0 && $_SESSION['role'] !== 'admin') {
    $stmt->close();
    header('Location: landlord_dashboard.php?error=Not+authorized+to+update+this+application');
    exit;
}

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
