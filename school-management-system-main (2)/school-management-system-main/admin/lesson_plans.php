<?php
require_once '../includes/auth.php';
requireLogin(['admin']);
$page_title = 'Lesson Plans';
$db = getDB();
$current_user = getCurrentUser();
$msg = '';

// Actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
if ($action === 'approve') {
    $db->prepare("UPDATE lesson_preparations SET status='signed', reviewed_by=?, reviewed_at=NOW() WHERE id=?")
       ->execute([$current_user['id'], $id]);

    // Notify teacher — fetch separately
    $planStmt = $db->prepare("SELECT teacher_id, title FROM lesson_preparations WHERE id=?");
    $planStmt->execute([$id]);
    $plan = $planStmt->fetch();
    if ($plan && $plan['teacher_id']) {
        addNotification($plan['teacher_id'], 'Lesson Plan Approved!', "Your lesson plan '{$plan['title']}' has been approved and signed.", 'success');
    }
    $msg = "Lesson plan approved and signed.";
    } elseif ($action === 'reject') {
        $db->prepare("UPDATE lesson_preparations SET status='rejected' WHERE id=?")->execute([$id]);
        $msg = "Lesson plan rejected.";
    }
}

$status_filter = $_GET['status'] ?? 'pending';
$plans = $db->prepare("
    SELECT lp.*, u.first_name, u.last_name, c.name as course_name,
           s.name as sec_name, cl.name as class_name,
           au.first_name as approver_fn, au.last_name as approver_ln
    FROM lesson_preparations lp
    JOIN users u ON u.id = lp.teacher_id
    JOIN courses c ON c.id = lp.course_id
    JOIN sections s ON s.id = lp.section_id
    JOIN classes cl ON cl.id = s.class_id
    LEFT JOIN users au ON au.id = lp.reviewed_by
    WHERE lp.status = ?
    ORDER BY lp.created_at DESC
");
$plans->execute([$status_filter]);
$plans = $plans->fetchAll();

$counts = $db->query("SELECT status,COUNT(*) as cnt FROM lesson_preparations GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <h4><i class="bi bi-file-earmark-check me-2"></i>Lesson Plan Approvals</h4>
    <p>Review and approve teacher lesson preparations</p>
</div>

<?php if ($msg): ?><div class="alert alert-success omk-alert alert-auto-dismiss"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<!-- Status tabs -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <?php foreach ([['pending','warning','Pending'],['approved','primary','Approved'],['signed','success','Signed'],['rejected','danger','Rejected']] as [$s,$c,$l]): ?>
    <a href="?status=<?= $s ?>" class="btn btn-<?= $status_filter===$s ? $c : 'outline-'.$c ?> btn-sm">
        <?= $l ?> <span class="badge bg-<?= $c ?> ms-1"><?= $counts[$s] ?? 0 ?></span>
    </a>
    <?php endforeach; ?>
</div>

<div class="row g-3">
<?php if (empty($plans)): ?>
<div class="col-12"><div class="omk-card text-center py-5 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2 opacity-30"></i>No lesson plans with this status.</div></div>
<?php else: foreach ($plans as $p): ?>
<div class="col-lg-6">
<div class="omk-card">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h6 class="fw-700 mb-1"><?= sanitize($p['title']) ?></h6>
                <div class="text-muted small">
                    <i class="bi bi-person me-1"></i><?= sanitize($p['first_name'].' '.$p['last_name']) ?>
                    <span class="mx-2">·</span>
                    <i class="bi bi-book me-1"></i><?= sanitize($p['course_name']) ?>
                    <span class="mx-2">·</span>
                    <?= sanitize($p['class_name'].'-'.$p['sec_name']) ?>
                </div>
            </div>
            <span class="badge bg-<?= ['pending'=>'warning','approved'=>'primary','signed'=>'success','rejected'=>'danger'][$p['status']] ?>">
                <?= ucfirst($p['status']) ?>
            </span>
        </div>

        <?php if ($p['objectives']): ?>
        <div class="mb-2">
            <div class="fw-semibold small text-muted mb-1">OBJECTIVES</div>
            <div class="small"><?= nl2br(sanitize($p['objectives'])) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($p['content']): ?>
        <div class="mb-2">
            <div class="fw-semibold small text-muted mb-1">CONTENT</div>
            <div class="small"><?= nl2br(sanitize(substr($p['content'],0,200))) ?>...</div>
        </div>
        <?php endif; ?>

        <?php if ($p['lesson_date']): ?>
        <div class="small text-muted mb-2"><i class="bi bi-calendar me-1"></i>Lesson date: <?= date('M d, Y', strtotime($p['lesson_date'])) ?></div>
        <?php endif; ?>

        <?php if ($p['file_path']): ?>
        <a href="<?= BASE_URL ?>/uploads/documents/<?= htmlspecialchars($p['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mb-2">
            <i class="bi bi-file-earmark me-1"></i>View Attached File
        </a>
        <?php endif; ?>

        <?php if ($p['status'] === 'signed' && $p['approver_fn']): ?>
        <div class="alert alert-success omk-alert py-2 small mb-0 mt-2">
            <i class="bi bi-pen me-1"></i>Signed by <?= sanitize($p['approver_fn'].' '.$p['approver_ln']) ?> on <?= date('M d, Y', strtotime($p['reviewed_at'])) ?>
        </div>
        <?php endif; ?>

        <?php if ($p['status'] === 'pending'): ?>
        <div class="d-flex gap-2 mt-3">
            <a href="?action=approve&id=<?= $p['id'] ?>&status=<?= $status_filter ?>" class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Approve & Sign</a>
            <a href="?action=reject&id=<?= $p['id'] ?>&status=<?= $status_filter ?>" class="btn btn-danger btn-sm"><i class="bi bi-x-lg me-1"></i>Reject</a>
        </div>
        <?php endif; ?>

        <div class="text-muted smaller mt-2"><i class="bi bi-clock me-1"></i>Submitted: <?= date('M d, Y g:i A', strtotime($p['created_at'])) ?></div>
    </div>
</div>
</div>
<?php endforeach; endif; ?>
</div>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
</body></html>