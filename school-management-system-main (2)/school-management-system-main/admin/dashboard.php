<?php
require_once '../includes/auth.php';
requireLogin(['admin']);
$page_title = 'Admin Dashboard';
$db = getDB();
$current_user = getCurrentUser();

// Stats
$stats = [
    'students'     => $db->query("SELECT COUNT(*) FROM users WHERE role='student' AND is_active=1")->fetchColumn(),
    'teachers'     => $db->query("SELECT COUNT(*) FROM users WHERE role='teacher' AND is_active=1")->fetchColumn(),
    'coordinators' => $db->query("SELECT COUNT(*) FROM users WHERE role='coordinator' AND is_active=1")->fetchColumn(),
    'classes'      => $db->query("SELECT COUNT(*) FROM classes")->fetchColumn(),
    'sections'     => $db->query("SELECT COUNT(*) FROM sections")->fetchColumn(),
    'courses'      => $db->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
];

// Recent users
$recent_users = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 8")->fetchAll();

// Pending lesson plans
$pending = $db->query("
    SELECT lp.*, u.first_name, u.last_name, c.name as course_name, s.name as section_name, cl.name as class_name
    FROM lesson_preparations lp
    JOIN users u ON u.id = lp.teacher_id
    JOIN courses c ON c.id = lp.course_id
    JOIN sections s ON s.id = lp.section_id
    JOIN classes cl ON cl.id = s.class_id
    WHERE lp.status = 'pending'
    ORDER BY lp.created_at DESC LIMIT 5
")->fetchAll();

include '../includes/header.php';
?>

<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<!-- Page Header -->
<div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</h4>
            <p>Welcome back, <?= sanitize($current_user['first_name']) ?>! Here's your school overview.</p>
        </div>
        <div class="text-white-50 small"><i class="bi bi-calendar3 me-1"></i><?= date('l, F j, Y') ?></div>
    </div>
</div>

<!-- Stats Grid -->
<div class="row g-3 mb-4">
    <?php
    $stats_display = [
        ['label'=>'Students',     'key'=>'students',     'icon'=>'bi-mortarboard-fill', 'bg'=>'linear-gradient(135deg,#10b981,#059669)'],
        ['label'=>'Teachers',     'key'=>'teachers',     'icon'=>'bi-person-video3',    'bg'=>'linear-gradient(135deg,#3b82f6,#1d4ed8)'],
        ['label'=>'Coordinators', 'key'=>'coordinators', 'icon'=>'bi-diagram-3-fill',   'bg'=>'linear-gradient(135deg,#f59e0b,#d97706)'],
        ['label'=>'Classes',      'key'=>'classes',      'icon'=>'bi-building',         'bg'=>'linear-gradient(135deg,#8b5cf6,#6d28d9)'],
        ['label'=>'Sections',     'key'=>'sections',     'icon'=>'bi-diagram-2',        'bg'=>'linear-gradient(135deg,#ec4899,#be185d)'],
        ['label'=>'Courses',      'key'=>'courses',      'icon'=>'bi-book-fill',        'bg'=>'linear-gradient(135deg,#14b8a6,#0f766e)'],
    ];
    foreach ($stats_display as $s):
    ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card" style="background:<?= $s['bg'] ?>">
            <div class="stat-label"><?= $s['label'] ?></div>
            <div class="stat-num"><?= $stats[$s['key']] ?></div>
            <i class="bi <?= $s['icon'] ?> stat-icon"></i>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Pending Lesson Plans -->
    <div class="col-lg-6">
        <div class="omk-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-check text-warning me-2"></i>Pending Lesson Plans</span>
                <a href="lesson_plans.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pending)): ?>
                <div class="text-center py-5 text-muted"><i class="bi bi-check-all fs-2 d-block mb-2 text-success"></i>All plans reviewed!</div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($pending as $p): ?>
                    <div class="list-group-item d-flex align-items-start gap-3 py-3">
                        <div class="flex-grow-1">
                            <div class="fw-semibold small"><?= sanitize($p['title']) ?></div>
                            <div class="text-muted smaller">
                                <?= sanitize($p['first_name'].' '.$p['last_name']) ?> •
                                <?= sanitize($p['course_name']) ?> •
                                <?= sanitize($p['class_name'].'-'.$p['section_name']) ?>
                            </div>
                            <div class="text-muted smaller"><?= date('M d, Y', strtotime($p['created_at'])) ?></div>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="lesson_plans.php?action=approve&id=<?= $p['id'] ?>" class="btn btn-sm btn-success btn-icon-sm" title="Approve" data-bs-toggle="tooltip"><i class="bi bi-check-lg"></i></a>
                            <a href="lesson_plans.php?action=reject&id=<?= $p['id'] ?>" class="btn btn-sm btn-danger btn-icon-sm" title="Reject" data-bs-toggle="tooltip"><i class="bi bi-x-lg"></i></a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Users -->
    <div class="col-lg-6">
        <div class="omk-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people text-primary me-2"></i>Recent Users</span>
                <a href="users.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table omk-table mb-0">
                        <thead><tr><th>Name</th><th>Role</th><th>Email</th><th>Joined</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent_users as $u): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-sm bg-<?= ['admin'=>'danger','coordinator'=>'warning','teacher'=>'primary','student'=>'success'][$u['role']] ?>">
                                        <?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?>
                                    </div>
                                    <span class="fw-medium small"><?= sanitize($u['first_name'].' '.$u['last_name']) ?></span>
                                </div>
                            </td>
                            <td><span class="badge bg-<?= ['admin'=>'danger','coordinator'=>'warning','teacher'=>'primary','student'=>'success'][$u['role']] ?>-subtle text-<?= ['admin'=>'danger','coordinator'=>'warning','teacher'=>'primary','student'=>'success'][$u['role']] ?>"><?= ucfirst($u['role']) ?></span></td>
                            <td class="text-muted small"><?= sanitize($u['email']) ?></td>
                            <td class="text-muted smaller"><?= date('M d', strtotime($u['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

</div></div><!-- end main-content / wrapper -->

<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
</body></html>
