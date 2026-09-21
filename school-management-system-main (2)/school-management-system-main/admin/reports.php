<?php
require_once '../includes/auth.php';
requireLogin(['admin']);
$page_title = 'Reports';
$db = getDB();
$current_user = getCurrentUser();

// Overall stats
$total_students     = $db->query("SELECT COUNT(*) FROM users WHERE role='student' AND is_active=1")->fetchColumn();
$total_teachers     = $db->query("SELECT COUNT(*) FROM users WHERE role='teacher' AND is_active=1")->fetchColumn();
$total_coordinators = $db->query("SELECT COUNT(*) FROM users WHERE role='coordinator' AND is_active=1")->fetchColumn();
$total_classes      = $db->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$total_sections     = $db->query("SELECT COUNT(*) FROM sections")->fetchColumn();
$total_courses      = $db->query("SELECT COUNT(*) FROM courses")->fetchColumn();
$total_posts        = $db->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$total_plans        = $db->query("SELECT COUNT(*) FROM lesson_preparations")->fetchColumn();
$signed_plans       = $db->query("SELECT COUNT(*) FROM lesson_preparations WHERE status='signed'")->fetchColumn();
$pending_plans      = $db->query("SELECT COUNT(*) FROM lesson_preparations WHERE status='pending'")->fetchColumn();

// Attendance overall
$att_present = $db->query("SELECT COUNT(*) FROM attendance WHERE status='present'")->fetchColumn();
$att_absent  = $db->query("SELECT COUNT(*) FROM attendance WHERE status='absent'")->fetchColumn();
$att_late    = $db->query("SELECT COUNT(*) FROM attendance WHERE status='late'")->fetchColumn();
$att_total   = $att_present + $att_absent + $att_late;
$att_pct     = $att_total > 0 ? round($att_present / $att_total * 100) : 0;

