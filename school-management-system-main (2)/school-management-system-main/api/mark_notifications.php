<?php
require_once '../includes/auth.php';
requireLogin();
$db = getDB();
$db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$_SESSION['user_id']]);
$ref = $_SERVER['HTTP_REFERER'] ?? BASE_URL.'/index.php';
header('Location: '.$ref);
exit;
