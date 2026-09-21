<?php
require_once '../includes/auth.php';
requireLogin(['coordinator']);
$page_title = 'Programs';
$db = getDB();
$current_user = getCurrentUser();

$my_sections = $db->prepare("
    SELECT s.*, cl.name as class_name, cl.grade_level
    FROM sections s JOIN classes cl ON cl.id = s.class_id
    WHERE s.coordinator_id = ? ORDER BY cl.grade_level, s.name
");
$my_sections->execute([$current_user['id']]);
$my_sections = $my_sections->fetchAll();

$section_id = (int)($_GET['section'] ?? ($my_sections[0]['id'] ?? 0));
$yr = $db->query("SELECT id FROM academic_years WHERE is_current=1")->fetchColumn();

// Courses assigned to this section
$section_courses = [];
if ($section_id) {
    $stmt = $db->prepare("
        SELECT DISTINCT c.id, c.name, c.color, u.id as teacher_id, u.first_name, u.last_name
        FROM teacher_courses tc
        JOIN courses c ON c.id = tc.course_id
        JOIN users u ON u.id = tc.teacher_id
        WHERE tc.section_id = ?
    ");
    $stmt->execute([$section_id]);
    $section_courses = $stmt->fetchAll();
}

// Existing programs for this section
$programs = [];
if ($section_id) {
    $stmt = $db->prepare("
        SELECT p.*, c.name as course_name, c.color, u.first_name, u.last_name
        FROM programs p
        JOIN courses c ON c.id = p.course_id
        JOIN users u ON u.id = p.teacher_id
        WHERE p.section_id = ?
        ORDER BY FIELD(p.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), p.start_time
    ");
    $stmt->execute([$section_id]);
    $programs = $stmt->fetchAll();
}

// 6 fixed sessions per day
$sessions = [
    1 => ['label'=>'Session 1','start'=>'08:00','end'=>'09:00'],
    2 => ['label'=>'Session 2','start'=>'09:00','end'=>'10:00'],
    3 => ['label'=>'Session 3','start'=>'10:00','end'=>'11:00'],
    4 => ['label'=>'Session 4','start'=>'11:00','end'=>'12:00'],
    5 => ['label'=>'Session 5','start'=>'12:00','end'=>'13:00'],
    6 => ['label'=>'Session 6','start'=>'13:00','end'=>'14:00'],
];

$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

// Build grid[day][sessionNum] = program
$grid = [];
foreach ($days as $d) $grid[$d] = [];
foreach ($programs as $p) {
    foreach ($sessions as $sn => $s) {
        if (substr($p['start_time'],0,5) === $s['start']) {
            $grid[$p['day_of_week']][$sn] = $p;
            break;
        }
    }
}

$cur_section = null;
foreach ($my_sections as $ms) if ($ms['id']==$section_id){$cur_section=$ms;break;}

include '../includes/header.php';
?>
<div class="omk-wrapper">
<?php include '../includes/sidebar.php'; ?>
<div class="main-content">

<div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4><i class="bi bi-calendar3 me-2"></i>Weekly Programs</h4>
            <p>Click any cell to assign a course — conflicts are detected automatically.</p>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="position-fixed top-0 end-0 p-3" style="z-index:9999">
    <div id="liveToast" class="toast align-items-center border-0">
        <div class="d-flex"><div class="toast-body fw-semibold" id="toastMsg"></div>
        <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div>
    </div>
</div>

<!-- Section tabs -->
<div class="d-flex gap-2 mb-4 flex-wrap align-items-center">
    <span class="text-muted small fw-semibold me-1">Section:</span>
    <?php foreach ($my_sections as $s): ?>
    <a href="programs.php?section=<?= $s['id'] ?>"
       class="btn btn-<?= $s['id']==$section_id?'primary':'outline-primary' ?> btn-sm">
        <?= sanitize($s['class_name'].' — '.$s['name']) ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if (!$section_id): ?>
<div class="omk-card text-center py-5 text-muted">
    <i class="bi bi-calendar-x fs-2 d-block mb-2 opacity-30"></i>No sections assigned yet.
</div>
<?php elseif (empty($section_courses)): ?>
<div class="omk-card text-center py-5 text-muted">
    <i class="bi bi-book fs-2 d-block mb-2 opacity-30"></i>
    No courses assigned to this section yet.<br>
    <span class="small">Ask the admin to assign courses & teachers first.</span>
</div>
<?php else: ?>

<!-- Stats + legend -->
<div class="d-flex align-items-center gap-4 mb-3 flex-wrap">
    <?php if ($cur_section): ?>
    <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background:#f0f4ff;border:1.5px solid #c7d2fe">
        <i class="bi bi-diagram-2 text-primary"></i>
        <span class="fw-semibold small" style="color:#1e3a5f"><?= sanitize($cur_section['class_name'].' — Section '.$cur_section['name']) ?></span>
    </div>
    <?php endif; ?>
    <div class="d-flex align-items-center gap-2 small text-muted">
        <div style="width:16px;height:16px;border-radius:5px;background:#f0f4ff;border:2px dashed #93c5fd"></div> Empty — click to assign
    </div>
    <div class="d-flex align-items-center gap-2 small text-muted">
        <div style="width:16px;height:16px;border-radius:5px;background:#4f46e5"></div> Assigned — click to edit
    </div>
    <div class="ms-auto text-muted small">
        <i class="bi bi-check2-circle me-1 text-success"></i><?= count($programs) ?> / <?= count($sessions) * count($days) ?> sessions filled
    </div>
</div>

<!-- ── GRID ── -->
<div class="omk-card mb-0">
    <div class="card-body p-0" style="overflow-x:auto">
        <table class="w-100" style="border-collapse:collapse;min-width:780px">
            <thead>
                <tr>
                    <!-- Time header -->
                    <th style="width:88px;padding:14px 10px;background:#f8fafc;border-bottom:2px solid #e2e8f0;border-right:1px solid #e2e8f0">
                        <div class="text-muted smaller fw-700 text-uppercase" style="letter-spacing:.5px">Time</div>
                    </th>
                    <?php foreach ($days as $i => $day):
                        $is_weekend   = ($i >= 5);
                        $filled_count = count(array_filter(array_keys($sessions), fn($sn) => isset($grid[$day][$sn])));
                    ?>
                    <th class="text-center" style="padding:12px 6px;background:<?= $is_weekend?'#f9fafb':'#fff' ?>;border-bottom:2px solid #e2e8f0;border-right:<?= $i<6?'1px solid #f0f0f0':'none' ?>">
                        <div class="fw-700 small" style="color:<?= $is_weekend?'#94a3b8':'#1e3a5f' ?>"><?= $day ?></div>
                        <?php if ($filled_count > 0): ?>
                        <span class="badge bg-primary-subtle text-primary mt-1" style="font-size:9px"><?= $filled_count ?> class<?= $filled_count>1?'es':'' ?></span>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:9px">—</span>
                        <?php endif; ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sessions as $sn => $session):
                    $is_last = ($sn === array_key_last($sessions));
                ?>
                <tr>
                    <!-- Time label -->
                    <td style="padding:8px 10px;background:#f8fafc;border-bottom:<?= $is_last?'none':'1px solid #f0f4f8' ?>;border-right:1px solid #e2e8f0;vertical-align:middle">
                        <div class="fw-700 small" style="color:#4f46e5"><?= $session['label'] ?></div>
                        <div class="text-muted" style="font-size:10px;line-height:1.5">
                            <?= date('g:i A', strtotime($session['start'])) ?><br>
                            <span style="opacity:.6">↓</span><br>
                            <?= date('g:i A', strtotime($session['end'])) ?>
                        </div>
                    </td>

                    <?php foreach ($days as $i => $day):
                        $is_weekend = ($i >= 5);
                        $prog       = $grid[$day][$sn] ?? null;
                    ?>
                    <td style="padding:5px;border-bottom:<?= $is_last?'none':'1px solid #f0f4f8' ?>;border-right:<?= $i<6?'1px solid #f0f4f8':'none' ?>;background:<?= $is_weekend?'#fafcff':'#fff' ?>;height:88px;vertical-align:stretch">

                        <?php if ($prog): ?>
                        <!-- Filled cell -->
                        <div class="grid-cell filled h-100 rounded-3 p-2 position-relative d-flex flex-column justify-content-between"
                             style="background:<?= htmlspecialchars($prog['color']) ?>18;border:1.5px solid <?= htmlspecialchars($prog['color']) ?>66;cursor:pointer;transition:filter .15s"
                             onclick="openEditModal(<?= $prog['id'] ?>,<?= $sn ?>,<?= htmlspecialchars(json_encode($day)) ?>,<?= $prog['course_id'] ?>,<?= htmlspecialchars(json_encode($prog['room']??'')) ?>)"
                             onmouseenter="this.style.filter='brightness(.94)'"
                             onmouseleave="this.style.filter='none'">
                            <div>
                                <div class="fw-800" style="font-size:11px;color:<?= htmlspecialchars($prog['color']) ?>;line-height:1.25;margin-bottom:3px">
                                    <?= sanitize($prog['course_name']) ?>
                                </div>
                                <div class="text-muted" style="font-size:9.5px;line-height:1.4">
                                    <i class="bi bi-person-fill" style="font-size:8px"></i>
                                    <?= sanitize($prog['first_name'].' '.$prog['last_name']) ?>
                                </div>
                                <?php if ($prog['room']): ?>
                                <div class="text-muted" style="font-size:9px">
                                    <i class="bi bi-geo-alt-fill" style="font-size:8px"></i> <?= sanitize($prog['room']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <!-- Remove button -->
                            <button class="position-absolute d-flex align-items-center justify-content-center"
                                    style="top:3px;right:3px;width:17px;height:17px;border-radius:50%;background:<?= htmlspecialchars($prog['color']) ?>;border:none;cursor:pointer;opacity:.65;transition:opacity .15s;padding:0"
                                    onmouseenter="this.style.opacity=1" onmouseleave="this.style.opacity=.65"
                                    onclick="event.stopPropagation();deleteProgram(<?= $prog['id'] ?>)"
                                    title="Remove">
                                <i class="bi bi-x text-white" style="font-size:11px;line-height:1"></i>
                            </button>
                        </div>

                        <?php else: ?>
                        <!-- Empty cell -->
                        <div class="grid-cell empty h-100 rounded-3 d-flex align-items-center justify-content-center"
                             style="background:#f8faff;border:2px dashed #d8e0f5;cursor:pointer;transition:all .18s;min-height:76px"
                             onclick="openAddModal(<?= $sn ?>, <?= htmlspecialchars(json_encode($day)) ?>)"
                             onmouseenter="this.style.background='#eef2ff';this.style.borderColor='#6366f1';this.querySelector('i').style.opacity='.8'"
                             onmouseleave="this.style.background='#f8faff';this.style.borderColor='#d8e0f5';this.querySelector('i').style.opacity='.25'">
                            <i class="bi bi-plus-circle text-primary" style="font-size:22px;opacity:.25;transition:opacity .18s"></i>
                        </div>
                        <?php endif; ?>

                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- ── ADD MODAL ── -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
        <div class="modal-content" style="border-radius:20px;border:none;box-shadow:0 24px 64px rgba(0,0,0,.16)">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div>
                    <h5 class="fw-800 mb-1"><i class="bi bi-calendar-plus me-2 text-primary"></i>Assign Course</h5>
                    <div class="small text-muted" id="addModalSub"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pt-3">
                <input type="hidden" id="addSession">
                <input type="hidden" id="addDay">
                <input type="hidden" id="addCourseId">
                <input type="hidden" id="addTeacherId">

                <label class="form-label small fw-semibold mb-2">Select Course <span class="text-danger">*</span></label>
                <div id="addCoursePicker" class="row g-2 mb-3">
                    <?php foreach ($section_courses as $sc): ?>
                    <div class="col-6">
                        <div class="course-card p-3 rounded-3 text-center"
                             data-cid="<?= $sc['id'] ?>" data-tid="<?= $sc['teacher_id'] ?>"
                             style="border:2px solid <?= htmlspecialchars($sc['color']) ?>40;background:<?= htmlspecialchars($sc['color']) ?>0f;cursor:pointer;transition:all .15s"
                             onclick="pickCourse(this,'add')">
                            <div style="width:34px;height:34px;border-radius:10px;background:<?= htmlspecialchars($sc['color']) ?>;margin:0 auto 6px;display:flex;align-items:center;justify-content:center">
                                <i class="bi bi-book-fill text-white" style="font-size:14px"></i>
                            </div>
                            <div class="fw-700" style="font-size:12px;color:<?= htmlspecialchars($sc['color']) ?>"><?= sanitize($sc['name']) ?></div>
                            <div class="text-muted" style="font-size:10px"><?= sanitize($sc['first_name'].' '.$sc['last_name']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Room <span class="text-muted fw-400">(optional)</span></label>
                    <input type="text" id="addRoom" class="form-control" placeholder="e.g. Room 101, Lab 2">
                </div>

                <div id="addConflict" class="alert alert-danger d-none py-2 small mb-0" style="border-radius:10px">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><span id="addConflictMsg"></span>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-2">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary px-4" id="addBtn" onclick="submitAdd()">
                    <i class="bi bi-check-lg me-1"></i>Assign
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── EDIT MODAL ── -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
        <div class="modal-content" style="border-radius:20px;border:none;box-shadow:0 24px 64px rgba(0,0,0,.16)">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div>
                    <h5 class="fw-800 mb-1"><i class="bi bi-pencil me-2 text-warning"></i>Edit Session</h5>
                    <div class="small text-muted" id="editModalSub"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-4 pt-3">
                <input type="hidden" id="editProgId">
                <input type="hidden" id="editSession">
                <input type="hidden" id="editDay">
                <input type="hidden" id="editCourseId">
                <input type="hidden" id="editTeacherId">

                <label class="form-label small fw-semibold mb-2">Change Course</label>
                <div id="editCoursePicker" class="row g-2 mb-3">
                    <?php foreach ($section_courses as $sc): ?>
                    <div class="col-6">
                        <div class="course-card p-3 rounded-3 text-center"
                             data-cid="<?= $sc['id'] ?>" data-tid="<?= $sc['teacher_id'] ?>"
                             style="border:2px solid <?= htmlspecialchars($sc['color']) ?>40;background:<?= htmlspecialchars($sc['color']) ?>0f;cursor:pointer;transition:all .15s"
                             onclick="pickCourse(this,'edit')">
                            <div style="width:34px;height:34px;border-radius:10px;background:<?= htmlspecialchars($sc['color']) ?>;margin:0 auto 6px;display:flex;align-items:center;justify-content:center">
                                <i class="bi bi-book-fill text-white" style="font-size:14px"></i>
                            </div>
                            <div class="fw-700" style="font-size:12px;color:<?= htmlspecialchars($sc['color']) ?>"><?= sanitize($sc['name']) ?></div>
                            <div class="text-muted" style="font-size:10px"><?= sanitize($sc['first_name'].' '.$sc['last_name']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Room</label>
                    <input type="text" id="editRoom" class="form-control" placeholder="e.g. Room 101">
                </div>

                <div id="editConflict" class="alert alert-danger d-none py-2 small mb-0" style="border-radius:10px">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><span id="editConflictMsg"></span>
                </div>
            </div>
            <div class="modal-footer border-0 px-4 pb-4 pt-2 d-flex justify-content-between">
                <button class="btn btn-outline-danger btn-sm" onclick="deleteProgram(document.getElementById('editProgId').value)">
                    <i class="bi bi-trash me-1"></i>Remove
                </button>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-warning px-4" id="editBtn" onclick="submitEdit()">
                        <i class="bi bi-save me-1"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

</div></div>
<button id="scrollTop"><i class="bi bi-chevron-up"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/js/main.js"></script>
<style>
.course-card.picked {
    transform: scale(1.05);
    box-shadow: 0 6px 18px rgba(0,0,0,.14);
}
.grid-cell.filled:hover { filter: brightness(.93) !important; }
</style>
<script>
const BASE       = '<?= BASE_URL ?>';
const SECTION_ID = <?= $section_id ?>;

const SESSIONS = {
    1:{start:'08:00',end:'09:00',label:'Session 1 · 8:00 – 9:00 AM'},
    2:{start:'09:00',end:'10:00',label:'Session 2 · 9:00 – 10:00 AM'},
    3:{start:'10:00',end:'11:00',label:'Session 3 · 10:00 – 11:00 AM'},
    4:{start:'11:00',end:'12:00',label:'Session 4 · 11:00 AM – 12:00 PM'},
    5:{start:'12:00',end:'13:00',label:'Session 5 · 12:00 – 1:00 PM'},
    6:{start:'13:00',end:'14:00',label:'Session 6 · 1:00 – 2:00 PM'},
};

// All programs for conflict checking
let programs = <?= json_encode(array_values($programs)) ?>;

// ── Helpers ──────────────────────────────────────────────────
function toast(msg,type='success'){
    const t=document.getElementById('liveToast'),m=document.getElementById('toastMsg');
    t.className=`toast align-items-center text-white bg-${type} border-0`;
    m.textContent=msg;new bootstrap.Toast(t,{delay:3500}).show();
}
function openModal(id)  { new bootstrap.Modal(document.getElementById(id)).show(); }
function closeModal(id) { bootstrap.Modal.getInstance(document.getElementById(id))?.hide(); }
async function ajax(action,data={}){
    const fd=new FormData(); fd.append('action',action);
    Object.entries(data).forEach(([k,v])=>fd.append(k,v));
    const r=await fetch(`${BASE}/api/coordinator_ajax.php`,{method:'POST',body:fd});
    return r.json();
}

// ── Conflict check ───────────────────────────────────────────
// Returns conflicting program if teacher is double-booked at same day+session
function checkConflict(day, sn, teacherId, excludeId=null) {
    const s = SESSIONS[sn];
    return programs.find(p =>
        p.day_of_week === day &&
        p.start_time.substring(0,5) === s.start &&
        parseInt(p.teacher_id) === parseInt(teacherId) &&
        (excludeId === null || parseInt(p.id) !== parseInt(excludeId))
    ) || null;
}

// ── Course picker ─────────────────────────────────────────────
function pickCourse(el, mode) {
    const prefix = mode === 'add' ? 'add' : 'edit';
    document.querySelectorAll(`#${prefix}CoursePicker .course-card`).forEach(c => c.classList.remove('picked'));
    el.classList.add('picked');
    document.getElementById(`${prefix}CourseId`).value  = el.dataset.cid;
    document.getElementById(`${prefix}TeacherId`).value = el.dataset.tid;

    // Live conflict check
    const day = document.getElementById(`${prefix}Day`).value;
    const sn  = parseInt(document.getElementById(`${prefix}Session`).value);
    const exId = mode==='edit' ? parseInt(document.getElementById('editProgId').value) : null;
    showConflict(prefix, day, sn, el.dataset.tid, exId);
}

function showConflict(prefix, day, sn, teacherId, excludeId=null) {
    const c    = checkConflict(day, sn, teacherId, excludeId);
    const box  = document.getElementById(`${prefix}Conflict`);
    const msg  = document.getElementById(`${prefix}ConflictMsg`);
    const btn  = document.getElementById(`${prefix}Btn`);
    if (c) {
        msg.textContent = `⚠ Conflict: This teacher already teaches "${c.course_name}" on ${day} at ${SESSIONS[sn].start}.`;
        box.classList.remove('d-none');
        btn.disabled = true;
    } else {
        box.classList.add('d-none');
        btn.disabled = false;
    }
}

// ── Open ADD modal ────────────────────────────────────────────
function openAddModal(sn, day) {
    // Reset
    document.querySelectorAll('#addCoursePicker .course-card').forEach(c=>c.classList.remove('picked'));
    document.getElementById('addCourseId').value = '';
    document.getElementById('addTeacherId').value= '';
    document.getElementById('addRoom').value     = '';
    document.getElementById('addBtn').disabled   = false;
    document.getElementById('addConflict').classList.add('d-none');
    document.getElementById('addSession').value  = sn;
    document.getElementById('addDay').value      = day;
    document.getElementById('addModalSub').innerHTML =
        `<strong>${day}</strong> &nbsp;·&nbsp; ${SESSIONS[sn].label}`;
    openModal('addModal');
}

// ── Open EDIT modal ───────────────────────────────────────────
function openEditModal(progId, sn, day, courseId, room) {
    document.getElementById('editProgId').value  = progId;
    document.getElementById('editSession').value = sn;
    document.getElementById('editDay').value     = day;
    document.getElementById('editRoom').value    = room;
    document.getElementById('editBtn').disabled  = false;
    document.getElementById('editConflict').classList.add('d-none');
    document.getElementById('editModalSub').innerHTML =
        `<strong>${day}</strong> &nbsp;·&nbsp; ${SESSIONS[sn].label}`;

    // Pre-select current course
    document.querySelectorAll('#editCoursePicker .course-card').forEach(c => {
        c.classList.remove('picked');
        if (parseInt(c.dataset.cid) === parseInt(courseId)) {
            c.classList.add('picked');
            document.getElementById('editCourseId').value  = c.dataset.cid;
            document.getElementById('editTeacherId').value = c.dataset.tid;
        }
    });

    openModal('editModal');
}

// ── Submit ADD ────────────────────────────────────────────────
async function submitAdd() {
    const courseId  = document.getElementById('addCourseId').value;
    const teacherId = document.getElementById('addTeacherId').value;
    const day       = document.getElementById('addDay').value;
    const sn        = parseInt(document.getElementById('addSession').value);
    const room      = document.getElementById('addRoom').value.trim();

    if (!courseId) { toast('Please select a course first.','danger'); return; }

    // Final conflict guard
    const conflict = checkConflict(day, sn, teacherId);
    if (conflict) {
        toast(`Conflict! ${conflict.course_name} is already scheduled at this time.`,'danger');
        return;
    }

    const btn=document.getElementById('addBtn');
    btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Saving...';btn.disabled=true;

    const d=await ajax('add_program',{
        section_id:SECTION_ID, course_id:courseId, teacher_id:teacherId,
        day_of_week:day, start_time:SESSIONS[sn].start, end_time:SESSIONS[sn].end, room
    });

    btn.innerHTML='<i class="bi bi-check-lg me-1"></i>Assign'; btn.disabled=false;

    if (d.success) { closeModal('addModal'); toast('Course assigned!'); setTimeout(()=>location.reload(),600); }
    else toast(d.error||'Error saving.','danger');
}

// ── Submit EDIT ───────────────────────────────────────────────
async function submitEdit() {
    const id        = document.getElementById('editProgId').value;
    const courseId  = document.getElementById('editCourseId').value;
    const teacherId = document.getElementById('editTeacherId').value;
    const day       = document.getElementById('editDay').value;
    const sn        = parseInt(document.getElementById('editSession').value);
    const room      = document.getElementById('editRoom').value.trim();

    if (!courseId) { toast('Please select a course.','danger'); return; }

    const conflict = checkConflict(day, sn, teacherId, parseInt(id));
    if (conflict) {
        toast(`Conflict! ${conflict.course_name} is already scheduled at this time.`,'danger');
        return;
    }

    const btn=document.getElementById('editBtn');
    btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Saving...';btn.disabled=true;

    const d=await ajax('edit_program',{
        id, course_id:courseId, teacher_id:teacherId,
        day_of_week:day, start_time:SESSIONS[sn].start, end_time:SESSIONS[sn].end, room
    });

    btn.innerHTML='<i class="bi bi-save me-1"></i>Save'; btn.disabled=false;

    if (d.success) { closeModal('editModal'); toast('Updated!'); setTimeout(()=>location.reload(),600); }
    else toast(d.error||'Error saving.','danger');
}

// ── Delete ────────────────────────────────────────────────────
async function deleteProgram(id) {
    if (!confirm('Remove this class from the schedule?')) return;
    closeModal('editModal');
    const d=await ajax('delete_program',{id});
    if (d.success) { toast('Removed.','warning'); setTimeout(()=>location.reload(),600); }
    else toast(d.error||'Error deleting.','danger');
}
</script>
</body></html>
