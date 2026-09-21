<?php
require_once '../includes/auth.php';
requireLogin(['admin']);
header('Content-Type: application/json');

$db  = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function jsonOk($data = []) { echo json_encode(['success' => true] + $data); exit; }
function jsonErr($msg)       { http_response_code(400); echo json_encode(['success' => false, 'error' => $msg]); exit; }

// ─── AUTO-GENERATE SYSTEM ID ───────────────────────────────────────────────
function generateSystemId($role, $db) {
    $prefix = ['student'=>'STU','teacher'=>'TCH','coordinator'=>'CRD','admin'=>'ADM'][$role] ?? 'USR';
    $year   = date('Y');
    $stmt   = $db->prepare("SELECT COUNT(*) FROM users WHERE role=? AND YEAR(created_at)=?");
    $stmt->execute([$role, $year]);
    $count  = (int)$stmt->fetchColumn() + 1;
    return $prefix . $year . str_pad($count, 4, '0', STR_PAD_LEFT);
}

// ─── GET NEXT SYSTEM ID ────────────────────────────────────────────────────
if ($action === 'get_next_id') {
    $role = $_GET['role'] ?? 'student';
    jsonOk(['id' => generateSystemId($role, $db)]);
}

// ─── ADD USER ──────────────────────────────────────────────────────────────
if ($action === 'add_user') {
    $role   = sanitize($_POST['role'] ?? '');
    $first  = sanitize($_POST['first_name'] ?? '');
    $last   = sanitize($_POST['last_name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $pass   = $_POST['password'] ?? '';
    $gender = $_POST['gender'] ?? 'male';
    $phone  = sanitize($_POST['phone'] ?? '');
    $dob    = $_POST['date_of_birth'] ?? '';
    $section_id = (int)($_POST['section_id'] ?? 0);

    if (!in_array($role, ['student','teacher','coordinator'])) jsonErr('Invalid role.');
    if (!$first || !$last)  jsonErr('First and last name are required.');
    if (!$email || !$pass)  jsonErr('Email and password are required.');

    // Check unique email
    $chk = $db->prepare("SELECT id FROM users WHERE email=?");
    $chk->execute([$email]);
    if ($chk->fetch()) jsonErr('This email is already registered.');

    // Auto-generate system ID
    $uid = generateSystemId($role, $db);

    $hashed = hashPassword($pass);
    $stmt = $db->prepare("INSERT INTO users (first_name,last_name,email,password,role,user_id_number,phone,gender,date_of_birth,must_change_password) VALUES (?,?,?,?,?,?,?,?,?,1)");
    $stmt->execute([$first,$last,$email,$hashed,$role,$uid,$phone,$gender,$dob?:null]);
    $newId = $db->lastInsertId();

    // Enroll student in section
    if ($role === 'student' && $section_id) {
        $yr = $db->query("SELECT id FROM academic_years WHERE is_current=1")->fetchColumn();
        $db->prepare("INSERT INTO student_sections (student_id,section_id,academic_year_id) VALUES (?,?,?)")->execute([$newId,$section_id,$yr]);
    }

    addNotification($newId, 'Welcome to OMK School!', 'Your account has been created. Please update your password.', 'success');
    jsonOk(['user_id' => $newId, 'system_id' => $uid, 'name' => "$first $last", 'email' => $email]);
}

// ─── EDIT USER ─────────────────────────────────────────────────────────────
if ($action === 'edit_user') {
    $id     = (int)($_POST['id'] ?? 0);
    $first  = sanitize($_POST['first_name'] ?? '');
    $last   = sanitize($_POST['last_name'] ?? '');
    $phone  = sanitize($_POST['phone'] ?? '');
    $gender = $_POST['gender'] ?? 'male';
    $dob    = $_POST['date_of_birth'] ?? '';
    $active = (int)($_POST['is_active'] ?? 1);

    if (!$id || !$first || !$last) jsonErr('ID, first and last name are required.');

    $db->prepare("UPDATE users SET first_name=?,last_name=?,phone=?,gender=?,date_of_birth=?,is_active=? WHERE id=?")
       ->execute([$first,$last,$phone,$gender,$dob?:null,$active,$id]);

    // Update section if student
    if (isset($_POST['section_id'])) {
        $new_sec = (int)$_POST['section_id'];
        $yr = $db->query("SELECT id FROM academic_years WHERE is_current=1")->fetchColumn();
        $db->prepare("DELETE FROM student_sections WHERE student_id=?")->execute([$id]);
        if ($new_sec) {
            $db->prepare("INSERT INTO student_sections (student_id,section_id,academic_year_id) VALUES (?,?,?)")->execute([$id,$new_sec,$yr]);
        }
    }
    jsonOk(['message' => 'User updated successfully.']);
}

// ─── RESET PASSWORD ────────────────────────────────────────────────────────
if ($action === 'reset_password') {
    $id   = (int)($_POST['id'] ?? 0);
    $pass = $_POST['password'] ?? '';
    if (!$id || strlen($pass) < 6) jsonErr('Invalid ID or password too short.');
    $db->prepare("UPDATE users SET password=?,must_change_password=1 WHERE id=?")->execute([hashPassword($pass),$id]);
    jsonOk(['message' => 'Password reset successfully.']);
}

// ─── DELETE/DEACTIVATE USER ────────────────────────────────────────────────
if ($action === 'toggle_user') {
    $id     = (int)($_POST['id'] ?? 0);
    $status = (int)($_POST['status'] ?? 0);
    $db->prepare("UPDATE users SET is_active=? WHERE id=?")->execute([$status,$id]);
    jsonOk(['message' => $status ? 'User activated.' : 'User deactivated.']);
}

// ─── GET USER (for edit modal) ─────────────────────────────────────────────
if ($action === 'get_user') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT id,first_name,last_name,email,role,user_id_number,phone,gender,date_of_birth,is_active FROM users WHERE id=?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) jsonErr('User not found.');
    // Get section if student
    $sec = $db->prepare("SELECT section_id FROM student_sections WHERE student_id=? LIMIT 1");
    $sec->execute([$id]);
    $user['section_id'] = $sec->fetchColumn() ?: 0;
    jsonOk(['user' => $user]);
}

