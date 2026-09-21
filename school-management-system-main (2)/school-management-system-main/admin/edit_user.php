<?php
require_once '../includes/auth.php';
requireLogin(['admin']);
$page_title = 'Edit User';
$db = getDB();
$current_user = getCurrentUser();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: users.php'); exit; }

$user = $db->prepare("SELECT * FROM users WHERE id=?");
$user->execute([$id]);
$user = $user->fetch();
if (!$user) { header('Location: users.php'); exit; }

$success = $error = '';

// Load sections for student enrollment
$sections_list = $db->query("
    SELECT s.id, CONCAT(cl.name,' - Section ',s.name) as label
    FROM sections s JOIN classes cl ON cl.id=s.class_id
    ORDER BY cl.grade_level, s.name
")->fetchAll();

// Current section if student
$current_section = null;
if ($user['role'] === 'student') {
    $cs = $db->prepare("SELECT section_id FROM student_sections WHERE student_id=? ORDER BY enrolled_at DESC LIMIT 1");
    $cs->execute([$id]);
    $current_section = $cs->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        $first  = sanitize($_POST['first_name'] ?? '');
        $last   = sanitize($_POST['last_name'] ?? '');
        $phone  = sanitize($_POST['phone'] ?? '');
        $gender = $_POST['gender'] ?? 'male';
        $dob    = $_POST['date_of_birth'] ?? null;
        $active = isset($_POST['is_active']) ? 1 : 0;

        $db->prepare("UPDATE users SET first_name=?,last_name=?,phone=?,gender=?,date_of_birth=?,is_active=? WHERE id=?")
           ->execute([$first,$last,$phone,$gender,$dob?:null,$active,$id]);

        // Update section if student
        if ($user['role'] === 'student' && isset($_POST['section_id'])) {
            $new_sec = (int)$_POST['section_id'];
            $yr = $db->query("SELECT id FROM academic_years WHERE is_current=1")->fetchColumn();
            // Remove old enrollment and add new
            $db->prepare("DELETE FROM student_sections WHERE student_id=?")->execute([$id]);
            if ($new_sec) {
                $db->prepare("INSERT INTO student_sections (student_id,section_id,academic_year_id) VALUES (?,?,?)")
                   ->execute([$id,$new_sec,$yr]);
            }
        }

        $success = "User information updated successfully!";
        // Refresh user data
        $stmt = $db->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
    }

    if ($action === 'reset_password') {
        $new_pass = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        if (strlen($new_pass) < 6) {
            $error = "Password must be at least 6 characters.";
        } elseif ($new_pass !== $confirm) {
            $error = "Passwords do not match.";
        } else {
            $db->prepare("UPDATE users SET password=?,must_change_password=1 WHERE id=?")
               ->execute([hashPassword($new_pass),$id]);
            $success = "Password reset successfully! User will be prompted to change it on next login.";
        }
    }
}

