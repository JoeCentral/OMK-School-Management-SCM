<?php
require_once '../includes/auth.php';
requireLogin(['teacher']);
$page_title = 'Student Grades';
$db = getDB();
$current_user = getCurrentUser();
$msg = $error = '';

const PASSING_PERCENTAGE = 50.0;

function gradeStatus(float $percentage): array {
    if ($percentage < PASSING_PERCENTAGE) {
        return [
            'label' => 'Needs Improvement',
            'class' => 'student-grade-danger',
            'badge' => 'bg-danger-subtle text-danger border border-danger-subtle',
            'type'  => 'warning',
        ];
    }
    return [
        'label' => 'Good',
        'class' => 'student-grade-success',
        'badge' => 'bg-success-subtle text-success border border-success-subtle',
        'type'  => 'success',
    ];
}

// Available courses/sections for this teacher. This keeps the page tied to
// the same teacher-course assignments used by Assessment & Grades.
$assignmentsStmt = $db->prepare("
    SELECT DISTINCT tc.course_id, tc.section_id,
           c.name AS course_name, c.code AS course_code, c.color AS course_color,
           s.name AS section_name, cl.name AS class_name
    FROM teacher_courses tc
    JOIN courses c ON c.id = tc.course_id
    JOIN sections s ON s.id = tc.section_id
    JOIN classes cl ON cl.id = s.class_id
    WHERE tc.teacher_id = ?
    ORDER BY cl.grade_level, s.name, c.name
");
$assignmentsStmt->execute([$current_user['id']]);
$assignments = $assignmentsStmt->fetchAll();

// Send teacher feedback and create a normal student notification.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_feedback') {
    $studentId   = (int)($_POST['student_id'] ?? 0);
    $assessmentId = (int)($_POST['assessment_id'] ?? 0);
    $courseId    = (int)($_POST['course_id'] ?? 0);
    $sectionId   = (int)($_POST['section_id'] ?? 0);
    $message     = trim(strip_tags((string)($_POST['message'] ?? '')));

    if (!$studentId || !$assessmentId || !$courseId || !$sectionId || $message === '') {
        $error = 'Please enter a feedback message before sending.';
    } elseif (mb_strlen($message) > 1000) {
        $error = 'Feedback message cannot exceed 1000 characters.';
    } elseif (!teacherOwnsCourseSection($db, (int)$current_user['id'], $courseId, $sectionId)) {
        $error = 'You can only send feedback for your assigned classes.';
    } else {
        $verify = $db->prepare("
            SELECT g.id, g.score, g.max_score, a.id AS assessment_id, a.title,
                   a.assessment_type, a.teacher_id, a.course_id, a.section_id,
                   u.first_name AS student_first_name, u.last_name AS student_last_name,
                   c.name AS course_name
            FROM grades g
            JOIN assessments a ON a.id = g.assessment_id
            JOIN users u ON u.id = g.student_id AND u.role = 'student' AND u.is_active = 1
            JOIN courses c ON c.id = a.course_id
            WHERE g.student_id = ?
              AND g.assessment_id = ?
              AND g.course_id = ?
              AND g.section_id = ?
              AND a.teacher_id = ?
              AND a.course_id = ?
              AND a.section_id = ?
            LIMIT 1
        ");
        $verify->execute([
            $studentId, $assessmentId, $courseId, $sectionId,
            $current_user['id'], $courseId, $sectionId
        ]);
        $grade = $verify->fetch();

        if (!$grade) {
            $error = 'The selected assessment result could not be verified.';
        } else {
            $percentage = (float)$grade['max_score'] > 0
                ? ((float)$grade['score'] / (float)$grade['max_score']) * 100
                : 0;
            $status = gradeStatus($percentage);

            try {
                $db->beginTransaction();

                $insert = $db->prepare("
                    INSERT INTO teacher_evaluations
                        (teacher_id, student_id, assessment_id, course_id, section_id, message)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([
                    $current_user['id'], $studentId, $assessmentId,
                    $courseId, $sectionId, $message
                ]);

                addNotification(
                    $studentId,
                    'Teacher Evaluation: ' . $grade['title'],
                    'From ' . $current_user['first_name'] . ' ' . $current_user['last_name'] . ': ' . $message,
                    $status['type'],
                    BASE_URL . '/student/grades.php#teacher-feedback'
                );

                $db->commit();
                $msg = 'Feedback sent to ' . $grade['student_first_name'] . ' ' . $grade['student_last_name'] . '.';
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                $error = 'Unable to send feedback. Please check that the feedback table has been installed.';
            }
        }
    }
}

$filterCategory = $_GET['category'] ?? 'needs';
if (!in_array($filterCategory, ['needs', 'good'], true)) $filterCategory = 'needs';
$courseFilter = (int)($_GET['course'] ?? 0);

// Pull only assessment results belonging to this teacher's assigned course/section pairs.
$results = [];
try {
    $sql = "
        SELECT g.id AS grade_id, g.student_id, g.course_id, g.section_id,
               g.score, g.max_score, g.grade_date, g.notes,
               a.id AS assessment_id, a.title AS assessment_title,
               a.assessment_type, a.assessment_date,
               u.first_name AS student_first_name, u.last_name AS student_last_name,
               u.user_id_number,
               c.name AS course_name, c.code AS course_code, c.color AS course_color,
               s.name AS section_name, cl.name AS class_name
        FROM grades g
        JOIN assessments a ON a.id = g.assessment_id
        JOIN users u ON u.id = g.student_id AND u.role = 'student' AND u.is_active = 1
        JOIN courses c ON c.id = g.course_id
        JOIN sections s ON s.id = g.section_id
        JOIN classes cl ON cl.id = s.class_id
        WHERE a.teacher_id = ?
          AND g.assessment_id IS NOT NULL
          AND g.score IS NOT NULL
          AND g.max_score > 0
          AND EXISTS (
              SELECT 1 FROM teacher_courses tc
              WHERE tc.teacher_id = a.teacher_id
                AND tc.course_id = a.course_id
                AND tc.section_id = a.section_id
          )";
    $params = [$current_user['id']];
    if ($courseFilter > 0) {
        $sql .= " AND g.course_id = ?";
        $params[] = $courseFilter;
    }
    $sql .= " ORDER BY a.assessment_date DESC, g.created_at DESC, u.first_name ASC, u.last_name ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
} catch (Throwable $e) {
    $error = 'Student Grades could not be loaded. Please make sure the Assessment & Grades database structure is available.';
}

$needs = [];
$good = [];
foreach ($results as $row) {
    $percentage = ((float)$row['score'] / (float)$row['max_score']) * 100;
    $row['percentage'] = $percentage;
    $row['status'] = gradeStatus($percentage);
    if ($percentage < PASSING_PERCENTAGE) $needs[] = $row;
    else $good[] = $row;
}

$currentRows = $filterCategory === 'needs' ? $needs : $good;

$courseChoices = [];
foreach ($assignments as $a) {
    $courseChoices[(int)$a['course_id']] = $a['course_name'];
}

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4><i class="bi bi-graph-up-arrow me-2"></i>Student Grades</h4>
            <p>Review student assessment performance and send evaluation feedback.</p>
        </div>
        <div class="text-white-50 small"><?= date('l, F j, Y') ?></div>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-success omk-alert alert-auto-dismiss"><i class="bi bi-check-circle-fill me-2"></i><?= sanitize($msg) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger omk-alert"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= sanitize($error) ?></div>
<?php endif; ?>

<div class="student-grade-toolbar omk-card mb-4">
    <div class="student-grade-toolbar-main">
        <div>
            <div class="small text-muted">Assessment performance</div>
            <div class="fw-700 text-dark">Students are grouped using a 50% performance threshold.</div>
        </div>
        <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
            <select name="course" class="form-select form-select-sm student-grade-course-filter" onchange="this.form.submit()">
                <option value="0">All Courses</option>
                <?php foreach ($courseChoices as $cid => $cname): ?>
                <option value="<?= (int)$cid ?>" <?= $courseFilter === (int)$cid ? 'selected' : '' ?>><?= sanitize($cname) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="category" value="<?= sanitize($filterCategory) ?>">
        </form>
    </div>
    <div class="student-grade-tabs">
        <a href="?category=needs<?= $courseFilter ? '&course='.(int)$courseFilter : '' ?>" class="student-grade-tab <?= $filterCategory === 'needs' ? 'active needs' : '' ?>">
            <span>Needs Improvement</span>
            <strong><?= count($needs) ?></strong>
        </a>
        <a href="?category=good<?= $courseFilter ? '&course='.(int)$courseFilter : '' ?>" class="student-grade-tab <?= $filterCategory === 'good' ? 'active good' : '' ?>">
            <span>Good Grades</span>
            <strong><?= count($good) ?></strong>
        </a>
    </div>
</div>

<div class="omk-card student-grade-table-card">
    <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <div>
            <div class="fw-700 text-dark"><?= $filterCategory === 'needs' ? 'Needs Improvement' : 'Good Grades' ?></div>
            <div class="smaller text-muted">
                <?= $filterCategory === 'needs' ? 'Students below 50% based on recorded assessment marks.' : 'Students at 50% or above based on recorded assessment marks.' ?>
            </div>
        </div>
        <span class="badge <?= $filterCategory === 'needs' ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-success-subtle text-success border border-success-subtle' ?> px-3 py-2">
            <?= count($currentRows) ?> record<?= count($currentRows) === 1 ? '' : 's' ?>
        </span>
    </div>

    <div class="table-responsive">
        <table class="table omk-table align-middle mb-0 student-grade-table">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Assessment</th>
                    <th>Score</th>
                    <th>Status</th>
                    <th>Date Recorded</th>
                    <th style="min-width:300px">Action / Send Evaluation</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($currentRows as $row): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="student-avatar-sm"><?= strtoupper(substr($row['student_first_name'],0,1).substr($row['student_last_name'],0,1)) ?></div>
                            <div>
                                <div class="fw-semibold text-dark"><?= sanitize($row['student_first_name'].' '.$row['student_last_name']) ?></div>
                                <div class="smaller text-muted"><?= sanitize($row['user_id_number']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark"><?= sanitize($row['assessment_title']) ?></div>
                        <div class="smaller text-muted"><?= sanitize(ucfirst($row['assessment_type'])) ?> · <?= sanitize($row['course_code']) ?></div>
                    </td>
                    <td>
                        <span class="student-grade-score <?= $row['status']['class'] ?>">
                            <?= rtrim(rtrim(number_format((float)$row['score'], 2, '.', ''), '0'), '.') ?> / <?= rtrim(rtrim(number_format((float)$row['max_score'], 2, '.', ''), '0'), '.') ?>
                        </span>
                        <div class="smaller mt-1 <?= $row['status']['class'] ?> fw-semibold"><?= number_format($row['percentage'], 1) ?>%</div>
                    </td>
                    <td><span class="badge <?= $row['status']['badge'] ?> student-grade-status"><?= sanitize($row['status']['label']) ?></span></td>
                    <td>
                        <div class="small fw-semibold text-dark"><?= date('M d, Y', strtotime($row['grade_date'] ?: $row['assessment_date'])) ?></div>
                        <div class="smaller text-muted"><?= date('g:i A', strtotime($row['grade_date'] ?: $row['assessment_date'])) ?></div>
                    </td>
                    <td>
                        <form method="POST" class="student-feedback-form">
                            <input type="hidden" name="action" value="send_feedback">
                            <input type="hidden" name="student_id" value="<?= (int)$row['student_id'] ?>">
                            <input type="hidden" name="assessment_id" value="<?= (int)$row['assessment_id'] ?>">
                            <input type="hidden" name="course_id" value="<?= (int)$row['course_id'] ?>">
                            <input type="hidden" name="section_id" value="<?= (int)$row['section_id'] ?>">
                            <input type="text" name="message" class="form-control form-control-sm" maxlength="1000" placeholder="Quick evaluation note..." required>
                            <button type="submit" class="btn btn-primary btn-sm flex-shrink-0">
                                <i class="bi bi-send me-1"></i>Send Feedback
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$currentRows): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="bi <?= $filterCategory === 'needs' ? 'bi-check-circle' : 'bi-bar-chart-line' ?>"></i>
                            <div class="fw-semibold">No <?= $filterCategory === 'needs' ? 'students need improvement' : 'good grade records' ?> found</div>
                            <p>Recorded assessment results will appear here automatically.</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
</body></html>
