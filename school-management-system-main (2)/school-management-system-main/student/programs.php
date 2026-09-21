<?php
require_once '../includes/auth.php';
requireLogin(['student']);
$page_title = 'My Schedule';
$db = getDB();
$current_user = getCurrentUser();

// Get student's section
$my_section = $db->prepare("
    SELECT ss.section_id, s.name as sec_name, cl.name as class_name
    FROM student_sections ss
    JOIN sections s ON s.id = ss.section_id
    JOIN classes cl ON cl.id = s.class_id
    WHERE ss.student_id = ? ORDER BY ss.enrolled_at DESC LIMIT 1
");
$my_section->execute([$current_user['id']]);
$my_section = $my_section->fetch();
$section_id = $my_section['section_id'] ?? 0;

// Get programs for this section
$programs = [];
if ($section_id) {
    $stmt = $db->prepare("
        SELECT p.*, c.name as course_name, c.color, u.first_name, u.last_name
        FROM programs p
        JOIN courses c ON c.id = p.course_id
        JOIN users u ON u.id = p.teacher_id
        WHERE p.section_id = ?
        ORDER BY FIELD(p.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), p.start_time
    ");
    $stmt->execute([$section_id]);
    $programs = $stmt->fetchAll();
}

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$sessions = [
    1 => ['start'=>'08:00','end'=>'09:00'],
    2 => ['start'=>'09:00','end'=>'10:00'],
    3 => ['start'=>'10:00','end'=>'11:00'],
    4 => ['start'=>'11:00','end'=>'12:00'],
    5 => ['start'=>'12:00','end'=>'13:00'],
    6 => ['start'=>'13:00','end'=>'14:00'],
];

// Build grid
$grid = [];
foreach ($days as $d) $grid[$d] = [];
foreach ($programs as $p) {
    foreach ($sessions as $sn => $s) {
        if (substr($p['start_time'],0,5) === $s['start']) {
            $grid[$p['day_of_week']][$sn] = $p;
            break;
        }
    }
}

// Today's day name
$today = date('l');
$today_sessions = $grid[$today] ?? [];

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4><i class="bi bi-calendar-week me-2"></i>My Weekly Schedule</h4>
            <p>
                <?php if ($my_section): ?>
                <?= sanitize($my_section['class_name'].' — Section '.$my_section['sec_name']) ?>
                &nbsp;·&nbsp; <?= count($programs) ?> classes per week
                <?php else: ?>
                Not enrolled in any section yet.
                <?php endif; ?>
            </p>
        </div>
        <div class="text-white-50 small"><i class="bi bi-calendar me-1"></i><?= date('l, F j, Y') ?></div>
    </div>
</div>

<?php if (!$section_id || empty($programs)): ?>
<div class="omk-card text-center py-5 text-muted">
    <i class="bi bi-calendar-x fs-2 d-block mb-2 opacity-30"></i>
    <h6 class="fw-700">No schedule yet</h6>
    <p class="small mb-0">Your coordinator hasn't set up the timetable yet. Check back later.</p>
</div>
<?php else: ?>

<!-- Today highlight -->
<?php if (!empty($today_sessions)): ?>
<div class="omk-card mb-4" style="border-left:4px solid #4f46e5">
    <div class="card-body p-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <div class="fw-700 text-primary fs-6"><i class="bi bi-sun me-2"></i>Today — <?= $today ?></div>
            <span class="badge bg-primary-subtle text-primary"><?= count($today_sessions) ?> class<?= count($today_sessions)>1?'es':'' ?></span>
        </div>
        <div class="row g-3">
            <?php foreach ($today_sessions as $sn => $p): ?>
            <div class="col-sm-6 col-md-4">
                <div class="p-3 rounded-3 d-flex gap-3 align-items-center"
                     style="background:<?= htmlspecialchars($p['color']) ?>15;border:1.5px solid <?= htmlspecialchars($p['color']) ?>44">
                    <div class="flex-shrink-0 text-center" style="min-width:44px">
                        <div class="fw-800 lh-1" style="font-size:20px;color:<?= htmlspecialchars($p['color']) ?>"><?= $sn ?></div>
                        <div style="font-size:9px;color:<?= htmlspecialchars($p['color']) ?>;opacity:.7;text-transform:uppercase;font-weight:600">session</div>
                    </div>
                    <div class="min-w-0">
                        <div class="fw-700 small" style="color:<?= htmlspecialchars($p['color']) ?>"><?= sanitize($p['course_name']) ?></div>
                        <div class="text-muted" style="font-size:11px"><i class="bi bi-clock me-1"></i><?= date('g:i A',strtotime($p['start_time'])) ?> – <?= date('g:i A',strtotime($p['end_time'])) ?></div>
                        <div class="text-muted" style="font-size:11px"><i class="bi bi-person me-1"></i><?= sanitize($p['first_name'].' '.$p['last_name']) ?></div>
                        <?php if ($p['room']): ?><div class="text-muted" style="font-size:11px"><i class="bi bi-geo-alt me-1"></i><?= sanitize($p['room']) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="omk-card mb-4 d-flex align-items-center gap-3 p-3" style="border-left:4px solid #10b981">
    <i class="bi bi-check-circle-fill text-success fs-4"></i>
    <div>
        <div class="fw-700 small">No classes today — <?= $today ?></div>
        <div class="text-muted smaller">Enjoy your free day!</div>
    </div>
</div>
<?php endif; ?>

<!-- Weekly grid -->
<div class="omk-card">
    <div class="card-header"><i class="bi bi-grid me-2 text-primary"></i>Full Weekly Timetable</div>
    <div class="card-body p-0" style="overflow-x:auto">
        <table class="w-100" style="border-collapse:collapse;min-width:700px">
            <thead>
                <tr>
                    <th style="width:80px;padding:12px 10px;background:#f8fafc;border-bottom:2px solid #e2e8f0;border-right:1px solid #e2e8f0">
                        <div class="text-muted smaller fw-700 text-uppercase" style="letter-spacing:.5px">Time</div>
                    </th>
                    <?php foreach ($days as $i => $day):
                        $is_weekend   = ($i >= 5);
                        $is_today     = ($day === $today);
                        $filled_count = count(array_filter(array_keys($sessions), fn($sn) => isset($grid[$day][$sn])));
                    ?>
                    <th class="text-center" style="padding:10px 6px;background:<?= $is_today?'#eef2ff':($is_weekend?'#f9fafb':'#fff') ?>;border-bottom:2px solid <?= $is_today?'#4f46e5':'#e2e8f0' ?>;border-right:<?= $i<6?'1px solid #f0f0f0':'none' ?>;min-width:110px">
                        <div class="fw-700 small" style="color:<?= $is_today?'#4f46e5':($is_weekend?'#94a3b8':'#1e3a5f') ?>">
                            <?= $day ?>
                            <?php if ($is_today): ?><span class="badge bg-primary ms-1" style="font-size:9px">Today</span><?php endif; ?>
                        </div>
                        <?php if ($filled_count > 0): ?>
                        <span class="badge bg-<?= $is_today?'primary':'secondary' ?>-subtle text-<?= $is_today?'primary':'secondary' ?> mt-1" style="font-size:9px"><?= $filled_count ?> class<?= $filled_count>1?'es':'' ?></span>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:9px">Free</span>
                        <?php endif; ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $sn => $session):
                    $is_last = ($sn === array_key_last($sessions));
                ?>
                <tr>
                    <!-- Time label -->
                    <td style="padding:8px 10px;background:#f8fafc;border-bottom:<?= $is_last?'none':'1px solid #f0f4f8' ?>;border-right:1px solid #e2e8f0;vertical-align:middle">
                        <div class="fw-700 small text-primary">S<?= $sn ?></div>
                        <div class="text-muted" style="font-size:10px;line-height:1.5">
                            <?= date('g:i A',strtotime($session['start'])) ?><br>
                            <?= date('g:i A',strtotime($session['end'])) ?>
                        </div>
                    </td>

                    <?php foreach ($days as $i => $day):
                        $is_weekend = ($i >= 5);
                        $is_today   = ($day === $today);
                        $prog       = $grid[$day][$sn] ?? null;
                    ?>
                    <td style="padding:5px;border-bottom:<?= $is_last?'none':'1px solid #f0f4f8' ?>;border-right:<?= $i<6?'1px solid #f0f4f8':'none' ?>;background:<?= $is_today?'#f5f7ff':($is_weekend?'#fafcff':'#fff') ?>;height:80px;vertical-align:stretch">

                        <?php if ($prog): ?>
                        <div class="h-100 rounded-3 p-2 d-flex flex-column justify-content-between"
                             style="background:<?= htmlspecialchars($prog['color']) ?>18;border:1.5px solid <?= htmlspecialchars($prog['color']) ?>55">
                            <div>
                                <div class="fw-700" style="font-size:11px;color:<?= htmlspecialchars($prog['color']) ?>;line-height:1.3;margin-bottom:2px">
                                    <?= sanitize($prog['course_name']) ?>
                                </div>
                                <div class="text-muted" style="font-size:9.5px">
                                    <i class="bi bi-person-fill" style="font-size:8px"></i>
                                    <?= sanitize($prog['first_name'].' '.$prog['last_name']) ?>
                                </div>
                                <?php if ($prog['room']): ?>
                                <div class="text-muted" style="font-size:9px">
                                    <i class="bi bi-geo-alt-fill" style="font-size:8px"></i> <?= sanitize($prog['room']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted" style="font-size:9px;opacity:.7">
                                <?= date('g:i',strtotime($prog['start_time'])) ?>–<?= date('g:i A',strtotime($prog['end_time'])) ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="h-100 rounded-3 d-flex align-items-center justify-content-center" style="min-height:68px">
                            <span class="text-muted" style="font-size:10px;opacity:.3">—</span>
                        </div>
                        <?php endif; ?>

                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
</body></html>