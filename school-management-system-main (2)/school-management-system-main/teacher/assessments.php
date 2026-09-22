<?php
require_once '../includes/auth.php';
requireLogin(['teacher']);
$page_title = 'Assessments & Grades';
$db = getDB();
$current_user = getCurrentUser();
$msg = $error = '';

$my_sections = $db->prepare("\n    SELECT DISTINCT s.id, s.name as sec_name, cl.name as class_name, cl.grade_level\n    FROM teacher_courses tc\n    JOIN sections s ON s.id=tc.section_id\n    JOIN classes cl ON cl.id=s.class_id\n    WHERE tc.teacher_id=?\n    ORDER BY cl.grade_level, s.name\n");
$my_sections->execute([$current_user['id']]);
$my_sections = $my_sections->fetchAll();
$section_id = (int)($_GET['section'] ?? ($my_sections[0]['id'] ?? 0));

$valid_section = false;
foreach ($my_sections as $section) {
    if ((int)$section['id'] === $section_id) { $valid_section = true; break; }
}
if (!$valid_section && !empty($my_sections)) $section_id = (int)$my_sections[0]['id'];

$my_courses = $db->prepare("\n    SELECT DISTINCT c.id, c.name, c.code, c.color\n    FROM teacher_courses tc\n    JOIN courses c ON c.id=tc.course_id\n    WHERE tc.teacher_id=? AND tc.section_id=?\n    ORDER BY c.name\n");
$my_courses->execute([$current_user['id'], $section_id]);
$my_courses = $my_courses->fetchAll();

function assessmentAgendaType(string $type): string {
    return in_array($type, ['assignment','quiz','test','exam','homework','project'], true) ? $type : 'other';
}

