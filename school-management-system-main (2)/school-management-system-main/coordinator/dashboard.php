<?php
require_once '../includes/auth.php';
requireLogin(['coordinator']);
$page_title = 'Coordinator Dashboard';
$db = getDB();
$current_user = getCurrentUser();

// Sections this coordinator manages
$my_sections = $db->query("
    SELECT s.*,cl.name as class_name,cl.grade_level,
    (SELECT COUNT(*) FROM student_sections WHERE section_id=s.id) as student_count
    FROM sections s JOIN classes cl ON cl.id=s.class_id
    WHERE s.coordinator_id={$current_user['id']}
    ORDER BY cl.grade_level,s.name
")->fetchAll();

$section_ids = array_column($my_sections,'id');
$total_students = array_sum(array_column($my_sections,'student_count'));

// Attendance stats today
$today_att = ['present'=>0,'absent'=>0,'late'=>0];
if (!empty($section_ids)) {
    $in = implode(',',array_fill(0,count($section_ids),'?'));
    $stmt = $db->prepare("SELECT status,COUNT(*) as cnt FROM attendance WHERE section_id IN ($in) AND date=CURDATE() GROUP BY status");
    $stmt->execute($section_ids);
    foreach ($stmt->fetchAll() as $r) $today_att[$r['status']] = $r['cnt'];
}

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4><i class="bi bi-diagram-3-fill me-2"></i>Coordinator Dashboard</h4>
            <p>Welcome, <?= sanitize($current_user['first_name']) ?>! Managing <?= count($my_sections) ?> section(s).</p>
        </div>
        <div class="text-white-50 small"><?= date('l, F j, Y') ?></div>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8)">
            <div class="stat-label">My Sections</div><div class="stat-num"><?= count($my_sections) ?></div>
            <i class="bi bi-diagram-2 stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669)">
            <div class="stat-label">Total Students</div><div class="stat-num"><?= $total_students ?></div>
            <i class="bi bi-people stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#047857)">
            <div class="stat-label">Present Today</div><div class="stat-num"><?= $today_att['present'] ?></div>
            <i class="bi bi-check-circle stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#ef4444,#b91c1c)">
            <div class="stat-label">Absent Today</div><div class="stat-num"><?= $today_att['absent'] ?></div>
            <i class="bi bi-x-circle stat-icon"></i>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="omk-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-diagram-2 me-2 text-primary"></i>My Sections</span>
                <a href="attendance.php" class="btn btn-primary btn-sm"><i class="bi bi-clipboard-check me-1"></i>Record Attendance</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table omk-table mb-0">
                        <thead><tr><th>Class</th><th>Section</th><th>Students</th><th>Today's Attendance</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($my_sections as $sec):
                            $sec_att = $db->prepare("SELECT status,COUNT(*) as c FROM attendance WHERE section_id=? AND date=CURDATE() GROUP BY status");
                            $sec_att->execute([$sec['id']]);
                            $sa = ['present'=>0,'absent'=>0,'late'=>0];
                            foreach ($sec_att->fetchAll() as $r) $sa[$r['status']] = $r['c'];
                        ?>
                        <tr>
                            <td class="fw-semibold small"><?= sanitize($sec['class_name']) ?></td>
                            <td><span class="badge bg-primary-subtle text-primary">Section <?= sanitize($sec['name']) ?></span></td>
                            <td><span class="fw-bold"><?= $sec['student_count'] ?></span></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <span class="badge bg-success-subtle text-success"><?= $sa['present'] ?> P</span>
                                    <span class="badge bg-danger-subtle text-danger"><?= $sa['absent'] ?> A</span>
                                    <span class="badge bg-warning-subtle text-warning"><?= $sa['late'] ?> L</span>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="attendance.php?section=<?= $sec['id'] ?>" class="btn btn-sm btn-outline-primary btn-sm"><i class="bi bi-clipboard-check me-1"></i>Attendance</a>
                                    <a href="grades.php?section=<?= $sec['id'] ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-star me-1"></i>Grades</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="omk-card">
            <div class="card-header"><i class="bi bi-lightning me-2 text-warning"></i>Quick Actions</div>
            <div class="list-group list-group-flush">
                <a href="attendance.php" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                    <div class="stat-card d-flex align-items-center justify-content-center" style="width:40px;height:40px;padding:0;background:linear-gradient(135deg,#10b981,#059669);border-radius:10px">
                        <i class="bi bi-clipboard-check text-white"></i>
                    </div>
                    <div><div class="fw-semibold small">Record Attendance</div><div class="text-muted smaller">Mark present/absent for today</div></div>
                </a>
                <a href="grades.php" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                    <div class="stat-card d-flex align-items-center justify-content-center" style="width:40px;height:40px;padding:0;background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:10px">
                        <i class="bi bi-star text-white"></i>
                    </div>
                    <div><div class="fw-semibold small">Manage Grades</div><div class="text-muted smaller">Enter and update grades</div></div>
                </a>
                <a href="programs.php" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                    <div class="stat-card d-flex align-items-center justify-content-center" style="width:40px;height:40px;padding:0;background:linear-gradient(135deg,#6366f1,#4338ca);border-radius:10px">
                        <i class="bi bi-calendar3 text-white"></i>
                    </div>
                    <div><div class="fw-semibold small">Weekly Programs</div><div class="text-muted smaller">Manage class schedules</div></div>
                </a>
                <a href="reports.php" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                    <div class="stat-card d-flex align-items-center justify-content-center" style="width:40px;height:40px;padding:0;background:linear-gradient(135deg,#3b82f6,#1d4ed8);border-radius:10px">
                        <i class="bi bi-bar-chart text-white"></i>
                    </div>
                    <div><div class="fw-semibold small">Reports</div><div class="text-muted smaller">Attendance and grade reports</div></div>
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
