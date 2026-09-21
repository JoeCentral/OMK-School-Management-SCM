<?php
require_once '../includes/auth.php';
requireLogin(['teacher']);
$page_title = 'Agenda';
$db = getDB();
$current_user = getCurrentUser();
$msg = $error = '';

// Get teacher's sections
$my_sections = $db->prepare("
    SELECT DISTINCT s.id, s.name as sec_name, cl.name as class_name, cl.grade_level
    FROM teacher_courses tc JOIN sections s ON s.id=tc.section_id JOIN classes cl ON cl.id=s.class_id
    WHERE tc.teacher_id=? ORDER BY cl.grade_level,s.name
");
$my_sections->execute([$current_user['id']]);
$my_sections = $my_sections->fetchAll();

$section_id = (int)($_GET['section'] ?? ($my_sections[0]['id'] ?? 0));

// Get courses for this section
$my_courses = $db->prepare("
    SELECT DISTINCT c.id,c.name,c.color FROM teacher_courses tc JOIN courses c ON c.id=tc.course_id
    WHERE tc.teacher_id=? AND tc.section_id=?
");
$my_courses->execute([$current_user['id'], $section_id]);
$my_courses = $my_courses->fetchAll();

// Handle add/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_event') {
        $title   = sanitize($_POST['title'] ?? '');
        $desc    = sanitize($_POST['description'] ?? '');
        $type    = $_POST['event_type'] ?? 'other';
        $date    = $_POST['event_date'] ?? '';
        $time    = $_POST['due_time'] ?? null;
        $cid     = (int)$_POST['course_id'];
        $sid     = (int)$_POST['section_id'];
        if ($title && $date && $cid && $sid) {
            $db->prepare("INSERT INTO agenda (teacher_id,section_id,course_id,title,description,event_type,event_date,due_time) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$current_user['id'],$sid,$cid,$title,$desc,$type,$date,$time?:null]);
            // Notify students
            $students = $db->prepare("SELECT student_id FROM student_sections WHERE section_id=?");
            $students->execute([$sid]);
            foreach ($students->fetchAll() as $st) {
                addNotification($st['student_id'], ucfirst($type).': '.$title, 'New agenda event on '.date('M d',strtotime($date)), 'warning');
            }
            $msg = "Event added to agenda!";
        } else $error = "Title, date, course and section are required.";
    }
    if ($action === 'delete_event') {
        $db->prepare("DELETE FROM agenda WHERE id=? AND teacher_id=?")->execute([(int)$_POST['event_id'],$current_user['id']]);
        $msg = "Event deleted.";
    }
}

// Get all agenda events for calendar (JSON)
$all_events = $db->prepare("
    SELECT a.*, c.name as course_name, c.color
    FROM agenda a JOIN courses c ON c.id=a.course_id
    WHERE a.teacher_id=?
");
$all_events->execute([$current_user['id']]);
$all_events = $all_events->fetchAll();

$calendar_events = [];
$type_colors = ['assignment'=>'#f59e0b','quiz'=>'#3b82f6','exam'=>'#ef4444','homework'=>'#8b5cf6','project'=>'#10b981','other'=>'#6b7280'];
foreach ($all_events as $ev) {
    $calendar_events[] = [
        'title' => $ev['course_name'].': '.$ev['title'],
        'start' => $ev['event_date'],
        'color' => $type_colors[$ev['event_type']] ?? '#6b7280',
        'extendedProps' => ['type'=>$ev['event_type'],'description'=>$ev['description']]
    ];
}

// Upcoming events list
$upcoming = $db->prepare("
    SELECT a.*, c.name as course_name, c.color, s.name as sec_name, cl.name as class_name
    FROM agenda a JOIN courses c ON c.id=a.course_id JOIN sections s ON s.id=a.section_id JOIN classes cl ON cl.id=s.class_id
    WHERE a.teacher_id=? AND a.event_date >= CURDATE()
    ORDER BY a.event_date ASC LIMIT 10
");
$upcoming->execute([$current_user['id']]);
$upcoming = $upcoming->fetchAll();

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <h4><i class="bi bi-calendar-event me-2"></i>Agenda & Calendar</h4>
    <p>Manage assignments, quizzes, exams and events for your classes.</p>
</div>

<?php if ($msg): ?><div class="alert alert-success omk-alert alert-auto-dismiss"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger omk-alert"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4">
    <!-- Add Event form -->
    <div class="col-lg-4">
        <div class="omk-card mb-3">
            <div class="card-header"><i class="bi bi-calendar-plus me-2 text-primary"></i>Add Event</div>
            <div class="card-body p-3">
                <form method="POST">
                    <input type="hidden" name="action" value="add_event">
                    <div class="mb-3">
                        <label class="form-label">Section *</label>
                        <select name="section_id" class="form-select" id="agendaSection" required onchange="window.location='agenda.php?section='+this.value">
                            <?php foreach ($my_sections as $ms): ?>
                            <option value="<?= $ms['id'] ?>" <?= $ms['id']==$section_id?'selected':'' ?>><?= sanitize($ms['class_name'].' - '.$ms['sec_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Course *</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">— Select —</option>
                            <?php foreach ($my_courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Event Title *</label>
                        <input type="text" name="title" class="form-control" placeholder="Chapter 3 Quiz" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Event Type *</label>
                        <select name="event_type" class="form-select">
                            <option value="assignment">Assignment</option>
                            <option value="quiz">Quiz</option>
                            <option value="exam">Exam</option>
                            <option value="homework">Homework</option>
                            <option value="project">Project</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date *</label>
                        <input type="date" name="event_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Due Time <span class="text-muted small">(optional)</span></label>
                        <input type="time" name="due_time" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Additional details..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-circle me-2"></i>Add to Agenda</button>
                </form>
            </div>
        </div>

        <!-- Event type legend -->
        <div class="omk-card">
            <div class="card-header small fw-semibold">Event Type Legend</div>
            <div class="card-body py-2 px-3">
                <?php foreach ($type_colors as $type => $color): ?>
                <div class="d-flex align-items-center gap-2 py-1">
                    <div style="width:12px;height:12px;border-radius:3px;background:<?= $color ?>;flex-shrink:0"></div>
                    <span class="small text-capitalize"><?= $type ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Calendar + upcoming -->
    <div class="col-lg-8">
        <div class="omk-card mb-4">
            <div class="card-body p-3">
                <div id="agendaCalendar"></div>
            </div>
        </div>

        <div class="omk-card">
            <div class="card-header"><i class="bi bi-list-ul me-2 text-primary"></i>Upcoming Events</div>
            <div class="card-body p-0">
                <?php if (empty($upcoming)): ?>
                <div class="text-center py-4 text-muted small"><i class="bi bi-calendar-x fs-3 d-block mb-2 opacity-30"></i>No upcoming events</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table omk-table mb-0">
                        <thead><tr><th>Date</th><th>Event</th><th>Course</th><th>Section</th><th>Type</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($upcoming as $ev): ?>
                        <tr>
                            <td class="fw-semibold small"><?= date('M d, Y', strtotime($ev['event_date'])) ?></td>
                            <td class="small"><?= sanitize($ev['title']) ?></td>
                            <td><span class="small" style="color:<?= htmlspecialchars($ev['color']) ?>"><?= sanitize($ev['course_name']) ?></span></td>
                            <td class="text-muted small"><?= sanitize($ev['class_name'].'-'.$ev['sec_name']) ?></td>
                            <td>
                                <span class="badge" style="background:<?= $type_colors[$ev['event_type']]??'#6b7280' ?>22;color:<?= $type_colors[$ev['event_type']]??'#6b7280' ?>;border:1px solid <?= $type_colors[$ev['event_type']]??'#6b7280' ?>55">
                                    <?= ucfirst($ev['event_type']) ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="delete_event">
                                    <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger btn-icon-sm" data-confirm="Delete this event?" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
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
    var calEl = document.getElementById('agendaCalendar');
    var events = <?= json_encode($calendar_events) ?>;
    var cal = new FullCalendar.Calendar(calEl, {
        initialView: 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listWeek' },
        events: events,
        height: 420,
        eventClick: function(info) {
            alert(info.event.title + '\n' + (info.event.extendedProps.description || ''));
        }
    });
    cal.render();
});
</script>
</body></html>
