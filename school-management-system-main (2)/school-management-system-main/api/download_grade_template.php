<?php
require_once '../includes/auth.php';
requireLogin(['coordinator']);

$db = getDB();
$current_user = getCurrentUser();

$section_id = (int)($_GET['section'] ?? 0);
$course_id  = (int)($_GET['course']  ?? 0);

if (!$section_id || !$course_id) die('Missing parameters.');

$chk = $db->prepare("SELECT id FROM sections WHERE id=? AND coordinator_id=?");
$chk->execute([$section_id, $current_user['id']]);
if (!$chk->fetch()) die('Access denied.');

$sec = $db->prepare("SELECT s.name as sec_name, cl.name as class_name FROM sections s JOIN classes cl ON cl.id=s.class_id WHERE s.id=?");
$sec->execute([$section_id]);
$sec = $sec->fetch();

$crs = $db->prepare("SELECT name FROM courses WHERE id=?");
$crs->execute([$course_id]);
$crs = $crs->fetch();

$students = $db->prepare("
    SELECT u.user_id_number, u.first_name, u.last_name
    FROM student_sections ss JOIN users u ON u.id=ss.student_id
    WHERE ss.section_id=? AND u.is_active=1
    ORDER BY u.last_name, u.first_name
");
$students->execute([$section_id]);
$students = $students->fetchAll();

$filename = 'grades_'.preg_replace('/[^a-z0-9]/i','_',$crs['name']).'_'.date('Y-m-d').'.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: no-cache');

$out = fopen('php://output','w');
fputs($out, "\xEF\xBB\xBF"); // BOM for Excel

fputcsv($out, ['OMK School - Grade Sheet']);
fputcsv($out, ['Section:', $sec['class_name'].' - Section '.$sec['sec_name'], 'Course:', $crs['name']]);
fputcsv($out, ['META:section_id='.$section_id.'|course_id='.$course_id]);
fputcsv($out, []);
fputcsv($out, ['INSTRUCTIONS: Fill columns C-H only. Do NOT change Student ID or Full Name.']);
fputcsv($out, []);
fputcsv($out, ['Student ID','Full Name','Assessment Name','Assessment Type','Score','Max Score','Date (YYYY-MM-DD)','Notes']);

foreach ($students as $st) {
    fputcsv($out, [
        $st['user_id_number'],
        $st['first_name'].' '.$st['last_name'],
        '',
        'exam',
        '',
        '100',
        date('Y-m-d'),
        '',
    ]);
}

fclose($out);
exit;