// ─── ADD CLASS ─────────────────────────────────────────────────────────────
if ($action === 'add_class') {
    $name  = sanitize($_POST['class_name'] ?? '');
    $grade = (int)($_POST['grade_level'] ?? 0);
    $yr    = $db->query("SELECT id FROM academic_years WHERE is_current=1")->fetchColumn();
    if (!$name || !$grade) jsonErr('Class name and grade level are required.');
    $db->prepare("INSERT INTO classes (name,grade_level,academic_year_id) VALUES (?,?,?)")->execute([$name,$grade,$yr]);
    $id = $db->lastInsertId();
    jsonOk(['id' => $id, 'name' => $name, 'grade' => $grade]);
}

// ─── EDIT CLASS ────────────────────────────────────────────────────────────
if ($action === 'edit_class') {
    $id    = (int)($_POST['id'] ?? 0);
    $name  = sanitize($_POST['class_name'] ?? '');
    $grade = (int)($_POST['grade_level'] ?? 0);
    if (!$id || !$name || !$grade) jsonErr('All fields required.');
    $db->prepare("UPDATE classes SET name=?,grade_level=? WHERE id=?")->execute([$name,$grade,$id]);
    jsonOk(['message' => 'Class updated.']);
}

// ─── DELETE CLASS ──────────────────────────────────────────────────────────
if ($action === 'delete_class') {
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare("DELETE FROM classes WHERE id=?")->execute([$id]);
    jsonOk(['message' => 'Class deleted.']);
}

// ─── ADD SECTION ───────────────────────────────────────────────────────────
if ($action === 'add_section') {
    $cid   = (int)($_POST['class_id'] ?? 0);
    $name  = sanitize($_POST['section_name'] ?? '');
    $max   = (int)($_POST['max_students'] ?? 30);
    $coord = (int)($_POST['coordinator_id'] ?? 0);
    if (!$cid || !$name) jsonErr('Class and section name required.');
    $db->prepare("INSERT INTO sections (class_id,name,max_students,coordinator_id) VALUES (?,?,?,?)")->execute([$cid,$name,$max,$coord?:null]);
    $id = $db->lastInsertId();
    // Get class name
    $cl = $db->prepare("SELECT name FROM classes WHERE id=?"); $cl->execute([$cid]); $cl = $cl->fetch();
    jsonOk(['id' => $id, 'name' => $name, 'class_name' => $cl['name'], 'max' => $max]);
}

// ─── EDIT SECTION ──────────────────────────────────────────────────────────
if ($action === 'edit_section') {
    $id    = (int)($_POST['id'] ?? 0);
    $name  = sanitize($_POST['section_name'] ?? '');
    $max   = (int)($_POST['max_students'] ?? 30);
    $coord = (int)($_POST['coordinator_id'] ?? 0);
    if (!$id || !$name) jsonErr('Section ID and name required.');
    $db->prepare("UPDATE sections SET name=?,max_students=?,coordinator_id=? WHERE id=?")->execute([$name,$max,$coord?:null,$id]);
    jsonOk(['message' => 'Section updated.']);
}

