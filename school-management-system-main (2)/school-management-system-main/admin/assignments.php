<?php
require_once '../includes/auth.php';
requireLogin(['admin']);
$page_title = 'Assignments & Agenda';
$db = getDB();
$current_user = getCurrentUser();

$type_filter    = $_GET['type'] ?? 'all';
$section_filter = (int)($_GET['section'] ?? 0);

$sql = "
    SELECT a.*, c.name as course_name, c.color,
           s.name as sec_name, cl.name as class_name,
           u.first_name, u.last_name
    FROM agenda a
    JOIN courses c ON c.id = a.course_id
    JOIN sections s ON s.id = a.section_id
    JOIN classes cl ON cl.id = s.class_id
    JOIN users u ON u.id = a.teacher_id
    WHERE 1=1
";
$params = [];
if ($type_filter !== 'all') { $sql .= " AND a.event_type=?"; $params[] = $type_filter; }
if ($section_filter)        { $sql .= " AND a.section_id=?"; $params[] = $section_filter; }
$sql .= " ORDER BY a.event_date DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$sections = $db->query("
    SELECT s.id, CONCAT(cl.name,' - Section ',s.name) as label
    FROM sections s JOIN classes cl ON cl.id=s.class_id
    ORDER BY cl.grade_level, s.name
")->fetchAll();

$type_colors = [
    'assignment' => 'warning',
    'quiz'       => 'primary',
    'exam'       => 'danger',
    'homework'   => 'info',
    'project'    => 'success',
    'other'      => 'secondary'
];

// Stats
$stats = $db->query("SELECT event_type, COUNT(*) as cnt FROM agenda GROUP BY event_type")->fetchAll(PDO::FETCH_KEY_PAIR);

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <h4><i class="bi bi-link-45deg me-2"></i>Assignments & Agenda</h4>
    <p>Overview of all assignments, quizzes and exams across all classes.</p>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4">
    <?php foreach ([['exam','danger','Exams'],['quiz','primary','Quizzes'],['assignment','warning','Assignments'],['homework','info','Homework'],['project','success','Projects']] as [$t,$c,$l]): ?>
    <div class="col-6 col-md-2">
        <div class="omk-card text-center p-3">
            <div class="fw-800 fs-3 text-<?= $c ?>"><?= $stats[$t] ?? 0 ?></div>
            <div class="text-muted small"><?= $l ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="omk-card mb-4">
    <div class="card-body p-3">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label small fw-semibold mb-1">Type</label>
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="all" <?= $type_filter==='all'?'selected':'' ?>>All Types</option>
                    <?php foreach (['exam','quiz','assignment','homework','project','other'] as $t): ?>
                    <option value="<?= $t ?>" <?= $type_filter===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label small fw-semibold mb-1">Section</label>
                <select name="section" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">All Sections</option>
                    <?php foreach ($sections as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $section_filter==$s['id']?'selected':'' ?>><?= sanitize($s['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <a href="assignments.php" class="btn btn-outline-secondary btn-sm">Clear</a>
            <span class="ms-auto text-muted small"><?= count($events) ?> events found</span>
        </form>
    </div>
</div>

<!-- Events table -->
<div class="omk-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table omk-table mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Course</th>
                        <th>Class / Section</th>
                        <th>Teacher</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($events)): ?>
                <tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-calendar-x fs-2 d-block mb-2 opacity-30"></i>No events found</td></tr>
                <?php else: foreach ($events as $ev): ?>
                <?php $isPast = $ev['event_date'] < date('Y-m-d'); ?>
                <tr class="<?= $isPast ? 'opacity-75' : '' ?>">
                    <td class="fw-semibold small"><?= sanitize($ev['title']) ?></td>
                    <td>
                        <span class="badge bg-<?= $type_colors[$ev['event_type']] ?? 'secondary' ?>-subtle text-<?= $type_colors[$ev['event_type']] ?? 'secondary' ?>">
                            <?= ucfirst($ev['event_type']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="d-flex align-items-center gap-2">
                            <span style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($ev['color']) ?>;display:inline-block;flex-shrink:0"></span>
                            <span class="small"><?= sanitize($ev['course_name']) ?></span>
                        </span>
                    </td>
                    <td class="small text-muted"><?= sanitize($ev['class_name'].' — Sec '.$ev['sec_name']) ?></td>
                    <td class="small"><?= sanitize($ev['first_name'].' '.$ev['last_name']) ?></td>
                    <td>
                        <span class="small fw-semibold <?= $isPast ? 'text-muted' : 'text-dark' ?>">
                            <?= date('M d, Y', strtotime($ev['event_date'])) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($isPast): ?>
                        <span class="badge bg-secondary-subtle text-secondary">Past</span>
                        <?php elseif ($ev['event_date'] === date('Y-m-d')): ?>
                        <span class="badge bg-danger-subtle text-danger">Today</span>
                        <?php else: ?>
                        <span class="badge bg-success-subtle text-success">Upcoming</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
</body></html>