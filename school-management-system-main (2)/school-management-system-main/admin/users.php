<?php
require_once '../includes/auth.php';
requireLogin(['admin']);
$page_title = 'Users Management';
$db = getDB();
$current_user = getCurrentUser();

$role_filter = $_GET['role'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
if ($role_filter !== 'all') { $sql .= " AND role=?"; $params[] = $role_filter; }
if ($search) { $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR user_id_number LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s,$s,$s]); }
$sql .= " ORDER BY created_at DESC";
$stmt = $db->prepare($sql); $stmt->execute($params);
$users = $stmt->fetchAll();

$sections_list = $db->query("SELECT s.id, CONCAT(cl.name,' — Section ',s.name) as label FROM sections s JOIN classes cl ON cl.id=s.class_id ORDER BY cl.grade_level, s.name")->fetchAll();

$role_colors = ['admin'=>'danger','coordinator'=>'warning','teacher'=>'primary','student'=>'success'];

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div><h4><i class="bi bi-people-fill me-2"></i>Users Management</h4><p>Manage all system users</p></div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="add_student.php"     class="btn btn-success btn-sm"><i class="bi bi-person-plus me-1"></i>Add Student</a>
            <a href="add_teacher.php"     class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i>Add Teacher</a>
            <a href="add_coordinator.php" class="btn btn-warning btn-sm text-dark"><i class="bi bi-person-plus me-1"></i>Add Coordinator</a>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:9999">
    <div id="liveToast" class="toast align-items-center border-0"><div class="d-flex"><div class="toast-body fw-semibold" id="toastMsg"></div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div></div>
</div>

<div class="omk-card">
    <div class="card-header">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
            <div class="input-group" style="max-width:280px">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="q" class="form-control" placeholder="Search by name, email, ID..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <select name="role" class="form-select" style="width:auto" onchange="this.form.submit()">
                <option value="all"         <?= $role_filter==='all'?'selected':'' ?>>All Roles</option>
                <option value="admin"       <?= $role_filter==='admin'?'selected':'' ?>>Admin</option>
                <option value="coordinator" <?= $role_filter==='coordinator'?'selected':'' ?>>Coordinator</option>
                <option value="teacher"     <?= $role_filter==='teacher'?'selected':'' ?>>Teacher</option>
                <option value="student"     <?= $role_filter==='student'?'selected':'' ?>>Student</option>
            </select>
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
            <a href="users.php" class="btn btn-outline-secondary btn-sm">Clear</a>
            <span class="ms-auto text-muted small"><?= count($users) ?> users found</span>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table omk-table mb-0">
                <thead><tr><th>System ID</th><th>User</th><th>Role</th><th>Email</th><th>Status</th><th>Joined</th><th class="text-center">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u):
                    $rc = $role_colors[$u['role']] ?? 'secondary'; ?>
                <tr id="user-row-<?= $u['id'] ?>">
                    <td>
                        <span class="font-monospace small fw-700 text-primary bg-primary-subtle px-2 py-1 rounded"><?= sanitize($u['user_id_number']) ?></span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="user-avatar-sm bg-<?= $rc ?>"><?= strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) ?></div>
                            <div>
                                <div class="fw-semibold small"><?= sanitize($u['first_name'].' '.$u['last_name']) ?></div>
                                <div class="text-muted" style="font-size:11px"><?= $u['gender'] === 'female' ? '♀ Female' : '♂ Male' ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge bg-<?= $rc ?>-subtle text-<?= $rc ?>"><?= ucfirst($u['role'] ?? 'unknown') ?></span></td>
                    <td class="small"><?= sanitize($u['email']) ?></td>
                    <td>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" <?= $u['is_active']?'checked':'' ?> <?= $u['id']==$current_user['id']?'disabled':'' ?>
                                onchange="toggleUser(<?= $u['id'] ?>, this.checked)" style="cursor:pointer">
                        </div>
                    </td>
                    <td class="text-muted smaller"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                    <td class="text-center">
                        <div class="d-flex gap-1 justify-content-center">
                            <button class="btn btn-sm btn-outline-primary btn-icon-sm" onclick="openEditModal(<?= $u['id'] ?>)" title="Edit" data-bs-toggle="tooltip"><i class="bi bi-pencil"></i></button>
                            <button class="btn btn-sm btn-outline-warning btn-icon-sm" onclick="openPassModal(<?= $u['id'] ?>, '<?= sanitize($u['first_name']) ?>', '<?= sanitize($u['last_name']) ?>', '<?= $u['role'] ?>')" title="Reset Password" data-bs-toggle="tooltip"><i class="bi bi-key"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-people fs-2 d-block mb-2 opacity-30"></i>No users found</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── EDIT USER MODAL ── -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content" style="border-radius:16px">
        <div class="modal-header border-0">
            <h5 class="modal-title fw-700" id="editModalTitle"><i class="bi bi-pencil me-2"></i>Edit User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="editModalBody">
            <div class="text-center py-4"><span class="spinner-border text-primary"></span></div>
        </div>
        <div class="modal-footer border-0 pt-0">
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" onclick="submitEditUser()" id="saveUserBtn"><i class="bi bi-save me-2"></i>Save Changes</button>
        </div>
    </div></div>
