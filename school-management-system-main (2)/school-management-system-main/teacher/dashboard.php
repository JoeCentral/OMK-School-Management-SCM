<?php
require_once '../includes/auth.php';
requireLogin(['teacher']);
$page_title = 'Teacher Dashboard';
$db = getDB();
$current_user = getCurrentUser();

// Get all sections/courses this teacher teaches
$my_classes = $db->prepare("
    SELECT DISTINCT s.id as section_id, s.name as sec_name, cl.name as class_name, cl.grade_level,
           c.id as course_id, c.name as course_name, c.color,
           (SELECT COUNT(*) FROM student_sections WHERE section_id=s.id) as student_count
    FROM teacher_courses tc
    JOIN sections s ON s.id = tc.section_id
    JOIN classes cl ON cl.id = s.class_id
    JOIN courses c ON c.id = tc.course_id
    WHERE tc.teacher_id = ?
    ORDER BY cl.grade_level, s.name, c.name
");
$my_classes->execute([$current_user['id']]);
$my_classes = $my_classes->fetchAll();

// Pending lesson plans
$pending_plans = $db->prepare("SELECT COUNT(*) FROM lesson_preparations WHERE teacher_id=? AND status='pending'")->execute([$current_user['id']]) ? $db->prepare("SELECT COUNT(*) FROM lesson_preparations WHERE teacher_id=? AND status='pending'"): null;
$pending_plans = $db->prepare("SELECT COUNT(*) as cnt FROM lesson_preparations WHERE teacher_id=? AND status='pending'");
$pending_plans->execute([$current_user['id']]);
$pending_cnt = $pending_plans->fetch()['cnt'];

// Upcoming agenda
$upcoming = $db->prepare("
    SELECT a.*, c.name as course_name, c.color, s.name as sec_name, cl.name as class_name
    FROM agenda a
    JOIN courses c ON c.id=a.course_id
    JOIN sections s ON s.id=a.section_id
    JOIN classes cl ON cl.id=s.class_id
    WHERE a.teacher_id=? AND a.event_date >= CURDATE()
    ORDER BY a.event_date ASC LIMIT 5
");
$upcoming->execute([$current_user['id']]);
$upcoming = $upcoming->fetchAll();

// Group by section
$sections_grouped = [];
foreach ($my_classes as $mc) {
    $key = $mc['section_id'];
    if (!isset($sections_grouped[$key])) {
        $sections_grouped[$key] = ['section_id'=>$mc['section_id'],'sec_name'=>$mc['sec_name'],'class_name'=>$mc['class_name'],'grade_level'=>$mc['grade_level'],'student_count'=>$mc['student_count'],'courses'=>[]];
    }
    $sections_grouped[$key]['courses'][] = $mc;
}

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4><i class="bi bi-person-video3 me-2"></i>My Classes</h4>
            <p>Welcome, <?= sanitize($current_user['first_name']) ?>! Select a class to post updates or manage agenda.</p>
        </div>
        <div class="text-white-50 small"><?= date('l, F j, Y') ?></div>
    </div>
</div>

<!-- Quick stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8)">
            <div class="stat-label">My Sections</div>
            <div class="stat-num"><?= count($sections_grouped) ?></div>
            <i class="bi bi-diagram-2 stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9)">
            <div class="stat-label">My Courses</div>
            <div class="stat-num"><?= count(array_unique(array_column($my_classes,'course_id'))) ?></div>
            <i class="bi bi-book stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
            <div class="stat-label">Pending Plans</div>
            <div class="stat-num"><?= $pending_cnt ?></div>
            <i class="bi bi-hourglass stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669)">
            <div class="stat-label">Upcoming Events</div>
            <div class="stat-num"><?= count($upcoming) ?></div>
            <i class="bi bi-calendar-event stat-icon"></i>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- My class sections -->
    <div class="col-lg-8">
        <h5 class="fw-700 mb-3"><i class="bi bi-grid me-2 text-primary"></i>My Sections</h5>
        <?php if (empty($sections_grouped)): ?>
        <div class="omk-card text-center py-5 text-muted"><i class="bi bi-person-slash fs-2 d-block mb-2 opacity-30"></i>No classes assigned yet.</div>
        <?php else: foreach ($sections_grouped as $section): ?>
        <div class="omk-card mb-3">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="fw-700 mb-1"><?= sanitize($section['class_name']) ?> — Section <?= sanitize($section['sec_name']) ?></h5>
                        <span class="badge bg-success-subtle text-success"><i class="bi bi-people me-1"></i><?= $section['student_count'] ?> students</span>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="classes.php?section=<?= $section['section_id'] ?>" class="btn btn-primary btn-sm"><i class="bi bi-megaphone me-1"></i>Manage Posts</a>
                        <a href="agenda.php?section=<?= $section['section_id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-event me-1"></i>Agenda</a>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($section['courses'] as $course): ?>
                    <span class="badge d-flex align-items-center gap-1 px-3 py-2" style="background:<?= htmlspecialchars($course['color']) ?>22;color:<?= htmlspecialchars($course['color']) ?>;border:1px solid <?= htmlspecialchars($course['color']) ?>44;font-size:12px;">
                        <i class="bi bi-circle-fill" style="font-size:6px"></i>
                        <?= sanitize($course['course_name']) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Upcoming agenda -->
    <div class="col-lg-4">
        <div class="omk-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-event text-primary me-2"></i>Upcoming Events</span>
                <a href="agenda.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($upcoming)): ?>
                <div class="text-center py-4 text-muted small"><i class="bi bi-calendar-x d-block fs-4 mb-1 opacity-40"></i>No upcoming events</div>
                <?php else: foreach ($upcoming as $ev): ?>
                <?php $type_colors = ['assignment'=>'warning','quiz'=>'primary','exam'=>'danger','homework'=>'info','project'=>'success','other'=>'secondary']; ?>
                <div class="d-flex gap-3 px-3 py-2 border-bottom">
                    <div class="text-center" style="min-width:40px">
                        <div class="fw-800 lh-1" style="font-size:18px;color:#1e3a5f"><?= date('d',strtotime($ev['event_date'])) ?></div>
                        <div class="text-muted" style="font-size:10px;text-transform:uppercase"><?= date('M',strtotime($ev['event_date'])) ?></div>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold small text-truncate"><?= sanitize($ev['title']) ?></div>
                        <div class="smaller text-muted"><?= sanitize($ev['course_name']) ?> · <?= sanitize($ev['class_name'].'-'.$ev['sec_name']) ?></div>
                        <span class="badge bg-<?= $type_colors[$ev['event_type']] ?>-subtle text-<?= $type_colors[$ev['event_type']] ?> mt-1" style="font-size:10px"><?= ucfirst($ev['event_type']) ?></span>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <div class="omk-card mt-3">
            <div class="card-header"><i class="bi bi-journal-text me-2 text-warning"></i>Quick Actions</div>
            <div class="list-group list-group-flush">
                <a href="lesson_plans.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-file-earmark-plus text-primary"></i>
                    <div><div class="fw-semibold small">Submit Lesson Plan</div><div class="text-muted smaller">Send for admin approval</div></div>
                </a>
                <a href="upload.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-cloud-upload text-success"></i>
                    <div><div class="fw-semibold small">Upload Document</div><div class="text-muted smaller">Share files with students</div></div>
                </a>
                <a href="agenda.php" class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-3">
                    <i class="bi bi-calendar-plus text-warning"></i>
                    <div><div class="fw-semibold small">Add Agenda Event</div><div class="text-muted smaller">Schedule exams, assignments</div></div>
                </a>
            </div>
        </div>
    </div>
</div>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
</body></html>
