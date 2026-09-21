<?php
require_once '../includes/auth.php';
requireLogin(['teacher']);
$page_title = 'Upload Files';
$db = getDB(); $current_user = getCurrentUser();
include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
<div class="page-header"><h4><i class="bi bi-cloud-upload me-2"></i>Upload Files</h4><p>Share documents with your students via the Posts page.</p></div>
<div class="omk-card text-center py-5"><i class="bi bi-arrow-left-circle fs-2 d-block mb-3 text-primary"></i><p class="text-muted">To upload files, go to <strong>My Classes</strong> and use the "Attach File" option when posting.</p><a href="classes.php" class="btn btn-primary">Go to My Classes</a></div>
</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
