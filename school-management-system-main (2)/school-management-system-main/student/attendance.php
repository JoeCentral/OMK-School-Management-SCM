<?php
require_once '../includes/auth.php';
requireLogin(['student']);
$page_title = 'My Attendance';
$db = getDB();
$current_user = getCurrentUser();

$my_section = $db->prepare("SELECT section_id FROM student_sections WHERE student_id=? ORDER BY enrolled_at DESC LIMIT 1");
$my_section->execute([$current_user['id']]);
$section_id = $my_section->fetchColumn();

// Fetch day-level attendance (deduplicate: if same date has multiple records, take the one with NULL course_id first)
$attendance = [];
if ($section_id) {
    $stmt = $db->prepare("
        SELECT date,
               COALESCE(
                   MAX(CASE WHEN course_id IS NULL THEN status END),
                   MAX(status)
               ) as status
        FROM attendance
        WHERE student_id=? AND section_id=?
        GROUP BY date
        ORDER BY date DESC
    ");
    $stmt->execute([$current_user['id'], $section_id]);
    $attendance = $stmt->fetchAll();
}

$stats = ['present'=>0,'absent'=>0];
foreach ($attendance as $a) {
    $s = $a['status'];
    if (isset($stats[$s])) $stats[$s]++;
}
$total = array_sum($stats);
$pct   = $total > 0 ? round($stats['present']/$total*100) : 0;

// Calendar events
$status_colors = ['present'=>'#10b981','absent'=>'#ef4444'];
$cal_events = [];
foreach ($attendance as $a) {
    $cal_events[] = [
        'title' => ucfirst($a['status']),
        'start' => $a['date'],
        'color' => $status_colors[$a['status']] ?? '#6b7280',
        'allDay'=> true,
    ];
}

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <h4><i class="bi bi-check2-circle me-2"></i>My Attendance</h4>
    <p>Your daily attendance record for the full school day.</p>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669)">
            <div class="stat-label">Present Days</div>
            <div class="stat-num"><?= $stats['present'] ?></div>
            <i class="bi bi-check-circle stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#ef4444,#b91c1c)">
            <div class="stat-label">Absent Days</div>
            <div class="stat-num"><?= $stats['absent'] ?></div>
            <i class="bi bi-x-circle stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8)">
            <div class="stat-label">Total Days</div>
            <div class="stat-num"><?= $total ?></div>
            <i class="bi bi-calendar stat-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="background:linear-gradient(135deg,<?= $pct>=75?'#10b981,#059669':'#ef4444,#b91c1c' ?>)">
            <div class="stat-label">Attendance Rate</div>
            <div class="stat-num"><?= $pct ?>%</div>
            <i class="bi bi-graph-up stat-icon"></i>
        </div>
    </div>
</div>

<?php if ($pct < 75 && $total > 0): ?>
<div class="alert alert-danger omk-alert d-flex gap-2 align-items-center mb-4">
    <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
    <div>
        <strong>Attendance Warning!</strong> Your rate is <?= $pct ?>% — below the required 75%.
        Please contact your coordinator.
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Calendar -->
    <div class="col-lg-7">
        <div class="omk-card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-calendar3 me-2 text-primary"></i>Attendance Calendar</span>
                <div class="d-flex gap-3">
                    <div class="d-flex align-items-center gap-1 smaller">
                        <div style="width:10px;height:10px;border-radius:3px;background:#10b981"></div>Present
                    </div>
                    <div class="d-flex align-items-center gap-1 smaller">
                        <div style="width:10px;height:10px;border-radius:3px;background:#ef4444"></div>Absent
                    </div>
                </div>
            </div>
            <div class="card-body p-3">
                <div id="attCalendar"></div>
            </div>
        </div>

        <!-- Attendance rate bar -->
        <div class="omk-card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold">Attendance Rate</span>
                    <span class="fw-800 fs-5 text-<?= $pct>=75?'success':'danger' ?>"><?= $pct ?>%</span>
                </div>
                <div class="omk-progress mb-2" style="height:14px;border-radius:20px">
                    <div class="omk-progress-bar" style="width:<?= $pct ?>%;height:14px;border-radius:20px;background:<?= $pct>=75?'linear-gradient(90deg,#10b981,#34d399)':'linear-gradient(90deg,#ef4444,#f87171)' ?>"></div>
                </div>
                <div class="d-flex justify-content-between text-muted smaller">
                    <span>0%</span>
                    <span class="fw-700 text-danger">75% required</span>
                    <span>100%</span>
                </div>
                <!-- 75% marker line -->
                <div class="position-relative" style="height:0">
                    <div style="position:absolute;left:75%;top:-16px;width:2px;height:14px;background:#ef4444;border-radius:2px"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Log -->
    <div class="col-lg-5">
        <div class="omk-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-ul me-2 text-primary"></i>Attendance Log</span>
                <span class="badge bg-primary-subtle text-primary"><?= count($attendance) ?> days</span>
            </div>
            <div class="card-body p-0" style="max-height:520px;overflow-y:auto">
                <?php if (empty($attendance)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-clipboard-x fs-2 d-block mb-2 opacity-30"></i>No attendance recorded yet.
                </div>
                <?php else: foreach ($attendance as $a):
                    $sc = $a['status']==='present'?'success':'danger';
                    $si = $a['status']==='present'?'bi-check-circle-fill':'bi-x-circle-fill';
                ?>
                <div class="d-flex align-items-center gap-3 px-4 py-3 border-bottom">
                    <div class="text-center flex-shrink-0" style="min-width:40px">
                        <div class="fw-800 lh-1" style="font-size:20px;color:#1e3a5f"><?= date('d',strtotime($a['date'])) ?></div>
                        <div class="text-muted" style="font-size:10px;text-transform:uppercase"><?= date('M',strtotime($a['date'])) ?></div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold small"><?= date('l',strtotime($a['date'])) ?></div>
                        <div class="text-muted smaller"><?= date('Y',strtotime($a['date'])) ?></div>
                    </div>
                    <div>
                        <span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?> px-3 py-2 fw-semibold">
                            <i class="bi <?= $si ?> me-1"></i><?= ucfirst($a['status']) ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded',function(){
    var cal=new FullCalendar.Calendar(document.getElementById('attCalendar'),{
        initialView:'dayGridMonth',
        headerToolbar:{left:'prev,next today',center:'title',right:'dayGridMonth,listMonth'},
        height:380,
        events:<?= json_encode($cal_events) ?>,
        eventClick:function(info){alert(info.event.startStr+': '+info.event.title);}
    });
    cal.render();
});
</script>
</body></html>
