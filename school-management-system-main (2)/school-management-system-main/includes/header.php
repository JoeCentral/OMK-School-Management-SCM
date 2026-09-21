<?php
require_once __DIR__ . '/auth.php';
$current_user = getCurrentUser();
$unread = $current_user ? getUnreadNotifications($current_user['id']) : 0;
$role = $_SESSION['role'] ?? '';
$dashLink = [
    'admin'       => BASE_URL.'/admin/dashboard.php',
    'coordinator' => BASE_URL.'/coordinator/dashboard.php',
    'teacher'     => BASE_URL.'/teacher/dashboard.php',
    'student'     => BASE_URL.'/student/dashboard.php',
][$role] ?? BASE_URL.'/index.php';

$roleColor = [
    'admin'       => 'danger',
    'coordinator' => 'warning',
    'teacher'     => 'primary',
    'student'     => 'success',
][$role] ?? 'secondary';

$roleIcon = [
    'admin'       => 'bi-shield-fill',
    'coordinator' => 'bi-diagram-3-fill',
    'teacher'     => 'bi-person-video3',
    'student'     => 'bi-mortarboard-fill',
][$role] ?? 'bi-person-fill';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? sanitize($page_title).' — ' : '' ?>OMK School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/style.css" rel="stylesheet">
    <?= isset($extra_head) ? $extra_head : '' ?>
</head>
<body>

<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg omk-navbar fixed-top">
    <div class="container-fluid px-3">
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $dashLink ?>">
            <div class="brand-icon">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
            <div class="d-none d-sm-block">
                <span class="brand-text">OMK</span>
                <span class="brand-sub">School</span>
            </div>
        </a>

        <!-- Mobile toggle -->
        <button class="navbar-toggler border-0 me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
            <i class="bi bi-list fs-4 text-white"></i>
        </button>

        <!-- Right side -->
        <div class="d-flex align-items-center gap-2 ms-auto">
            <!-- Role badge -->
            <span class="badge bg-<?= $roleColor ?> d-none d-md-inline-flex align-items-center gap-1 px-3 py-2">
                <i class="bi <?= $roleIcon ?>"></i> <?= ucfirst($role) ?>
            </span>

            <!-- Notifications -->
            <div class="dropdown">
                <button class="btn btn-icon position-relative" data-bs-toggle="dropdown">
                    <i class="bi bi-bell-fill text-white fs-5"></i>
                    <?php if ($unread > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:9px"><?= $unread ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notif-dropdown shadow-lg p-0">
                    <div class="notif-header px-3 py-2 d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Notifications</span>
                        <?php if ($unread > 0): ?>
                        <a href="<?= BASE_URL ?>/api/mark_notifications.php" class="small text-primary">Mark all read</a>
                        <?php endif; ?>
                    </div>
                    <?php
                    $db = getDB();
                    $notifs = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 8");
                    $notifs->execute([$current_user['id']]);
                    $notif_list = $notifs->fetchAll();
                    if (empty($notif_list)):
                    ?>
                    <div class="text-center py-4 text-muted small"><i class="bi bi-bell-slash fs-4 d-block mb-1"></i>No notifications</div>
                    <?php else: foreach ($notif_list as $n): ?>
                    <a href="<?= $n['link'] ?: '#' ?>" class="notif-item d-flex gap-2 px-3 py-2 text-decoration-none <?= $n['is_read'] ? '' : 'unread' ?>">
                        <div class="notif-dot bg-<?= $n['type'] ?>"></div>
                        <div>
                            <div class="small fw-semibold text-dark"><?= sanitize($n['title']) ?></div>
                            <div class="smaller text-muted"><?= sanitize(substr($n['message'],0,60)) ?>...</div>
                            <div class="smaller text-muted"><?= date('M d, g:i A', strtotime($n['created_at'])) ?></div>
                        </div>
                    </a>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- User menu -->
            <div class="dropdown">
                <button class="btn btn-icon d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                    <div class="user-avatar-sm bg-<?= $roleColor ?>">
                        <?= strtoupper(substr($current_user['first_name'],0,1).substr($current_user['last_name'],0,1)) ?>
                    </div>
                    <span class="d-none d-lg-inline text-white small fw-medium"><?= sanitize($current_user['first_name']) ?></span>
                    <i class="bi bi-chevron-down text-white-50 small d-none d-lg-inline"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end shadow">
                    <div class="dropdown-header">
                        <div class="fw-semibold"><?= sanitize($current_user['first_name'].' '.$current_user['last_name']) ?></div>
                        <div class="small text-muted"><?= sanitize($current_user['email']) ?></div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="<?= BASE_URL ?>/<?= $role ?>/profile.php"><i class="bi bi-person-circle me-2"></i>My Profile</a>
                    <a class="dropdown-item" href="<?= BASE_URL ?>/<?= $role ?>/settings.php"><i class="bi bi-gear me-2"></i>Settings</a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/api/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
                </div>
            </div>
        </div>
    </div>
</nav>
