<?php
require_once '../includes/auth.php';
requireLogin(['student']);
$page_title = 'My Grades';
$db = getDB();
$current_user = getCurrentUser();

// Ensure table exists (in case student hits page before coordinator)
// Table is created via omk_school.sql — no inline creation needed

// Get student's section
$my_section = $db->prepare("
    SELECT ss.section_id, s.name as sec_name, cl.name as class_name
    FROM student_sections ss
    JOIN sections s ON s.id=ss.section_id
    JOIN classes cl ON cl.id=s.class_id
    WHERE ss.student_id=? ORDER BY ss.enrolled_at DESC LIMIT 1
");
$my_section->execute([$current_user['id']]);
$my_section = $my_section->fetch();
$section_id = $my_section['section_id'] ?? 0;

// Get enrolled courses
$enrolled_courses = [];
if ($section_id) {
    $stmt = $db->prepare("
        SELECT DISTINCT c.id, c.name, c.color, c.code,
               u.first_name as teacher_fn, u.last_name as teacher_ln
        FROM teacher_courses tc
        JOIN courses c ON c.id=tc.course_id
        JOIN users u ON u.id=tc.teacher_id
        WHERE tc.section_id=? ORDER BY c.name
    ");
    $stmt->execute([$section_id]);
    $enrolled_courses = $stmt->fetchAll();
}

// Get grade files per course for this section
$files_map = [];
if ($section_id && !empty($enrolled_courses)) {
    $course_ids = implode(',', array_column($enrolled_courses, 'id'));
    $stmt = $db->prepare("
        SELECT gf.*, u.first_name, u.last_name
        FROM grade_documents gf
        JOIN users u ON u.id=gf.uploaded_by
        WHERE gf.section_id=? AND gf.course_id IN ($course_ids)
        ORDER BY gf.created_at DESC
    ");
    $stmt->execute([$section_id]);
    foreach ($stmt->fetchAll() as $f) {
        $files_map[$f['course_id']][] = $f;
    }
}

$total_files   = array_sum(array_map('count', $files_map));
$courses_with  = count(array_filter($enrolled_courses, fn($c) => !empty($files_map[$c['id']])));

function fmtSize($b){if($b>=1048576)return round($b/1048576,1).' MB';if($b>=1024)return round($b/1024,1).' KB';return $b.' B';}
function fIcon($n){$e=strtolower(pathinfo($n,PATHINFO_EXTENSION));$m=['pdf'=>['bi-file-earmark-pdf-fill','danger'],'xlsx'=>['bi-file-earmark-excel-fill','success'],'xls'=>['bi-file-earmark-excel-fill','success'],'csv'=>['bi-file-earmark-spreadsheet','success'],'docx'=>['bi-file-earmark-word-fill','primary'],'doc'=>['bi-file-earmark-word-fill','primary'],'png'=>['bi-file-earmark-image-fill','warning'],'jpg'=>['bi-file-earmark-image-fill','warning'],'jpeg'=>['bi-file-earmark-image-fill','warning']];return $m[$e]??['bi-file-earmark-fill','secondary'];}

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4><i class="bi bi-star-fill me-2"></i>My Grades</h4>
            <p>
                Grade files uploaded by your coordinator
                <?= $my_section ? '— '.sanitize($my_section['class_name'].' — Section '.$my_section['sec_name']) : '' ?>
            </p>
        </div>
        <div class="text-white-50 small"><?= date('l, F j, Y') ?></div>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat-card" style="background:linear-gradient(135deg,#4f46e5,#4338ca)">
            <div class="stat-label">Grade Files</div>
            <div class="stat-num"><?= $total_files ?></div>
            <i class="bi bi-file-earmark-fill stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669)">
            <div class="stat-label">Courses with Grades</div>
            <div class="stat-num"><?= $courses_with ?>/<?= count($enrolled_courses) ?></div>
            <i class="bi bi-book-fill stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
            <div class="stat-label">Pending</div>
            <div class="stat-num"><?= count($enrolled_courses)-$courses_with ?></div>
            <i class="bi bi-hourglass-split stat-icon"></i>
        </div>
    </div>
</div>

<?php if (empty($enrolled_courses)): ?>
<div class="omk-card text-center py-5 text-muted">
    <i class="bi bi-book fs-2 d-block mb-2 opacity-30"></i>
    <h6 class="fw-700">Not enrolled in any courses yet</h6>
    <p class="small mb-0">Contact your coordinator to get enrolled.</p>
</div>
<?php else: ?>

<!-- Courses -->
<?php foreach ($enrolled_courses as $course):
    $cfiles = $files_map[$course['id']] ?? [];
    $has_files = !empty($cfiles);
?>
<div class="omk-card mb-4">
    <!-- Course header -->
    <div class="card-header d-flex align-items-center gap-3"
         style="background:<?= htmlspecialchars($course['color']) ?>12;border-bottom:2px solid <?= htmlspecialchars($course['color']) ?>30">
        <div style="width:42px;height:42px;border-radius:12px;background:<?= htmlspecialchars($course['color']) ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="bi bi-book-fill text-white" style="font-size:18px"></i>
        </div>
        <div class="flex-grow-1">
            <div class="fw-700" style="color:<?= htmlspecialchars($course['color']) ?>"><?= sanitize($course['name']) ?></div>
            <div class="text-muted smaller">
                <i class="bi bi-person me-1"></i><?= sanitize($course['teacher_fn'].' '.$course['teacher_ln']) ?>
                &nbsp;·&nbsp;
                <i class="bi bi-tag me-1"></i><?= sanitize($course['code']) ?>
            </div>
        </div>
        <?php if ($has_files): ?>
        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
            <i class="bi bi-check-circle me-1"></i><?= count($cfiles) ?> file<?= count($cfiles)!=1?'s':'' ?> available
        </span>
        <?php else: ?>
        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2">
            <i class="bi bi-hourglass-split me-1"></i>Not yet uploaded
        </span>
        <?php endif; ?>
    </div>

    <!-- Files -->
    <div class="card-body p-0">
        <?php if (!$has_files): ?>
        <div class="text-center py-4 text-muted">
            <i class="bi bi-clock fs-4 d-block mb-1 opacity-30"></i>
            <div class="small">Your coordinator hasn't uploaded grades for this course yet.</div>
        </div>
        <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($cfiles as $gf):
                [$icon,$ic]=fIcon($gf['file_name']);
            ?>
            <div class="list-group-item d-flex align-items-center gap-3 px-4 py-3">
                <!-- File icon -->
                <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-3"
                     style="width:52px;height:52px;background:#f8f9fa">
                    <i class="bi <?= $icon ?> text-<?= $ic ?>" style="font-size:28px"></i>
                </div>

                <!-- File info -->
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-700"><?= sanitize($gf['file_name']) ?></div>
                    <?php if ($gf['description']): ?>
                    <div class="text-muted small"><?= sanitize($gf['description']) ?></div>
                    <?php endif; ?>
                    <div class="text-muted smaller mt-1 d-flex gap-3 flex-wrap">
                        <span><i class="bi bi-clock me-1"></i><?= date('M d, Y \a\t g:i A',strtotime($gf['created_at'])) ?></span>
                        <span><i class="bi bi-hdd me-1"></i><?= fmtSize($gf['file_size']) ?></span>
                        <span><i class="bi bi-person me-1"></i><?= sanitize($gf['first_name'].' '.$gf['last_name']) ?></span>
                    </div>
                </div>

                <!-- Download button -->
                <a href="<?= BASE_URL ?>/uploads/grades/<?= urlencode($gf['file_path']) ?>"
                   download="<?= htmlspecialchars($gf['file_name']) ?>"
                   class="btn btn-primary flex-shrink-0">
                    <i class="bi bi-download me-2"></i>Download
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
</body></html>
