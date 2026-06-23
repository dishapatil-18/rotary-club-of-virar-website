<?php
require_once __DIR__ . '/includes/session_security.php';
secureSessionStart();
require_once __DIR__ . '/includes/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

$email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Rate limiting (simple session-based) — separate key from login.php
if (!isset($_SESSION['ajax_login_attempts'])) {
    $_SESSION['ajax_login_attempts'] = 0;
}
if ($_SESSION['ajax_login_attempts'] >= 10) {
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Try again later.']);
    exit;
}

$stmt = $conn->prepare("SELECT admin_id, name, email, password, role, photo_url FROM admins WHERE email = ? AND status = 'active'");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 1) {
    $admin = $res->fetch_assoc();

    if (password_verify($password, $admin['password'])) {

        $_SESSION['ajax_login_attempts'] = 0;

        regenerateSession();

        $_SESSION['admin_id']     = $admin['admin_id'];
        $_SESSION['admin_name']   = $admin['name'];
        $_SESSION['admin_role']   = $admin['role'];
        $_SESSION['admin_photo']  = $admin['photo_url'];
        $_SESSION['is_logged_in'] = true;
        $_SESSION['last_activity'] = time();

        echo json_encode(['success' => true]);
        exit;
    }
}

$_SESSION['ajax_login_attempts']++;
echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
