<?php
require_once '../includes/auth.php';
requireLogin(['coordinator']);
$page_title = 'Students';
$db = getDB();
$current_user = getCurrentUser();

$my_sections = $db->prepare("
    SELECT s.*, cl.name as class_name, cl.grade_level
    FROM sections s JOIN classes cl ON cl.id = s.class_id
    WHERE s.coordinator_id = ? ORDER BY cl.grade_level, s.name
");
$my_sections->execute([$current_user['id']]);
$my_sections = $my_sections->fetchAll();

$section_id = (int)($_GET['section'] ?? ($my_sections[0]['id'] ?? 0));
$search     = trim($_GET['q'] ?? '');

$cur_section = null;
foreach ($my_sections as $ms) if ($ms['id'] == $section_id) { $cur_section = $ms; break; }

$students = [];
if ($section_id) {
    $sql = "
        SELECT u.*,
               (SELECT COUNT(*) FROM attendance WHERE student_id=u.id AND section_id=? AND status='present') as present_count,
               (SELECT COUNT(*) FROM attendance WHERE student_id=u.id AND section_id=?) as total_att,
               (SELECT COUNT(*) FROM grades WHERE student_id=u.id AND section_id=?) as grade_count,
               (SELECT AVG((score/max_score)*100) FROM grades WHERE student_id=u.id AND section_id=? AND max_score>0) as grade_avg
        FROM users u
        JOIN student_sections ss ON ss.student_id = u.id
        WHERE ss.section_id = ? AND u.is_active = 1
    ";
    $params = [$section_id,$section_id,$section_id,$section_id,$section_id];
    if ($search) {
        $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.user_id_number LIKE ?)";
        $s = "%$search%";
        $params = array_merge($params,[$s,$s,$s,$s]);
    }
    $sql .= " ORDER BY u.last_name, u.first_name";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
}

$total = count($students);
$at_risk = $excellent = 0;
foreach ($students as $st) {
    $ap = $st['total_att'] > 0 ? ($st['present_count']/$st['total_att'])*100 : 100;
    if ($ap < 75) $at_risk++;
    if ($st['grade_avg'] >= 85) $excellent++;
}

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4><i class="bi bi-people-fill me-2"></i>Students</h4>
            <p>Overview of all students in your sections</p>
        </div>
        <div class="text-white-50 small"><?= date('l, F j, Y') ?></div>
    </div>
</div>

<!-- Section tabs -->
<div class="d-flex gap-2 mb-4 flex-wrap align-items-center">
    <span class="text-muted small fw-semibold me-1">Section:</span>
    <?php foreach ($my_sections as $s): ?>
    <a href="students.php?section=<?= $s['id'] ?>"
       class="btn btn-<?= $s['id']==$section_id?'primary':'outline-primary' ?> btn-sm">
        <?= sanitize($s['class_name'].' — '.$s['name']) ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if (!$section_id): ?>
<div class="omk-card text-center py-5 text-muted">
    <i class="bi bi-people fs-2 d-block mb-2 opacity-30"></i>No sections assigned yet.
</div>
<?php else: ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8)">
            <div class="stat-label">Total Students</div>
            <div class="stat-num"><?= $total ?></div>
            <i class="bi bi-people stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669)">
            <div class="stat-label">Excellent ≥85%</div>
            <div class="stat-num"><?= $excellent ?></div>
            <i class="bi bi-star-fill stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#ef4444,#b91c1c)">
            <div class="stat-label">At Risk &lt;75%</div>
            <div class="stat-num"><?= $at_risk ?></div>
            <i class="bi bi-exclamation-triangle stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9)">
            <div class="stat-label">Section</div>
            <div class="stat-num" style="font-size:18px"><?= sanitize($cur_section['class_name'].' '.$cur_section['name']) ?></div>
            <i class="bi bi-diagram-2 stat-icon"></i>
        </div>
    </div>
</div>