// ─── DELETE SECTION ────────────────────────────────────────────────────────
if ($action === 'delete_section') {
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare("DELETE FROM sections WHERE id=?")->execute([$id]);
    jsonOk(['message' => 'Section deleted.']);
}

// ─── ADD COURSE ────────────────────────────────────────────────────────────
if ($action === 'add_course') {
    $name  = sanitize($_POST['name'] ?? '');
    $code  = sanitize($_POST['code'] ?? '');
    $desc  = sanitize($_POST['description'] ?? '');
    $color = $_POST['color'] ?? '#4f46e5';
    if (!$name) jsonErr('Course name is required.');
    // Auto-generate code if empty
    if (!$code) {
        $words = preg_split('/\s+/', strtoupper($name));
        $code  = '';
        foreach ($words as $w) $code .= substr($w, 0, 3);
        $code = substr($code, 0, 6);
        $exists = $db->prepare("SELECT id FROM courses WHERE code LIKE ?");
        $exists->execute([$code.'%']);
        if ($exists->fetch()) $code .= rand(10,99);
    }
    try {
        $db->prepare("INSERT INTO courses (name,code,description,color,credits) VALUES (?,?,?,?,1)")->execute([$name,$code,$desc,$color]);
        $id = $db->lastInsertId();
        jsonOk(['id'=>$id,'name'=>$name,'code'=>$code,'color'=>$color,'description'=>$desc]);
    } catch (Exception $e) { jsonErr('Course code already exists. Try a different name.'); }
}

// ─── EDIT COURSE ───────────────────────────────────────────────────────────
if ($action === 'edit_course') {
    $id    = (int)($_POST['id'] ?? 0);
    $name  = sanitize($_POST['name'] ?? '');
    $code  = sanitize($_POST['code'] ?? '');
    $desc  = sanitize($_POST['description'] ?? '');
    $color = $_POST['color'] ?? '#4f46e5';
    if (!$id || !$name || !$code) jsonErr('ID, name and code required.');
    $db->prepare("UPDATE courses SET name=?,code=?,description=?,color=? WHERE id=?")->execute([$name,$code,$desc,$color,$id]);
    jsonOk(['message'=>'Course updated.']);
}

// ─── DELETE COURSE ─────────────────────────────────────────────────────────
if ($action === 'delete_course') {
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare("DELETE FROM courses WHERE id=?")->execute([$id]);
    jsonOk(['message' => 'Course deleted.']);
}

// ─── ASSIGN TEACHER ────────────────────────────────────────────────────────
if ($action === 'assign_teacher') {
    $tid = (int)($_POST['teacher_id'] ?? 0);
    $cid = (int)($_POST['course_id']  ?? 0);
    $sid = (int)($_POST['section_id'] ?? 0);
    $yr  = $db->query("SELECT id FROM academic_years WHERE is_current=1")->fetchColumn();
    if (!$tid || !$cid || !$sid) jsonErr('Teacher, course and section are required.');
    try {
        $db->prepare("INSERT INTO teacher_courses (teacher_id,course_id,section_id,academic_year_id) VALUES (?,?,?,?)")->execute([$tid,$cid,$sid,$yr]);
        $aId = $db->lastInsertId();
        // Get names for response
        $t = $db->prepare("SELECT first_name,last_name FROM users WHERE id=?"); $t->execute([$tid]); $t = $t->fetch();
        $c = $db->prepare("SELECT name,color FROM courses WHERE id=?"); $c->execute([$cid]); $c = $c->fetch();
        $s = $db->prepare("SELECT s.name as sec,cl.name as class FROM sections s JOIN classes cl ON cl.id=s.class_id WHERE s.id=?"); $s->execute([$sid]); $s = $s->fetch();
        jsonOk(['id'=>$aId,'teacher'=>$t['first_name'].' '.$t['last_name'],'course'=>$c['name'],'color'=>$c['color'],'section'=>$s['class'].'-'.$s['sec']]);
    } catch (Exception $e) { jsonErr('This assignment already exists.'); }
}

// ─── REMOVE ASSIGNMENT ─────────────────────────────────────────────────────
if ($action === 'remove_assignment') {
    $id = (int)($_POST['id'] ?? 0);
    $db->prepare("DELETE FROM teacher_courses WHERE id=?")->execute([$id]);
    jsonOk(['message' => 'Assignment removed.']);
}

jsonErr('Unknown action.');
