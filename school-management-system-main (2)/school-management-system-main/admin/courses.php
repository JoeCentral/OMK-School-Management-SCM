<?php
require_once '../includes/auth.php';
requireLogin(['admin']);
$page_title = 'Courses & Assignments';
$db = getDB();
$current_user = getCurrentUser();
$yr = $db->query("SELECT id FROM academic_years WHERE is_current=1")->fetchColumn();

$courses     = $db->query("SELECT * FROM courses ORDER BY name")->fetchAll();
$teachers    = $db->query("SELECT id,first_name,last_name,user_id_number FROM users WHERE role='teacher' AND is_active=1 ORDER BY first_name")->fetchAll();
$sections    = $db->query("SELECT s.id,s.name as sec,cl.name as class FROM sections s JOIN classes cl ON cl.id=s.class_id ORDER BY cl.grade_level,s.name")->fetchAll();
$assignments = $db->query("
    SELECT tc.*,u.first_name,u.last_name,c.name as course_name,c.color,s.name as sec_name,cl.name as class_name
    FROM teacher_courses tc
    JOIN users u ON u.id=tc.teacher_id
    JOIN courses c ON c.id=tc.course_id
    JOIN sections s ON s.id=tc.section_id
    JOIN classes cl ON cl.id=s.class_id
    ORDER BY cl.grade_level,s.name,c.name
")->fetchAll();

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <h4><i class="bi bi-book-fill me-2"></i>Courses & Teacher Assignments</h4>
    <p>Manage courses and connect teachers to sections</p>
</div>

<!-- Toast -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:9999">
    <div id="liveToast" class="toast align-items-center border-0"><div class="d-flex"><div class="toast-body fw-semibold" id="toastMsg"></div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div></div>
</div>

<div class="row g-4">

    <!-- ── COURSES ── -->
    <div class="col-lg-4">
        <div class="omk-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-book me-2 text-primary"></i>Courses <span class="badge bg-primary-subtle text-primary ms-1" id="courseCount"><?= count($courses) ?></span></span>
                <button class="btn btn-primary btn-sm" onclick="openModal('addCourseModal')"><i class="bi bi-plus me-1"></i>Add</button>
            </div>
            <div class="card-body p-0" id="courseList">
                <?php foreach ($courses as $c): ?>
                <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom course-item" id="course-item-<?= $c['id'] ?>">
                    <div style="width:10px;height:40px;border-radius:4px;background:<?= htmlspecialchars($c['color']) ?>;flex-shrink:0"></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold small text-truncate"><?= sanitize($c['name']) ?></div>
                        <div class="text-muted" style="font-size:11px"><?= sanitize($c['code']) ?></div>
                    </div>
                    <div class="d-flex gap-1 flex-shrink-0">
                        <button class="btn btn-sm btn-outline-primary btn-icon-sm" onclick='editCourse(<?= $c["id"] ?>,<?= htmlspecialchars(json_encode($c["name"])) ?>,<?= htmlspecialchars(json_encode($c["code"])) ?>,<?= htmlspecialchars(json_encode($c["description"])) ?>,"<?= htmlspecialchars($c["color"]) ?>")' title="Edit"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-sm btn-outline-danger btn-icon-sm" onclick="deleteCourse(<?= $c['id'] ?>, '<?= sanitize($c['name']) ?>')" title="Delete"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- ── ASSIGNMENTS ── -->
    <div class="col-lg-8">
        <!-- Assign form -->
        <div class="omk-card mb-4">
            <div class="card-header"><i class="bi bi-link-45deg me-2 text-success"></i>Assign Teacher to Course & Section</div>
            <div class="card-body">
                <div class="row g-3">

                    <!-- ── Teacher Search with Dropdown ── -->
                    <div class="col-md-4">
                        <label class="form-label">Teacher</label>
                        <div class="position-relative">
                            <!-- Search input -->
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"
                                    style="border:1.5px solid #e2e8f0;border-radius:10px 0 0 10px">
                                    <i class="bi bi-search text-muted" id="searchIcon"></i>
                                </span>
                                <input type="text" id="teacherSearch" class="form-control border-start-0"
                                    placeholder="Type to search..."
                                    autocomplete="off"
                                    style="border:1.5px solid #e2e8f0;border-left:none;border-radius:0 10px 10px 0">
                            </div>
                            <!-- Hidden ID -->
                            <input type="hidden" id="asTeacher" value="">
                            <!-- Dropdown list -->
                            <div id="teacherDropdown" class="position-absolute w-100 bg-white d-none"
                                style="z-index:1050;top:calc(100% + 4px);border-radius:12px;border:1.5px solid #e2e8f0;box-shadow:0 8px 28px rgba(0,0,0,0.13);max-height:240px;overflow-y:auto">
                            </div>
                            <!-- Selected pill -->
                            <div id="selectedTeacherPill" class="d-none mt-2 px-3 py-2 d-flex align-items-center justify-content-between"
                                style="background:#eff6ff;border:1.5px solid #93c5fd;border-radius:10px">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-sm bg-primary flex-shrink-0" id="pillAvatar" style="font-size:11px"></div>
                                    <div>
                                        <div class="fw-semibold small" id="pillName"></div>
                                        <div class="text-muted font-monospace" style="font-size:10px" id="pillId"></div>
                                    </div>
                                </div>
                                <button type="button" class="btn-close" style="font-size:10px" onclick="clearTeacher()" title="Clear"></button>
                            </div>
                        </div>
                    </div>

                    <!-- Course -->
                    <div class="col-md-4">
                        <label class="form-label">Course</label>
                        <select id="asCourse" class="form-select">
                            <option value="">— Select Course —</option>
                            <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Section -->
                    <div class="col-md-4">
                        <label class="form-label">Section</label>
                        <select id="asSection" class="form-select">
                            <option value="">— Select Section —</option>
                            <?php foreach ($sections as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= sanitize($s['class'].' — Sec '.$s['sec']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-success" onclick="submitAssignment()" id="assignBtn">
                        <i class="bi bi-link me-2"></i>Assign Teacher
                    </button>
                </div>
            </div>
        </div>

        <!-- Assignments list -->
        <div class="omk-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi bi-list-check me-2 text-primary"></i>Current Assignments <span class="badge bg-primary-subtle text-primary ms-1" id="assignCount"><?= count($assignments) ?></span></span>
                <div class="input-group" style="max-width:220px">
                    <span class="input-group-text bg-white border-end-0"
                        style="border:1.5px solid #e2e8f0;border-radius:10px 0 0 10px;font-size:12px">
                        <i class="bi bi-funnel text-muted"></i>
                    </span>
                    <input type="text" id="filterAssign" class="form-control border-start-0"
                        placeholder="Filter..."
                        style="border:1.5px solid #e2e8f0;border-left:none;border-radius:0 10px 10px 0;font-size:13px"
                        oninput="filterAssignments(this.value)">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table omk-table mb-0">
                        <thead><tr><th>Teacher</th><th>Course</th><th>Class / Section</th><th></th></tr></thead>
                        <tbody id="assignTbody">
                        <?php foreach ($assignments as $a): ?>
                        <tr id="assign-row-<?= $a['id'] ?>" class="assign-row"
                            data-teacher="<?= strtolower(sanitize($a['first_name'].' '.$a['last_name'])) ?>"
                            data-course="<?= strtolower(sanitize($a['course_name'])) ?>"
                            data-section="<?= strtolower(sanitize($a['class_name'].'-'.$a['sec_name'])) ?>">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-sm bg-primary"><?= strtoupper(substr($a['first_name'],0,1).substr($a['last_name'],0,1)) ?></div>
                                    <span class="fw-medium small"><?= sanitize($a['first_name'].' '.$a['last_name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="d-flex align-items-center gap-2">
                                    <span style="width:8px;height:8px;border-radius:50%;background:<?= htmlspecialchars($a['color']) ?>;display:inline-block;flex-shrink:0"></span>
                                    <span class="small"><?= sanitize($a['course_name']) ?></span>
                                </span>
                            </td>
                            <td class="small text-muted"><?= sanitize($a['class_name'].' — Sec '.$a['sec_name']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-danger btn-icon-sm" onclick="removeAssignment(<?= $a['id'] ?>)">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($assignments)): ?>
                        <tr id="emptyAssign"><td colspan="4" class="text-center text-muted py-4">No assignments yet</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div id="noResultsRow" class="text-center py-4 text-muted d-none">
                    <i class="bi bi-search d-block fs-4 mb-1 opacity-40"></i>
                    No assignments match your filter
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── ADD COURSE MODAL ── -->
<div class="modal fade" id="addCourseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px">
        <div class="modal-header border-0">
            <h5 class="modal-title fw-700"><i class="bi bi-book me-2 text-primary"></i>Add New Course</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Course Name <span class="text-danger">*</span></label><input type="text" id="newCourseName" class="form-control" placeholder="e.g. Mathematics"></div>
            <div class="mb-3"><label class="form-label">Course Code <span class="text-muted small">(auto-generated if empty)</span></label><input type="text" id="newCourseCode" class="form-control" placeholder="Leave blank to auto-generate"></div>
            <div class="mb-3"><label class="form-label">Description</label><textarea id="newCourseDesc" class="form-control" rows="2" placeholder="Brief description of the course"></textarea></div>
            <div class="mb-3"><label class="form-label">Color</label><div class="d-flex gap-2 align-items-center"><input type="color" id="newCourseColor" class="form-control form-control-color" value="#4f46e5" style="width:60px"><span class="text-muted small">Choose a color to identify this course</span></div></div>
        </div>
        <div class="modal-footer border-0 pt-0">
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" onclick="submitAddCourse()" id="addCourseBtn"><i class="bi bi-plus me-1"></i>Add Course</button>
        </div>
    </div></div>
</div>

<!-- ── EDIT COURSE MODAL ── -->
<div class="modal fade" id="editCourseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px">
        <div class="modal-header border-0">
            <h5 class="modal-title fw-700"><i class="bi bi-pencil me-2 text-primary"></i>Edit Course</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editCourseId">
            <div class="mb-3"><label class="form-label">Course Name <span class="text-danger">*</span></label><input type="text" id="editCourseName" class="form-control"></div>
            <div class="mb-3"><label class="form-label">Course Code <span class="text-danger">*</span></label><input type="text" id="editCourseCode" class="form-control"></div>
            <div class="mb-3"><label class="form-label">Description</label><textarea id="editCourseDesc" class="form-control" rows="2"></textarea></div>
            <div class="mb-3"><label class="form-label">Color</label><input type="color" id="editCourseColor" class="form-control form-control-color" style="width:60px"></div>
        </div>
        <div class="modal-footer border-0 pt-0">
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" onclick="submitEditCourse()"><i class="bi bi-save me-1"></i>Save Changes</button>
        </div>
    </div></div>
</div>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
<script>
const BASE = '<?= BASE_URL ?>';

// ── Teacher data from PHP ──
const teacherData = <?= json_encode(array_map(fn($t) => [
    'id'   => $t['id'],
    'name' => $t['first_name'].' '.$t['last_name'],
    'uid'  => $t['user_id_number'],
    'init' => strtoupper(substr($t['first_name'],0,1).substr($t['last_name'],0,1)),
], $teachers)) ?>;

// ── DOM refs ──
const searchInput   = document.getElementById('teacherSearch');
const dropdown      = document.getElementById('teacherDropdown');
const hiddenTeacher = document.getElementById('asTeacher');
const pill          = document.getElementById('selectedTeacherPill');
const pillName      = document.getElementById('pillName');
const pillId        = document.getElementById('pillId');
const pillAvatar    = document.getElementById('pillAvatar');
const searchIcon    = document.getElementById('searchIcon');

// ── Search input handler ──
searchInput.addEventListener('input', function() {
    const q = this.value.trim().toLowerCase();
    if (!q) { dropdown.classList.add('d-none'); return; }

    const filtered = teacherData.filter(t =>
        t.name.toLowerCase().includes(q) || t.uid.toLowerCase().includes(q)
    );

    if (!filtered.length) {
        dropdown.innerHTML = `
            <div class="text-center py-4 text-muted">
                <i class="bi bi-person-slash d-block fs-3 mb-2 opacity-40"></i>
                <div class="small">No teacher found for <strong>"${q}"</strong></div>
            </div>`;
    } else {
        dropdown.innerHTML = filtered.map((t, i) => `
            <div class="teacher-option d-flex align-items-center gap-3 px-3 py-2"
                data-id="${t.id}" data-name="${t.name}" data-uid="${t.uid}" data-init="${t.init}"
                style="cursor:pointer;transition:background .12s;${i < filtered.length-1 ? 'border-bottom:1px solid #f0f4f8' : ''}">
                <div class="user-avatar-sm bg-primary flex-shrink-0" style="font-size:11px">${t.init}</div>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold small">${highlight(t.name, q)}</div>
                    <div class="text-muted font-monospace" style="font-size:10px">${t.uid}</div>
                </div>
                <i class="bi bi-arrow-right-circle text-muted small opacity-50"></i>
            </div>`).join('');

        dropdown.querySelectorAll('.teacher-option').forEach(el => {
            el.addEventListener('mouseenter', () => el.style.background = '#f0f4ff');
            el.addEventListener('mouseleave', () => el.style.background = '');
            el.addEventListener('click', () =>
                selectTeacher(el.dataset.id, el.dataset.name, el.dataset.uid, el.dataset.init)
            );
        });
    }
    dropdown.classList.remove('d-none');
});

function highlight(text, q) {
    if (!q) return text;
    const re = new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')})`, 'gi');
    return text.replace(re, '<mark style="background:#fef08a;padding:0 1px;border-radius:2px">$1</mark>');
}

function selectTeacher(id, name, uid, init) {
    hiddenTeacher.value   = id;
    searchInput.value     = '';
    searchInput.disabled  = true;
    searchInput.placeholder = 'Teacher selected ✓';
    searchIcon.className  = 'bi bi-check-circle-fill text-success';
    dropdown.classList.add('d-none');
    pillAvatar.textContent = init;
    pillName.textContent   = name;
    pillId.textContent     = uid;
    pill.classList.remove('d-none');
}

function clearTeacher() {
    hiddenTeacher.value      = '';
    searchInput.disabled     = false;
    searchInput.placeholder  = 'Type to search...';
    searchInput.value        = '';
    searchIcon.className     = 'bi bi-search text-muted';
    pill.classList.add('d-none');
    dropdown.classList.add('d-none');
}

// Close dropdown on outside click
document.addEventListener('click', e => {
    if (!e.target.closest('#teacherSearch') && !e.target.closest('#teacherDropdown')) {
        dropdown.classList.add('d-none');
    }
});

// ── Filter assignments table ──
function filterAssignments(q) {
    q = q.trim().toLowerCase();
    let visible = 0;
    document.querySelectorAll('.assign-row').forEach(row => {
        const match = !q ||
            row.dataset.teacher.includes(q) ||
            row.dataset.course.includes(q) ||
            row.dataset.section.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    document.getElementById('noResultsRow').classList.toggle('d-none', visible > 0 || !q);
}

// ── Helpers ──
function openModal(id)  { new bootstrap.Modal(document.getElementById(id)).show(); }
function closeModal(id) { bootstrap.Modal.getInstance(document.getElementById(id))?.hide(); }
function toast(msg, type='success') {
    const t=document.getElementById('liveToast'), m=document.getElementById('toastMsg');
    t.className=`toast align-items-center text-white bg-${type} border-0`;
    m.textContent=msg; new bootstrap.Toast(t,{delay:3500}).show();
}
async function ajax(action, data={}) {
    const fd=new FormData(); fd.append('action',action);
    Object.entries(data).forEach(([k,v])=>fd.append(k,v));
    const r=await fetch(`${BASE}/api/admin_ajax.php`,{method:'POST',body:fd});
    return r.json();
}

// ── Courses ──
async function submitAddCourse() {
    const name=document.getElementById('newCourseName').value.trim();
    const code=document.getElementById('newCourseCode').value.trim();
    const desc=document.getElementById('newCourseDesc').value.trim();
    const color=document.getElementById('newCourseColor').value;
    if(!name){toast('Course name required.','danger');return;}
    const btn=document.getElementById('addCourseBtn');
    btn.innerHTML='<span class="spinner-border spinner-border-sm"></span>';btn.disabled=true;
    const d=await ajax('add_course',{name,code,description:desc,color});
    btn.innerHTML='<i class="bi bi-plus me-1"></i>Add Course';btn.disabled=false;
    if(d.success){
        document.getElementById('courseList').insertAdjacentHTML('beforeend',`
        <div class="d-flex align-items-center gap-3 px-3 py-2 border-bottom course-item" id="course-item-${d.id}">
            <div style="width:10px;height:40px;border-radius:4px;background:${d.color};flex-shrink:0"></div>
            <div class="flex-grow-1 min-w-0"><div class="fw-semibold small text-truncate">${d.name}</div><div class="text-muted" style="font-size:11px">${d.code}</div></div>
            <div class="d-flex gap-1 flex-shrink-0">
                <button class="btn btn-sm btn-outline-primary btn-icon-sm" onclick='editCourse(${d.id},${JSON.stringify(d.name)},${JSON.stringify(d.code)},${JSON.stringify(d.description||"")},"${d.color}")'><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger btn-icon-sm" onclick="deleteCourse(${d.id},'${d.name}')"><i class="bi bi-trash"></i></button>
            </div>
        </div>`);
        document.getElementById('asCourse').insertAdjacentHTML('beforeend',`<option value="${d.id}">${d.name}</option>`);
        document.getElementById('courseCount').textContent=document.querySelectorAll('.course-item').length;
        document.getElementById('newCourseName').value='';
        document.getElementById('newCourseCode').value='';
        document.getElementById('newCourseDesc').value='';
        closeModal('addCourseModal');
        toast(`Course "${d.name}" added! (Code: ${d.code})`);
    } else toast(d.error,'danger');
}

function editCourse(id,name,code,desc,color){
    document.getElementById('editCourseId').value=id;
    document.getElementById('editCourseName').value=name;
    document.getElementById('editCourseCode').value=code;
    document.getElementById('editCourseDesc').value=desc||'';
    document.getElementById('editCourseColor').value=color;
    openModal('editCourseModal');
}

async function submitEditCourse(){
    const id=document.getElementById('editCourseId').value;
    const name=document.getElementById('editCourseName').value.trim();
    const code=document.getElementById('editCourseCode').value.trim();
    const desc=document.getElementById('editCourseDesc').value.trim();
    const color=document.getElementById('editCourseColor').value;
    if(!name||!code){toast('Name and code required.','danger');return;}
    const d=await ajax('edit_course',{id,name,code,description:desc,color});
    if(d.success){
        const item=document.getElementById(`course-item-${id}`);
        item.querySelector('div[style*="width:10px"]').style.background=color;
        item.querySelectorAll('.fw-semibold')[0].textContent=name;
        item.querySelectorAll('.text-muted')[0].textContent=code;
        closeModal('editCourseModal');toast('Course updated!');
    } else toast(d.error,'danger');
}

async function deleteCourse(id,name){
    if(!confirm(`Delete course "${name}"?`))return;
    const d=await ajax('delete_course',{id});
    if(d.success){
        document.getElementById(`course-item-${id}`)?.remove();
        document.getElementById('courseCount').textContent=document.querySelectorAll('.course-item').length;
        toast('Course deleted.','warning');
    } else toast(d.error,'danger');
}

// ── Assignments ──
async function submitAssignment(){
    const tid=document.getElementById('asTeacher').value;
    const cid=document.getElementById('asCourse').value;
    const sid=document.getElementById('asSection').value;
    if(!tid){toast('Please search and select a teacher first.','danger');return;}
    if(!cid||!sid){toast('Course and section are required.','danger');return;}
    const btn=document.getElementById('assignBtn');
    btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Assigning...';btn.disabled=true;
    const d=await ajax('assign_teacher',{teacher_id:tid,course_id:cid,section_id:sid});
    btn.innerHTML='<i class="bi bi-link me-2"></i>Assign Teacher';btn.disabled=false;
    if(d.success){
        document.getElementById('emptyAssign')?.remove();
        const init=d.teacher.split(' ').map(w=>w[0]||'').join('').toUpperCase().slice(0,2);
        document.getElementById('assignTbody').insertAdjacentHTML('beforeend',`
        <tr id="assign-row-${d.id}" class="assign-row"
            data-teacher="${d.teacher.toLowerCase()}"
            data-course="${d.course.toLowerCase()}"
            data-section="${d.section.toLowerCase()}">
            <td><div class="d-flex align-items-center gap-2"><div class="user-avatar-sm bg-primary">${init}</div><span class="fw-medium small">${d.teacher}</span></div></td>
            <td><span class="d-flex align-items-center gap-2"><span style="width:8px;height:8px;border-radius:50%;background:${d.color};display:inline-block;flex-shrink:0"></span><span class="small">${d.course}</span></span></td>
            <td class="small text-muted">${d.section}</td>
            <td><button class="btn btn-sm btn-outline-danger btn-icon-sm" onclick="removeAssignment(${d.id})"><i class="bi bi-x-lg"></i></button></td>
        </tr>`);
        document.getElementById('assignCount').textContent=document.querySelectorAll('.assign-row').length;
        clearTeacher();
        document.getElementById('asCourse').value='';
        document.getElementById('asSection').value='';
        toast('Teacher assigned successfully!');
    } else toast(d.error,'danger');
}

async function removeAssignment(id){
    if(!confirm('Remove this assignment?'))return;
    const d=await ajax('remove_assignment',{id});
    if(d.success){
        document.getElementById(`assign-row-${id}`)?.remove();
        document.getElementById('assignCount').textContent=document.querySelectorAll('.assign-row').length;
        toast('Assignment removed.','warning');
    } else toast(d.error,'danger');
}
</script>
</body></html>