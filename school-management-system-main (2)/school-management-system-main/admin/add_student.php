<?php
require_once '../includes/auth.php';
requireLogin(['admin']);
$page_title = 'Add Student';
$db = getDB();
$current_user = getCurrentUser();
$sections_list = $db->query("SELECT s.id, CONCAT(cl.name,' — Section ',s.name) as label FROM sections s JOIN classes cl ON cl.id=s.class_id ORDER BY cl.grade_level, s.name")->fetchAll();
include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">
<div class="page-header">
    <div class="d-flex align-items-center gap-3">
        <a href="users.php" class="btn btn-sm btn-light btn-icon-sm"><i class="bi bi-arrow-left"></i></a>
        <div><h4><i class="bi bi-person-plus-fill me-2"></i>Add New Student</h4><p>Fill in the details — credentials are generated automatically.</p></div>
    </div>
</div>

<div id="toastWrap" class="position-fixed top-0 end-0 p-3" style="z-index:9999">
    <div id="liveToast" class="toast align-items-center border-0" role="alert"><div class="d-flex"><div class="toast-body fw-semibold" id="toastMsg"></div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div></div>
</div>

<div class="row justify-content-center"><div class="col-xl-9">

<!-- ID Badge -->
<div class="omk-card mb-4">
    <div class="card-body p-4">
        <div class="d-flex align-items-center gap-4 flex-wrap">
            <div class="d-flex align-items-center gap-3">
                <div style="width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,#10b981,#059669);display:flex;align-items:center;justify-content:center">
                    <i class="bi bi-person-badge-fill text-white fs-4"></i>
                </div>
                <div>
                    <div class="text-muted small fw-600 text-uppercase" style="letter-spacing:1px">Auto-Generated Student ID</div>
                    <div class="fw-800 fs-3 font-monospace" id="displayId" style="color:#1e3a5f;letter-spacing:3px">
                        <span class="placeholder col-5 rounded"></span>
                    </div>
                </div>
            </div>
            <div class="ms-auto text-end d-none d-md-block">
                <span class="badge bg-success-subtle text-success px-3 py-2"><i class="bi bi-shield-check me-1"></i>Unique & Auto-assigned</span>
            </div>
        </div>
    </div>
</div>

<div class="omk-card">
<div class="card-header d-flex align-items-center gap-2">
    <div style="width:8px;height:8px;border-radius:50%;background:#10b981"></div>
    <span class="fw-semibold">Student Information</span>
</div>
<div class="card-body p-4">
<form id="userForm">
    <input type="hidden" name="role" value="student">

    <p class="text-muted small mb-3"><i class="bi bi-person me-1"></i><strong>Personal Details</strong></p>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">First Name <span class="text-danger">*</span></label>
            <input type="text" name="first_name" id="fn" class="form-control" placeholder="First name" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Last Name <span class="text-danger">*</span></label>
            <input type="text" name="last_name" id="ln" class="form-control" placeholder="Last name" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Gender</label>
            <select name="gender" class="form-select"><option value="male">Male</option><option value="female">Female</option></select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="date_of_birth" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Phone</label>
            <input type="tel" name="phone" class="form-control" placeholder="Phone number">
        </div>
    </div>

    <hr class="my-4">
    <p class="text-muted small mb-3"><i class="bi bi-shield-lock me-1"></i><strong>Login Credentials</strong> <span class="badge bg-info-subtle text-info ms-1">Auto-generated</span></p>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Email Address <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="email" name="email" id="ef" class="form-control" placeholder="email@omk.edu" required>
                <button type="button" class="btn btn-outline-secondary" onclick="resetEmail()" title="Re-generate"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Password <span class="text-danger">*</span></label>
            <div class="input-group">
                <input type="text" name="password" id="pf" class="form-control" placeholder="Password" required>
                <button type="button" class="btn btn-outline-secondary" onclick="resetPass()" title="Re-generate"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
        </div>
    </div>

    <hr class="my-4">
    <p class="text-muted small mb-3"><i class="bi bi-diagram-2 me-1"></i><strong>Enrollment</strong> <span class="text-muted">(Optional)</span></p>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Assign to Section</label>
            <select name="section_id" class="form-select">
                <option value="">— Assign later —</option>
                <?php foreach ($sections_list as $s): ?><option value="<?= $s['id'] ?>"><?= sanitize($s['label']) ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="d-flex gap-2 mt-4 flex-wrap">
        <button type="submit" class="btn btn-success px-4" id="submitBtn"><i class="bi bi-person-check me-2"></i>Add Student</button>
        <button type="button" class="btn btn-outline-secondary" onclick="resetForm()"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
        <a href="users.php" class="btn btn-link text-muted ms-auto">Cancel</a>
    </div>
