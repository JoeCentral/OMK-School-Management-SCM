<?php
require_once '../includes/auth.php';
requireLogin(['student']);
$db = getDB();
$current_user = getCurrentUser();

// Get student's section
$my_section = $db->prepare("
    SELECT ss.*, s.name as sec_name, cl.name as class_name, cl.grade_level
    FROM student_sections ss
    JOIN sections s ON s.id = ss.section_id
    JOIN classes cl ON cl.id = s.class_id
    WHERE ss.student_id = ? ORDER BY ss.enrolled_at DESC LIMIT 1
");
$my_section->execute([$current_user['id']]);
$my_section = $my_section->fetch();
$section_id = $my_section['section_id'] ?? 0;

// Are we viewing a specific course?
$course_id = (int)($_GET['course'] ?? 0);

// ─────────────────────────────────────────────
// COURSE DETAIL VIEW
// ─────────────────────────────────────────────
if ($course_id && $section_id) {
    $page_title = 'Course Detail';

    // Verify enrollment
    $enrolled = $db->prepare("SELECT id FROM student_sections WHERE student_id=? AND section_id=?");
    $enrolled->execute([$current_user['id'], $section_id]);
    if (!$enrolled->fetch()) { header('Location: courses.php'); exit; }

    // Course info
    $course = $db->prepare("SELECT * FROM courses WHERE id=?");
    $course->execute([$course_id]);
    $course = $course->fetch();
    if (!$course) { header('Location: courses.php'); exit; }

    // Teacher info
    $teacher = $db->prepare("
        SELECT u.* FROM teacher_courses tc
        JOIN users u ON u.id = tc.teacher_id
        WHERE tc.course_id=? AND tc.section_id=? LIMIT 1
    ");
    $teacher->execute([$course_id, $section_id]);
    $teacher = $teacher->fetch();

    // Posts
    $posts = $db->prepare("
        SELECT p.*, u.first_name, u.last_name
        FROM posts p JOIN users u ON u.id = p.teacher_id
        WHERE p.course_id=? AND p.section_id=?
        ORDER BY p.is_pinned DESC, p.created_at DESC
    ");
    $posts->execute([$course_id, $section_id]);
    $posts = $posts->fetchAll();

    // Upcoming agenda
    $agenda = $db->prepare("
        SELECT * FROM agenda
        WHERE course_id=? AND section_id=? AND event_date >= CURDATE()
        ORDER BY event_date ASC
    ");
    $agenda->execute([$course_id, $section_id]);
    $agenda = $agenda->fetchAll();

    // All agenda for calendar
    $all_agenda = $db->prepare("SELECT * FROM agenda WHERE course_id=? AND section_id=?");
    $all_agenda->execute([$course_id, $section_id]);
    $all_agenda = $all_agenda->fetchAll();

    $type_colors = ['assignment'=>'#f59e0b','quiz'=>'#3b82f6','exam'=>'#ef4444','homework'=>'#8b5cf6','project'=>'#10b981','other'=>'#6b7280'];
    $calendar_events = [];
    foreach ($all_agenda as $ev) {
        $calendar_events[] = [
            'title' => $ev['title'],
            'start' => $ev['event_date'],
            'color' => $type_colors[$ev['event_type']] ?? '#6b7280'
        ];
    }

    include '../includes/header.php';
    ?>
    <div class="omk-wrapper">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">

    <!-- Course header -->
    <div class="page-header" style="background:linear-gradient(135deg,<?= htmlspecialchars($course['color']) ?>dd,<?= htmlspecialchars($course['color']) ?>88)">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <a href="courses.php" class="btn btn-sm btn-light btn-icon-sm"><i class="bi bi-arrow-left"></i></a>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                    <span class="badge bg-white bg-opacity-25 text-white"><?= sanitize($course['code']) ?></span>
                    <?php if ($my_section): ?>
                    <span class="badge bg-white bg-opacity-25 text-white"><?= sanitize($my_section['class_name'].' — Sec '.$my_section['sec_name']) ?></span>
                    <?php endif; ?>
                </div>
                <h4 class="mb-0"><?= sanitize($course['name']) ?></h4>
                <?php if ($teacher): ?>
                <p class="mb-0 mt-1"><i class="bi bi-person me-1"></i><?= sanitize($teacher['first_name'].' '.$teacher['last_name']) ?></p>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <div class="text-center text-white px-3 py-2 rounded-3 bg-white bg-opacity-10">
                    <div class="fw-800 fs-5"><?= count($posts) ?></div>
                    <div style="font-size:11px;opacity:.8">Posts</div>
                </div>
                <div class="text-center text-white px-3 py-2 rounded-3 bg-white bg-opacity-10">
                    <div class="fw-800 fs-5"><?= count($agenda) ?></div>
                    <div style="font-size:11px;opacity:.8">Upcoming</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" id="courseTab">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabPosts"><i class="bi bi-chat-square-text me-2"></i>Updates (<?= count($posts) ?>)</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabAgenda"><i class="bi bi-list-ul me-2"></i>Agenda</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabCalendar"><i class="bi bi-calendar3 me-2"></i>Calendar</a></li>
    </ul>

    <div class="tab-content">
        <!-- Posts -->
        <div class="tab-pane fade show active" id="tabPosts">
            <?php if (empty($posts)): ?>
            <div class="omk-card text-center py-5 text-muted">
                <i class="bi bi-chat-square fs-2 d-block mb-2 opacity-30"></i>
                No posts yet from your teacher.
            </div>
            <?php else: foreach ($posts as $p): ?>
            <div class="post-item <?= $p['post_type'] ?> mb-3" style="border-left-color:<?= htmlspecialchars($course['color']) ?>">
                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                    <?php if ($p['is_pinned']): ?><i class="bi bi-pin-fill text-danger" title="Pinned"></i><?php endif; ?>
                    <span class="badge bg-<?= ['text'=>'secondary','announcement'=>'success','document'=>'warning'][$p['post_type']] ?? 'secondary' ?>-subtle text-<?= ['text'=>'secondary','announcement'=>'success','document'=>'warning'][$p['post_type']] ?? 'secondary' ?>">
                        <i class="bi bi-<?= ['text'=>'chat','announcement'=>'megaphone','document'=>'file-earmark'][$p['post_type']] ?? 'chat' ?> me-1"></i>
                        <?= ucfirst($p['post_type']) ?>
                    </span>
                </div>
                <h6 class="fw-700 mb-2"><?= sanitize($p['title']) ?></h6>
                <?php if ($p['content']): ?>
                <p class="text-muted small mb-2"><?= nl2br(sanitize($p['content'])) ?></p>
                <?php endif; ?>
                <?php if ($p['file_path']): ?>
                <a href="<?= BASE_URL ?>/uploads/documents/<?= htmlspecialchars($p['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i><?= sanitize($p['file_name']) ?>
                </a>
                <?php endif; ?>
                <div class="text-muted smaller mt-2">
                    <i class="bi bi-person me-1"></i><?= sanitize($p['first_name'].' '.$p['last_name']) ?>
                    <span class="ms-3"><i class="bi bi-clock me-1"></i><?= date('M d, Y g:i A', strtotime($p['created_at'])) ?></span>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Agenda list -->
        <div class="tab-pane fade" id="tabAgenda">
            <?php if (empty($agenda)): ?>
            <div class="omk-card text-center py-5 text-muted">
                <i class="bi bi-calendar-x fs-2 d-block mb-2 opacity-30"></i>
                No upcoming events for this course.
            </div>
            <?php else: ?>
            <div class="row g-3">
                <?php foreach ($agenda as $ev):
                $tc = $type_colors[$ev['event_type']] ?? '#6b7280'; ?>
                <div class="col-md-6">
                    <div class="omk-card" style="border-left:4px solid <?= $tc ?>">
                        <div class="card-body p-3">
                            <div class="d-flex gap-3">
                                <div class="text-center flex-shrink-0 px-2 py-1 rounded-2" style="background:<?= $tc ?>18;min-width:48px">
                                    <div class="fw-800 lh-1" style="color:<?= $tc ?>;font-size:22px"><?= date('d',strtotime($ev['event_date'])) ?></div>
                                    <div style="color:<?= $tc ?>;font-size:10px;text-transform:uppercase;font-weight:600"><?= date('M',strtotime($ev['event_date'])) ?></div>
                                </div>
                                <div>
                                    <div class="fw-700 small"><?= sanitize($ev['title']) ?></div>
                                    <span class="badge mt-1" style="background:<?= $tc ?>22;color:<?= $tc ?>;border:1px solid <?= $tc ?>44;font-size:10px"><?= ucfirst($ev['event_type']) ?></span>
                                    <?php if ($ev['due_time']): ?>
                                    <div class="text-muted smaller mt-1"><i class="bi bi-alarm me-1"></i><?= date('g:i A', strtotime($ev['due_time'])) ?></div>
                                    <?php endif; ?>
                                    <?php if ($ev['description']): ?>
                                    <div class="text-muted smaller mt-1"><?= sanitize($ev['description']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Calendar -->
        <div class="tab-pane fade" id="tabCalendar">
            <div class="omk-card">
                <div class="card-body p-3">
                    <div id="courseCalendar"></div>
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
    document.querySelector('a[href="#tabCalendar"]').addEventListener('shown.bs.tab', function() {
        if (window._calInit) return; window._calInit = true;
        var cal = new FullCalendar.Calendar(document.getElementById('courseCalendar'), {
            initialView: 'dayGridMonth',
            headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,listWeek' },
            events: <?= json_encode($calendar_events) ?>,
            height: 480,
            eventClick: function(info) { alert(info.event.title); }
        });
        cal.render();
    });
    </script>
    </body></html>
    <?php
    exit;
}

// ─────────────────────────────────────────────
// COURSES LIST VIEW
// ─────────────────────────────────────────────
$page_title = 'My Courses';

$my_courses = [];
if ($section_id) {
    $stmt = $db->prepare("
        SELECT DISTINCT c.*, u.first_name as teacher_fn, u.last_name as teacher_ln,
               (SELECT COUNT(*) FROM posts WHERE course_id=c.id AND section_id=?) as post_count,
               (SELECT COUNT(*) FROM agenda WHERE course_id=c.id AND section_id=? AND event_date >= CURDATE()) as upcoming_count
        FROM teacher_courses tc
        JOIN courses c ON c.id = tc.course_id
        JOIN users u ON u.id = tc.teacher_id
        WHERE tc.section_id = ?
    ");
    $stmt->execute([$section_id, $section_id, $section_id]);
    $my_courses = $stmt->fetchAll();
}

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4><i class="bi bi-book-fill me-2"></i>My Courses</h4>
            <p>
                <?php if ($my_section): ?>
                <?= sanitize($my_section['class_name']) ?> — Section <?= sanitize($my_section['sec_name']) ?> &nbsp;·&nbsp; <?= count($my_courses) ?> courses enrolled
                <?php else: ?>
                You are not enrolled in any section yet.
                <?php endif; ?>
            </p>
        </div>
        <div class="text-white-50 small"><?= date('l, F j, Y') ?></div>
    </div>
</div>

<?php if (empty($my_courses)): ?>
<div class="omk-card text-center py-5 text-muted">
    <i class="bi bi-book-half fs-1 d-block mb-3 opacity-20"></i>
    <h5 class="fw-700">No courses found</h5>
    <p class="small">You haven't been enrolled in any courses yet.<br>Please contact your coordinator.</p>
</div>
<?php else: ?>

<div class="row g-4">
    <?php foreach ($my_courses as $c): ?>
    <div class="col-sm-6 col-lg-4">
        <a href="courses.php?course=<?= $c['id'] ?>&section=<?= $section_id ?>" class="text-decoration-none">
            <div class="omk-card h-100 course-card" style="transition:transform .2s,box-shadow .2s;cursor:pointer">
                <!-- Color banner -->
                <div class="position-relative overflow-hidden" style="height:90px;background:<?= htmlspecialchars($c['color']) ?>">
                    <div style="position:absolute;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,0.1);top:-30px;right:-20px"></div>
                    <div style="position:absolute;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,0.08);bottom:-20px;left:20px"></div>
                    <div class="d-flex align-items-center justify-content-center h-100 position-relative">
                        <i class="bi bi-book-fill text-white" style="font-size:36px;opacity:0.85"></i>
                    </div>
                    <?php if ($c['upcoming_count'] > 0): ?>
                    <div class="position-absolute top-0 end-0 m-2">
                        <span class="badge bg-danger" style="font-size:10px"><i class="bi bi-bell-fill me-1"></i><?= $c['upcoming_count'] ?> upcoming</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Card body -->
                <div class="card-body p-4">
                    <div class="mb-1">
                        <span class="badge" style="background:<?= htmlspecialchars($c['color']) ?>22;color:<?= htmlspecialchars($c['color']) ?>;border:1px solid <?= htmlspecialchars($c['color']) ?>44;font-size:10px"><?= sanitize($c['code']) ?></span>
                    </div>
                    <h6 class="fw-700 mb-1 text-dark"><?= sanitize($c['name']) ?></h6>
                    <?php if ($c['description']): ?>
                    <p class="text-muted smaller mb-3"><?= sanitize(substr($c['description'],0,70)) ?>...</p>
                    <?php endif; ?>

                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="user-avatar-sm bg-primary flex-shrink-0" style="font-size:10px">
                            <?= strtoupper(substr($c['teacher_fn'],0,1).substr($c['teacher_ln'],0,1)) ?>
                        </div>
                        <span class="small text-muted"><?= sanitize($c['teacher_fn'].' '.$c['teacher_ln']) ?></span>
                    </div>

                    <div class="d-flex gap-3 pt-3 border-top">
                        <div class="text-center flex-grow-1">
                            <div class="fw-700 small"><?= $c['post_count'] ?></div>
                            <div class="text-muted" style="font-size:10px">Posts</div>
                        </div>
                        <div class="text-center flex-grow-1">
                            <div class="fw-700 small <?= $c['upcoming_count']>0 ? 'text-danger' : '' ?>"><?= $c['upcoming_count'] ?></div>
                            <div class="text-muted" style="font-size:10px">Upcoming</div>
                        </div>
                        <div class="text-center flex-grow-1">
                            <i class="bi bi-arrow-right-circle text-primary" style="font-size:20px"></i>
                            <div class="text-muted" style="font-size:10px">Open</div>
                        </div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
<style>
.course-card:hover { transform: translateY(-5px); box-shadow: 0 12px 30px rgba(0,0,0,0.12) !important; }
</style>
</body></html>