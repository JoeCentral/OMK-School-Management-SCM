<?php
require_once '../includes/auth.php';
requireLogin(['coordinator']);
$page_title = 'Grades';
$db = getDB();
$current_user = getCurrentUser();

// My sections
$my_sections = $db->prepare("
    SELECT s.*, cl.name as class_name, cl.grade_level
    FROM sections s JOIN classes cl ON cl.id = s.class_id
    WHERE s.coordinator_id = ? ORDER BY cl.grade_level, s.name
");
$my_sections->execute([$current_user['id']]);
$my_sections = $my_sections->fetchAll();

$section_id = (int)($_GET['section'] ?? ($my_sections[0]['id'] ?? 0));
$course_id  = (int)($_GET['course']  ?? 0);

// Courses in this section
$courses = [];
if ($section_id) {
    $stmt = $db->prepare("
        SELECT DISTINCT c.id, c.name, c.color
        FROM teacher_courses tc JOIN courses c ON c.id = tc.course_id
        WHERE tc.section_id = ? ORDER BY c.name
    ");
    $stmt->execute([$section_id]);
    $courses = $stmt->fetchAll();
    if (!$course_id && !empty($courses)) $course_id = $courses[0]['id'];
}

$msg = $error = '';

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $title   = sanitize($_POST['title'] ?? '');
    $desc    = sanitize($_POST['description'] ?? '');
    $sid     = (int)$_POST['section_id'];
    $cid     = (int)$_POST['course_id'];

    if (!$title)               { $error = 'Please enter a title.'; }
    elseif (empty($_FILES['grade_file']['name'])) { $error = 'Please select a file.'; }
    elseif ($_FILES['grade_file']['error'] !== UPLOAD_ERR_OK) { $error = 'Upload error. Try again.'; }
    else {
        $allowed = ['pdf','doc','docx','xls','xlsx','csv','jpg','jpeg','png'];
        $ext     = strtolower(pathinfo($_FILES['grade_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $error = 'File type not allowed. Allowed: PDF, Word, Excel, CSV, Image.';
        } else {
            if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0777, true);
            $fname = time() . '_grades_' . preg_replace('/[^a-z0-9._-]/i', '_', $_FILES['grade_file']['name']);
            if (move_uploaded_file($_FILES['grade_file']['tmp_name'], UPLOAD_PATH . $fname)) {
                $db->prepare("INSERT INTO grade_documents (section_id, course_id, uploaded_by, title, file_path, file_name, file_size, description) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([$sid, $cid, $current_user['id'], $title, $fname, $_FILES['grade_file']['name'], $_FILES['grade_file']['size'], $desc]);
                $msg = 'Grade document uploaded successfully!';
            } else {
                $error = 'Failed to save file. Check uploads/documents folder exists.';
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $doc = $db->prepare("SELECT * FROM grade_documents WHERE id=? AND uploaded_by=?");
    $doc->execute([$did, $current_user['id']]);
    $doc = $doc->fetch();
    if ($doc) {
        @unlink(UPLOAD_PATH . $doc['file_path']);
        $db->prepare("DELETE FROM grade_documents WHERE id=?")->execute([$did]);
        header("Location: grades.php?section=$section_id&course=$course_id&deleted=1"); exit;
    }
}

// Get documents for current section + course
$documents = [];
if ($section_id && $course_id) {
    $stmt = $db->prepare("
        SELECT gd.*, u.first_name, u.last_name
        FROM grade_documents gd
        JOIN users u ON u.id = gd.uploaded_by
        WHERE gd.section_id = ? AND gd.course_id = ?
        ORDER BY gd.created_at DESC
    ");
    $stmt->execute([$section_id, $course_id]);
    $documents = $stmt->fetchAll();
}

$cur_section = null;
foreach ($my_sections as $ms) if ($ms['id'] == $section_id) { $cur_section = $ms; break; }
$cur_course = null;
foreach ($courses as $c) if ($c['id'] == $course_id) { $cur_course = $c; break; }

// File icon helper
function fileIcon($name) {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return match($ext) {
        'pdf'           => ['bi-file-earmark-pdf-fill',   'text-danger'],
        'doc','docx'    => ['bi-file-earmark-word-fill',  'text-primary'],
        'xls','xlsx'    => ['bi-file-earmark-excel-fill', 'text-success'],
        'csv'           => ['bi-file-earmark-spreadsheet','text-success'],
        'jpg','jpeg','png' => ['bi-file-earmark-image-fill','text-warning'],
        default         => ['bi-file-earmark-fill',       'text-secondary'],
    };
}

function formatSize($bytes) {
    if ($bytes >= 1048576) return round($bytes/1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes/1024, 1)    . ' KB';
    return $bytes . ' B';
}

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4><i class="bi bi-star-fill me-2"></i>Grades Documents</h4>
            <p>Upload and manage grade documents per course and section</p>
        </div>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-success omk-alert alert-auto-dismiss d-flex gap-2">
    <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger omk-alert d-flex gap-2">
    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-warning omk-alert alert-auto-dismiss d-flex gap-2">
    <i class="bi bi-trash"></i> Document deleted.
</div>
<?php endif; ?>

<!-- Filters -->
<div class="omk-card mb-4">
    <div class="card-body p-3">
        <div class="row g-3 align-items-end">
            <div class="col-sm-5">
                <label class="form-label small fw-semibold">Section</label>
                <select class="form-select" onchange="window.location='grades.php?section='+this.value">
                    <?php foreach ($my_sections as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $s['id'] == $section_id ? 'selected' : '' ?>>
                        <?= sanitize($s['class_name'] . ' — Section ' . $s['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-5">
                <label class="form-label small fw-semibold">Course</label>
                <select class="form-select" onchange="window.location='grades.php?section=<?= $section_id ?>&course='+this.value">
                    <option value="">— Select Course —</option>
                    <?php foreach ($courses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $course_id ? 'selected' : '' ?>>
                        <?= sanitize($c['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<?php if (!$section_id || !$course_id): ?>
<div class="omk-card text-center py-5 text-muted">
    <i class="bi bi-star fs-2 d-block mb-2 opacity-30"></i>
    Select a section and course to manage grade documents.
</div>
<?php else: ?>

<div class="row g-4">

    <!-- Upload form -->
    <div class="col-lg-4">
        <div class="omk-card">
            <div class="card-header">
                <i class="bi bi-cloud-upload me-2 text-primary"></i>Upload Grade Document
                <?php if ($cur_course): ?>
                <div class="mt-1">
                    <span class="badge" style="background:<?= htmlspecialchars($cur_course['color']) ?>22;color:<?= htmlspecialchars($cur_course['color']) ?>;border:1px solid <?= htmlspecialchars($cur_course['color']) ?>44">
                        <?= sanitize($cur_course['name']) ?>
                    </span>
                    <?php if ($cur_section): ?>
                    <span class="badge bg-primary-subtle text-primary ms-1"><?= sanitize($cur_section['class_name'] . ' — Sec ' . $cur_section['name']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action"     value="upload">
                    <input type="hidden" name="section_id" value="<?= $section_id ?>">
                    <input type="hidden" name="course_id"  value="<?= $course_id ?>">

                    <div class="mb-3">
                        <label class="form-label">Document Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="e.g. Midterm Grades — Chapter 1-5" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">File <span class="text-danger">*</span></label>
                        <input type="file" name="grade_file" class="form-control"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png" required>
                        <div class="form-text">Allowed: PDF, Word, Excel, CSV, Image</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Notes <span class="text-muted small">(optional)</span></label>
                        <textarea name="description" class="form-control" rows="2"
                                  placeholder="e.g. First semester final grades..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-upload me-2"></i>Upload Document
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Documents list -->
    <div class="col-lg-8">
        <div class="omk-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>
                    <i class="bi bi-folder-fill me-2 text-warning"></i>
                    Uploaded Documents
                    <span class="badge bg-secondary-subtle text-secondary ms-1"><?= count($documents) ?></span>
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($documents)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-folder-open fs-2 d-block mb-2 opacity-30"></i>
                    <p class="mb-0">No documents uploaded yet.<br>Upload a grade document using the form.</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($documents as $doc):
                        [$icon, $color] = fileIcon($doc['file_name']);
                    ?>
                    <div class="list-group-item px-4 py-3">
                        <div class="d-flex align-items-start gap-3">

                            <!-- File icon -->
                            <div class="flex-shrink-0 mt-1">
                                <i class="bi <?= $icon ?> <?= $color ?>" style="font-size:28px"></i>
                            </div>

                            <!-- Info -->
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-700"><?= sanitize($doc['title']) ?></div>
                                <?php if ($doc['description']): ?>
                                <div class="text-muted small mt-1"><?= sanitize($doc['description']) ?></div>
                                <?php endif; ?>
                                <div class="d-flex flex-wrap gap-3 mt-2" style="font-size:12px">
                                    <span class="text-muted">
                                        <i class="bi bi-file-earmark me-1"></i><?= sanitize($doc['file_name']) ?>
                                    </span>
                                    <?php if ($doc['file_size']): ?>
                                    <span class="text-muted">
                                        <i class="bi bi-hdd me-1"></i><?= formatSize($doc['file_size']) ?>
                                    </span>
                                    <?php endif; ?>
                                    <span class="text-muted">
                                        <i class="bi bi-person me-1"></i><?= sanitize($doc['first_name'] . ' ' . $doc['last_name']) ?>
                                    </span>
                                    <span class="text-muted">
                                        <i class="bi bi-clock me-1"></i><?= date('M d, Y g:i A', strtotime($doc['created_at'])) ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="d-flex gap-2 flex-shrink-0">
                                <a href="<?= BASE_URL ?>/uploads/documents/<?= htmlspecialchars($doc['file_path']) ?>"
                                   target="_blank"
                                   class="btn btn-sm btn-primary"
                                   title="View / Download">
                                    <i class="bi bi-eye me-1"></i>View
                                </a>
                                <a href="grades.php?section=<?= $section_id ?>&course=<?= $course_id ?>&delete=<?= $doc['id'] ?>"
                                   class="btn btn-sm btn-outline-danger"
                                   data-confirm="Delete this document?"
                                   title="Delete">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>
<?php endif; ?>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
</body></html>
