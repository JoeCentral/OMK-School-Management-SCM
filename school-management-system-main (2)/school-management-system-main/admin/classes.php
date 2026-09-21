<?php
require_once '../includes/auth.php';
requireLogin(['admin']);
$page_title = 'Classes & Sections';
$db = getDB();
$current_user = getCurrentUser();

$classes = $db->query("SELECT c.*,(SELECT COUNT(*) FROM sections WHERE class_id=c.id) as section_count FROM classes c ORDER BY grade_level")->fetchAll();
$sections = $db->query("
    SELECT s.*,cl.name as class_name,cl.grade_level,u.first_name,u.last_name,
    (SELECT COUNT(*) FROM student_sections WHERE section_id=s.id) as student_count
    FROM sections s JOIN classes cl ON cl.id=s.class_id
    LEFT JOIN users u ON u.id=s.coordinator_id
    ORDER BY cl.grade_level, s.name
")->fetchAll();
$coordinators = $db->query("SELECT id,first_name,last_name FROM users WHERE role='coordinator' AND is_active=1")->fetchAll();

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div><h4><i class="bi bi-building me-2"></i>Classes & Sections</h4><p>Manage school classes and sections</p></div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:9999">
    <div id="liveToast" class="toast align-items-center border-0"><div class="d-flex"><div class="toast-body fw-semibold" id="toastMsg"></div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div></div>
</div>

<div class="row g-4">

    <!-- ── CLASSES ── -->
    <div class="col-lg-5">
        <div class="omk-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-building me-2 text-primary"></i>Classes <span class="badge bg-primary-subtle text-primary ms-1" id="classCount"><?= count($classes) ?></span></span>
                <button class="btn btn-primary btn-sm" onclick="openModal('addClassModal')"><i class="bi bi-plus me-1"></i>Add Class</button>
            </div>
            <div class="card-body p-0">
                <table class="table omk-table mb-0" id="classTable">
                    <thead><tr><th>Grade</th><th>Name</th><th>Sections</th><th class="text-center">Actions</th></tr></thead>
                    <tbody id="classTbody">
                    <?php foreach ($classes as $cl): ?>
                    <tr id="class-row-<?= $cl['id'] ?>">
                        <td><span class="badge bg-primary-subtle text-primary">Grade <?= $cl['grade_level'] ?></span></td>
                        <td class="fw-semibold small"><?= sanitize($cl['name']) ?></td>
                        <td><span class="badge bg-secondary-subtle text-secondary"><?= $cl['section_count'] ?></span></td>
                        <td class="text-center">
                            <div class="d-flex gap-1 justify-content-center">
                                <button class="btn btn-sm btn-outline-primary btn-icon-sm" onclick='editClass(<?= $cl["id"] ?>,<?= htmlspecialchars(json_encode($cl["name"])) ?>,<?= $cl["grade_level"] ?>)' title="Edit"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-outline-danger btn-icon-sm" onclick="deleteClass(<?= $cl['id'] ?>, '<?= sanitize($cl['name']) ?>')" title="Delete"><i class="bi bi-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── SECTIONS ── -->
    <div class="col-lg-7">
        <div class="omk-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-diagram-2 me-2 text-success"></i>Sections <span class="badge bg-success-subtle text-success ms-1" id="sectionCount"><?= count($sections) ?></span></span>
                <button class="btn btn-success btn-sm" onclick="openModal('addSectionModal')"><i class="bi bi-plus me-1"></i>Add Section</button>
            </div>
            <div class="card-body p-0">
                <table class="table omk-table mb-0">
                    <thead><tr><th>Class</th><th>Section</th><th>Coordinator</th><th>Students</th><th>Max</th><th class="text-center">Actions</th></tr></thead>
                    <tbody id="sectionTbody">
                    <?php foreach ($sections as $s): ?>
                    <tr id="section-row-<?= $s['id'] ?>">
                        <td class="small"><?= sanitize($s['class_name']) ?></td>
                        <td><span class="badge bg-success-subtle text-success fw-bold">Section <?= sanitize($s['name']) ?></span></td>
                        <td class="small text-muted"><?= $s['first_name'] ? sanitize($s['first_name'].' '.$s['last_name']) : '—' ?></td>
                        <td class="fw-semibold"><?= $s['student_count'] ?></td>
                        <td class="text-muted small"><?= $s['max_students'] ?></td>
                        <td class="text-center">
                            <div class="d-flex gap-1 justify-content-center">
                                <button class="btn btn-sm btn-outline-primary btn-icon-sm" onclick='editSection(<?= $s["id"] ?>,<?= htmlspecialchars(json_encode($s["name"])) ?>,<?= $s["max_students"] ?>,<?= $s["coordinator_id"]??"0" ?>)' title="Edit"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-outline-danger btn-icon-sm" onclick="deleteSection(<?= $s['id'] ?>)" title="Delete"><i class="bi bi-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ── ADD CLASS MODAL ── -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px">
        <div class="modal-header border-0">
            <h5 class="modal-title fw-700"><i class="bi bi-building me-2 text-primary"></i>Add New Class</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body pt-0">
            <div class="mb-3"><label class="form-label">Class Name <span class="text-danger">*</span></label><input type="text" id="newClassName" class="form-control" placeholder="e.g. Grade 10"></div>
            <div class="mb-3"><label class="form-label">Grade Level <span class="text-danger">*</span></label><input type="number" id="newClassGrade" class="form-control" min="1" max="12" placeholder="e.g. 10"></div>
        </div>
        <div class="modal-footer border-0 pt-0">
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" id="addClassBtn" onclick="submitAddClass()"><i class="bi bi-plus me-1"></i>Add Class</button>
        </div>
    </div></div>
</div>

<!-- ── EDIT CLASS MODAL ── -->
<div class="modal fade" id="editClassModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px">
        <div class="modal-header border-0">
            <h5 class="modal-title fw-700"><i class="bi bi-pencil me-2 text-primary"></i>Edit Class</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body pt-0">
            <input type="hidden" id="editClassId">
            <div class="mb-3"><label class="form-label">Class Name <span class="text-danger">*</span></label><input type="text" id="editClassName" class="form-control"></div>
            <div class="mb-3"><label class="form-label">Grade Level <span class="text-danger">*</span></label><input type="number" id="editClassGrade" class="form-control" min="1" max="12"></div>
        </div>
        <div class="modal-footer border-0 pt-0">
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" onclick="submitEditClass()"><i class="bi bi-save me-1"></i>Save Changes</button>
        </div>
    </div></div>
</div>

<!-- ── ADD SECTION MODAL ── -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px">
        <div class="modal-header border-0">
            <h5 class="modal-title fw-700"><i class="bi bi-diagram-2 me-2 text-success"></i>Add New Section</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body pt-0">
            <div class="mb-3">
                <label class="form-label">Class <span class="text-danger">*</span></label>
                <select id="newSectionClass" class="form-select">
                    <option value="">— Select Class —</option>
                    <?php foreach ($classes as $cl): ?><option value="<?= $cl['id'] ?>"><?= sanitize($cl['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3"><label class="form-label">Section Name <span class="text-danger">*</span></label><input type="text" id="newSectionName" class="form-control" placeholder="e.g. A, B, C"></div>
            <div class="mb-3"><label class="form-label">Max Students</label><input type="number" id="newSectionMax" class="form-control" value="30" min="1"></div>
            <div class="mb-3">
                <label class="form-label">Coordinator</label>
                <select id="newSectionCoord" class="form-select">
                    <option value="0">— None —</option>
                    <?php foreach ($coordinators as $c): ?><option value="<?= $c['id'] ?>"><?= sanitize($c['first_name'].' '.$c['last_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer border-0 pt-0">
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-success" onclick="submitAddSection()"><i class="bi bi-plus me-1"></i>Add Section</button>
        </div>
    </div></div>
</div>

<!-- ── EDIT SECTION MODAL ── -->
<div class="modal fade" id="editSectionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px">
        <div class="modal-header border-0">
            <h5 class="modal-title fw-700"><i class="bi bi-pencil me-2 text-success"></i>Edit Section</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body pt-0">
            <input type="hidden" id="editSectionId">
            <div class="mb-3"><label class="form-label">Section Name <span class="text-danger">*</span></label><input type="text" id="editSectionName" class="form-control"></div>
            <div class="mb-3"><label class="form-label">Max Students</label><input type="number" id="editSectionMax" class="form-control" min="1"></div>
            <div class="mb-3">
                <label class="form-label">Coordinator</label>
                <select id="editSectionCoord" class="form-select">
                    <option value="0">— None —</option>
                    <?php foreach ($coordinators as $c): ?><option value="<?= $c['id'] ?>"><?= sanitize($c['first_name'].' '.$c['last_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer border-0 pt-0">
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-success" onclick="submitEditSection()"><i class="bi bi-save me-1"></i>Save Changes</button>
        </div>
    </div></div>
</div>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
<script>
const BASE = '<?= BASE_URL ?>';

function openModal(id) { new bootstrap.Modal(document.getElementById(id)).show(); }
function closeModal(id) { bootstrap.Modal.getInstance(document.getElementById(id))?.hide(); }

function toast(msg, type='success') {
    const t = document.getElementById('liveToast'), m = document.getElementById('toastMsg');
    t.className = `toast align-items-center text-white bg-${type} border-0`;
    m.textContent = msg; new bootstrap.Toast(t, {delay:3500}).show();
}

async function ajax(action, data={}) {
    const fd = new FormData(); fd.append('action', action);
    Object.entries(data).forEach(([k,v]) => fd.append(k, v));
    const r = await fetch(`${BASE}/api/admin_ajax.php`, {method:'POST', body:fd});
    return r.json();
}

// ── Classes ──
async function submitAddClass() {
    const name = document.getElementById('newClassName').value.trim();
    const grade = document.getElementById('newClassGrade').value.trim();
    if (!name || !grade) { toast('Name and grade required.','danger'); return; }
    const btn = document.getElementById('addClassBtn');
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; btn.disabled = true;
    const d = await ajax('add_class', {class_name:name, grade_level:grade});
    btn.innerHTML = '<i class="bi bi-plus me-1"></i>Add Class'; btn.disabled = false;
    if (d.success) {
        // Inject row
        const tbody = document.getElementById('classTbody');
        tbody.insertAdjacentHTML('beforeend', `
        <tr id="class-row-${d.id}">
            <td><span class="badge bg-primary-subtle text-primary">Grade ${grade}</span></td>
            <td class="fw-semibold small">${name}</td>
            <td><span class="badge bg-secondary-subtle text-secondary">0</span></td>
            <td class="text-center"><div class="d-flex gap-1 justify-content-center">
                <button class="btn btn-sm btn-outline-primary btn-icon-sm" onclick='editClass(${d.id},"${name}",${grade})'><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger btn-icon-sm" onclick="deleteClass(${d.id},'${name}')"><i class="bi bi-trash"></i></button>
            </div></td>
        </tr>`);
        // Also add to section modal dropdown
        document.getElementById('newSectionClass').insertAdjacentHTML('beforeend', `<option value="${d.id}">${name}</option>`);
        document.getElementById('classCount').textContent = tbody.children.length;
        document.getElementById('newClassName').value = '';
        document.getElementById('newClassGrade').value = '';
        closeModal('addClassModal'); toast('Class added!');
    } else toast(d.error, 'danger');
}

function editClass(id, name, grade) {
    document.getElementById('editClassId').value = id;
    document.getElementById('editClassName').value = name;
    document.getElementById('editClassGrade').value = grade;
    openModal('editClassModal');
}

async function submitEditClass() {
    const id = document.getElementById('editClassId').value;
    const name = document.getElementById('editClassName').value.trim();
    const grade = document.getElementById('editClassGrade').value.trim();
    if (!name || !grade) { toast('All fields required.','danger'); return; }
    const d = await ajax('edit_class', {id, class_name:name, grade_level:grade});
    if (d.success) {
        const row = document.getElementById(`class-row-${id}`);
        row.cells[0].innerHTML = `<span class="badge bg-primary-subtle text-primary">Grade ${grade}</span>`;
        row.cells[1].textContent = name;
        closeModal('editClassModal'); toast('Class updated!');
    } else toast(d.error,'danger');
}

async function deleteClass(id, name) {
    if (!confirm(`Delete class "${name}" and all its sections?`)) return;
    const d = await ajax('delete_class', {id});
    if (d.success) {
        document.getElementById(`class-row-${id}`)?.remove();
        document.getElementById('classCount').textContent = document.getElementById('classTbody').children.length;
        toast('Class deleted.','warning');
    } else toast(d.error,'danger');
}

// ── Sections ──
async function submitAddSection() {
    const cid = document.getElementById('newSectionClass').value;
    const name = document.getElementById('newSectionName').value.trim();
    const max = document.getElementById('newSectionMax').value;
    const coord = document.getElementById('newSectionCoord').value;
    if (!cid || !name) { toast('Class and section name required.','danger'); return; }
    const d = await ajax('add_section', {class_id:cid, section_name:name, max_students:max, coordinator_id:coord});
    if (d.success) {
        const tbody = document.getElementById('sectionTbody');
        tbody.insertAdjacentHTML('beforeend', `
        <tr id="section-row-${d.id}">
            <td class="small">${d.class_name}</td>
            <td><span class="badge bg-success-subtle text-success fw-bold">Section ${name}</span></td>
            <td class="small text-muted">—</td>
            <td class="fw-semibold">0</td>
            <td class="text-muted small">${max}</td>
            <td class="text-center"><div class="d-flex gap-1 justify-content-center">
                <button class="btn btn-sm btn-outline-primary btn-icon-sm" onclick='editSection(${d.id},"${name}",${max},${coord})'><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger btn-icon-sm" onclick="deleteSection(${d.id})"><i class="bi bi-trash"></i></button>
            </div></td>
        </tr>`);
        document.getElementById('sectionCount').textContent = tbody.children.length;
        document.getElementById('newSectionName').value = '';
        closeModal('addSectionModal'); toast('Section added!');
    } else toast(d.error,'danger');
}

function editSection(id, name, max, coord) {
    document.getElementById('editSectionId').value = id;
    document.getElementById('editSectionName').value = name;
    document.getElementById('editSectionMax').value = max;
    document.getElementById('editSectionCoord').value = coord || 0;
    openModal('editSectionModal');
}

async function submitEditSection() {
    const id = document.getElementById('editSectionId').value;
    const name = document.getElementById('editSectionName').value.trim();
    const max = document.getElementById('editSectionMax').value;
    const coord = document.getElementById('editSectionCoord').value;
    if (!name) { toast('Section name required.','danger'); return; }
    const d = await ajax('edit_section', {id, section_name:name, max_students:max, coordinator_id:coord});
    if (d.success) {
        const row = document.getElementById(`section-row-${id}`);
        row.cells[1].innerHTML = `<span class="badge bg-success-subtle text-success fw-bold">Section ${name}</span>`;
        row.cells[4].textContent = max;
        closeModal('editSectionModal'); toast('Section updated!');
    } else toast(d.error,'danger');
}

async function deleteSection(id) {
    if (!confirm('Delete this section? Students will be unenrolled.')) return;
    const d = await ajax('delete_section', {id});
    if (d.success) {
        document.getElementById(`section-row-${id}`)?.remove();
        document.getElementById('sectionCount').textContent = document.getElementById('sectionTbody').children.length;
        toast('Section deleted.','warning');
    } else toast(d.error,'danger');
}
</script>
</body></html>