</form>

<div id="successPanel" class="d-none mt-4 p-4 rounded-3" style="background:#f0fdf4;border:1px solid #86efac">
    <div class="d-flex align-items-start gap-3">
        <i class="bi bi-check-circle-fill text-success fs-3 mt-1"></i>
        <div class="flex-grow-1">
            <div class="fw-700 text-success mb-2">Student Added Successfully!</div>
            <div class="row g-2 mb-3">
                <div class="col-sm-4"><div class="text-muted smaller">Name</div><div class="fw-semibold small" id="sucName"></div></div>
                <div class="col-sm-4"><div class="text-muted smaller">System ID</div><div class="fw-700 font-monospace text-primary" id="sucId"></div></div>
                <div class="col-sm-4"><div class="text-muted smaller">Email</div><div class="fw-semibold small" id="sucEmail"></div></div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success btn-sm" onclick="addAnother()"><i class="bi bi-plus me-1"></i>Add Another</button>
                <a href="users.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-people me-1"></i>View All Users</a>
            </div>
        </div>
    </div>
</div>
</div></div>

</div></div>
</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
<script>
const BASE='<?= BASE_URL ?>';
const fn=document.getElementById('fn'),ln=document.getElementById('ln'),ef=document.getElementById('ef'),pf=document.getElementById('pf');
let sysId='';

fetch(`${BASE}/api/admin_ajax.php?action=get_next_id&role=student`).then(r=>r.json()).then(d=>{sysId=d.id;document.getElementById('displayId').textContent=d.id;});

function autoGen(){
    const f=fn.value.trim().toLowerCase(),l=ln.value.trim().toLowerCase();
    if(!ef.dataset.manual&&f&&l){ef.value=f.charAt(0)+l.charAt(0)+sysId.replace(/\D/g,'')+'@omk.edu';}
    if(!pf.dataset.manual&&f&&l){pf.value=f+l+'123';}
}
[fn,ln].forEach(e=>e.addEventListener('input',autoGen));
ef.addEventListener('input',()=>{ef.dataset.manual=ef.value?'1':'';});
pf.addEventListener('input',()=>{pf.dataset.manual=pf.value?'1':'';});
function resetEmail(){ef.dataset.manual='';autoGen();}
function resetPass(){pf.dataset.manual='';autoGen();}

document.getElementById('userForm').addEventListener('submit',async function(e){
    e.preventDefault();
    const btn=document.getElementById('submitBtn');
    btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>Adding...';btn.disabled=true;
    const fd=new FormData(this);fd.append('action','add_user');
    try{
        const r=await fetch(`${BASE}/api/admin_ajax.php`,{method:'POST',body:fd});
        const d=await r.json();
        if(d.success){
            document.getElementById('sucName').textContent=d.name;
            document.getElementById('sucId').textContent=d.system_id;
            document.getElementById('sucEmail').textContent=d.email;
            document.getElementById('successPanel').classList.remove('d-none');
            document.getElementById('userForm').classList.add('d-none');
            toast('Student added!','success');
            fetch(`${BASE}/api/admin_ajax.php?action=get_next_id&role=student`).then(r=>r.json()).then(d=>{sysId=d.id;document.getElementById('displayId').textContent=d.id;});
        }else{toast(d.error,'danger');}
    }catch(err){toast('Server error.','danger');}
    btn.innerHTML='<i class="bi bi-person-check me-2"></i>Add Student';btn.disabled=false;
});

function addAnother(){
    document.getElementById('successPanel').classList.add('d-none');
    document.getElementById('userForm').classList.remove('d-none');
    document.getElementById('userForm').reset();ef.dataset.manual='';pf.dataset.manual='';
}
function resetForm(){document.getElementById('userForm').reset();ef.dataset.manual='';pf.dataset.manual='';}
function toast(msg,type){
    const t=document.getElementById('liveToast'),m=document.getElementById('toastMsg');
    t.className=`toast align-items-center text-white bg-${type} border-0`;m.textContent=msg;
    new bootstrap.Toast(t,{delay:4000}).show();
}
</script>
</body></html>