</div>

<!-- ── RESET PASSWORD MODAL ── -->
<div class="modal fade" id="passModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px">
        <div class="modal-header border-0">
            <h5 class="modal-title fw-700"><i class="bi bi-key me-2 text-warning"></i>Reset Password</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="passUserId">
            <p class="text-muted small mb-3">Resetting password for: <strong id="passUserName"></strong></p>
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <div class="input-group">
                    <input type="text" id="newPassInput" class="form-control" placeholder="Min 6 characters">
                    <button type="button" class="btn btn-outline-secondary" onclick="setDefaultPass()" title="Set default password"><i class="bi bi-magic me-1"></i>Default</button>
                </div>
                <div class="form-text" id="passHint"></div>
            </div>
        </div>
        <div class="modal-footer border-0 pt-0">
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-warning text-dark fw-semibold" onclick="submitResetPass()"><i class="bi bi-key me-2"></i>Reset Password</button>
        </div>
    </div></div>
</div>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
<script>
const BASE = '<?= BASE_URL ?>';
const sections = <?= json_encode($sections_list) ?>;

function toast(msg,type='success'){const t=document.getElementById('liveToast'),m=document.getElementById('toastMsg');t.className=`toast align-items-center text-white bg-${type} border-0`;m.textContent=msg;new bootstrap.Toast(t,{delay:4000}).show();}
async function ajax(action,data={}){const fd=new FormData();fd.append('action',action);Object.entries(data).forEach(([k,v])=>fd.append(k,v));const r=await fetch(`${BASE}/api/admin_ajax.php`,{method:'POST',body:fd});return r.json();}

// Toggle active/inactive
async function toggleUser(id, active) {
    const d = await ajax('toggle_user', {id, status: active?1:0});
    if(d.success) toast(active?'User activated.':'User deactivated.', active?'success':'warning');
    else toast(d.error,'danger');
}

