<?php
$role = $_SESSION['role'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']);

$nav = [
    'admin' => [
        ['icon'=>'bi-speedometer2','label'=>'Dashboard',    'href'=>'dashboard.php'],
        ['icon'=>'bi-people-fill','label'=>'Users',          'href'=>'users.php', 'sub'=>[
            ['label'=>'All Users',       'href'=>'users.php'],
            ['label'=>'Add Student',     'href'=>'add_student.php'],
            ['label'=>'Add Teacher',     'href'=>'add_teacher.php'],
            ['label'=>'Add Coordinator', 'href'=>'add_coordinator.php'],
        ]],
        ['icon'=>'bi-building','label'=>'Classes',           'href'=>'classes.php'],
        ['icon'=>'bi-book-fill','label'=>'Courses',          'href'=>'courses.php'],
        ['icon'=>'bi-link-45deg','label'=>'Assignments',     'href'=>'assignments.php'],
        ['icon'=>'bi-file-earmark-check','label'=>'Lesson Plans','href'=>'lesson_plans.php'],
        ['icon'=>'bi-bar-chart-fill','label'=>'Reports',     'href'=>'reports.php'],
    ],
    'coordinator' => [
        ['icon'=>'bi-speedometer2','label'=>'Dashboard',     'href'=>'dashboard.php'],
        ['icon'=>'bi-calendar3','label'=>'Programs',         'href'=>'programs.php'],
        ['icon'=>'bi-clipboard-check','label'=>'Attendance', 'href'=>'attendance.php'],
        ['icon'=>'bi-star-fill','label'=>'Grades',           'href'=>'grades.php'],
        ['icon'=>'bi-people','label'=>'Students',            'href'=>'students.php'],
        ['icon'=>'bi-bar-chart','label'=>'Reports',          'href'=>'reports.php'],
    ],
    'teacher' => [
        ['icon'=>'bi-speedometer2','label'=>'Dashboard',     'href'=>'dashboard.php'],
        ['icon'=>'bi-megaphone-fill','label'=>'My Classes',  'href'=>'classes.php'],
        ['icon'=>'bi-calendar-event','label'=>'Agenda',      'href'=>'agenda.php'],
        ['icon'=>'bi-journal-text','label'=>'Lesson Plans',  'href'=>'lesson_plans.php'],
        
    ],
    'student' => [
        ['icon'=>'bi-speedometer2','label'=>'Dashboard',     'href'=>'dashboard.php'],
        ['icon'=>'bi-book','label'=>'My Courses',            'href'=>'courses.php'],
        ['icon'=>'bi-calendar-week','label'=>'My Schedule',  'href'=>'programs.php'],
        ['icon'=>'bi-calendar3','label'=>'Agenda',           'href'=>'agenda.php'],
        ['icon'=>'bi-check2-circle','label'=>'Attendance',   'href'=>'attendance.php'],
        ['icon'=>'bi-graph-up','label'=>'Grades',            'href'=>'grades.php'],
    ],
];

$links = $nav[$role] ?? [];
?>

<!-- Sidebar for desktop -->
<div class="omk-sidebar d-none d-lg-flex flex-column" id="mainSidebar">
    <div class="sidebar-user-info">
        <div class="user-avatar-lg bg-<?= ['admin'=>'danger','coordinator'=>'warning','teacher'=>'primary','student'=>'success'][$role]??'secondary' ?>">
            <?= strtoupper(substr($current_user['first_name'],0,1).substr($current_user['last_name'],0,1)) ?>
        </div>
        <div class="ms-2">
            <div class="fw-semibold text-white small"><?= sanitize($current_user['first_name'].' '.$current_user['last_name']) ?></div>
            <div class="text-white-50" style="font-size:11px"><?= sanitize($current_user['email']) ?></div>
        </div>
    </div>

    <nav class="sidebar-nav flex-grow-1">
        <?php foreach ($links as $item): ?>
        <?php if (isset($item['sub'])): ?>
        <div class="sidebar-group">
            <a class="sidebar-link <?= $current_page == $item['href'] ? 'active' : '' ?>" data-bs-toggle="collapse" href="#nav_<?= md5($item['label']) ?>">
                <i class="bi <?= $item['icon'] ?>"></i>
                <span><?= $item['label'] ?></span>
                <i class="bi bi-chevron-down ms-auto small"></i>
            </a>
            <div class="collapse <?= in_array($current_page, array_column($item['sub'], 'href')) ? 'show' : '' ?>" id="nav_<?= md5($item['label']) ?>">
                <?php foreach ($item['sub'] as $sub): ?>
                <a class="sidebar-sublink <?= $current_page == $sub['href'] ? 'active' : '' ?>" href="<?= BASE_URL ?>/<?= $role ?>/<?= $sub['href'] ?>">
                    <i class="bi bi-dot"></i> <?= $sub['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <a class="sidebar-link <?= $current_page == $item['href'] ? 'active' : '' ?>" href="<?= BASE_URL ?>/<?= $role ?>/<?= $item['href'] ?>">
            <i class="bi <?= $item['icon'] ?>"></i>
            <span><?= $item['label'] ?></span>
        </a>
        <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>/api/logout.php" class="sidebar-link text-danger-emphasis">
            <i class="bi bi-box-arrow-right"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start omk-offcanvas" id="sidebarOffcanvas" tabindex="-1">
    <div class="offcanvas-header border-bottom border-white border-opacity-10">
        <div class="d-flex align-items-center gap-2">
            <div class="brand-icon small-icon"><i class="bi bi-mortarboard-fill"></i></div>
            <span class="fw-bold text-white">OMK School</span>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="sidebar-user-info">
            <div class="user-avatar-lg bg-<?= ['admin'=>'danger','coordinator'=>'warning','teacher'=>'primary','student'=>'success'][$role]??'secondary' ?>">
                <?= strtoupper(substr($current_user['first_name'],0,1).substr($current_user['last_name'],0,1)) ?>
            </div>
            <div class="ms-2">
                <div class="fw-semibold text-white small"><?= sanitize($current_user['first_name'].' '.$current_user['last_name']) ?></div>
                <div class="text-white-50" style="font-size:11px"><?= ucfirst($role) ?></div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <?php foreach ($links as $item): ?>
            <a class="sidebar-link <?= $current_page == $item['href'] ? 'active' : '' ?>" href="<?= BASE_URL ?>/<?= $role ?>/<?= $item['href'] ?>">
                <i class="bi <?= $item['icon'] ?>"></i>
                <span><?= $item['label'] ?></span>
            </a>
            <?php endforeach; ?>
            <a href="<?= BASE_URL ?>/api/logout.php" class="sidebar-link text-danger-emphasis mt-2">
                <i class="bi bi-box-arrow-right"></i><span>Logout</span>
            </a>
        </nav>
    </div>
</div>
