<?php
require_once '../includes/auth.php';
requireLogin();
$page_title = 'My Profile';
$db = getDB();
$current_user = getCurrentUser();
$role = $_SESSION['role'];
$msg = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $phone   = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $dob     = $_POST['date_of_birth'] ?? null;
        $db->prepare("UPDATE users SET phone=?,address=?,date_of_birth=? WHERE id=?")->execute([$phone,$address,$dob?:null,$current_user['id']]);
        $msg = "Profile updated successfully!";
        $current_user = getCurrentUser();
    }

    if ($action === 'change_password') {
        $old  = $_POST['old_password'] ?? '';
        $new  = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';

        if (!password_verify($old, $current_user['password'])) {
            $error = "Current password is incorrect.";
        } elseif (strlen($new) < 6) {
            $error = "New password must be at least 6 characters.";
        } elseif ($new !== $conf) {
            $error = "Passwords do not match.";
        } else {
            $db->prepare("UPDATE users SET password=?,must_change_password=0 WHERE id=?")->execute([hashPassword($new),$current_user['id']]);
            $msg = "Password changed successfully!";
        }
    }
}

$rc = ['admin'=>'danger','coordinator'=>'warning','teacher'=>'primary','student'=>'success'][$role] ?? 'secondary';

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <h4><i class="bi bi-person-circle me-2"></i>My Profile</h4>
    <p>Manage your personal information and security settings.</p>
</div>

<?php if ($msg): ?><div class="alert alert-success omk-alert alert-auto-dismiss"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger omk-alert"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($current_user['must_change_password']): ?>
<div class="alert alert-warning omk-alert d-flex gap-2 align-items-center mb-4">
    <i class="bi bi-key-fill fs-5"></i>
    <div><strong>Action required:</strong> Please change your password for security reasons.</div>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Profile card -->
    <div class="col-lg-4">
        <div class="omk-card text-center p-4">
            <div class="user-avatar-xl bg-<?= $rc ?> mx-auto mb-3">
                <?= strtoupper(substr($current_user['first_name'],0,1).substr($current_user['last_name'],0,1)) ?>
            </div>
            <h5 class="fw-700"><?= sanitize($current_user['first_name'].' '.$current_user['last_name']) ?></h5>
            <div class="text-muted small mb-2"><?= sanitize($current_user['email']) ?></div>
            <span class="badge bg-<?= $rc ?>-subtle text-<?= $rc ?> px-3 py-2 mb-3"><?= ucfirst($role) ?></span>
            <div class="row g-2 mt-2 text-start">
                <?php if ($current_user['phone']): ?>
                <div class="col-12 small"><i class="bi bi-telephone me-2 text-muted"></i><?= sanitize($current_user['phone']) ?></div>
                <?php endif; ?>
                <?php if ($current_user['date_of_birth']): ?>
                <div class="col-12 small"><i class="bi bi-calendar me-2 text-muted"></i><?= date('M d, Y',strtotime($current_user['date_of_birth'])) ?></div>
                <?php endif; ?>
                <div class="col-12 small"><i class="bi bi-person-badge me-2 text-muted"></i>ID: <?= sanitize($current_user['user_id_number']) ?></div>
                <div class="col-12 small"><i class="bi bi-gender-<?= $current_user['gender']==='female'?'female':'male' ?> me-2 text-muted"></i><?= ucfirst($current_user['gender']) ?></div>
                <div class="col-12 small"><i class="bi bi-clock me-2 text-muted"></i>Joined <?= date('M Y',strtotime($current_user['created_at'])) ?></div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <!-- Update profile -->
        <div class="omk-card mb-4">
            <div class="card-header"><i class="bi bi-pencil-square me-2 text-primary"></i>Update Information</div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" value="<?= sanitize($current_user['first_name']) ?>" disabled>
                            <div class="form-text">Name changes must be done by admin.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" value="<?= sanitize($current_user['last_name']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" value="<?= sanitize($current_user['phone'] ?? '') ?>" placeholder="+961 xx xxx xxx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" value="<?= $current_user['date_of_birth'] ?? '' ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Your address..."><?= sanitize($current_user['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-save me-2"></i>Save Changes</button>
                </form>
            </div>
        </div>

        <!-- Change password -->
        <div class="omk-card">
            <div class="card-header"><i class="bi bi-shield-lock me-2 text-warning"></i>Change Password</div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="old_password" class="form-control" required placeholder="Enter current password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required placeholder="Min 6 characters">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required placeholder="Repeat new password">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-warning mt-3"><i class="bi bi-key me-2"></i>Change Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
</body></html>
