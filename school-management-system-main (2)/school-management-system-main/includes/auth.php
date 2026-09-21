<?php
require_once __DIR__ . '/config.php';

function startSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn() {
    startSecureSession();
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function requireLogin($allowed_roles = []) {
    startSecureSession();
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php?msg=login_required');
        exit;
    }
    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        header('Location: ' . BASE_URL . '/index.php?msg=unauthorized');
        exit;
    }
}

function getCurrentUser() {
    startSecureSession();
    if (!isLoggedIn()) return null;
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function login($email, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        startSecureSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['name']    = $user['first_name'] . ' ' . $user['last_name'];
        return $user;
    }
    return false;
}

function logout() {
    startSecureSession();
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php?msg=logged_out');
    exit;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function generateStudentEmail($first, $last, $id) {
    $f = strtolower(substr(trim($first), 0, 1));
    $l = strtolower(substr(trim($last), 0, 1));
    return $f . $l . $id . '@omk.edu';
}

function generateStaffEmail($first, $id) {
    return strtolower(trim($first)) . $id . '@omk.edu';
}

function generateStudentPassword($first, $last) {
    return strtolower(trim($first)) . strtolower(trim($last)) . '123';
}

function generateStaffPassword($first, $last) {
    return strtolower(trim($first)) . strtolower(trim($last)) . '333';
}

function addNotification($user_id, $title, $message, $type = 'info', $link = '') {
    $db = getDB();
    $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?,?,?,?,?)")
       ->execute([$user_id, $title, $message, $type, $link]);
}

function getUnreadNotifications($user_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return $stmt->fetch()['cnt'];
}

function sanitize($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

function redirectToDashboard($role) {
    $map = [
        'admin'       => BASE_URL . '/admin/dashboard.php',
        'coordinator' => BASE_URL . '/coordinator/dashboard.php',
        'teacher'     => BASE_URL . '/teacher/dashboard.php',
        'student'     => BASE_URL . '/student/dashboard.php',
    ];
    header('Location: ' . ($map[$role] ?? BASE_URL . '/index.php'));
    exit;
}