$rc = ['admin'=>'danger','coordinator'=>'warning','teacher'=>'primary','student'=>'success'][$user['role']] ?? 'secondary';
include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <div class="d-flex align-items-center gap-3">
        <a href="users.php" class="btn btn-sm btn-light btn-icon-sm"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h4><i class="bi bi-pencil-square me-2"></i>Edit User</h4>
            <p>Editing: <strong><?= sanitize($user['first_name'].' '.$user['last_name']) ?></strong> — <?= ucfirst($user['role']) ?></p>
        </div>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success omk-alert alert-auto-dismiss">
    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger omk-alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left: User card -->
    <div class="col-lg-3">
        <div class="omk-card text-center p-4">
            <div class="user-avatar-xl bg-<?= $rc ?> mx-auto mb-3">
                <?= strtoupper(substr($user['first_name'],0,1).substr($user['last_name'],0,1)) ?>
            </div>
            <h6 class="fw-700"><?= sanitize($user['first_name'].' '.$user['last_name']) ?></h6>
            <div class="text-muted small mb-2"><?= sanitize($user['email']) ?></div>
            <span class="badge bg-<?= $rc ?>-subtle text-<?= $rc ?> px-3 py-2 mb-3"><?= ucfirst($user['role']) ?></span>
            <div class="text-start small mt-2">
                <div class="py-1 border-bottom"><span class="text-muted">ID:</span> <strong><?= sanitize($user['user_id_number']) ?></strong></div>
                <div class="py-1 border-bottom"><span class="text-muted">Gender:</span> <?= ucfirst($user['gender']) ?></div>
                <div class="py-1 border-bottom">
                    <span class="text-muted">Status:</span>
                    <?php if ($user['is_active']): ?>
                    <span class="badge bg-success-subtle text-success">Active</span>
                    <?php else: ?>
                    <span class="badge bg-danger-subtle text-danger">Inactive</span>
                    <?php endif; ?>
                </div>
                <div class="py-1"><span class="text-muted">Joined:</span> <?= date('M d, Y',strtotime($user['created_at'])) ?></div>
            </div>
        </div>
    </div>

    <!-- Right: Edit forms -->
    <div class="col-lg-9">
        <!-- Update Info -->
        <div class="omk-card mb-4">
            <div class="card-header"><i class="bi bi-person-gear me-2 text-primary"></i>Update Information</div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="action" value="update_info">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" value="<?= sanitize($user['first_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" value="<?= sanitize($user['last_name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?= sanitize($user['email']) ?>" disabled>
                            <div class="form-text">Email cannot be changed.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" value="<?= sanitize($user['phone'] ?? '') ?>" placeholder="+961 xx xxx xxx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="male"   <?= $user['gender']==='male'  ?'selected':'' ?>>Male</option>
                                <option value="female" <?= $user['gender']==='female'?'selected':'' ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" value="<?= $user['date_of_birth'] ?? '' ?>">
                        </div>

                        <?php if ($user['role'] === 'student'): ?>
                        <div class="col-md-6">
                            <label class="form-label">Enrolled Section</label>
                            <select name="section_id" class="form-select">
                                <option value="">— No Section —</option>
                                <?php foreach ($sections_list as $sec): ?>
                                <option value="<?= $sec['id'] ?>" <?= $current_section==$sec['id']?'selected':'' ?>>
                                    <?= sanitize($sec['label']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="col-md-6">
                            <label class="form-label">Account Status</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= $user['is_active']?'checked':'' ?>>
                                <label class="form-check-label" for="is_active">
                                    <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Changes</button>
                        <a href="users.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reset Password -->
        <div class="omk-card">
            <div class="card-header"><i class="bi bi-key me-2 text-warning"></i>Reset Password</div>
            <div class="card-body p-4">
                <div class="alert alert-warning omk-alert small mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    The user will be required to change their password on next login.
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">New Password</label>
                            <input type="text" name="new_password" class="form-control" placeholder="Min 6 characters" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password</label>
                            <input type="text" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                        </div>
                    </div>

                    <!-- Quick set to auto-generated -->
                    <?php if ($user['role'] === 'student'): ?>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="
                            const p='<?= strtolower($user['first_name']).strtolower($user['last_name']) ?>123';
                            document.querySelector('[name=new_password]').value=p;
                            document.querySelector('[name=confirm_password]').value=p;">
                            <i class="bi bi-magic me-1"></i>Set to default (<?= strtolower($user['first_name']).strtolower($user['last_name']) ?>123)
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="
                            const p='<?= strtolower($user['first_name']).strtolower($user['last_name']) ?>333';
                            document.querySelector('[name=new_password]').value=p;
                            document.querySelector('[name=confirm_password]').value=p;">
                            <i class="bi bi-magic me-1"></i>Set to default (<?= strtolower($user['first_name']).strtolower($user['last_name']) ?>333)
                        </button>
                    </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-warning"><i class="bi bi-key me-2"></i>Reset Password</button>
                    </div>
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