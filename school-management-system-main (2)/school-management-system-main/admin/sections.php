<?php
require_once '../includes/auth.php';
requireLogin(['admin']);
header('Location: classes.php');
exit;