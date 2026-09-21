<?php
require_once '../includes/auth.php';
requireLogin(['teacher']);
$page_title = 'Lesson Plans';
$db = getDB();
$current_user = getCurrentUser();
$msg = $error = '';

$my_sections = $db->prepare("
    SELECT DISTINCT s.id,s.name as sec_name,cl.name as class_name,cl.grade_level
    FROM teacher_courses tc JOIN sections s ON s.id=tc.section_id JOIN classes cl ON cl.id=s.class_id
    WHERE tc.teacher_id=? ORDER BY cl.grade_level,s.name
");
$my_sections->execute([$current_user['id']]);
$my_sections = $my_sections->fetchAll();

$section_id = (int)($_GET['section'] ?? ($my_sections[0]['id'] ?? 0));

$my_courses = $db->prepare("
    SELECT DISTINCT c.id,c.name,c.color FROM teacher_courses tc JOIN courses c ON c.id=tc.course_id
    WHERE tc.teacher_id=? AND tc.section_id=?
");
$my_courses->execute([$current_user['id'], $section_id]);
$my_courses = $my_courses->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'submit_plan') {
        $title      = sanitize($_POST['title'] ?? '');
        $objectives = sanitize($_POST['objectives'] ?? '');
        $content    = sanitize($_POST['content'] ?? '');
        $materials  = sanitize($_POST['materials'] ?? '');
        $week       = (int)($_POST['week_number'] ?? 0);
        $date       = $_POST['lesson_date'] ?? '';
        $cid        = (int)$_POST['course_id'];
        $sid        = (int)$_POST['section_id'];
        $file_path = ''; $file_name = '';

        if ($_FILES['file']['size'] > 0) {
            $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['pdf','doc','docx','ppt','pptx'])) {
                $fname = time().'_plan_'.preg_replace('/[^a-z0-9._-]/i','_',$_FILES['file']['name']);
                if (move_uploaded_file($_FILES['file']['tmp_name'], UPLOAD_PATH.$fname)) {
                    $file_path = $fname; $file_name = $_FILES['file']['name'];
                }
            }
        }

        if ($title && $cid && $sid) {
            $db->prepare("INSERT INTO lesson_preparations (teacher_id,section_id,course_id,title,objectives,content,materials,week_number,lesson_date,file_path,file_name,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending')")
               ->execute([$current_user['id'],$sid,$cid,$title,$objectives,$content,$materials,$week,$date?:null,$file_path,$file_name]);
            // Notify admins
            $admins = $db->query("SELECT id FROM users WHERE role='admin' AND is_active=1")->fetchAll();
            foreach ($admins as $adm) {
                addNotification($adm['id'], 'New Lesson Plan: '.$title, $current_user['first_name'].' submitted a lesson plan for review.', 'info', BASE_URL.'/admin/lesson_plans.php');
            }
            $msg = "Lesson plan submitted for admin approval!";
        } else $error = "Title, course and section are required.";
    }
    if ($action === 'delete_plan') {
        $pid = (int)$_POST['plan_id'];
        $plan = $db->prepare("SELECT * FROM lesson_preparations WHERE id=? AND teacher_id=? AND status='pending'");
        $plan->execute([$pid,$current_user['id']]);
        if ($plan->fetch()) {
            $db->prepare("DELETE FROM lesson_preparations WHERE id=?")->execute([$pid]);
            $msg = "Plan deleted.";
        } else $error = "Cannot delete approved or rejected plans.";
    }
}

