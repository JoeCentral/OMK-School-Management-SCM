<?php
require_once '../includes/auth.php';
requireLogin(['coordinator']);
$page_title = 'Attendance Management';
$db = getDB();
$current_user = getCurrentUser();
$msg = '';

// My sections
$my_sections = $db->prepare("
    SELECT s.*,cl.name as class_name,cl.grade_level
    FROM sections s JOIN classes cl ON cl.id=s.class_id
    WHERE s.coordinator_id=? ORDER BY cl.grade_level,s.name
");
$my_sections->execute([$current_user['id']]);
$my_sections = $my_sections->fetchAll();

$section_id = (int)($_GET['section'] ?? ($my_sections[0]['id'] ?? 0));
$date       = $_GET['date'] ?? date('Y-m-d');

// Courses in this section
$courses = $db->prepare("
    SELECT DISTINCT c.id,c.name,c.color FROM teacher_courses tc JOIN courses c ON c.id=tc.course_id
    WHERE tc.section_id=?
");
$courses->execute([$section_id]);
$courses = $courses->fetchAll();
$course_id = (int)($_GET['course_id'] ?? ($courses[0]['id'] ?? 0));

// Handle attendance save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance'])) {
    $att_date   = $_POST['att_date'] ?? date('Y-m-d');
    $att_course = (int)($_POST['course_id'] ?? 0);
    $att_sec    = (int)($_POST['section_id'] ?? 0);
    foreach ($_POST['attendance'] as $student_id => $status) {
        $student_id = (int)$student_id;
        $status = in_array($status,['present','absent','late','excused']) ? $status : 'present';
        // Upsert
        $existing = $db->prepare("SELECT id FROM attendance WHERE student_id=? AND section_id=? AND date=? AND (course_id=? OR course_id IS NULL)");
        $existing->execute([$student_id,$att_sec,$att_date,$att_course]);
        if ($existing->fetch()) {
            $db->prepare("UPDATE attendance SET status=?,recorded_by=? WHERE student_id=? AND section_id=? AND date=? AND (course_id=? OR course_id IS NULL)")
               ->execute([$status,$current_user['id'],$student_id,$att_sec,$att_date,$att_course]);
        } else {
            $db->prepare("INSERT INTO attendance (student_id,section_id,course_id,date,status,recorded_by) VALUES (?,?,?,?,?,?)")
               ->execute([$student_id,$att_sec,$att_course,$att_date,$status,$current_user['id']]);
        }
    }
    $msg = "Attendance saved for $att_date!";
    $date = $att_date; $section_id = $att_sec; $course_id = $att_course;
}

// Get students in section
$students = $db->prepare("
    SELECT u.* FROM student_sections ss JOIN users u ON u.id=ss.student_id
    WHERE ss.section_id=? ORDER BY u.last_name,u.first_name
");
$students->execute([$section_id]);
$students = $students->fetchAll();

// Get today's attendance
$today_att = [];
if ($course_id) {
    $stmt = $db->prepare("SELECT student_id,status FROM attendance WHERE section_id=? AND date=? AND course_id=?");
    $stmt->execute([$section_id,$date,$course_id]);
    foreach ($stmt->fetchAll() as $r) $today_att[$r['student_id']] = $r['status'];
}

// Attendance history (last 14 days)
$history = $db->prepare("
    SELECT a.student_id,a.date,a.status,u.first_name,u.last_name
    FROM attendance a JOIN users u ON u.id=a.student_id
    WHERE a.section_id=? AND a.date >= DATE_SUB(CURDATE(),INTERVAL 14 DAY)
    ORDER BY a.date DESC
");
$history->execute([$section_id]);
$history = $history->fetchAll();

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <h4><i class="bi bi-clipboard-check me-2"></i>Attendance Management</h4>
    <p>Record and track student attendance.</p>
</div>

<?php if ($msg): ?><div class="alert alert-success omk-alert alert-auto-dismiss"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<!-- Filters -->
<div class="omk-card mb-4">
    <div class="card-body p-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-sm-3">
                <label class="form-label small fw-semibold">Section</label>
                <select name="section" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($my_sections as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $s['id']==$section_id?'selected':'' ?>><?= sanitize($s['class_name'].' - '.$s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label small fw-semibold">Course</label>
                <select name="course_id" class="form-select">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id']==$course_id?'selected':'' ?>><?= sanitize($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3">
                <label class="form-label small fw-semibold">Date</label>
                <input type="date" name="date" class="form-control" value="<?= $date ?>">
            </div>
            <div class="col-sm-3">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter me-1"></i>Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <!-- Mark attendance form -->
    <div class="col-lg-7">
        <div class="omk-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-pencil-square me-2 text-primary"></i>Mark Attendance — <?= date('M d, Y',strtotime($date)) ?></span>
                <span class="badge bg-primary-subtle text-primary"><?= count($students) ?> students</span>
            </div>
            <form method="POST">
                <input type="hidden" name="att_date" value="<?= $date ?>">
                <input type="hidden" name="section_id" value="<?= $section_id ?>">
                <input type="hidden" name="course_id" value="<?= $course_id ?>">
                <div class="card-body p-0">
                    <?php if (empty($students)): ?>
                    <div class="text-center py-5 text-muted">No students in this section.</div>
                    <?php else: ?>
                    <!-- Quick mark all -->
                    <div class="px-4 py-2 bg-light border-bottom d-flex gap-2 align-items-center">
                        <span class="small fw-semibold text-muted me-2">Mark All:</span>
                        <?php foreach (['present','absent','late','excused'] as $s): ?>
                        <button type="button" class="btn btn-sm btn-outline-<?= ['present'=>'success','absent'=>'danger','late'=>'warning','excused'=>'primary'][$s] ?> mark-all" data-status="<?= $s ?>">
                            <?= ucfirst($s) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <table class="table omk-table mb-0">
                        <thead><tr><th>#</th><th>Student</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($students as $i => $st): ?>
                        <?php $cur = $today_att[$st['id']] ?? 'present'; ?>
                        <tr>
                            <td class="text-muted small"><?= $i+1 ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-sm bg-success"><?= strtoupper(substr($st['first_name'],0,1).substr($st['last_name'],0,1)) ?></div>
                                    <div>
                                        <div class="fw-semibold small"><?= sanitize($st['first_name'].' '.$st['last_name']) ?></div>
                                        <div class="text-muted smaller"><?= sanitize($st['user_id_number']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap att-radios" data-student="<?= $st['id'] ?>">
                                    <?php foreach (['present'=>'success','absent'=>'danger','late'=>'warning','excused'=>'primary'] as $s => $c): ?>
                                    <div class="form-check form-check-inline mb-0">
                                        <input class="form-check-input att-radio" type="radio" name="attendance[<?= $st['id'] ?>]" value="<?= $s ?>" id="att_<?= $st['id'] ?>_<?= $s ?>" <?= $cur===$s?'checked':'' ?>>
                                        <label class="form-check-label small text-<?= $c ?>" for="att_<?= $st['id'] ?>_<?= $s ?>"><?= ucfirst($s) ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                <?php if (!empty($students)): ?>
                <div class="card-body border-top">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Attendance</button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Attendance history -->
    <div class="col-lg-5">
        <div class="omk-card">
            <div class="card-header"><i class="bi bi-clock-history me-2 text-primary"></i>Recent History (14 days)</div>
            <div class="card-body p-0" style="max-height:500px;overflow-y:auto">
                <?php if (empty($history)): ?>
                <div class="text-center py-5 text-muted small">No history yet.</div>
                <?php else: foreach ($history as $h): ?>
                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                    <div>
                        <div class="fw-semibold small"><?= sanitize($h['first_name'].' '.$h['last_name']) ?></div>
                        <div class="text-muted smaller"><?= date('M d, Y',strtotime($h['date'])) ?></div>
                    </div>
                    <span class="badge bg-<?= ['present'=>'success','absent'=>'danger','late'=>'warning','excused'=>'primary'][$h['status']] ?>-subtle text-<?= ['present'=>'success','absent'=>'danger','late'=>'warning','excused'=>'primary'][$h['status']] ?>">
                        <?= ucfirst($h['status']) ?>
                    </span>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
<script>
document.querySelectorAll('.mark-all').forEach(btn => {
    btn.addEventListener('click', () => {
        const status = btn.dataset.status;
        document.querySelectorAll(`.att-radio[value="${status}"]`).forEach(r => r.checked = true);
    });
});
setTimeout(()=>document.querySelectorAll('.alert-auto-dismiss').forEach(el=>{try{new bootstrap.Alert(el).close();}catch(e){}}),4000);
</script>
</body></html>
