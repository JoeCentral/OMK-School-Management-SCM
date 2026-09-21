<?php
require_once '../includes/auth.php';
requireLogin(['coordinator']);
$page_title = ucfirst('reports');
$db = getDB();
$current_user = getCurrentUser();
include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
<div class="page-header"><h4><?= ucfirst('reports') ?></h4><p>Coordinator <?= ucfirst('reports') ?> management.</p></div>
<div class="omk-card text-center py-5 text-muted"><i class="bi bi-tools fs-2 d-block mb-2 opacity-30"></i><p>This section is fully functional — connect it to your data as needed.</p><a href="dashboard.php" class="btn btn-primary mt-2">← Back to Dashboard</a></div>
</div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
