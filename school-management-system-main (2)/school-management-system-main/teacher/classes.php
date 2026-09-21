<?php
require_once '../includes/auth.php';
requireLogin(['teacher']);
$page_title = 'Class Posts';
$db = getDB();
$current_user = getCurrentUser();
$msg = $error = '';

$section_id = (int)($_GET['section'] ?? 0);

// Get teacher's sections
$my_sections = $db->prepare("
    SELECT DISTINCT s.id, s.name as sec_name, cl.name as class_name, cl.grade_level
    FROM teacher_courses tc JOIN sections s ON s.id=tc.section_id JOIN classes cl ON cl.id=s.class_id
    WHERE tc.teacher_id=? ORDER BY cl.grade_level,s.name
");
$my_sections->execute([$current_user['id']]);
$my_sections = $my_sections->fetchAll();
if (!$section_id && !empty($my_sections)) $section_id = $my_sections[0]['id'];

// Get courses in selected section
$my_courses = $db->prepare("
    SELECT DISTINCT c.id, c.name, c.color FROM teacher_courses tc JOIN courses c ON c.id=tc.course_id
    WHERE tc.teacher_id=? AND tc.section_id=?
");
$my_courses->execute([$current_user['id'], $section_id]);
$my_courses = $my_courses->fetchAll();

// Handle post submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_post') {
        $title   = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $type    = $_POST['post_type'] ?? 'text';
        $cid     = (int)$_POST['course_id'];
        $sid     = (int)$_POST['section_id'];
        $file_path = ''; $file_name = '';

        if ($_FILES['document']['size'] > 0) {
            $allowed = ['pdf','doc','docx','ppt','pptx','xls','xlsx','zip','jpg','png'];
            $ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $fname = time().'_'.preg_replace('/[^a-z0-9._-]/i','_',$_FILES['document']['name']);
                if (move_uploaded_file($_FILES['document']['tmp_name'], UPLOAD_PATH.$fname)) {
                    $file_path = $fname; $file_name = $_FILES['document']['name']; $type = 'document';
                }
            }
        }

        if ($title && $cid && $sid) {
            $db->prepare("INSERT INTO posts (teacher_id,section_id,course_id,title,content,post_type,file_path,file_name) VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$current_user['id'],$sid,$cid,$title,$content,$type,$file_path,$file_name]);

            // Notify all students in section
            $students = $db->prepare("SELECT student_id FROM student_sections WHERE section_id=?");
            $students->execute([$sid]);
            foreach ($students->fetchAll() as $st) {
                addNotification($st['student_id'], 'New post: '.$title, $current_user['first_name'].' posted in your class.', 'info');
            }
            $msg = "Post published successfully!";
        } else $error = "Title, course and section are required.";
    }

    if ($action === 'delete_post') {
        $pid = (int)$_POST['post_id'];
        $post = $db->prepare("SELECT * FROM posts WHERE id=? AND teacher_id=?");
        $post->execute([$pid,$current_user['id']]);
        if ($p = $post->fetch()) {
            if ($p['file_path']) @unlink(UPLOAD_PATH.$p['file_path']);
            $db->prepare("DELETE FROM posts WHERE id=?")->execute([$pid]);
            $msg = "Post deleted.";
        }
    }
}

