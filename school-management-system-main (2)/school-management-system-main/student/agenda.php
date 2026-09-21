<?php
require_once '../includes/auth.php';
requireLogin(['student']);
$page_title = 'My Agenda';
$db = getDB();
$current_user = getCurrentUser();

$my_section = $db->prepare("SELECT section_id FROM student_sections WHERE student_id=? ORDER BY enrolled_at DESC LIMIT 1");
$my_section->execute([$current_user['id']]);
$section_id = $my_section->fetchColumn();

$all_agenda = $calendar_events = [];
if ($section_id) {
    $stmt = $db->prepare("
        SELECT a.*, c.name as course_name, c.color
        FROM agenda a JOIN courses c ON c.id=a.course_id
        WHERE a.section_id=?
        ORDER BY a.event_date ASC
    ");
    $stmt->execute([$section_id]);
    $all_agenda = $stmt->fetchAll();
}

$type_colors = ['assignment'=>'#f59e0b','quiz'=>'#3b82f6','exam'=>'#ef4444','homework'=>'#8b5cf6','project'=>'#10b981','other'=>'#6b7280'];
foreach ($all_agenda as $ev) {
    $calendar_events[] = [
        'title' => $ev['course_name'].': '.$ev['title'],
        'start' => $ev['event_date'],
        'color' => $type_colors[$ev['event_type']] ?? '#6b7280',
        'extendedProps' => ['type'=>$ev['event_type'],'course'=>$ev['course_name'],'description'=>$ev['description']]
    ];
}

// Group upcoming by date
$upcoming_grouped = [];
foreach ($all_agenda as $ev) {
    if ($ev['event_date'] >= date('Y-m-d')) {
        $upcoming_grouped[$ev['event_date']][] = $ev;
    }
}
ksort($upcoming_grouped);

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <h4><i class="bi bi-calendar3 me-2"></i>My Agenda</h4>
    <p>All assignments, quizzes, exams and events across your courses.</p>
</div>

<div class="row g-4">
    <!-- Calendar -->
    <div class="col-lg-8">
        <div class="omk-card mb-4">
            <div class="card-header"><i class="bi bi-calendar3 me-2 text-primary"></i>Events Calendar</div>
            <div class="card-body p-3">
                <div id="studentCalendar"></div>
            </div>
        </div>

        <!-- Event type legend -->
        <div class="omk-card">
            <div class="card-header small fw-semibold">Legend</div>
            <div class="card-body py-2">
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($type_colors as $type => $color): ?>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:14px;height:14px;border-radius:4px;background:<?= $color ?>"></div>
                        <span class="small text-capitalize"><?= $type ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming list grouped by date -->
    <div class="col-lg-4">
        <div class="omk-card">
            <div class="card-header"><i class="bi bi-list-ul me-2 text-warning"></i>Upcoming Events</div>
            <div class="card-body p-0" style="max-height:600px;overflow-y:auto">
                <?php if (empty($upcoming_grouped)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-calendar-check fs-2 d-block mb-2 opacity-30"></i>
                    No upcoming events
                </div>
                <?php else: foreach ($upcoming_grouped as $date => $events): ?>
                <div class="px-3 py-2 bg-light border-bottom">
                    <div class="fw-700 small" style="color:#1e3a5f">
                        <?= date('l', strtotime($date)) ?>
                        <span class="fw-400 text-muted ms-1"><?= date('M d, Y', strtotime($date)) ?></span>
                    </div>
                </div>
                <?php foreach ($events as $ev):
                $tc = $type_colors[$ev['event_type']] ?? '#6b7280'; ?>
                <div class="d-flex gap-2 px-3 py-2 border-bottom align-items-start">
                    <div style="width:4px;border-radius:4px;background:<?= $tc ?>;min-height:40px;flex-shrink:0;margin-top:2px"></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold small text-truncate"><?= sanitize($ev['title']) ?></div>
                        <div class="smaller" style="color:<?= htmlspecialchars($ev['color']) ?>"><?= sanitize($ev['course_name']) ?></div>
                        <span style="font-size:9px;background:<?= $tc ?>22;color:<?= $tc ?>;border:1px solid <?= $tc ?>44;padding:1px 6px;border-radius:10px"><?= ucfirst($ev['event_type']) ?></span>
                        <?php if ($ev['due_time']): ?>
                        <div class="smaller text-muted mt-0.5"><i class="bi bi-alarm me-1"></i><?= date('g:i A',strtotime($ev['due_time'])) ?></div>
                        <?php endif; ?>
                        <?php if ($ev['description']): ?>
                        <div class="smaller text-muted mt-1"><?= sanitize(substr($ev['description'],0,60)) ?>...</div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; endforeach; endif; ?>
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
document.addEventListener('DOMContentLoaded', function() {
    var cal = new FullCalendar.Calendar(document.getElementById('studentCalendar'), {
        initialView: 'dayGridMonth',
        headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,listWeek,listMonth' },
        events: <?= json_encode($calendar_events) ?>,
        height: 480,
        eventClick: function(info) {
            var p = info.event.extendedProps;
            alert('📚 ' + info.event.title + '\n\nType: ' + p.type + '\nCourse: ' + p.course + (p.description ? '\n\n' + p.description : ''));
        }
    });
    cal.render();
});
</script>
</body></html>
