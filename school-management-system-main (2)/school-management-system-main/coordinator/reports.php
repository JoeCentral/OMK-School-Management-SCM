<?php
require_once '../includes/auth.php';
requireLogin(['coordinator']);

$page_title = 'Reports';
$db = getDB();
$current_user = getCurrentUser();

$report_type = $_GET['report_type'] ?? 'test';
$section_id  = (int)($_GET['section'] ?? 0);
$course_id   = (int)($_GET['course'] ?? 0);
$date_from   = $_GET['date_from'] ?? date('Y-m-01');
$date_to     = $_GET['date_to'] ?? date('Y-m-d');

if (!in_array($report_type, ['test', 'attendance'], true)) {
    $report_type = 'test';
}

$my_sections_stmt = $db->prepare("
    SELECT s.*, cl.name AS class_name, cl.grade_level
    FROM sections s
    JOIN classes cl ON cl.id = s.class_id
    WHERE s.coordinator_id = ?
    ORDER BY cl.grade_level, s.name
");
$my_sections_stmt->execute([$current_user['id']]);
$my_sections = $my_sections_stmt->fetchAll();

if (!$section_id && !empty($my_sections)) {
    $section_id = (int)$my_sections[0]['id'];
}

$cur_section = null;
foreach ($my_sections as $section) {
    if ((int)$section['id'] === $section_id) {
        $cur_section = $section;
        break;
    }
}

if (!$cur_section) {
    $section_id = 0;
}

$courses = [];
$cur_course = null;

if ($section_id) {
    $courses_stmt = $db->prepare("
        SELECT DISTINCT c.id, c.name, c.color
        FROM teacher_courses tc
        JOIN courses c ON c.id = tc.course_id
        WHERE tc.section_id = ?
        ORDER BY c.name
    ");
    $courses_stmt->execute([$section_id]);
    $courses = $courses_stmt->fetchAll();

    if ($report_type === 'test') {
        if (!$course_id && !empty($courses)) {
            $course_id = (int)$courses[0]['id'];
        }
    } else {
        if ($course_id) {
            $course_exists = false;
            foreach ($courses as $course) {
                if ((int)$course['id'] === $course_id) {
                    $course_exists = true;
                    break;
                }
            }
            if (!$course_exists) {
                $course_id = 0;
            }
        }
    }

    foreach ($courses as $course) {
        if ((int)$course['id'] === $course_id) {
            $cur_course = $course;
            break;
        }
    }
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $date_from = date('Y-m-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_to = date('Y-m-d');
}

if ($date_from > $date_to) {
    [$date_from, $date_to] = [$date_to, $date_from];
}

$report_rows = [];
$summary = [
    'students' => 0,
    'records'  => 0,
    'average'  => 0
];

if ($report_type === 'test' && $section_id && $course_id) {
    $stmt = $db->prepare("
        SELECT
            g.id,
            g.assessment_type,
            g.assessment_name,
            g.score,
            g.max_score,
            g.grade_date,
            g.notes,
            u.id AS student_id,
            u.first_name,
            u.last_name,
            u.user_id_number
        FROM grades g
        JOIN users u ON u.id = g.student_id
        JOIN student_sections ss
          ON ss.student_id = g.student_id
         AND ss.section_id = g.section_id
        WHERE g.section_id = ?
          AND g.course_id = ?
          AND g.grade_date BETWEEN ? AND ?
        ORDER BY
            g.grade_date ASC,
            u.last_name ASC,
            u.first_name ASC,
            g.assessment_name ASC
    ");
    $stmt->execute([$section_id, $course_id, $date_from, $date_to]);
    $report_rows = $stmt->fetchAll();

    $student_ids = [];
    $percentage_total = 0;

    foreach ($report_rows as $row) {
        $student_ids[$row['student_id']] = true;

        $score = (float)$row['score'];
        $max_score = (float)$row['max_score'];
        $percentage_total += $max_score > 0 ? ($score / $max_score) * 100 : 0;
    }

    $summary['students'] = count($student_ids);
    $summary['records'] = count($report_rows);
    $summary['average'] = count($report_rows)
        ? round($percentage_total / count($report_rows), 1)
        : 0;
}

if ($report_type === 'attendance' && $section_id) {
    $course_condition = '';
    $params = [$date_from, $date_to];

    if ($course_id) {
        $course_condition = ' AND a.course_id = ?';
        $params[] = $course_id;
    }

    $params[] = $section_id;

    $stmt = $db->prepare("
        SELECT
            u.id AS student_id,
            u.first_name,
            u.last_name,
            u.user_id_number,
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
            SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) AS excused_count,
            COUNT(a.id) AS total_records
        FROM student_sections ss
        JOIN users u ON u.id = ss.student_id
        LEFT JOIN attendance a
          ON a.student_id = u.id
         AND a.section_id = ss.section_id
         AND a.date BETWEEN ? AND ?
         $course_condition
        WHERE ss.section_id = ?
          AND u.is_active = 1
        GROUP BY u.id, u.first_name, u.last_name, u.user_id_number
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute($params);
    $report_rows = $stmt->fetchAll();

    $attendance_total = 0;

    foreach ($report_rows as &$row) {
        $total = (int)$row['total_records'];
        $present = (int)$row['present_count'];

        $row['attendance_percentage'] = $total > 0
            ? round(($present / $total) * 100, 1)
            : 0;

        $attendance_total += $row['attendance_percentage'];
    }
    unset($row);

    $summary['students'] = count($report_rows);
    $summary['records'] = array_sum(array_map(
        fn($row) => (int)$row['total_records'],
        $report_rows
    ));
    $summary['average'] = count($report_rows)
        ? round($attendance_total / count($report_rows), 1)
        : 0;
}

function reportGrade(float $percentage): string {
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 85) return 'A';
    if ($percentage >= 80) return 'A-';
    if ($percentage >= 75) return 'B+';
    if ($percentage >= 70) return 'B';
    if ($percentage >= 65) return 'C+';
    if ($percentage >= 60) return 'C';
    if ($percentage >= 50) return 'D';
    return 'F';
}

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header no-print">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4><i class="bi bi-file-earmark-bar-graph-fill me-2"></i>Reports</h4>
            <p>Generate test and attendance reports for your sections.</p>
        </div>
        <div class="text-white-50 small"><?= date('l, F j, Y') ?></div>
    </div>
</div>

<!-- Filters -->
<div class="omk-card mb-4 no-print">
    <div class="card-header">
        <i class="bi bi-funnel me-2 text-primary"></i>Report Filters
    </div>
    <div class="card-body p-4">
        <form method="GET">
            <div class="row g-3 align-items-end">

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Report Type</label>
                    <select name="report_type" class="form-select">
                        <option value="test" <?= $report_type === 'test' ? 'selected' : '' ?>>Test Report</option>
                        <option value="attendance" <?= $report_type === 'attendance' ? 'selected' : '' ?>>Attendance Report</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Section</label>
                    <select name="section" class="form-select" onchange="this.form.submit()">
                        <?php if (empty($my_sections)): ?>
                            <option value="0">No sections assigned</option>
                        <?php else: ?>
                            <?php foreach ($my_sections as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $s['id'] == $section_id ? 'selected' : '' ?>>
                                    <?= sanitize($s['class_name'] . ' — Section ' . $s['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Course</label>
                    <select name="course" class="form-select">
                        <?php if ($report_type === 'attendance'): ?>
                            <option value="0" <?= $course_id === 0 ? 'selected' : '' ?>>All Courses</option>
                        <?php endif; ?>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= $course['id'] ?>" <?= $course['id'] == $course_id ? 'selected' : '' ?>>
                                <?= sanitize($course['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>" required>
                </div>

                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-file-earmark-bar-graph me-1"></i>Generate Report
                    </button>
                </div>

                <div class="col-md-3">
                    <button type="button" onclick="window.print()" class="btn btn-outline-dark w-100">
                        <i class="bi bi-printer me-1"></i>Print / Save PDF
                    </button>
                </div>

                <div class="col-md-3">
                    <a href="reports.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                    </a>
                </div>

            </div>
        </form>
    </div>
</div>

<!-- Printable Report -->
<div id="printReport">
    <div class="omk-card mb-4">
        <div class="card-body p-4">

            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                <div>
                    <div class="text-muted small text-uppercase fw-bold" style="letter-spacing:.6px">
                        OMK School Management System
                    </div>
                    <h4 class="fw-bold mb-1 mt-1">
                        <?= $report_type === 'test' ? 'Test Report' : 'Attendance Report' ?>
                    </h4>

                    <?php if ($cur_section): ?>
                        <div class="text-muted">
                            <?= sanitize($cur_section['class_name']) ?>
                            — Section <?= sanitize($cur_section['name']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($cur_course): ?>
                        <div class="text-muted small">
                            Course: <?= sanitize($cur_course['name']) ?>
                        </div>
                    <?php elseif ($report_type === 'attendance'): ?>
                        <div class="text-muted small">Course: All Courses</div>
                    <?php endif; ?>
                </div>

                <div class="text-end">
                    <div class="small text-muted">Report Period</div>
                    <div class="fw-semibold">
                        <?= date('d M Y', strtotime($date_from)) ?>
                        —
                        <?= date('d M Y', strtotime($date_to)) ?>
                    </div>
                </div>
            </div>

            <!-- Summary -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="p-3 rounded-3 border bg-light">
                        <div class="small text-muted">
                            <?= $report_type === 'test' ? 'Students Assessed' : 'Students' ?>
                        </div>
                        <div class="fs-4 fw-bold"><?= $summary['students'] ?></div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="p-3 rounded-3 border bg-light">
                        <div class="small text-muted">
                            <?= $report_type === 'test' ? 'Assessment Records' : 'Attendance Records' ?>
                        </div>
                        <div class="fs-4 fw-bold"><?= $summary['records'] ?></div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="p-3 rounded-3 border bg-light">
                        <div class="small text-muted">
                            <?= $report_type === 'test' ? 'Average Score' : 'Average Attendance' ?>
                        </div>
                        <div class="fs-4 fw-bold"><?= $summary['average'] ?>%</div>
                    </div>
                </div>
            </div>

            <?php if ($report_type === 'test'): ?>

                <?php if (empty($report_rows)): ?>
                    <div class="text-center py-5 text-muted border rounded-3">
                        <i class="bi bi-file-earmark-x fs-2 d-block mb-2 opacity-50"></i>
                        No test or assessment records found for the selected filters.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table omk-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student</th>
                                    <th>Student ID</th>
                                    <th>Assessment</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th class="text-center">Score</th>
                                    <th class="text-center">Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($report_rows as $i => $row):
                                $score = (float)$row['score'];
                                $max_score = (float)$row['max_score'];
                                $pct = $max_score > 0 ? round(($score / $max_score) * 100, 1) : 0;
                                $grade = reportGrade($pct);
                            ?>
                                <tr>
                                    <td class="text-muted small"><?= $i + 1 ?></td>
                                    <td class="fw-semibold">
                                        <?= sanitize($row['first_name'] . ' ' . $row['last_name']) ?>
                                    </td>
                                    <td><?= sanitize($row['user_id_number']) ?></td>
                                    <td><?= sanitize($row['assessment_name'] ?? 'Assessment') ?></td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary">
                                            <?= sanitize(ucfirst($row['assessment_type'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $row['grade_date'] ? date('d M Y', strtotime($row['grade_date'])) : '—' ?>
                                    </td>
                                    <td class="text-center fw-semibold">
                                        <?= htmlspecialchars($row['score']) ?>/<?= htmlspecialchars($row['max_score']) ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $pct >= 50 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                            <?= $pct ?>% · <?= $grade ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php else: ?>

                <?php if (empty($report_rows)): ?>
                    <div class="text-center py-5 text-muted border rounded-3">
                        <i class="bi bi-calendar-x fs-2 d-block mb-2 opacity-50"></i>
                        No students found for this section.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table omk-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student</th>
                                    <th>Student ID</th>
                                    <th class="text-center">Present</th>
                                    <th class="text-center">Late</th>
                                    <th class="text-center">Absent</th>
                                    <th class="text-center">Excused</th>
                                    <th class="text-center">Attendance %</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($report_rows as $i => $row):
                                $ap = (float)$row['attendance_percentage'];
                                $att_class = $ap >= 85 ? 'success' : ($ap >= 75 ? 'warning' : 'danger');
                            ?>
                                <tr>
                                    <td class="text-muted small"><?= $i + 1 ?></td>
                                    <td class="fw-semibold">
                                        <?= sanitize($row['first_name'] . ' ' . $row['last_name']) ?>
                                    </td>
                                    <td><?= sanitize($row['user_id_number']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-success-subtle text-success">
                                            <?= (int)$row['present_count'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-warning-subtle text-warning">
                                            <?= (int)$row['late_count'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger-subtle text-danger">
                                            <?= (int)$row['absent_count'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary-subtle text-primary">
                                            <?= (int)$row['excused_count'] ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $att_class ?>-subtle text-<?= $att_class ?>">
                                            <?= $ap ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

            <div class="d-none d-print-block mt-4 pt-3 border-top">
                <div class="row">
                    <div class="col-6 small">
                        <strong>Generated by:</strong><br>
                        <?= sanitize($current_user['first_name'] . ' ' . $current_user['last_name']) ?><br>
                        Coordinator
                    </div>
                    <div class="col-6 text-end small">
                        <strong>Generated on:</strong><br>
                        <?= date('d M Y, h:i A') ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

</div>
</div>

<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>

<style>
@media print {
    @page {
        size: A4 landscape;
        margin: 12mm;
    }

    body {
        background: #fff !important;
    }

    .no-print,
    .sidebar,
    #scrollTop,
    header,
    nav {
        display: none !important;
    }

    .omk-wrapper {
        display: block !important;
    }

    .main-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }

    .omk-card {
        border: 0 !important;
        box-shadow: none !important;
        margin: 0 !important;
    }

    .card-body {
        padding: 0 !important;
    }

    #printReport {
        display: block !important;
        width: 100%;
    }

    table {
        width: 100% !important;
        font-size: 11px !important;
    }

    th,
    td {
        padding: 6px !important;
    }

    .d-print-block {
        display: block !important;
    }
}
</style>

<script>
const dateFrom = document.querySelector('input[name="date_from"]');
const dateTo = document.querySelector('input[name="date_to"]');

function validateReportDates() {
    if (dateFrom && dateTo && dateFrom.value && dateTo.value && dateFrom.value > dateTo.value) {
        alert('Date From cannot be later than Date To.');
        dateTo.value = dateFrom.value;
    }
}

if (dateFrom) dateFrom.addEventListener('change', validateReportDates);
if (dateTo) dateTo.addEventListener('change', validateReportDates);
</script>

</body>
</html>
