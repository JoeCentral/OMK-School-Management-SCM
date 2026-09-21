<?php
require_once '../includes/auth.php';
requireLogin(['coordinator']);
header('Content-Type: application/json');

$db = getDB();
$current_user = getCurrentUser();

function jsonOk($d=[]) { echo json_encode(['success'=>true]+$d); exit; }
function jsonErr($m)   { echo json_encode(['success'=>false,'error'=>$m]); exit; }

if ($_SERVER['REQUEST_METHOD']!=='POST') jsonErr('POST required.');
if (empty($_FILES['grade_file']))        jsonErr('No file uploaded.');

$file = $_FILES['grade_file'];
if ($file['error'] !== UPLOAD_ERR_OK)   jsonErr('Upload error: '.$file['error']);

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') jsonErr('Only .csv files accepted. Save your Excel file as CSV first.');

$handle = fopen($file['tmp_name'], 'r');
if (!$handle) jsonErr('Cannot read uploaded file.');

// Strip BOM
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") rewind($handle);

$section_id   = 0;
$course_id    = 0;
$rows         = [];
$errors       = [];
$line_num     = 0;
$header_found = false;
$valid_types  = ['quiz','exam','assignment','project','midterm','final','participation'];

while (($row = fgetcsv($handle)) !== false) {
    $line_num++;
    if (empty(array_filter($row, fn($v) => trim($v) !== ''))) continue;

    // Meta line — format: META:section_id=5|course_id=3
    if (isset($row[0]) && strpos($row[0], 'META:') === 0) {
        preg_match('/section_id=(\d+)\|course_id=(\d+)/', $row[0], $m);
        if ($m) { $section_id = (int)$m[1]; $course_id = (int)$m[2]; }
        continue;
    }

    // Header row detection
    if (!$header_found) {
        if (isset($row[0]) && strtolower(trim($row[0])) === 'student id') {
            $header_found = true;
        }
        continue;
    }

    // Data rows
    if (count($row) < 5) continue;

    $uid       = trim($row[0] ?? '');
    $name      = trim($row[1] ?? '');
    $ass_name  = trim($row[2] ?? '');
    $ass_type  = strtolower(trim($row[3] ?? 'exam'));
    $score_raw = trim($row[4] ?? '');
    $max_raw   = trim($row[5] ?? '100');
    $date_raw  = trim($row[6] ?? '');
    $notes     = trim($row[7] ?? '');

    if ($ass_name === '' || $score_raw === '') continue;
    if ($uid === '') { $errors[] = "Line $line_num: Missing Student ID."; continue; }
    if (!is_numeric($score_raw)) { $errors[] = "Line $line_num ($name): Invalid score '$score_raw'."; continue; }

    $score = (float)$score_raw;
    $max   = is_numeric($max_raw) && (float)$max_raw > 0 ? (float)$max_raw : 100.0;

    if ($score < 0)    { $errors[] = "Line $line_num ($name): Score cannot be negative."; continue; }
    if ($score > $max) { $errors[] = "Line $line_num ($name): Score $score exceeds max $max."; continue; }
    if (!in_array($ass_type, $valid_types)) $ass_type = 'exam';

    $date = date('Y-m-d');
    if ($date_raw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_raw)) {
        $date = $date_raw;
    }

    $rows[] = compact('uid','name','ass_name','ass_type','score','max','date','notes');
}
fclose($handle);

if (!$section_id || !$course_id) {
    jsonErr('Cannot read section/course info from file. Please download a fresh template.');
}
if (empty($rows)) {
    jsonErr('No valid data found. Fill Assessment Name (col C) and Score (col E) for at least one student.');
}

// Verify coordinator owns section
$chk = $db->prepare("SELECT id FROM sections WHERE id=? AND coordinator_id=?");
$chk->execute([$section_id, $current_user['id']]);
if (!$chk->fetch()) jsonErr('Access denied — section does not belong to you.');

// Pre-load enrolled students: user_id_number => db id
$enr = $db->prepare("
    SELECT u.id, u.user_id_number
    FROM student_sections ss JOIN users u ON u.id=ss.student_id
    WHERE ss.section_id=? AND u.is_active=1
");
$enr->execute([$section_id]);
$enrolled = [];
foreach ($enr->fetchAll() as $e) $enrolled[trim($e['user_id_number'])] = (int)$e['id'];

$inserted = $updated = $skipped = 0;

foreach ($rows as $row) {
    $uid = trim($row['uid']);

    // Match student (exact first, then case-insensitive)
    $student_db_id = $enrolled[$uid] ?? null;
    if (!$student_db_id) {
        foreach ($enrolled as $eid => $sid) {
            if (strtolower($eid) === strtolower($uid)) { $student_db_id = $sid; break; }
        }
    }
    if (!$student_db_id) {
        $errors[] = "Student ID '$uid' ({$row['name']}) not found in this section — skipped.";
        $skipped++;
        continue;
    }

    // Upsert
    $ex = $db->prepare("SELECT id FROM grades WHERE student_id=? AND course_id=? AND section_id=? AND assessment_name=?");
    $ex->execute([$student_db_id, $course_id, $section_id, $row['ass_name']]);
    $existing = $ex->fetch();

    if ($existing) {
        $db->prepare("UPDATE grades SET score=?,max_score=?,assessment_type=?,grade_date=?,notes=?,recorded_by=? WHERE id=?")
           ->execute([$row['score'],$row['max'],$row['ass_type'],$row['date'],$row['notes'],$current_user['id'],$existing['id']]);
        $updated++;
    } else {
        $db->prepare("INSERT INTO grades (student_id,course_id,section_id,assessment_type,assessment_name,score,max_score,grade_date,notes,recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([$student_db_id,$course_id,$section_id,$row['ass_type'],$row['ass_name'],$row['score'],$row['max'],$row['date'],$row['notes'],$current_user['id']]);
        $inserted++;
    }
}

jsonOk([
    'inserted'   => $inserted,
    'updated'    => $updated,
    'skipped'    => $skipped,
    'errors'     => $errors,
    'section_id' => $section_id,
    'course_id'  => $course_id,
]);