// Edit user modal
let editingUserId = null;
async function openEditModal(id) {
    editingUserId = id;
    document.getElementById('editModalBody').innerHTML = '<div class="text-center py-4"><span class="spinner-border text-primary"></span></div>';
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
    const d = await fetch(`${BASE}/api/admin_ajax.php?action=get_user&id=${id}`).then(r=>r.json());
    if (!d.success) { document.getElementById('editModalBody').innerHTML = '<p class="text-danger text-center">Failed to load user.</p>'; return; }
    const u = d.user;
    let sectionOpts = '<option value="0">— No Section —</option>';
    sections.forEach(s => { sectionOpts += `<option value="${s.id}" ${u.section_id==s.id?'selected':''}>${s.label}</option>`; });

    document.getElementById('editModalTitle').innerHTML = `<i class="bi bi-pencil me-2"></i>Edit: ${u.first_name} ${u.last_name}`;
    document.getElementById('editModalBody').innerHTML = `
    <div class="row g-3">
        <div class="col-md-3 text-center">
            <div class="user-avatar-xl bg-${'admin,danger|coordinator,warning|teacher,primary|student,success'.split('|').find(x=>x.startsWith(u.role))?.split(',')[1]||'secondary'} mx-auto mb-2">${u.first_name.charAt(0).toUpperCase()}${u.last_name.charAt(0).toUpperCase()}</div>
            <div class="fw-700 small">${u.first_name} ${u.last_name}</div>
            <span class="badge bg-secondary-subtle text-secondary font-monospace">${u.user_id_number}</span>
        </div>
        <div class="col-md-9">
            <div class="row g-3">
                <div class="col-6"><label class="form-label">First Name *</label><input class="form-control" id="eu_fn" value="${u.first_name}"></div>
                <div class="col-6"><label class="form-label">Last Name *</label><input class="form-control" id="eu_ln" value="${u.last_name}"></div>
                <div class="col-6"><label class="form-label">Phone</label><input class="form-control" id="eu_phone" value="${u.phone||''}"></div>
                <div class="col-6"><label class="form-label">Gender</label><select class="form-select" id="eu_gender"><option value="male" ${u.gender==='male'?'selected':''}>Male</option><option value="female" ${u.gender==='female'?'selected':''}>Female</option></select></div>
                <div class="col-6"><label class="form-label">Date of Birth</label><input type="date" class="form-control" id="eu_dob" value="${u.date_of_birth||''}"></div>
                <div class="col-6"><label class="form-label">Status</label><select class="form-select" id="eu_active"><option value="1" ${u.is_active?'selected':''}>Active</option><option value="0" ${!u.is_active?'selected':''}>Inactive</option></select></div>
                ${u.role==='student'?`<div class="col-12"><label class="form-label">Section</label><select class="form-select" id="eu_section">${sectionOpts}</select></div>`:''}
            </div>
        </div>
    </div>`;
}

async function submitEditUser(){
    if(!editingUserId) return;
    const fn=document.getElementById('eu_fn')?.value.trim();
    const ln=document.getElementById('eu_ln')?.value.trim();
    const phone=document.getElementById('eu_phone')?.value;
    const gender=document.getElementById('eu_gender')?.value;
    const dob=document.getElementById('eu_dob')?.value;
    const active=document.getElementById('eu_active')?.value;
    const sec=document.getElementById('eu_section')?.value;
    if(!fn||!ln){toast('Name fields required.','danger');return;}
    const data={id:editingUserId,first_name:fn,last_name:ln,phone:phone||'',gender,date_of_birth:dob||'',is_active:active};
    if(sec!==undefined)data.section_id=sec;
    const d=await ajax('edit_user',data);
    if(d.success){
        // Update row in table
        const row=document.getElementById(`user-row-${editingUserId}`);
        if(row){
            const nameDiv=row.querySelector('.fw-semibold.small');
            if(nameDiv)nameDiv.textContent=fn+' '+ln;
        }
        bootstrap.Modal.getInstance(document.getElementById('editUserModal'))?.hide();
        toast('User updated successfully!');
    }else toast(d.error,'danger');
}

// Password reset
let passRole='', passFirst='', passLast='';
function openPassModal(id,first,last,role){
    passRole=role;passFirst=first;passLast=last;
    document.getElementById('passUserId').value=id;
    document.getElementById('passUserName').textContent=first+' '+last;
    const hint=role==='student'?`Default: ${first.toLowerCase()}${last.toLowerCase()}123`:`Default: ${first.toLowerCase()}${last.toLowerCase()}333`;
    document.getElementById('passHint').textContent=hint;
    document.getElementById('newPassInput').value='';
    new bootstrap.Modal(document.getElementById('passModal')).show();
}
function setDefaultPass(){
    const p=passRole==='student'?passFirst.toLowerCase()+passLast.toLowerCase()+'123':passFirst.toLowerCase()+passLast.toLowerCase()+'333';
    document.getElementById('newPassInput').value=p;
}
async function submitResetPass(){
    const id=document.getElementById('passUserId').value;
    const pass=document.getElementById('newPassInput').value.trim();
    if(pass.length<6){toast('Password must be at least 6 characters.','danger');return;}
    const d=await ajax('reset_password',{id,password:pass});
    if(d.success){bootstrap.Modal.getInstance(document.getElementById('passModal'))?.hide();toast('Password reset successfully!');}
    else toast(d.error,'danger');
}
</script>
</body></html>
