<?php
// ============================================================
// Authentication Helper Functions
// ============================================================

require_once __DIR__ . '/config.php';

function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn() {
    startSecureSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /rental_risk/index.php?error=Please+log+in+first');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: /rental_risk/index.php?error=Access+denied');
        exit;
    }
}

function requireAnyRole(array $roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: /rental_risk/index.php?error=Access+denied');
        exit;
    }
}

function loginUser($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username, password, role, is_active FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) return ['success' => false, 'message' => 'Invalid username or password.'];
    if (!$user['is_active']) return ['success' => false, 'message' => 'Your account has been deactivated. Contact admin.'];
    if (!password_verify($password, $user['password'])) return ['success' => false, 'message' => 'Invalid username or password.'];

    startSecureSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    return ['success' => true, 'role' => $user['role']];
}

function logoutUser() {
    startSecureSession();
    $_SESSION = [];
    session_destroy();
    header('Location: /rental_risk/index.php?msg=Logged+out+successfully');
    exit;
}

function registerUser($username, $email, $password, $role) {
    $db = getDB();

    // Check duplicate
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        return ['success' => false, 'message' => 'Username or email already exists.'];
    }
    $stmt->close();

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $email, $hashed, $role);
    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        $stmt->close();
        return ['success' => true, 'user_id' => $new_id];
    }
    $stmt->close();
    return ['success' => false, 'message' => 'Registration failed. Try again.'];
}
?>