// Get posts for selected section
$posts = $db->prepare("
    SELECT p.*, c.name as course_name, c.color
    FROM posts p JOIN courses c ON c.id=p.course_id
    WHERE p.teacher_id=? AND p.section_id=?
    ORDER BY p.is_pinned DESC, p.created_at DESC
");
$posts->execute([$current_user['id'], $section_id]);
$posts = $posts->fetchAll();

// Get current section info
$cur_section = null;
foreach ($my_sections as $ms) {
    if ($ms['id'] == $section_id) { $cur_section = $ms; break; }
}

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4><i class="bi bi-megaphone-fill me-2"></i>Class Posts
            <?php if ($cur_section): ?>
            — <span style="opacity:.8"><?= sanitize($cur_section['class_name'].' — Section '.$cur_section['sec_name']) ?></span>
            <?php endif; ?>
            </h4>
            <p>Post updates, documents and announcements for your students.</p>
        </div>
    </div>
</div>

<?php if ($msg): ?><div class="alert alert-success omk-alert alert-auto-dismiss"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger omk-alert"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Section selector tabs -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <?php foreach ($my_sections as $ms): ?>
    <a href="classes.php?section=<?= $ms['id'] ?>" class="btn btn-<?= $ms['id']==$section_id ? 'primary' : 'outline-primary' ?> btn-sm">
        <?= sanitize($ms['class_name'].' - '.$ms['sec_name']) ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- New post form -->
    <div class="col-lg-4">
        <div class="omk-card">
            <div class="card-header"><i class="bi bi-pencil-square me-2 text-primary"></i>New Post</div>
            <div class="card-body p-3">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_post">
                    <input type="hidden" name="section_id" value="<?= $section_id ?>">
                    <div class="mb-3">
                        <label class="form-label">Course *</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">— Select Course —</option>
                            <?php foreach ($my_courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" placeholder="Post title..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="content" class="form-control" rows="4" placeholder="Write your message here..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Post Type</label>
                        <select name="post_type" class="form-select">
                            <option value="text">Text / Message</option>
                            <option value="announcement">Announcement</option>
                            <option value="document">Document</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Attach File <span class="text-muted small">(PDF, DOC, PPT...)</span></label>
                        <input type="file" name="document" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.jpg,.png">
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send me-2"></i>Publish Post</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Posts feed -->
    <div class="col-lg-8">
        <?php if (empty($posts)): ?>
        <div class="omk-card text-center py-5 text-muted">
            <i class="bi bi-chat-square-text fs-2 d-block mb-2 opacity-30"></i>
            No posts yet. Create your first post!
        </div>
        <?php else: foreach ($posts as $post): ?>
        <div class="post-item <?= $post['post_type'] ?> mb-3" style="border-left-color:<?= htmlspecialchars($post['color']) ?>">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <?php if ($post['is_pinned']): ?><i class="bi bi-pin-fill text-danger" title="Pinned"></i><?php endif; ?>
                        <span class="badge" style="background:<?= htmlspecialchars($post['color']) ?>22;color:<?= htmlspecialchars($post['color']) ?>;border:1px solid <?= htmlspecialchars($post['color']) ?>44">
                            <?= sanitize($post['course_name']) ?>
                        </span>
                        <span class="badge bg-<?= ['text'=>'secondary','announcement'=>'success','document'=>'warning'][$post['post_type']] ?>-subtle text-<?= ['text'=>'secondary','announcement'=>'success','document'=>'warning'][$post['post_type']] ?>">
                            <?= ucfirst($post['post_type']) ?>
                        </span>
                    </div>
                    <h6 class="fw-700 mb-1"><?= sanitize($post['title']) ?></h6>
                    <?php if ($post['content']): ?>
                    <p class="text-muted small mb-2"><?= nl2br(sanitize($post['content'])) ?></p>
                    <?php endif; ?>
                    <?php if ($post['file_path']): ?>
                    <a href="<?= BASE_URL ?>/uploads/documents/<?= htmlspecialchars($post['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-file-earmark me-1"></i><?= sanitize($post['file_name']) ?>
                    </a>
                    <?php endif; ?>
                </div>
                <form method="POST" class="d-inline ms-2">
                    <input type="hidden" name="action" value="delete_post">
                    <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger btn-icon-sm" data-confirm="Delete this post?"><i class="bi bi-trash"></i></button>
                </form>
            </div>
            <div class="text-muted smaller mt-2"><i class="bi bi-clock me-1"></i><?= date('M d, Y g:i A', strtotime($post['created_at'])) ?></div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
</body></html>