<!-- Table -->
<div class="omk-card">
    <div class="card-header">
        <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
            <input type="hidden" name="section" value="<?= $section_id ?>">
            <div class="input-group" style="max-width:300px">
                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" name="q" class="form-control border-start-0" placeholder="Search name, email, ID..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Search</button>
            <?php if ($search): ?><a href="students.php?section=<?= $section_id ?>" class="btn btn-outline-secondary btn-sm">Clear</a><?php endif; ?>
            <span class="ms-auto text-muted small"><?= count($students) ?> students found</span>
        </form>
    </div>

    <div class="card-body p-0">
        <?php if (empty($students)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-person-slash fs-2 d-block mb-2 opacity-30"></i>
            <?= $search ? 'No students match your search.' : 'No students enrolled in this section.' ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table omk-table mb-0">
                <thead>
                    <tr>
                        <th style="width:30px">#</th>
                        <th>Student</th>
                        <th>System ID</th>
                        <th>Attendance</th>
                        <th>Avg Grade</th>
                        <th>Status</th>
                        <th class="text-center">Profile</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $i => $st):
                    $att_pct = $st['total_att'] > 0 ? round(($st['present_count']/$st['total_att'])*100) : 100;
                    $att_color = $att_pct >= 85 ? 'success' : ($att_pct >= 75 ? 'warning' : 'danger');
                    $grade_avg = $st['grade_avg'] ? round($st['grade_avg']) : null;
                    $grade_color = $grade_avg === null ? 'secondary' : ($grade_avg >= 70 ? 'success' : ($grade_avg >= 50 ? 'warning' : 'danger'));
                    $letter = $grade_avg===null?'—':($grade_avg>=90?'A+':($grade_avg>=85?'A':($grade_avg>=80?'A-':($grade_avg>=75?'B+':($grade_avg>=70?'B':($grade_avg>=65?'B-':($grade_avg>=60?'C':($grade_avg>=50?'D':'F'))))))));
                    $at_risk_st = $att_pct < 75;
                ?>
                <tr id="main-row-<?= $st['id'] ?>" style="<?= $at_risk_st?'background:#fff5f5':'' ?>">
                    <td class="text-muted small"><?= $i+1 ?></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="user-avatar-sm bg-success flex-shrink-0">
                                <?= strtoupper(substr($st['first_name'],0,1).substr($st['last_name'],0,1)) ?>
                            </div>
                            <div>
                                <div class="fw-semibold small"><?= sanitize($st['first_name'].' '.$st['last_name']) ?></div>
                                <div class="text-muted" style="font-size:10px"><?= sanitize($st['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="font-monospace small fw-700 text-primary bg-primary-subtle px-2 py-1 rounded">
                            <?= sanitize($st['user_id_number']) ?>
                        </span>
                    </td>
                    <td style="min-width:130px">
                        <div class="d-flex align-items-center gap-2">
                            <div class="flex-grow-1">
                                <div class="omk-progress" style="height:6px">
                                    <div class="omk-progress-bar" style="width:<?= $att_pct ?>%;background:var(--bs-<?= $att_color ?>)"></div>
                                </div>
                            </div>
                            <span class="small fw-700 text-<?= $att_color ?>"><?= $att_pct ?>%</span>
                        </div>
                        <div class="text-muted" style="font-size:10px"><?= $st['present_count'] ?>/<?= $st['total_att'] ?> days</div>
                    </td>
                    <td class="text-center">
                        <?php if ($grade_avg !== null): ?>
                        <div class="fw-700 small text-<?= $grade_color ?>"><?= $grade_avg ?>%</div>
                        <span class="badge bg-<?= $grade_color ?>-subtle text-<?= $grade_color ?> fw-bold" style="font-size:10px"><?= $letter ?></span>
                        <?php else: ?>
                        <span class="text-muted small">No grades</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($at_risk_st): ?>
                        <span class="badge bg-danger-subtle text-danger"><i class="bi bi-exclamation-triangle me-1"></i>At Risk</span>
                        <?php else: ?>
                        <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Good</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary btn-icon-sm" id="eye-<?= $st['id'] ?>"
                            onclick="toggleDetail(<?= $st['id'] ?>)" title="View full profile">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>

                <!-- Expandable profile row -->
                <tr id="detail-<?= $st['id'] ?>" class="d-none">
                    <td colspan="7" class="p-0">
                        <div class="p-4" style="background:#f8fafc;border-top:2px solid #e2e8f0;border-bottom:2px solid #e2e8f0">
                            <div class="row g-4">

                                <!-- Personal info -->
                                <div class="col-md-3">
                                    <div class="d-flex align-items-center gap-3 mb-3">
                                        <div class="user-avatar-xl bg-success flex-shrink-0">
                                            <?= strtoupper(substr($st['first_name'],0,1).substr($st['last_name'],0,1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-700"><?= sanitize($st['first_name'].' '.$st['last_name']) ?></div>
                                            <div class="text-muted small"><?= sanitize($st['email']) ?></div>
                                            <div class="text-muted smaller font-monospace"><?= sanitize($st['user_id_number']) ?></div>
                                        </div>
                                    </div>
                                    <div class="small">
                                        <?php if ($st['phone']): ?>
                                        <div class="d-flex gap-2 mb-2 text-muted"><i class="bi bi-telephone-fill text-primary"></i><?= sanitize($st['phone']) ?></div>
                                        <?php endif; ?>
                                        <?php if ($st['date_of_birth']): ?>
                                        <div class="d-flex gap-2 mb-2 text-muted"><i class="bi bi-calendar-fill text-success"></i><?= date('M d, Y',strtotime($st['date_of_birth'])) ?></div>
                                        <?php endif; ?>
                                        <div class="d-flex gap-2 mb-2 text-muted"><i class="bi bi-person-fill text-warning"></i><?= ucfirst($st['gender']) ?></div>
                                    </div>
                                </div>

                                <!-- Attendance breakdown -->
                                <div class="col-md-3">
                                    <div class="fw-semibold small mb-3 text-dark"><i class="bi bi-check2-circle me-2 text-success"></i>Attendance Details</div>
                                    <?php
                                    $att_d_stmt = $db->prepare("SELECT status,COUNT(*) as cnt FROM attendance WHERE student_id=? AND section_id=? GROUP BY status");
                                    $att_d_stmt->execute([$st['id'],$section_id]);
                                    $att_d = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
                                    foreach ($att_d_stmt->fetchAll() as $r) $att_d[$r['status']] = $r['cnt'];
                                    ?>
                                    <div class="text-center mb-3">
                                        <div class="fw-800" style="font-size:38px;color:<?= $att_pct>=75?'#10b981':'#ef4444' ?>;line-height:1"><?= $att_pct ?>%</div>
                                        <div class="text-muted smaller mt-1">Attendance Rate</div>
                                    </div>
                                    <div class="omk-progress mb-3">
                                        <div class="omk-progress-bar" style="width:<?= $att_pct ?>%;background:<?= $att_pct>=75?'linear-gradient(90deg,#10b981,#34d399)':'linear-gradient(90deg,#ef4444,#f87171)' ?>"></div>
                                    </div>
                                    <div class="row g-1 text-center">
                                        <?php foreach (['present'=>'success','absent'=>'danger','late'=>'warning','excused'=>'primary'] as $s=>$c): ?>
                                        <div class="col-3">
                                            <div class="fw-700 text-<?= $c ?>"><?= $att_d[$s] ?></div>
                                            <div class="text-muted" style="font-size:9px;text-transform:capitalize"><?= $s ?></div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if ($att_pct < 75): ?>
                                    <div class="alert alert-danger omk-alert py-2 px-3 small mt-3 mb-0">
                                        <i class="bi bi-exclamation-triangle me-1"></i>Below 75% threshold
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Grades by course -->
                                <div class="col-md-6">
                                    <div class="fw-semibold small mb-3 text-dark"><i class="bi bi-star me-2 text-warning"></i>Grades by Course</div>
                                    <?php
                                    $sg_stmt = $db->prepare("
                                        SELECT g.*, c.name as course_name, c.color
                                        FROM grades g JOIN courses c ON c.id=g.course_id
                                        WHERE g.student_id=? AND g.section_id=?
                                        ORDER BY c.name, g.created_at DESC
                                    ");
                                    $sg_stmt->execute([$st['id'],$section_id]);
                                    $sg = $sg_stmt->fetchAll();
                                    $sg_by_course = [];
                                    foreach ($sg as $g) $sg_by_course[$g['course_name']][] = $g;
                                    ?>
                                    <?php if (empty($sg)): ?>
                                    <div class="text-muted small text-center py-3 rounded-3" style="background:#fff;border:1px dashed #e2e8f0">
                                        <i class="bi bi-star opacity-30 d-block fs-3 mb-1"></i>No grades recorded yet
                                    </div>
                                    <?php else: ?>
                                    <div class="row g-2">
                                        <?php foreach ($sg_by_course as $cname => $cg):
                                            $cavg = round(array_sum(array_map(fn($x)=>$x['max_score']>0?($x['score']/$x['max_score'])*100:0,$cg))/count($cg));
                                            $cc = $cavg>=70?'success':($cavg>=50?'warning':'danger');
                                            $color = $cg[0]['color'];
                                        ?>
                                        <div class="col-12">
                                            <div class="p-3 rounded-3" style="background:#fff;border:1px solid #e2e8f0;border-left:4px solid <?= htmlspecialchars($color) ?>">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="fw-semibold small"><?= sanitize($cname) ?></span>
                                                    <span class="badge bg-<?= $cc ?>-subtle text-<?= $cc ?> fw-bold"><?= $cavg ?>%</span>
                                                </div>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php foreach ($cg as $g):
                                                        $gpct = $g['max_score']>0?round(($g['score']/$g['max_score'])*100):0;
                                                        $gc = $gpct>=70?'success':($gpct>=50?'warning':'danger');
                                                    ?>
                                                    <span class="badge bg-<?= $gc ?>-subtle text-<?= $gc ?>" style="font-size:10px" title="<?= sanitize($g['assessment_type']) ?>">
                                                        <?= sanitize(substr($g['assessment_name'],0,14)) ?>: <strong><?= $g['score'] ?>/<?= $g['max_score'] ?></strong>
                                                    </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
<script>
function toggleDetail(id) {
    const row = document.getElementById('detail-'+id);
    const btn = document.getElementById('eye-'+id);
    const isHidden = row.classList.contains('d-none');
    row.classList.toggle('d-none', !isHidden);
    btn.innerHTML = isHidden ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    btn.classList.toggle('btn-primary', isHidden);
    btn.classList.toggle('btn-outline-primary', !isHidden);
    if (isHidden) row.scrollIntoView({behavior:'smooth', block:'nearest'});
}
</script>
</body></html>