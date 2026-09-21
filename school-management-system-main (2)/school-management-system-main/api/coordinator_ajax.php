<?php
require_once '../includes/auth.php';
requireLogin(['coordinator']);
header('Content-Type: application/json');

$db = getDB();
$current_user = getCurrentUser();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function jsonOk($data=[]) { echo json_encode(['success'=>true]+$data); exit; }
function jsonErr($msg)     { http_response_code(400); echo json_encode(['success'=>false,'error'=>$msg]); exit; }

// Verify coordinator owns this section
function verifySection($section_id, $coordinator_id, $db) {
    $stmt = $db->prepare("SELECT id FROM sections WHERE id=? AND coordinator_id=?");
    $stmt->execute([$section_id, $coordinator_id]);
    return $stmt->fetch() !== false;
}

// ── ADD PROGRAM ────────────────────────────────────────────────────────────
if ($action === 'add_program') {
    $section_id  = (int)($_POST['section_id']  ?? 0);
    $course_id   = (int)($_POST['course_id']   ?? 0);
    $teacher_id  = (int)($_POST['teacher_id']  ?? 0);
    $day         = $_POST['day_of_week'] ?? '';
    $start       = $_POST['start_time']  ?? '';
    $end         = $_POST['end_time']    ?? '';
    $room        = sanitize($_POST['room'] ?? '');
    $yr          = $db->query("SELECT id FROM academic_years WHERE is_current=1")->fetchColumn();

    if (!verifySection($section_id, $current_user['id'], $db)) jsonErr('Access denied.');
    if (!$course_id || !$teacher_id || !$day || !$start || !$end) jsonErr('All fields required.');

    $db->prepare("INSERT INTO programs (section_id,course_id,teacher_id,day_of_week,start_time,end_time,room,academic_year_id) VALUES (?,?,?,?,?,?,?,?)")
       ->execute([$section_id,$course_id,$teacher_id,$day,$start,$end,$room,$yr]);
    jsonOk(['id' => $db->lastInsertId()]);
}

// ── EDIT PROGRAM ───────────────────────────────────────────────────────────
if ($action === 'edit_program') {
    $id         = (int)($_POST['id']          ?? 0);
    $course_id  = (int)($_POST['course_id']   ?? 0);
    $teacher_id = (int)($_POST['teacher_id']  ?? 0);
    $day        = $_POST['day_of_week'] ?? '';
    $start      = $_POST['start_time']  ?? '';
    $end        = $_POST['end_time']    ?? '';
    $room       = sanitize($_POST['room'] ?? '');
    if (!$id) jsonErr('Invalid program ID.');
    $db->prepare("UPDATE programs SET course_id=?,teacher_id=?,day_of_week=?,start_time=?,end_time=?,room=? WHERE id=?")
       ->execute([$course_id,$teacher_id,$day,$start,$end,$room,$id]);
    jsonOk(['message'=>'Updated.']);
}

// ── DELETE PROGRAM ─────────────────────────────────────────────────────────
if ($action === 'delete_program') {
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare("DELETE FROM programs WHERE id=?")->execute([$id]);
    jsonOk(['message'=>'Deleted.']);
}

// ── ADD GRADE ──────────────────────────────────────────────────────────────
if ($action === 'add_grade') {
    $student_id  = (int)($_POST['student_id']  ?? 0);
    $course_id   = (int)($_POST['course_id']   ?? 0);
    $section_id  = (int)($_POST['section_id']  ?? 0);
    $ass_name    = sanitize($_POST['assessment_name'] ?? '');
    $ass_type    = $_POST['assessment_type'] ?? 'exam';
    $score       = (float)($_POST['score']     ?? 0);
    $max_score   = (float)($_POST['max_score'] ?? 100);
    $grade_date  = $_POST['grade_date'] ?? date('Y-m-d');
    $notes       = sanitize($_POST['notes'] ?? '');

    if (!$student_id || !$course_id || !$section_id || !$ass_name) jsonErr('Required fields missing.');
    if (!verifySection($section_id, $current_user['id'], $db)) jsonErr('Access denied.');

    // Check if grade already exists for this student/course/assessment_name — update if so
    $existing = $db->prepare("SELECT id FROM grades WHERE student_id=? AND course_id=? AND section_id=? AND assessment_name=?");
    $existing->execute([$student_id,$course_id,$section_id,$ass_name]);
    if ($ex = $existing->fetch()) {
        $db->prepare("UPDATE grades SET score=?,max_score=?,assessment_type=?,grade_date=?,notes=?,recorded_by=? WHERE id=?")
           ->execute([$score,$max_score,$ass_type,$grade_date,$notes,$current_user['id'],$ex['id']]);
    } else {
        $db->prepare("INSERT INTO grades (student_id,course_id,section_id,assessment_type,assessment_name,score,max_score,grade_date,notes,recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([$student_id,$course_id,$section_id,$ass_type,$ass_name,$score,$max_score,$grade_date,$notes,$current_user['id']]);
    }
    jsonOk(['message'=>'Grade saved.']);
}

// ── DELETE GRADE ───────────────────────────────────────────────────────────
if ($action === 'delete_grade') {
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare("DELETE FROM grades WHERE id=?")->execute([$id]);
    jsonOk(['message'=>'Deleted.']);
}

jsonErr('Unknown action.');