// Students per section
$section_stats = $db->query("
    SELECT cl.name as class_name, s.name as sec_name,
           COUNT(ss.student_id) as student_count,
           s.max_students
    FROM sections s
    JOIN classes cl ON cl.id = s.class_id
    LEFT JOIN student_sections ss ON ss.section_id = s.id
    GROUP BY s.id
    ORDER BY cl.grade_level, s.name
")->fetchAll();

// Lesson plans by status
$plan_stats = $db->query("SELECT status, COUNT(*) as cnt FROM lesson_preparations GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

// Attendance by section (last 30 days)
$att_by_section = $db->query("
    SELECT cl.name as class_name, s.name as sec_name,
           SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) as present,
           SUM(CASE WHEN a.status='absent'  THEN 1 ELSE 0 END) as absent,
           COUNT(*) as total
    FROM attendance a
    JOIN sections s ON s.id = a.section_id
    JOIN classes cl ON cl.id = s.class_id
    WHERE a.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY s.id
    ORDER BY cl.grade_level, s.name
")->fetchAll();

// Top teachers by posts
$top_teachers = $db->query("
    SELECT u.first_name, u.last_name, COUNT(p.id) as post_count
    FROM users u LEFT JOIN posts p ON p.teacher_id = u.id
    WHERE u.role = 'teacher' AND u.is_active = 1
    GROUP BY u.id ORDER BY post_count DESC LIMIT 5
")->fetchAll();

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4><i class="bi bi-bar-chart-fill me-2"></i>School Reports</h4>
            <p>Complete overview of school activity and performance.</p>
        </div>
        <div class="text-white-50 small"><i class="bi bi-calendar me-1"></i>Generated: <?= date('M d, Y g:i A') ?></div>
    </div>
</div>

<!-- Main stats -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Students','students',$total_students,'bi-mortarboard-fill','linear-gradient(135deg,#10b981,#059669)'],
        ['Teachers','teachers',$total_teachers,'bi-person-video3','linear-gradient(135deg,#3b82f6,#1d4ed8)'],
        ['Coordinators','coord',$total_coordinators,'bi-diagram-3-fill','linear-gradient(135deg,#f59e0b,#d97706)'],
        ['Classes','classes',$total_classes,'bi-building','linear-gradient(135deg,#8b5cf6,#6d28d9)'],
        ['Sections','sections',$total_sections,'bi-diagram-2','linear-gradient(135deg,#ec4899,#be185d)'],
        ['Courses','courses',$total_courses,'bi-book-fill','linear-gradient(135deg,#14b8a6,#0f766e)'],
    ] as [$label,,$val,$icon,$bg]): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card" style="background:<?= $bg ?>">
            <div class="stat-label"><?= $label ?></div>
            <div class="stat-num"><?= $val ?></div>
            <i class="bi <?= $icon ?> stat-icon"></i>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
    <!-- Attendance summary -->
    <div class="col-lg-4">
        <div class="omk-card h-100">
            <div class="card-header"><i class="bi bi-check2-circle me-2 text-success"></i>Overall Attendance</div>
            <div class="card-body text-center">
                <div class="fw-800 mb-1" style="font-size:48px;color:<?= $att_pct>=75?'#10b981':'#ef4444' ?>"><?= $att_pct ?>%</div>
                <div class="text-muted small mb-3">Attendance Rate (all time)</div>
                <div class="omk-progress mb-3">
                    <div class="omk-progress-bar" style="width:<?= $att_pct ?>%;background:<?= $att_pct>=75?'linear-gradient(90deg,#10b981,#34d399)':'linear-gradient(90deg,#ef4444,#f87171)' ?>"></div>
                </div>
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="fw-700 text-success fs-5"><?= $att_present ?></div>
                        <div class="text-muted smaller">Present</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-700 text-danger fs-5"><?= $att_absent ?></div>
                        <div class="text-muted smaller">Absent</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-700 text-warning fs-5"><?= $att_late ?></div>
                        <div class="text-muted smaller">Late</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lesson plans summary -->
    <div class="col-lg-4">
        <div class="omk-card h-100">
            <div class="card-header"><i class="bi bi-journal-check me-2 text-primary"></i>Lesson Plans</div>
            <div class="card-body">
                <div class="fw-800 fs-1 text-center text-primary mb-1"><?= $total_plans ?></div>
                <div class="text-center text-muted small mb-3">Total Plans Submitted</div>
                <?php foreach ([['signed','success','Signed/Approved'],['pending','warning','Pending Review'],['rejected','danger','Rejected']] as [$s,$c,$l]): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small"><?= $l ?></span>
                    <span class="badge bg-<?= $c ?>-subtle text-<?= $c ?> fw-bold"><?= $plan_stats[$s] ?? 0 ?></span>
                </div>
                <div class="omk-progress mb-3" style="height:6px">
                    <div class="omk-progress-bar" style="width:<?= $total_plans>0?round(($plan_stats[$s]??0)/$total_plans*100):0 ?>%;background:var(--bs-<?= $c ?>)"></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Content activity -->
    <div class="col-lg-4">
        <div class="omk-card h-100">
            <div class="card-header"><i class="bi bi-activity me-2 text-warning"></i>Content Activity</div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div class="stat-card d-flex align-items-center justify-content-center" style="width:36px;height:36px;padding:0;background:linear-gradient(135deg,#4f46e5,#6366f1);border-radius:10px">
                            <i class="bi bi-chat-square-text text-white small"></i>
                        </div>
                        <span class="small fw-semibold">Total Posts</span>
                    </div>
                    <span class="fw-800 fs-4"><?= $total_posts ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div class="stat-card d-flex align-items-center justify-content-center" style="width:36px;height:36px;padding:0;background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:10px">
                            <i class="bi bi-calendar-event text-white small"></i>
                        </div>
                        <span class="small fw-semibold">Agenda Events</span>
                    </div>
                    <span class="fw-800 fs-4"><?= $db->query("SELECT COUNT(*) FROM agenda")->fetchColumn() ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="stat-card d-flex align-items-center justify-content-center" style="width:36px;height:36px;padding:0;background:linear-gradient(135deg,#10b981,#059669);border-radius:10px">
                            <i class="bi bi-link text-white small"></i>
                        </div>
                        <span class="small fw-semibold">Teacher Assignments</span>
                    </div>
                    <span class="fw-800 fs-4"><?= $db->query("SELECT COUNT(*) FROM teacher_courses")->fetchColumn() ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Section enrollment -->
    <div class="col-lg-6">
        <div class="omk-card">
            <div class="card-header"><i class="bi bi-diagram-2 me-2 text-primary"></i>Section Enrollment</div>
            <div class="card-body p-0">
                <table class="table omk-table mb-0">
                    <thead><tr><th>Class</th><th>Section</th><th>Students</th><th>Capacity</th><th>Fill Rate</th></tr></thead>
                    <tbody>
                    <?php foreach ($section_stats as $s):
                        $pct = $s['max_students'] > 0 ? round($s['student_count'] / $s['max_students'] * 100) : 0;
                        $color = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success');
                    ?>
                    <tr>
                        <td class="small fw-semibold"><?= sanitize($s['class_name']) ?></td>
                        <td><span class="badge bg-primary-subtle text-primary">Sec <?= sanitize($s['sec_name']) ?></span></td>
                        <td class="fw-bold"><?= $s['student_count'] ?></td>
                        <td class="text-muted small"><?= $s['max_students'] ?></td>
                        <td style="min-width:120px">
                            <div class="d-flex align-items-center gap-2">
                                <div class="omk-progress flex-grow-1">
                                    <div class="omk-progress-bar" style="width:<?= $pct ?>%;background:var(--bs-<?= $color ?>)"></div>
                                </div>
                                <span class="smaller text-<?= $color ?> fw-semibold"><?= $pct ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Attendance by section -->
    <div class="col-lg-6">
        <div class="omk-card">
            <div class="card-header"><i class="bi bi-clipboard-data me-2 text-success"></i>Attendance by Section (Last 30 Days)</div>
            <div class="card-body p-0">
                <?php if (empty($att_by_section)): ?>
                <div class="text-center py-4 text-muted small">No attendance data yet.</div>
                <?php else: ?>
                <table class="table omk-table mb-0">
                    <thead><tr><th>Class</th><th>Section</th><th>Present</th><th>Absent</th><th>Rate</th></tr></thead>
                    <tbody>
                    <?php foreach ($att_by_section as $a):
                        $pct = $a['total'] > 0 ? round($a['present'] / $a['total'] * 100) : 0;
                        $color = $pct >= 75 ? 'success' : 'danger';
                    ?>
                    <tr>
                        <td class="small fw-semibold"><?= sanitize($a['class_name']) ?></td>
                        <td><span class="badge bg-success-subtle text-success">Sec <?= sanitize($a['sec_name']) ?></span></td>
                        <td class="text-success fw-semibold"><?= $a['present'] ?></td>
                        <td class="text-danger fw-semibold"><?= $a['absent'] ?></td>
                        <td>
                            <span class="badge bg-<?= $color ?>-subtle text-<?= $color ?> fw-bold"><?= $pct ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top teachers -->
    <div class="col-lg-6">
        <div class="omk-card">
            <div class="card-header"><i class="bi bi-trophy me-2 text-warning"></i>Most Active Teachers (by Posts)</div>
            <div class="card-body p-0">
                <?php foreach ($top_teachers as $i => $t): ?>
                <div class="d-flex align-items-center gap-3 px-4 py-3 border-bottom">
                    <div class="fw-800 text-muted" style="min-width:24px">#<?= $i+1 ?></div>
                    <div class="user-avatar-sm bg-primary"><?= strtoupper(substr($t['first_name'],0,1).substr($t['last_name'],0,1)) ?></div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold small"><?= sanitize($t['first_name'].' '.$t['last_name']) ?></div>
                    </div>
                    <span class="badge bg-primary-subtle text-primary fw-bold"><?= $t['post_count'] ?> posts</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Quick links -->
    <div class="col-lg-6">
        <div class="omk-card">
            <div class="card-header"><i class="bi bi-grid me-2 text-primary"></i>Quick Navigation</div>
            <div class="row g-0">
                <?php foreach ([
                    ['users.php','bi-people-fill','primary','All Users','View all students, teachers'],
                    ['lesson_plans.php','bi-file-earmark-check','warning','Lesson Plans','Review and approve plans'],
                    ['assignments.php','bi-calendar-event','danger','Assignments','View all agenda events'],
                    ['courses.php','bi-book-fill','success','Courses','Manage courses & assignments'],
                ] as [$href,$icon,$color,$title,$desc]): ?>
                <div class="col-6">
                    <a href="<?= $href ?>" class="d-flex align-items-center gap-3 p-3 border-bottom border-end text-decoration-none hover-bg">
                        <div class="stat-card d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px;height:40px;padding:0;background:var(--bs-<?= $color ?>);border-radius:12px">
                            <i class="bi <?= $icon ?> text-white"></i>
                        </div>
                        <div>
                            <div class="fw-semibold small text-dark"><?= $title ?></div>
                            <div class="text-muted smaller"><?= $desc ?></div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
</body></html>