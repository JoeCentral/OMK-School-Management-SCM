<?php
require_once 'includes/auth.php';

// Already logged in → redirect
if (isLoggedIn()) {
    redirectToDashboard($_SESSION['role']);
}

$error = '';
$msg = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $user = login($email, $password);
        if ($user) {
            redirectToDashboard($user['role']);
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OMK School — Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/style.css" rel="stylesheet">
    <style>
        .floating-shapes { position:fixed;inset:0;overflow:hidden;pointer-events:none; }
        .shape { position:absolute;border-radius:50%;background:rgba(255,255,255,0.05);animation:float 6s ease-in-out infinite; }
        .shape:nth-child(1){width:300px;height:300px;top:-100px;left:-100px;animation-delay:0s;}
        .shape:nth-child(2){width:200px;height:200px;bottom:100px;right:-50px;animation-delay:2s;}
        .shape:nth-child(3){width:150px;height:150px;top:50%;left:60%;animation-delay:4s;}
        @keyframes float{0%,100%{transform:translateY(0) rotate(0deg);}50%{transform:translateY(-20px) rotate(5deg);}}
        .login-features { list-style:none;padding:0;margin:0; }
        .login-features li { display:flex;align-items:center;gap:10px;padding:8px 0;color:rgba(255,255,255,0.85);font-size:14px; }
        .login-features li i { color:#818cf8;font-size:16px; }
    </style>
</head>
<body style="margin:0;padding:0;">

<div class="login-page">
    <!-- Floating bg shapes -->
    <div class="floating-shapes">
        <div class="shape"></div><div class="shape"></div><div class="shape"></div>
    </div>

    <div class="container">
        <div class="row min-vh-100 align-items-center justify-content-center py-4">

            <!-- Left info panel (hidden on mobile) -->
            <div class="col-lg-5 d-none d-lg-block pe-5">
                <div class="text-white">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="brand-icon" style="width:52px;height:52px;font-size:24px;">
                            <i class="bi bi-mortarboard-fill"></i>
                        </div>
                        <div>
                            <div class="fw-800 fs-3 lh-1">OMK School</div>
                            <div style="opacity:.7;font-size:13px;letter-spacing:2px;text-transform:uppercase;">Management System</div>
                        </div>
                    </div>
                    <h2 class="fw-700 mb-3" style="font-size:28px;line-height:1.3;">
                        Smart School Management<br>for Modern Education
                    </h2>
                    <p style="opacity:.75;font-size:14px;line-height:1.7;margin-bottom:28px;">
                        A complete platform connecting administrators, coordinators, teachers, and students in one unified system.
                    </p>
                    <ul class="login-features">
                        <li><i class="bi bi-check-circle-fill"></i> Real-time agenda & calendar for all courses</li>
                        <li><i class="bi bi-check-circle-fill"></i> Lesson plan submission & approval workflow</li>
                        <li><i class="bi bi-check-circle-fill"></i> Attendance tracking with detailed reports</li>
                        <li><i class="bi bi-check-circle-fill"></i> Grade management across all assessments</li>
                        <li><i class="bi bi-check-circle-fill"></i> Document sharing and course materials</li>
                        <li><i class="bi bi-check-circle-fill"></i> Multi-role access with smart dashboards</li>
                    </ul>
                </div>
            </div>

            <!-- Login card -->
            <div class="col-sm-10 col-md-8 col-lg-5 col-xl-4">
                <div class="login-card">
                    <!-- Logo -->
                    <div class="text-center mb-4">
                        <div class="login-logo">
                            <i class="bi bi-mortarboard-fill"></i>
                        </div>
                        <h4 class="fw-700 mb-1" style="color:#1e3a5f;">Welcome Back</h4>
                        <p class="text-muted small">Sign in to your account</p>
                    </div>

                    <!-- Messages -->
                    <?php if ($error): ?>
                    <div class="alert alert-danger omk-alert d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($msg === 'logged_out'): ?>
                    <div class="alert alert-success omk-alert alert-auto-dismiss d-flex align-items-center gap-2 mb-3">
                        <i class="bi bi-check-circle-fill"></i> Logged out successfully.
                    </div>
                    <?php elseif ($msg === 'login_required'): ?>
                    <div class="alert alert-warning omk-alert alert-auto-dismiss mb-3">
                        <i class="bi bi-lock-fill me-2"></i>Please login to continue.
                    </div>
                    <?php endif; ?>

                    <!-- Role selector -->
                    <div class="mb-4">
                        <label class="form-label text-center d-block text-muted small mb-3">SELECT YOUR ROLE</label>
                        <div class="row g-2">
                            <?php
                            $roles = [
                                ['admin','bi-shield-fill','text-danger','Admin'],
                                ['coordinator','bi-diagram-3-fill','text-warning','Coordinator'],
                                ['teacher','bi-person-video3','text-primary','Teacher'],
                                ['student','bi-mortarboard-fill','text-success','Student'],
                            ];
                            foreach ($roles as [$r,$icon,$cls,$label]):
                            ?>
                            <div class="col-6">
                                <div class="role-btn <?= $r==='student'?'selected':'' ?>" data-role="<?= $r ?>">
                                    <i class="bi <?= $icon ?> <?= $cls ?>"></i>
                                    <span class="text-muted"><?= $label ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Login form -->
                    <form method="POST" action="">
                        <input type="hidden" name="role_hint" id="role_input" value="student">

                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope-fill text-muted"></i></span>
                                <input type="email" name="email" class="form-control border-start-0" placeholder="your@omk.edu" required autofocus style="border-radius:0 10px 10px 0;">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock-fill text-muted"></i></span>
                                <input type="password" name="password" id="passwordField" class="form-control border-start-0 border-end-0" placeholder="••••••••" required style="border-radius:0;">
                                <button type="button" class="input-group-text" onclick="togglePass()" style="border-radius:0 10px 10px 0;cursor:pointer;border:1.5px solid #e2e8f0;border-left:none;">
                                    <i class="bi bi-eye-fill text-muted" id="passToggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-3 fw-600" style="font-size:15px;">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                        </button>
                    </form>

                    <!-- Demo accounts -->
                    <div class="mt-4 p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                        <p class="text-center text-muted small mb-2 fw-600">DEMO ACCOUNTS</p>
                        <div class="row g-1 text-center" style="font-size:11px;">
                            <div class="col-6"><span class="badge bg-danger-subtle text-danger">Admin</span><br><span class="text-muted">admin@omk.edu / password</span></div>
                            <div class="col-6"><span class="badge bg-warning-subtle text-warning">Coordinator</span><br><span class="text-muted">rawan333@omk.edu</span></div>
                            <div class="col-6 mt-1"><span class="badge bg-primary-subtle text-primary">Teacher</span><br><span class="text-muted">ibrahim101@omk.edu</span></div>
                            <div class="col-6 mt-1"><span class="badge bg-success-subtle text-success">Student</span><br><span class="text-muted">ij123456@omk.edu</span></div>
                        </div>
                        <p class="text-center text-muted mt-2 mb-0" style="font-size:10px;">All demo passwords: <strong>[name][lastname]333</strong> or <strong>123</strong></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePass() {
    const f = document.getElementById('passwordField');
    const i = document.getElementById('passToggleIcon');
    if (f.type === 'password') { f.type = 'text'; i.className = 'bi bi-eye-slash-fill text-muted'; }
    else { f.type = 'password'; i.className = 'bi bi-eye-fill text-muted'; }
}
document.querySelectorAll('.role-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        document.getElementById('role_input').value = btn.dataset.role;
    });
});
setTimeout(() => document.querySelectorAll('.alert-auto-dismiss').forEach(el => { try { new bootstrap.Alert(el).close(); } catch(e){} }), 4000);
</script>
</body>
</html>