$plans = $db->prepare("
    SELECT lp.*,c.name as course_name,c.color,s.name as sec_name,cl.name as class_name,
           au.first_name as approver_fn,au.last_name as approver_ln
    FROM lesson_preparations lp
    JOIN courses c ON c.id=lp.course_id
    JOIN sections s ON s.id=lp.section_id
    JOIN classes cl ON cl.id=s.class_id
    LEFT JOIN users au ON au.id=lp.approved_by
    WHERE lp.teacher_id=?
    ORDER BY lp.created_at DESC
");
$plans->execute([$current_user['id']]);
$plans = $plans->fetchAll();

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <h4><i class="bi bi-journal-text me-2"></i>Lesson Plans</h4>
    <p>Submit lesson preparations for admin review and signature.</p>
</div>

<?php if ($msg): ?><div class="alert alert-success omk-alert alert-auto-dismiss"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger omk-alert"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4">
    <!-- Submit form -->
    <div class="col-lg-5">
        <div class="omk-card">
            <div class="card-header"><i class="bi bi-file-earmark-plus me-2 text-primary"></i>New Lesson Plan</div>
            <div class="card-body p-3">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="submit_plan">
                    <div class="mb-3">
                        <label class="form-label">Section *</label>
                        <select name="section_id" class="form-select" required onchange="window.location='lesson_plans.php?section='+this.value">
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
                        <label class="form-label">Plan Title *</label>
                        <input type="text" name="title" class="form-control" placeholder="Chapter 5: Quadratic Equations" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Week Number</label>
                            <input type="number" name="week_number" class="form-control" min="1" max="40" placeholder="1">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Lesson Date</label>
                            <input type="date" name="lesson_date" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Learning Objectives</label>
                        <textarea name="objectives" class="form-control" rows="3" placeholder="Students will be able to..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Lesson Content</label>
                        <textarea name="content" class="form-control" rows="4" placeholder="Describe lesson content, activities, methodology..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Materials & Resources</label>
                        <textarea name="materials" class="form-control" rows="2" placeholder="Textbook pages, online resources..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Attach File <span class="text-muted small">(PDF, DOC, PPT)</span></label>
                        <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx">
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-2"></i>Submit for Approval</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Plans list -->
    <div class="col-lg-7">
        <div class="omk-card">
            <div class="card-header"><i class="bi bi-list-check me-2 text-primary"></i>My Submitted Plans (<?= count($plans) ?>)</div>
            <div class="card-body p-0">
                <?php if (empty($plans)): ?>
                <div class="text-center py-5 text-muted"><i class="bi bi-journal fs-2 d-block mb-2 opacity-30"></i>No plans submitted yet.</div>
                <?php else: foreach ($plans as $p):
                    $sc = ['pending'=>'warning','approved'=>'primary','signed'=>'success','rejected'=>'danger'][$p['status']]; ?>
                <div class="border-bottom px-4 py-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="fw-700 small"><?= sanitize($p['title']) ?></div>
                            <div class="text-muted smaller">
                                <span style="color:<?= htmlspecialchars($p['color']) ?>"><?= sanitize($p['course_name']) ?></span> ·
                                <?= sanitize($p['class_name'].'-'.$p['sec_name']) ?>
                                <?php if ($p['week_number']): ?> · Week <?= $p['week_number'] ?><?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-<?= $sc ?>-subtle text-<?= $sc ?>">
                                <?php if ($p['status']==='signed'): ?><i class="bi bi-pen me-1"></i><?php endif; ?>
                                <?= ucfirst($p['status']) ?>
                            </span>
                            <?php if ($p['status']==='pending'): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="delete_plan">
                                <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger btn-icon-sm" data-confirm="Delete this plan?" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($p['objectives']): ?>
                    <div class="text-muted small mb-1"><strong>Objectives:</strong> <?= sanitize(substr($p['objectives'],0,100)) ?>...</div>
                    <?php endif; ?>

                    <?php if ($p['status']==='signed'): ?>
                    <div class="alert alert-success omk-alert py-1 px-3 small mt-2 mb-0">
                        <i class="bi bi-pen me-1"></i>Signed by <?= sanitize($p['approver_fn'].' '.$p['approver_ln']) ?> · <?= date('M d, Y', strtotime($p['approved_at'])) ?>
                    </div>
                    <?php elseif ($p['status']==='rejected'): ?>
                    <div class="alert alert-danger omk-alert py-1 px-3 small mt-2 mb-0">
                        <i class="bi bi-x-circle me-1"></i>Rejected by admin
                    </div>
                    <?php endif; ?>

                    <?php if ($p['file_path']): ?>
                    <a href="<?= BASE_URL ?>/uploads/documents/<?= htmlspecialchars($p['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary mt-2">
                        <i class="bi bi-file-earmark me-1"></i><?= sanitize($p['file_name']) ?>
                    </a>
                    <?php endif; ?>

                    <div class="text-muted smaller mt-1"><i class="bi bi-clock me-1"></i><?= date('M d, Y g:i A', strtotime($p['created_at'])) ?></div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
</body></html>
