<?php
require_once '../includes/auth.php';
requireLogin(['student']);
$page_title = 'Student Dashboard';
$db = getDB();
$current_user = getCurrentUser();

// Get student's section
$my_section = $db->prepare("
    SELECT ss.*, s.name as sec_name, cl.name as class_name, cl.grade_level
    FROM student_sections ss
    JOIN sections s ON s.id=ss.section_id
    JOIN classes cl ON cl.id=s.class_id
    WHERE ss.student_id=? ORDER BY ss.enrolled_at DESC LIMIT 1
");
$my_section->execute([$current_user['id']]);
$my_section = $my_section->fetch();
$section_id = $my_section['section_id'] ?? 0;

// Courses count
$course_count = 0;
if ($section_id) {
    $course_count = $db->prepare("SELECT COUNT(DISTINCT course_id) FROM teacher_courses WHERE section_id=?");
    $course_count->execute([$section_id]);
    $course_count = $course_count->fetchColumn();
}

// Upcoming agenda
$upcoming = [];
if ($section_id) {
    $stmt = $db->prepare("
        SELECT a.*, c.name as course_name, c.color
        FROM agenda a JOIN courses c ON c.id=a.course_id
        WHERE a.section_id=? AND a.event_date >= CURDATE()
        ORDER BY a.event_date ASC LIMIT 5
    ");
    $stmt->execute([$section_id]);
    $upcoming = $stmt->fetchAll();
}

// Recent posts (last 5)
$recent_posts = [];
if ($section_id) {
    $stmt = $db->prepare("
        SELECT p.*, c.name as course_name, c.color, u.first_name, u.last_name
        FROM posts p
        JOIN courses c ON c.id=p.course_id
        JOIN users u ON u.id=p.teacher_id
        WHERE p.section_id=?
        ORDER BY p.created_at DESC LIMIT 5
    ");
    $stmt->execute([$section_id]);
    $recent_posts = $stmt->fetchAll();
}

// Attendance summary
$att = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
if ($section_id) {
    $stmt = $db->prepare("SELECT status,COUNT(*) as cnt FROM attendance WHERE student_id=? AND section_id=? GROUP BY status");
    $stmt->execute([$current_user['id'],$section_id]);
    foreach ($stmt->fetchAll() as $r) $att[$r['status']] = $r['cnt'];
}
$att_total = array_sum($att);
$att_pct   = $att_total > 0 ? round($att['present']/$att_total*100) : 0;

// Grades summary
$grades_avg = 0;
if ($section_id) {
    $stmt = $db->prepare("SELECT AVG((score/max_score)*100) as avg FROM grades WHERE student_id=? AND section_id=?");
    $stmt->execute([$current_user['id'],$section_id]);
    $grades_avg = round($stmt->fetchColumn() ?? 0);
}

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4><i class="bi bi-mortarboard-fill me-2"></i>My Dashboard</h4>
            <p>
                Welcome back, <strong><?= sanitize($current_user['first_name']) ?></strong>!
                <?php if ($my_section): ?>
                &nbsp;·&nbsp; <?= sanitize($my_section['class_name'].' — Section '.$my_section['sec_name']) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="text-white-50 small"><?= date('l, F j, Y') ?></div>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8)">
            <div class="stat-label">Enrolled Courses</div>
            <div class="stat-num"><?= $course_count ?></div>
            <i class="bi bi-book stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669)">
            <div class="stat-label">Attendance</div>
            <div class="stat-num"><?= $att_pct ?>%</div>
            <i class="bi bi-check-circle stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
            <div class="stat-label">Upcoming Events</div>
            <div class="stat-num"><?= count($upcoming) ?></div>
            <i class="bi bi-calendar-event stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9)">
            <div class="stat-label">Avg. Grade</div>
            <div class="stat-num"><?= $grades_avg > 0 ? $grades_avg.'%' : '—' ?></div>
            <i class="bi bi-graph-up stat-icon"></i>
        </div>
    </div>
</div>

<?php if ($att_pct < 75 && $att_total > 0): ?>
<div class="alert alert-danger omk-alert d-flex gap-2 align-items-center mb-4">
    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
    <div><strong>Attendance Warning:</strong> Your attendance rate is <?= $att_pct ?>% — below the 75% requirement. Contact your coordinator.</div>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Recent Updates -->
    <div class="col-lg-8">
        <div class="omk-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-chat-square-text me-2 text-primary"></i>Recent Updates</span>
                <a href="courses.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-book me-1"></i>My Courses</a>
            </div>
            <div class="card-body p-3">
                <?php if (empty($recent_posts)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-chat-square fs-2 d-block mb-2 opacity-30"></i>
                    No updates yet from your teachers.
                </div>
                <?php else: foreach ($recent_posts as $p): ?>
                <div class="post-item <?= $p['post_type'] ?> mb-3" style="border-left-color:<?= htmlspecialchars($p['color']) ?>">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                <span class="badge" style="background:<?= htmlspecialchars($p['color']) ?>22;color:<?= htmlspecialchars($p['color']) ?>;border:1px solid <?= htmlspecialchars($p['color']) ?>44;font-size:10px">
                                    <?= sanitize($p['course_name']) ?>
                                </span>
                                <span class="badge bg-<?= ['text'=>'secondary','announcement'=>'success','document'=>'warning'][$p['post_type']] ?? 'secondary' ?>-subtle text-<?= ['text'=>'secondary','announcement'=>'success','document'=>'warning'][$p['post_type']] ?? 'secondary' ?>" style="font-size:10px">
                                    <?= ucfirst($p['post_type']) ?>
                                </span>
                            </div>
                            <h6 class="fw-700 mb-1 small"><?= sanitize($p['title']) ?></h6>
                            <?php if ($p['content']): ?>
                            <div class="text-muted smaller"><?= sanitize(substr($p['content'],0,120)) ?><?= strlen($p['content'])>120?'...':'' ?></div>
                            <?php endif; ?>
                            <?php if ($p['file_path']): ?>
                            <a href="<?= BASE_URL ?>/uploads/documents/<?= htmlspecialchars($p['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2" style="font-size:11px">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i>Download
                            </a>
                            <?php endif; ?>
                        </div>
                        <a href="courses.php?course=<?= $p['course_id'] ?>&section=<?= $section_id ?>" class="btn btn-sm btn-outline-secondary flex-shrink-0" style="font-size:11px">
                            View
                        </a>
                    </div>
                    <div class="text-muted smaller mt-2">
                        <i class="bi bi-person me-1"></i><?= sanitize($p['first_name'].' '.$p['last_name']) ?>
                        <span class="ms-3"><i class="bi bi-clock me-1"></i><?= date('M d, g:i A', strtotime($p['created_at'])) ?></span>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Right sidebar -->
    <div class="col-lg-4">
        <!-- Upcoming events -->
        <div class="omk-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-event me-2 text-warning"></i>Upcoming</span>
                <a href="agenda.php" class="btn btn-sm btn-outline-warning">Full Calendar</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($upcoming)): ?>
                <div class="text-center py-4 text-muted small">
                    <i class="bi bi-calendar-check fs-3 d-block mb-1 opacity-40"></i>Nothing upcoming
                </div>
                <?php else:
                $tc = ['assignment'=>'warning','quiz'=>'primary','exam'=>'danger','homework'=>'info','project'=>'success','other'=>'secondary'];
                foreach ($upcoming as $ev): ?>
                <div class="d-flex gap-3 px-3 py-2 border-bottom align-items-start">
                    <div class="text-center flex-shrink-0" style="min-width:38px">
                        <div class="fw-800 lh-1" style="font-size:18px;color:#1e3a5f"><?= date('d',strtotime($ev['event_date'])) ?></div>
                        <div class="text-muted" style="font-size:10px;text-transform:uppercase"><?= date('M',strtotime($ev['event_date'])) ?></div>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold small text-truncate"><?= sanitize($ev['title']) ?></div>
                        <div class="smaller" style="color:<?= htmlspecialchars($ev['color']) ?>"><?= sanitize($ev['course_name']) ?></div>
                        <span class="badge bg-<?= $tc[$ev['event_type']] ?? 'secondary' ?>-subtle text-<?= $tc[$ev['event_type']] ?? 'secondary' ?>" style="font-size:9px"><?= ucfirst($ev['event_type']) ?></span>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Attendance summary -->
        <div class="omk-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-check2-circle me-2 text-success"></i>Attendance</span>
                <a href="attendance.php" class="btn btn-sm btn-outline-success">Details</a>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="fw-800" style="font-size:42px;color:<?= $att_pct>=75?'#10b981':'#ef4444' ?>;line-height:1"><?= $att_pct ?>%</div>
                    <div class="text-muted small mt-1">Attendance Rate</div>
                </div>
                <div class="omk-progress mb-3">
                    <div class="omk-progress-bar" style="width:<?= $att_pct ?>%;background:<?= $att_pct>=75?'linear-gradient(90deg,#10b981,#34d399)':'linear-gradient(90deg,#ef4444,#f87171)' ?>"></div>
                </div>
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="fw-700 text-success"><?= $att['present'] ?></div>
                        <div class="text-muted smaller">Present</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-700 text-danger"><?= $att['absent'] ?></div>
                        <div class="text-muted smaller">Absent</div>
                    </div>
                    <div class="col-4">
                        <div class="fw-700 text-warning"><?= $att['late'] ?></div>
                        <div class="text-muted smaller">Late</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
</body></html>