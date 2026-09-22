<?php
require_once '../includes/auth.php';
requireLogin(['student']);

$page_title = 'My Grades';
$db = getDB();
$current_user = getCurrentUser();

// Get student's section
$my_section = $db->prepare("
    SELECT ss.section_id,
           s.name AS sec_name,
           cl.name AS class_name
    FROM student_sections ss
    JOIN sections s ON s.id = ss.section_id
    JOIN classes cl ON cl.id = s.class_id
    WHERE ss.student_id = ?
    ORDER BY ss.enrolled_at DESC
    LIMIT 1
");
$my_section->execute([$current_user['id']]);
$my_section = $my_section->fetch();

$section_id = (int)($my_section['section_id'] ?? 0);

// Get enrolled courses
$enrolled_courses = [];

if ($section_id) {
    $stmt = $db->prepare("
        SELECT DISTINCT
               c.id,
               c.name,
               c.color,
               c.code,
               u.first_name AS teacher_fn,
               u.last_name AS teacher_ln
        FROM teacher_courses tc
        JOIN courses c ON c.id = tc.course_id
        JOIN users u ON u.id = tc.teacher_id
        WHERE tc.section_id = ?
        ORDER BY c.name
    ");
    $stmt->execute([$section_id]);
    $enrolled_courses = $stmt->fetchAll();
}

// Get grade files per course for this section
$files_map = [];

if ($section_id && !empty($enrolled_courses)) {
    $course_ids = implode(',', array_map(
        'intval',
        array_column($enrolled_courses, 'id')
    ));

    $stmt = $db->prepare("
        SELECT gf.*,
               u.first_name,
               u.last_name
        FROM grade_documents gf
        JOIN users u ON u.id = gf.uploaded_by
        WHERE gf.section_id = ?
          AND gf.course_id IN ($course_ids)
        ORDER BY gf.created_at DESC
    ");
    $stmt->execute([$section_id]);

    foreach ($stmt->fetchAll() as $file) {
        $files_map[(int)$file['course_id']][] = $file;
    }
}

// Teacher-recorded assessment results.
// The newest teacher evaluation for each assessment is also loaded here so
// feedback is shown directly inside the relevant subject's grade table.
$assessment_results = [];

if ($section_id && !empty($enrolled_courses)) {
    try {
        $stmt = $db->prepare("
            SELECT
                g.id AS grade_id,
                g.course_id,
                g.score,
                g.max_score,
                g.grade_date,
                g.created_at AS grade_created_at,
                g.updated_at AS grade_updated_at,
                g.notes,
                a.id AS assessment_id,
                a.title,
                a.assessment_type,
                a.assessment_date,
                c.name AS course_name,
                c.code AS course_code,
                u.first_name AS teacher_first_name,
                u.last_name AS teacher_last_name,
                e.id AS evaluation_id,
                e.message AS evaluation_message,
                e.created_at AS evaluation_created_at,
                e.updated_at AS evaluation_updated_at,
                evaluator.first_name AS evaluator_first_name,
                evaluator.last_name AS evaluator_last_name
            FROM grades g
            JOIN assessments a ON a.id = g.assessment_id
            JOIN courses c ON c.id = g.course_id
            JOIN users u ON u.id = a.teacher_id
            LEFT JOIN teacher_evaluations e
              ON e.id = (
                  SELECT e2.id
                  FROM teacher_evaluations e2
                  WHERE e2.student_id = g.student_id
                    AND e2.assessment_id = g.assessment_id
                  ORDER BY e2.updated_at DESC, e2.id DESC
                  LIMIT 1
              )
            LEFT JOIN users evaluator ON evaluator.id = e.teacher_id
            WHERE g.student_id = ?
              AND g.section_id = ?
              AND g.assessment_id IS NOT NULL
            ORDER BY a.assessment_date DESC,
                     COALESCE(e.updated_at, g.updated_at, g.created_at) DESC
        ");

        $stmt->execute([$current_user['id'], $section_id]);

        foreach ($stmt->fetchAll() as $result) {
            $assessment_results[(int)$result['course_id']][] = $result;
        }
    } catch (Throwable $e) {
        $assessment_results = [];
    }
}

// Recent teacher evaluations for the summary card.
$teacher_evaluations = [];

try {
    $evalStmt = $db->prepare("
        SELECT
            e.id,
            e.message,
            e.created_at,
            e.updated_at,
            a.title AS assessment_title,
            a.assessment_type,
            a.assessment_date,
            c.name AS course_name,
            c.code AS course_code,
            c.color AS course_color,
            u.first_name AS teacher_first_name,
            u.last_name AS teacher_last_name
        FROM teacher_evaluations e
        JOIN assessments a ON a.id = e.assessment_id
        JOIN courses c ON c.id = e.course_id
        JOIN users u ON u.id = e.teacher_id
        WHERE e.student_id = ?
        ORDER BY e.updated_at DESC, e.id DESC
        LIMIT 5
    ");
    $evalStmt->execute([$current_user['id']]);
    $teacher_evaluations = $evalStmt->fetchAll();
} catch (Throwable $e) {
    $teacher_evaluations = [];
}

$total_files = array_sum(array_map('count', $files_map));
$courses_with = count(
    array_filter(
        $enrolled_courses,
        fn($course) => !empty($files_map[$course['id']])
    )
);

function fmtSize($bytes)
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' B';
}

function fIcon($name)
{
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    $map = [
        'pdf'  => ['bi-file-earmark-pdf-fill', 'danger'],
        'xlsx' => ['bi-file-earmark-excel-fill', 'success'],
        'xls'  => ['bi-file-earmark-excel-fill', 'success'],
        'csv'  => ['bi-file-earmark-spreadsheet', 'success'],
        'docx' => ['bi-file-earmark-word-fill', 'primary'],
        'doc'  => ['bi-file-earmark-word-fill', 'primary'],
        'png'  => ['bi-file-earmark-image-fill', 'warning'],
        'jpg'  => ['bi-file-earmark-image-fill', 'warning'],
        'jpeg' => ['bi-file-earmark-image-fill', 'warning'],
    ];

    return $map[$extension] ?? ['bi-file-earmark-fill', 'secondary'];
}

function resultStatus(float $percentage): array
{
    if ($percentage < 50) {
        return [
            'label' => 'Needs Improvement',
            'class' => 'student-grade-danger',
            'badge' => 'bg-danger-subtle text-danger border border-danger-subtle',
        ];
    }

    return [
        'label' => 'Good',
        'class' => 'student-grade-success',
        'badge' => 'bg-success-subtle text-success border border-success-subtle',
    ];
}

function formatDateTimeSafe(?string $value): string
{
    if (!$value) {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp
        ? date('M d, Y, g:i A', $timestamp)
        : '—';
}

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>

<div class="main-content">

    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h4><i class="bi bi-star-fill me-2"></i>My Grades</h4>
                <p>
                    Grade files and assessment results
                    <?= $my_section
                        ? '— ' . sanitize($my_section['class_name'] . ' — Section ' . $my_section['sec_name'])
                        : '' ?>
                </p>
            </div>

            <div class="text-white-50 small"><?= date('l, F j, Y') ?></div>
        </div>
    </div>

    <!-- Teacher evaluations / performance feedback summary -->
    <div class="omk-card teacher-feedback-card mb-4" id="teacher-feedback">
        <div class="teacher-feedback-header">
            <div>
                <div class="fw-700 text-dark">
                    <i class="bi bi-chat-square-heart me-2 text-primary"></i>
                    Teacher Evaluations &amp; Performance Feedback
                </div>
                <div class="smaller text-muted">
                    Recent feedback sent by your teachers about your assessment performance.
                </div>
            </div>

            <?php if ($teacher_evaluations): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                    <?= count($teacher_evaluations) ?> recent
                </span>
            <?php endif; ?>
        </div>

        <?php if (!$teacher_evaluations): ?>
            <div class="teacher-feedback-empty">
                <i class="bi bi-chat-left-text"></i>
                <span>No teacher evaluation has been sent yet.</span>
            </div>
        <?php else: ?>
            <div class="teacher-feedback-list">
                <?php foreach ($teacher_evaluations as $evaluation): ?>
                    <div class="teacher-feedback-item">
                        <div class="teacher-feedback-item-top">
                            <div class="fw-semibold text-dark">
                                Teacher Evaluation: <?= sanitize($evaluation['assessment_title']) ?>
                            </div>
                            <span class="smaller text-muted">
                                <?= formatDateTimeSafe($evaluation['updated_at'] ?: $evaluation['created_at']) ?>
                            </span>
                        </div>

                        <div class="teacher-feedback-meta smaller text-muted">
                            <span>
                                <i class="bi bi-person me-1"></i>
                                From:
                                <?= sanitize($evaluation['teacher_first_name'] . ' ' . $evaluation['teacher_last_name']) ?>
                            </span>

                            <span>
                                <i class="bi bi-book me-1"></i>
                                <?= sanitize($evaluation['course_name']) ?>
                                ·
                                <?= sanitize(ucfirst($evaluation['assessment_type'])) ?>
                            </span>
                        </div>

                        <div class="teacher-feedback-message">
                            <?= nl2br(sanitize($evaluation['message'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="stat-card" style="background:linear-gradient(135deg,#4f46e5,#4338ca)">
                <div class="stat-label">Grade Files</div>
                <div class="stat-num"><?= $total_files ?></div>
                <i class="bi bi-file-earmark-fill stat-icon"></i>
            </div>
        </div>

        <div class="col-6 col-md-4">
            <div class="stat-card" style="background:linear-gradient(135deg,#10b981,#059669)">
                <div class="stat-label">Courses with Grades</div>
                <div class="stat-num">
                    <?= $courses_with ?>/<?= count($enrolled_courses) ?>
                </div>
                <i class="bi bi-book-fill stat-icon"></i>
            </div>
        </div>

        <div class="col-6 col-md-4">
            <div class="stat-card" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
                <div class="stat-label">Pending</div>
                <div class="stat-num">
                    <?= max(0, count($enrolled_courses) - $courses_with) ?>
                </div>
                <i class="bi bi-hourglass-split stat-icon"></i>
            </div>
        </div>
    </div>

    <?php if (empty($enrolled_courses)): ?>

        <div class="omk-card text-center py-5 text-muted">
            <i class="bi bi-book fs-2 d-block mb-2 opacity-30"></i>
            <h6 class="fw-700">Not enrolled in any courses yet</h6>
            <p class="small mb-0">
                Contact your coordinator to get enrolled.
            </p>
        </div>

    <?php else: ?>

        <?php foreach ($enrolled_courses as $course): ?>
            <?php
                $courseId = (int)$course['id'];
                $cfiles = $files_map[$courseId] ?? [];
                $cresults = $assessment_results[$courseId] ?? [];
                $has_files = !empty($cfiles);
            ?>

            <div class="omk-card mb-4">

                <!-- Course header -->
                <div
                    class="card-header d-flex align-items-center gap-3"
                    style="background:<?= htmlspecialchars($course['color']) ?>12;border-bottom:2px solid <?= htmlspecialchars($course['color']) ?>30"
                >
                    <div
                        style="width:42px;height:42px;border-radius:12px;background:<?= htmlspecialchars($course['color']) ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0"
                    >
                        <i class="bi bi-book-fill text-white" style="font-size:18px"></i>
                    </div>

                    <div class="flex-grow-1">
                        <div
                            class="fw-700"
                            style="color:<?= htmlspecialchars($course['color']) ?>"
                        >
                            <?= sanitize($course['name']) ?>
                        </div>

                        <div class="text-muted smaller">
                            <i class="bi bi-person me-1"></i>
                            <?= sanitize($course['teacher_fn'] . ' ' . $course['teacher_ln']) ?>
                            &nbsp;·&nbsp;
                            <i class="bi bi-tag me-1"></i>
                            <?= sanitize($course['code']) ?>
                        </div>
                    </div>

                    <?php if ($has_files): ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                            <i class="bi bi-check-circle me-1"></i>
                            <?= count($cfiles) ?> file<?= count($cfiles) !== 1 ? 's' : '' ?> available
                        </span>
                    <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2">
                            <i class="bi bi-hourglass-split me-1"></i>
                            Not yet uploaded
                        </span>
                    <?php endif; ?>
                </div>

                <div class="card-body p-0">

                    <!-- Assessment results -->
                    <?php if ($cresults): ?>

                        <div class="px-4 pt-3 pb-2 border-bottom bg-light">
                            <div class="small fw-semibold text-dark">
                                <i class="bi bi-clipboard-check me-2 text-primary"></i>
                                Assessment Results
                            </div>
                        </div>

                        <div class="table-responsive student-result-table-wrap">
                            <table class="table omk-table align-middle mb-0 student-result-table">
                                <thead>
                                    <tr>
                                        <th>Assessment / Record</th>
                                        <th>Score</th>
                                        <th>Status</th>
                                        <th>Teacher Feedback</th>
                                        <th>Last Updated</th>
                                    </tr>
                                </thead>

                                <tbody>
                                <?php foreach ($cresults as $result): ?>
                                    <?php
                                        $percentage = (float)$result['max_score'] > 0
                                            ? ((float)$result['score'] / (float)$result['max_score']) * 100
                                            : 0;

                                        $status = resultStatus($percentage);

                                        $gradeUpdated = $result['grade_updated_at']
                                            ?: ($result['grade_created_at'] ?: $result['grade_date']);

                                        $feedbackUpdated = $result['evaluation_updated_at']
                                            ?: $result['evaluation_created_at'];

                                        $gradeTime = $gradeUpdated ? strtotime($gradeUpdated) : 0;
                                        $feedbackTime = $feedbackUpdated ? strtotime($feedbackUpdated) : 0;

                                        $lastUpdated = $feedbackTime > $gradeTime
                                            ? $feedbackUpdated
                                            : $gradeUpdated;
                                    ?>

                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-dark">
                                                <?= sanitize($result['title']) ?>
                                            </div>

                                            <div class="smaller text-muted">
                                                <?= sanitize(ucfirst($result['assessment_type'])) ?>
                                            </div>

                                            <?php if (!empty($result['notes'])): ?>
                                                <div class="smaller text-muted mt-1">
                                                    <i class="bi bi-chat-left-text me-1"></i>
                                                    <?= sanitize($result['notes']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="fw-bold <?= $status['class'] ?>">
                                                <?= rtrim(rtrim(number_format((float)$result['score'], 2, '.', ''), '0'), '.') ?>
                                                /
                                                <?= rtrim(rtrim(number_format((float)$result['max_score'], 2, '.', ''), '0'), '.') ?>
                                            </div>

                                            <div class="smaller <?= $status['class'] ?> fw-semibold">
                                                <?= number_format($percentage, 1) ?>%
                                            </div>
                                        </td>

                                        <td>
                                            <span class="badge <?= $status['badge'] ?> student-grade-status">
                                                <?= sanitize($status['label']) ?>
                                            </span>
                                        </td>

                                        <td class="student-result-feedback-cell">
                                            <?php if (!empty($result['evaluation_message'])): ?>
                                                <div class="student-result-feedback">
                                                    <div class="student-result-feedback-message">
                                                        <i class="bi bi-chat-left-quote me-1"></i>
                                                        <?= nl2br(sanitize($result['evaluation_message'])) ?>
                                                    </div>

                                                    <div class="smaller text-muted mt-1">
                                                        <i class="bi bi-person me-1"></i>
                                                        <?= sanitize(
                                                            ($result['evaluator_first_name'] ?: $result['teacher_first_name']) .
                                                            ' ' .
                                                            ($result['evaluator_last_name'] ?: $result['teacher_last_name'])
                                                        ) ?>
                                                    </div>

                                                    <div class="smaller text-muted">
                                                        <?= formatDateTimeSafe($feedbackUpdated) ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted smaller">
                                                    No feedback yet.
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <div class="small text-dark fw-semibold">
                                                <?= formatDateTimeSafe($lastUpdated) ?>
                                            </div>

                                            <div class="smaller text-muted">
                                                <?= $feedbackTime > $gradeTime
                                                    ? 'Feedback activity'
                                                    : 'Grade activity' ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    <?php endif; ?>

                    <!-- Grade files -->
                    <?php if (!$has_files): ?>

                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-clock fs-4 d-block mb-1 opacity-30"></i>
                            <div class="small">
                                Your coordinator hasn't uploaded grades for this course yet.
                            </div>
                        </div>

                    <?php else: ?>

                        <div class="list-group list-group-flush">
                            <?php foreach ($cfiles as $gf): ?>
                                <?php [$icon, $ic] = fIcon($gf['file_name']); ?>

                                <div class="list-group-item d-flex align-items-center gap-3 px-4 py-3">
                                    <div
                                        class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-3"
                                        style="width:52px;height:52px;background:#f8f9fa"
                                    >
                                        <i
                                            class="bi <?= $icon ?> text-<?= $ic ?>"
                                            style="font-size:28px"
                                        ></i>
                                    </div>

                                    <div class="flex-grow-1 min-w-0">
                                        <div class="fw-700">
                                            <?= sanitize($gf['file_name']) ?>
                                        </div>

                                        <?php if ($gf['description']): ?>
                                            <div class="text-muted small">
                                                <?= sanitize($gf['description']) ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="text-muted smaller mt-1 d-flex gap-3 flex-wrap">
                                            <span>
                                                <i class="bi bi-clock me-1"></i>
                                                <?= date('M d, Y \a\t g:i A', strtotime($gf['created_at'])) ?>
                                            </span>

                                            <span>
                                                <i class="bi bi-hdd me-1"></i>
                                                <?= fmtSize($gf['file_size']) ?>
                                            </span>

                                            <span>
                                                <i class="bi bi-person me-1"></i>
                                                <?= sanitize($gf['first_name'] . ' ' . $gf['last_name']) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <a
                                        href="<?= BASE_URL ?>/uploads/grades/<?= urlencode($gf['file_path']) ?>"
                                        download="<?= htmlspecialchars($gf['file_name']) ?>"
                                        class="btn btn-primary flex-shrink-0"
                                    >
                                        <i class="bi bi-download me-2"></i>
                                        Download
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php endif; ?>

                </div>
            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>
</div>

<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
</body>
</html>