function updateAssessmentStatus(PDO $db, int $assessmentId): void {
    $stmt = $db->prepare("\n        SELECT a.section_id, a.course_id, a.assessment_date, COUNT(ss.student_id) student_count,\n               COUNT(DISTINCT CASE WHEN g.score IS NOT NULL THEN g.student_id END) graded_count\n        FROM assessments a\n        LEFT JOIN student_sections ss ON ss.section_id=a.section_id\n        LEFT JOIN grades g ON g.assessment_id=a.id AND g.student_id=ss.student_id\n        WHERE a.id=?\n        GROUP BY a.id\n    ");
    $stmt->execute([$assessmentId]);
    $row = $stmt->fetch();
    if (!$row) return;

    $today = date('Y-m-d');
    if ($row['assessment_date'] > $today) {
        $status = 'upcoming';
    } elseif ((int)$row['student_count'] > 0 && (int)$row['graded_count'] >= (int)$row['student_count']) {
        $status = 'graded';
    } else {
        $status = 'grading';
    }
    $db->prepare("UPDATE assessments SET status=? WHERE id=?")->execute([$status, $assessmentId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_assessment') {
        $sid = (int)($_POST['section_id'] ?? 0);
        $cid = (int)($_POST['course_id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $type = $_POST['assessment_type'] ?? 'test';
        $date = $_POST['assessment_date'] ?? '';
        $time = trim($_POST['assessment_time'] ?? '');
        $maxScore = (float)($_POST['max_score'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');

        if (!teacherOwnsCourseSection($db, (int)$current_user['id'], $cid, $sid)) {
            $error = 'You can only create assessments for your assigned courses and sections.';
        } elseif (!$title || !$date || !$cid || !$sid || $maxScore <= 0) {
            $error = 'Assessment name, course, date and total marks are required.';
        } elseif (!in_array($type, ['quiz','test','exam','assignment','project','midterm','final','participation'], true)) {
            $error = 'Invalid assessment type.';
        } else {
            try {
                $db->beginTransaction();
                $stmt = $db->prepare("\n                    INSERT INTO assessments (teacher_id, course_id, section_id, title, assessment_type, assessment_date, assessment_time, max_score, description)\n                    VALUES (?,?,?,?,?,?,?,?,?)\n                ");
                $stmt->execute([$current_user['id'], $cid, $sid, $title, $type, $date, $time !== '' ? $time : null, $maxScore, $description]);
                $assessmentId = (int)$db->lastInsertId();

                $agenda = $db->prepare("\n                    INSERT INTO agenda (teacher_id, section_id, assessment_id, course_id, title, description, event_type, event_date, due_time)\n                    VALUES (?,?,?,?,?,?,?,?,?)\n                ");
                $agenda->execute([$current_user['id'], $sid, $assessmentId, $cid, $title, $description, assessmentAgendaType($type), $date, $time !== '' ? $time : null]);
                $agendaId = (int)$db->lastInsertId();
                $db->prepare("UPDATE assessments SET agenda_id=? WHERE id=?")->execute([$agendaId, $assessmentId]);

                $db->commit();

                $students = $db->prepare("SELECT student_id FROM student_sections WHERE section_id=?");
                $students->execute([$sid]);
                foreach ($students->fetchAll() as $student) {
                    addNotification(
                        $student['student_id'],
                        ucfirst($type) . ': ' . $title,
                        'Scheduled for ' . date('M d, Y', strtotime($date)) . '.',
                        'warning',
                        BASE_URL . '/student/grades.php'
                    );
                }
                $msg = 'Assessment created and added to the agenda.';
                $section_id = $sid;
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                $error = 'Unable to create the assessment. Please try again.';
            }
        }
    }

    if ($action === 'update_assessment') {
        $assessmentId = (int)($_POST['assessment_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM assessments WHERE id=? AND teacher_id=?");
        $stmt->execute([$assessmentId, $current_user['id']]);
        $assessment = $stmt->fetch();

        if (!$assessment) {
            $error = 'Assessment not found.';
        } else {
            $title = sanitize($_POST['title'] ?? '');
            $type = $_POST['assessment_type'] ?? $assessment['assessment_type'];
            $date = $_POST['assessment_date'] ?? '';
            $time = trim($_POST['assessment_time'] ?? '');
            $maxScore = (float)($_POST['max_score'] ?? 0);
            $description = sanitize($_POST['description'] ?? '');

            if (!$title || !$date || $maxScore <= 0) {
                $error = 'Assessment name, date and total marks are required.';
            } elseif (!in_array($type, ['quiz','test','exam','assignment','project','midterm','final','participation'], true)) {
                $error = 'Invalid assessment type.';
            } else {
                $maxRecorded = $db->prepare('SELECT MAX(score) FROM grades WHERE assessment_id=?');
                $maxRecorded->execute([$assessmentId]);
                $highestRecorded = $maxRecorded->fetchColumn();
                if ($highestRecorded !== null && (float)$highestRecorded > $maxScore) {
                    $error = 'Total marks cannot be lower than an already recorded mark.';
                } else try {
                    $db->beginTransaction();
                    $db->prepare("\n                        UPDATE assessments SET title=?, assessment_type=?, assessment_date=?, assessment_time=?, max_score=?, description=?\n                        WHERE id=? AND teacher_id=?\n                    ")->execute([$title, $type, $date, $time !== '' ? $time : null, $maxScore, $description, $assessmentId, $current_user['id']]);

                    // Keep existing student results aligned with changes to the assessment.
                    // The grades.updated_at column records this as a grade-related update.
                    $db->prepare("\n                        UPDATE grades\n                        SET max_score=?, grade_date=?\n                        WHERE assessment_id=?\n                    ")->execute([$maxScore, $date, $assessmentId]);

                    if (!empty($assessment['agenda_id'])) {
                        $db->prepare("\n                            UPDATE agenda SET title=?, description=?, event_type=?, event_date=?, due_time=?\n                            WHERE id=? AND teacher_id=?\n                        ")->execute([$title, $description, assessmentAgendaType($type), $date, $time !== '' ? $time : null, $assessment['agenda_id'], $current_user['id']]);
                    } else {
                        $agenda = $db->prepare("\n                            INSERT INTO agenda (teacher_id, section_id, assessment_id, course_id, title, description, event_type, event_date, due_time)\n                            VALUES (?,?,?,?,?,?,?,?,?)\n                        ");
                        $agenda->execute([$current_user['id'], $assessment['section_id'], $assessmentId, $assessment['course_id'], $title, $description, assessmentAgendaType($type), $date, $time !== '' ? $time : null]);
                        $db->prepare("UPDATE assessments SET agenda_id=? WHERE id=?")->execute([(int)$db->lastInsertId(), $assessmentId]);
                    }
                    $db->commit();
                    updateAssessmentStatus($db, $assessmentId);
                    $msg = 'Assessment updated. Its agenda date was updated too.';
                    $section_id = (int)$assessment['section_id'];
                } catch (Throwable $e) {
                    if ($db->inTransaction()) $db->rollBack();
                    $error = 'Unable to update the assessment. Please try again.';
                }
            }
        }
    }

    if ($action === 'delete_assessment') {
        $assessmentId = (int)($_POST['assessment_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM assessments WHERE id=? AND teacher_id=?");
        $stmt->execute([$assessmentId, $current_user['id']]);
        $assessment = $stmt->fetch();
        if ($assessment) {
            try {
                $db->beginTransaction();
                $db->prepare("DELETE FROM grades WHERE assessment_id=?")->execute([$assessmentId]);
                // Remove feedback tied to an assessment before removing the assessment itself.
                $db->prepare("DELETE FROM teacher_evaluations WHERE assessment_id=?")->execute([$assessmentId]);
                if (!empty($assessment['agenda_id'])) {
                    $db->prepare("DELETE FROM agenda WHERE id=? AND teacher_id=?")->execute([(int)$assessment['agenda_id'], $current_user['id']]);
                } else {
                    $db->prepare("DELETE FROM agenda WHERE assessment_id=? AND teacher_id=?")->execute([$assessmentId, $current_user['id']]);
                }
                $db->prepare("DELETE FROM assessments WHERE id=? AND teacher_id=?")->execute([$assessmentId, $current_user['id']]);
                $db->commit();
                $msg = 'Assessment and its linked agenda event were deleted.';
                $section_id = (int)$assessment['section_id'];
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                $error = 'Unable to delete the assessment. Please try again.';
            }
        }
    }

    if ($action === 'save_grades') {
        $assessmentId = (int)($_POST['assessment_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM assessments WHERE id=? AND teacher_id=?");
        $stmt->execute([$assessmentId, $current_user['id']]);
        $assessment = $stmt->fetch();
        if (!$assessment) {
            $error = 'Assessment not found.';
        } else {
            try {
                $db->beginTransaction();
                $students = $db->prepare("\n                    SELECT u.id, u.first_name, u.last_name\n                    FROM student_sections ss JOIN users u ON u.id=ss.student_id\n                    WHERE ss.section_id=? AND u.role='student' AND u.is_active=1\n                    ORDER BY u.first_name, u.last_name\n                ");
                $students->execute([$assessment['section_id']]);
                $studentRows = $students->fetchAll();
                $upsert = $db->prepare("\n                    SELECT id FROM grades WHERE assessment_id=? AND student_id=? LIMIT 1\n                ");
                $insert = $db->prepare("\n                    INSERT INTO grades (student_id, course_id, section_id, assessment_id, assessment_type, assessment_name, score, max_score, grade_date, notes, recorded_by)\n                    VALUES (?,?,?,?,?,?,?,?,?,?,?)\n                ");
                $update = $db->prepare("\n                    UPDATE grades SET course_id=?, section_id=?, assessment_type=?, assessment_name=?, score=?, max_score=?, grade_date=?, notes=?, recorded_by=?\n                    WHERE id=?\n                ");
                $delete = $db->prepare("DELETE FROM grades WHERE id=?");

                foreach ($studentRows as $student) {
                    $sid = (int)$student['id'];
                    $raw = trim($_POST['score'][$sid] ?? '');
                    $note = sanitize($_POST['note'][$sid] ?? '');
                    $gradeId = null;
                    $upsert->execute([$assessmentId, $sid]);
                    $gradeId = $upsert->fetchColumn();

                    if ($raw === '') {
                        if ($gradeId) $delete->execute([(int)$gradeId]);
                        continue;
                    }

                    if (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > (float)$assessment['max_score']) {
                        throw new RuntimeException('Invalid marks entered for ' . $student['first_name'] . ' ' . $student['last_name'] . '.');
                    }
                    $score = (float)$raw;
                    if ($gradeId) {
                        $update->execute([$assessment['course_id'], $assessment['section_id'], $assessment['assessment_type'], $assessment['title'], $score, $assessment['max_score'], $assessment['assessment_date'], $note, $current_user['id'], (int)$gradeId]);
                    } else {
                        $insert->execute([$sid, $assessment['course_id'], $assessment['section_id'], $assessmentId, $assessment['assessment_type'], $assessment['title'], $score, $assessment['max_score'], $assessment['assessment_date'], $note, $current_user['id']]);
                    }
                }
                updateAssessmentStatus($db, $assessmentId);
                $db->commit();
                $msg = 'Grades saved successfully.';
                $section_id = (int)$assessment['section_id'];
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to save grades. Please try again.';
            }
        }
    }
}

$selected_assessment_id = (int)($_GET['assessment'] ?? 0);
$status_filter = $_GET['status'] ?? 'all';
if (!in_array($status_filter, ['all', 'upcoming', 'grading', 'completed'], true)) $status_filter = 'all';
$assessment_stmt = $db->prepare("\n    SELECT a.*, c.name AS course_name, c.code AS course_code, c.color AS course_color,\n           s.name AS sec_name, cl.name AS class_name, ga.event_date AS agenda_date\n    FROM assessments a\n    JOIN courses c ON c.id=a.course_id\n    JOIN sections s ON s.id=a.section_id\n    JOIN classes cl ON cl.id=s.class_id\n    LEFT JOIN agenda ga ON ga.id=a.agenda_id\n    WHERE a.teacher_id=? AND a.section_id=?\n    ORDER BY a.assessment_date DESC, a.created_at DESC\n");
$assessment_stmt->execute([$current_user['id'], $section_id]);
$assessments = $assessment_stmt->fetchAll();

foreach ($assessments as &$a) updateAssessmentStatus($db, (int)$a['id']);
unset($a);
if (!empty($assessments)) {
    $assessment_stmt->execute([$current_user['id'], $section_id]);
    $assessments = $assessment_stmt->fetchAll();
}

if ($status_filter !== 'all') {
    $stored_status = $status_filter === 'completed' ? 'graded' : $status_filter;
    $assessments = array_values(array_filter($assessments, fn($assessment) => $assessment['status'] === $stored_status));
}

$selected = null;
foreach ($assessments as $a) {
    if ((int)$a['id'] === $selected_assessment_id) { $selected = $a; break; }
}
if (!$selected && !empty($assessments)) {
    foreach ($assessments as $a) {
        if ($a['status'] !== 'upcoming') { $selected = $a; break; }
    }
    if (!$selected) $selected = $assessments[0];
}

$students = [];
$grade_map = [];
if ($selected) {
    $student_stmt = $db->prepare("\n        SELECT u.id, u.user_id_number, u.first_name, u.last_name\n        FROM student_sections ss\n        JOIN users u ON u.id=ss.student_id\n        WHERE ss.section_id=? AND u.role='student' AND u.is_active=1\n        ORDER BY u.first_name, u.last_name\n    ");
    $student_stmt->execute([$selected['section_id']]);
    $students = $student_stmt->fetchAll();

    $grade_stmt = $db->prepare("SELECT student_id, score, notes FROM grades WHERE assessment_id=?");
    $grade_stmt->execute([$selected['id']]);
    foreach ($grade_stmt->fetchAll() as $g) $grade_map[(int)$g['student_id']] = $g;
}

$stats = ['student_count'=>0,'graded_count'=>0,'average'=>0,'highest'=>null,'lowest'=>null,'pass_rate'=>0];
if ($selected) {
    $stats['student_count'] = count($students);
    $scores = [];
    foreach ($students as $student) {
        if (isset($grade_map[(int)$student['id']]) && $grade_map[(int)$student['id']]['score'] !== null) {
            $scores[] = ((float)$grade_map[(int)$student['id']]['score'] / (float)$selected['max_score']) * 100;
        }
    }
    $stats['graded_count'] = count($scores);
    if ($scores) {
        $stats['average'] = array_sum($scores) / count($scores);
        $stats['highest'] = max($scores);
        $stats['lowest'] = min($scores);
        $stats['pass_rate'] = count(array_filter($scores, fn($v) => $v >= 50)) / count($scores) * 100;
    }
}

$calendar_events = [];
foreach ($assessments as $a) {
    $calendar_events[] = [
        'id' => (string)$a['id'],
        'title' => $a['course_name'] . ': ' . $a['title'],
        'start' => $a['assessment_date'] . (!empty($a['assessment_time']) ? 'T' . $a['assessment_time'] : ''),
        'color' => $a['course_color'] ?: '#4f46e5',
        'url' => 'assessments.php?section=' . (int)$section_id . '&assessment=' . (int)$a['id'],
        'extendedProps' => ['status'=>$a['status'], 'type'=>$a['assessment_type']]
    ];
}

$cur_section = null;
foreach ($my_sections as $s) if ((int)$s['id'] === $section_id) { $cur_section = $s; break; }

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content teacher-class-page">

<div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <div class="page-kicker"><i class="bi bi-clipboard-data me-1"></i>Class assessment records</div>
            <h4>Assessments & Grades</h4>
            <p>Create a record for a paper-based assessment, then enter student marks.</p>
        </div>
        <button class="btn btn-light page-header-action" data-bs-toggle="modal" data-bs-target="#createAssessmentModal"><i class="bi bi-plus-circle me-2"></i>New Assessment</button>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-success omk-alert alert-auto-dismiss"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger omk-alert"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($cur_section): ?>
<div class="class-workspace-card mb-4">
    <div class="class-banner class-banner-compact">
        <div>
            <div class="class-eyebrow"><i class="bi bi-person-workspace me-1"></i>Teacher Workspace</div>
            <h3><?= sanitize($cur_section['class_name']) ?> <span class="fw-normal">— Section <?= sanitize($cur_section['sec_name']) ?></span></h3>
            <div class="class-banner-meta">
                <span><i class="bi bi-people me-1"></i>Class records</span>
                <span><i class="bi bi-clipboard-data me-1"></i>Assessments & grades</span>
            </div>
        </div>
        <div class="class-banner-icon"><i class="bi bi-clipboard-check"></i></div>
    </div>
    <nav class="class-tabs" aria-label="Class navigation">
        <a href="classes.php?section=<?= $section_id ?>&view=overview" class="class-tab">
            <span class="class-tab-icon"><i class="bi bi-grid-1x2"></i></span><span>Overview</span>
        </a>
        <a href="classes.php?section=<?= $section_id ?>&view=students" class="class-tab">
            <span class="class-tab-icon"><i class="bi bi-people"></i></span><span>Students</span>
        </a>
        <a href="classes.php?section=<?= $section_id ?>&view=posts" class="class-tab">
            <span class="class-tab-icon"><i class="bi bi-megaphone"></i></span><span>Posts</span>
        </a>
        <a href="assessments.php?section=<?= $section_id ?>" class="class-tab active" aria-current="page">
            <span class="class-tab-icon"><i class="bi bi-clipboard-data"></i></span><span>Assessments & Grades</span>
        </a>
        <a href="classes.php?section=<?= $section_id ?>&view=performance" class="class-tab">
            <span class="class-tab-icon"><i class="bi bi-bar-chart"></i></span><span>Performance</span>
        </a>
    </nav>
    <div class="class-page-heading">
        <div class="class-page-heading-icon"><i class="bi bi-clipboard-data"></i></div>
        <div>
            <div class="class-breadcrumb">My Classes <span>/</span> <?= sanitize($cur_section['class_name'].' - '.$cur_section['sec_name']) ?></div>
            <h5>Assessments & Grades</h5>
            <p>Record marks for paper-based assessments and keep them connected to the class calendar.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#4f46e5,#4338ca)"><div class="stat-label">Assessments</div><div class="stat-num"><?= count($assessments) ?></div><i class="bi bi-clipboard-check stat-icon"></i></div></div>
    <div class="col-6 col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8)"><div class="stat-label">Completed</div><div class="stat-num"><?= count(array_filter($assessments, fn($a)=>$a['status']==='graded')) ?></div><i class="bi bi-check2-circle stat-icon"></i></div></div>
    <div class="col-6 col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#d97706)"><div class="stat-label">Pending Grades</div><div class="stat-num"><?= count(array_filter($assessments, fn($a)=>$a['status']==='grading')) ?></div><i class="bi bi-hourglass-split stat-icon"></i></div></div>
    <div class="col-6 col-md-3"><div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669)"><div class="stat-label">Upcoming</div><div class="stat-num"><?= count(array_filter($assessments, fn($a)=>$a['status']==='upcoming')) ?></div><i class="bi bi-calendar-event stat-icon"></i></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="omk-card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="bi bi-list-check me-2 text-primary"></i>Assessment Records</span>
                <div class="btn-group btn-group-sm" role="group">
                    <a class="btn <?= $status_filter==='all'?'btn-secondary':'btn-outline-secondary' ?>" href="?section=<?= $section_id ?>">All</a>
                    <a class="btn <?= $status_filter==='upcoming'?'btn-warning':'btn-outline-warning' ?>" href="?section=<?= $section_id ?>&status=upcoming">Upcoming</a>
                    <a class="btn <?= $status_filter==='grading'?'btn-primary':'btn-outline-primary' ?>" href="?section=<?= $section_id ?>&status=grading">Grading</a>
                    <a class="btn <?= $status_filter==='completed'?'btn-success':'btn-outline-success' ?>" href="?section=<?= $section_id ?>&status=completed">Completed</a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (!$assessments): ?>
                    <div class="text-center py-5 text-muted"><i class="bi bi-clipboard2-x fs-2 d-block mb-2 opacity-30"></i>No assessments yet. Create the first one for this class.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                    <?php foreach ($assessments as $a):
                        $statusClass = ['upcoming'=>'warning','grading'=>'primary','graded'=>'success'][$a['status']] ?? 'secondary';
                        $statusLabel = $a['status'] === 'graded' ? 'Completed' : ucfirst($a['status']);
                        $isSelected = $selected && (int)$selected['id'] === (int)$a['id'];
                    ?>
                        <a href="assessments.php?section=<?= $section_id ?>&assessment=<?= (int)$a['id'] ?>" class="list-group-item list-group-item-action py-3 px-4 <?= $isSelected?'bg-light':'' ?>" id="<?= $a['status']==='grading'?'grading':($a['status']==='graded'?'completed':'upcoming') ?>">
                            <div class="d-flex align-items-start gap-3">
                                <div class="assessment-icon" style="background:<?= htmlspecialchars(($a['course_color'] ?: '#4f46e5')) ?>16;color:<?= htmlspecialchars($a['course_color'] ?: '#4f46e5') ?>"><i class="bi bi-clipboard-check"></i></div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div>
                                            <div class="fw-700 text-dark text-truncate"><?= sanitize($a['title']) ?></div>
                                            <div class="smaller text-muted"><?= sanitize($a['course_name']) ?> · <?= sanitize($a['class_name'].'-'.$a['sec_name']) ?></div>
                                        </div>
                                        <span class="badge bg-<?= $statusClass ?>-subtle text-<?= $statusClass ?> border border-<?= $statusClass ?>-subtle"><?= $statusLabel ?></span>
                                    </div>
                                    <div class="d-flex flex-wrap gap-3 smaller text-muted mt-2">
                                        <span><i class="bi bi-calendar3 me-1"></i><?= date('M d, Y', strtotime($a['assessment_date'])) ?></span>
                                        <?php if ($a['assessment_time']): ?><span><i class="bi bi-clock me-1"></i><?= date('g:i A', strtotime($a['assessment_time'])) ?></span><?php endif; ?>
                                        <span><i class="bi bi-tag me-1"></i><?= ucfirst($a['assessment_type']) ?></span>
                                        <span><i class="bi bi-award me-1"></i><?= rtrim(rtrim(number_format((float)$a['max_score'],2,'.',''), '0'), '.') ?> marks</span>
                                    </div>
                                </div>
                                <i class="bi bi-chevron-right text-muted"></i>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="omk-card">
            <div class="card-header"><i class="bi bi-calendar3 me-2 text-primary"></i>Assessment Calendar</div>
            <div class="card-body"><div id="assessmentCalendar"></div></div>
        </div>
    </div>

    <div class="col-xl-5">
        <?php if (!$selected): ?>
            <div class="omk-card h-100 text-center py-5 text-muted"><i class="bi bi-mouse2 fs-2 d-block mb-2 opacity-30"></i><h6>Select an assessment</h6><p class="small mb-0">Choose a record from the list to view details or enter marks.</p></div>
        <?php else: ?>
            <div class="omk-card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div><i class="bi bi-clipboard-check me-2 text-primary"></i><?= sanitize($selected['title']) ?></div>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editAssessmentModal"><i class="bi bi-pencil"></i></button>
                            <form method="POST" onsubmit="return confirm('Delete this assessment and all recorded marks?');">
                                <input type="hidden" name="action" value="delete_assessment"><input type="hidden" name="assessment_id" value="<?= (int)$selected['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= ucfirst($selected['assessment_type']) ?></span>
                        <span class="badge bg-light text-dark border"><i class="bi bi-award me-1"></i><?= rtrim(rtrim(number_format((float)$selected['max_score'],2,'.',''), '0'), '.') ?> marks</span>
                        <span class="badge bg-light text-dark border"><i class="bi bi-calendar3 me-1"></i><?= date('M d, Y', strtotime($selected['assessment_date'])) ?></span>
                    </div>
                    <?php if ($selected['description']): ?><p class="text-muted small mb-3"><?= nl2br(sanitize($selected['description'])) ?></p><?php endif; ?>
                    <div class="row g-2">
                        <div class="col-6"><div class="assessment-mini-stat"><span>Students</span><strong><?= $stats['student_count'] ?></strong></div></div>
                        <div class="col-6"><div class="assessment-mini-stat"><span>Graded</span><strong><?= $stats['graded_count'] ?></strong></div></div>
                        <div class="col-4"><div class="assessment-mini-stat"><span>Average</span><strong><?= $stats['graded_count'] ? number_format($stats['average'],1).'%' : '—' ?></strong></div></div>
                        <div class="col-4"><div class="assessment-mini-stat"><span>Highest</span><strong><?= $stats['highest'] !== null ? number_format($stats['highest'],1).'%' : '—' ?></strong></div></div>
                        <div class="col-4"><div class="assessment-mini-stat"><span>Pass Rate</span><strong><?= $stats['graded_count'] ? number_format($stats['pass_rate'],1).'%' : '—' ?></strong></div></div>
                    </div>
                </div>
            </div>

            <div class="omk-card">
                <div class="card-header"><i class="bi bi-pencil-square me-2 text-primary"></i>Grade Students</div>
                <div class="card-body p-0">
                    <?php if (!$students): ?>
                        <div class="text-center py-4 text-muted small">No students are enrolled in this section.</div>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="save_grades">
                            <input type="hidden" name="assessment_id" value="<?= (int)$selected['id'] ?>">
                            <div class="table-responsive">
                                <table class="table omk-table mb-0">
                                    <thead><tr><th>Student</th><th style="width:100px">Marks</th><th style="width:90px">%</th><th>Remarks</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($students as $student): $g=$grade_map[(int)$student['id']] ?? null; $pct=($g && $g['score'] !== null) ? ((float)$g['score']/(float)$selected['max_score']*100) : null; ?>
                                        <tr>
                                            <td><div class="fw-semibold small"><?= sanitize($student['first_name'].' '.$student['last_name']) ?></div><div class="smaller text-muted"><?= sanitize($student['user_id_number']) ?></div></td>
                                            <td><input type="number" class="form-control form-control-sm grade-input" name="score[<?= (int)$student['id'] ?>]" min="0" max="<?= htmlspecialchars($selected['max_score']) ?>" step="0.01" value="<?= $g ? htmlspecialchars($g['score']) : '' ?>" data-max="<?= htmlspecialchars($selected['max_score']) ?>"></td>
                                            <td class="fw-semibold grade-percent" data-score="<?= $g ? htmlspecialchars($g['score']) : '' ?>"><?= $pct !== null ? number_format($pct,1).'%' : '—' ?></td>
                                            <td><input type="text" class="form-control form-control-sm" name="note[<?= (int)$student['id'] ?>]" value="<?= $g ? htmlspecialchars($g['notes'] ?? '') : '' ?>" placeholder="Optional"></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-between align-items-center p-3 border-top gap-2">
                                <span class="smaller text-muted"><i class="bi bi-info-circle me-1"></i>Leave marks blank for students not yet graded.</span>
                                <button class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Grades</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>

<!-- Create Assessment Modal -->
<div class="modal fade" id="createAssessmentModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content border-0 shadow">
<form method="POST">
<input type="hidden" name="action" value="create_assessment"><input type="hidden" name="section_id" value="<?= $section_id ?>">
<div class="modal-header"><h5 class="modal-title"><i class="bi bi-plus-circle me-2 text-primary"></i>New Assessment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<div class="row g-3">
<div class="col-md-7"><label class="form-label">Assessment Name *</label><input name="title" class="form-control" placeholder="e.g. Chapter 3 Test" required></div>
<div class="col-md-5"><label class="form-label">Course *</label><select name="course_id" class="form-select" required><option value="">— Select Course —</option><?php foreach($my_courses as $c): ?><option value="<?= (int)$c['id'] ?>"><?= sanitize($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">Type *</label><select name="assessment_type" class="form-select"><option value="test">Test</option><option value="quiz">Quiz</option><option value="exam">Exam</option><option value="assignment">Assignment</option><option value="project">Project</option><option value="midterm">Midterm</option><option value="final">Final</option><option value="participation">Participation</option></select></div>
<div class="col-md-4"><label class="form-label">Date *</label><input type="date" name="assessment_date" class="form-control" required></div>
<div class="col-md-4"><label class="form-label">Time</label><input type="time" name="assessment_time" class="form-control"></div>
<div class="col-md-4"><label class="form-label">Total Marks *</label><input type="number" name="max_score" class="form-control" min="0.01" step="0.01" value="100" required></div>
<div class="col-md-8"><label class="form-label">Description</label><input type="text" name="description" class="form-control" placeholder="Optional notes about the assessment"></div>
</div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Create Assessment</button></div>
</form></div></div></div>

<?php if ($selected): ?>
<div class="modal fade" id="editAssessmentModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content border-0 shadow">
<form method="POST">
<input type="hidden" name="action" value="update_assessment"><input type="hidden" name="assessment_id" value="<?= (int)$selected['id'] ?>">
<div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Assessment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><div class="row g-3">
<div class="col-md-7"><label class="form-label">Assessment Name *</label><input name="title" class="form-control" value="<?= htmlspecialchars($selected['title']) ?>" required></div>
<div class="col-md-5"><label class="form-label">Type *</label><select name="assessment_type" class="form-select"><?php foreach(['test'=>'Test','quiz'=>'Quiz','exam'=>'Exam','assignment'=>'Assignment','project'=>'Project','midterm'=>'Midterm','final'=>'Final','participation'=>'Participation'] as $v=>$label): ?><option value="<?= $v ?>" <?= $selected['assessment_type']===$v?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">Date *</label><input type="date" name="assessment_date" class="form-control" value="<?= htmlspecialchars($selected['assessment_date']) ?>" required></div>
<div class="col-md-4"><label class="form-label">Time</label><input type="time" name="assessment_time" class="form-control" value="<?= htmlspecialchars($selected['assessment_time'] ?? '') ?>"></div>
<div class="col-md-4"><label class="form-label">Total Marks *</label><input type="number" name="max_score" class="form-control" min="0.01" step="0.01" value="<?= htmlspecialchars($selected['max_score']) ?>" required></div>
<div class="col-12"><label class="form-label">Description</label><input type="text" name="description" class="form-control" value="<?= htmlspecialchars($selected['description'] ?? '') ?>"></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Changes</button></div>
</form></div></div></div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const calEl = document.getElementById('assessmentCalendar');
    if (calEl) {
        const events = <?= json_encode($calendar_events) ?>;
        const cal = new FullCalendar.Calendar(calEl, {
            initialView: 'dayGridMonth',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listWeek' },
            events: events,
            height: 420,
            eventClick: function(info) {
                info.jsEvent.preventDefault();
                if (info.event.url) window.location.href = info.event.url;
            }
        });
        cal.render();
    }
    document.querySelectorAll('.grade-input').forEach(function(input) {
        input.addEventListener('input', function() {
            const pct = this.closest('tr').querySelector('.grade-percent');
            const value = parseFloat(this.value);
            const max = parseFloat(this.dataset.max);
            pct.textContent = (!isNaN(value) && max > 0) ? ((value / max) * 100).toFixed(1) + '%' : '—';
        });
    });
});
</script>
</body></html>
