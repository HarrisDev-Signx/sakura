<?php
/**
 * api_import_soal.php — Import soal ujian dari file Excel/CSV
 *
 * File Excel/CSV di-parse di BROWSER (SheetJS), lalu hasil parse dikirim
 * ke sini sebagai JSON (field "rows"). Pendekatan ini dipakai karena
 * shared hosting (InfinityFree) tidak menyediakan library PHP untuk baca
 * file Excel secara native, sedangkan parsing di browser sudah tersedia
 * lewat SheetJS yang di-load di js/ujian_import.js.
 *
 * Format kolom per baris (sesuai template dari download_template.php):
 * [No, Pertanyaan, Pilihan A, B, C, D, E, F, Jawaban Benar]
 */
require_once 'config.php';
require_once 'exam_helper.php';

header('Content-Type: application/json');
requireLogin();

function jres($data) { echo json_encode($data); exit; }

$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
if (!$isAdmin) {
    jres(['success' => false, 'message' => 'Akses ditolak.']);
}

$action = $_POST['action'] ?? '';
if ($action !== 'import_questions') {
    jres(['success' => false, 'message' => 'Aksi tidak dikenali.']);
}

$db = getDB();
$examId   = (int)($_POST['exam_id'] ?? 0);
$rowsJson = $_POST['rows'] ?? '';

if (!$examId) {
    jres(['success' => false, 'message' => 'Ujian tidak valid.']);
}

// Pastikan ujian memang ada sebelum lanjut
$examCheck = $db->prepare("SELECT id FROM exams WHERE id = ?");
$examCheck->execute([$examId]);
if (!$examCheck->fetch()) {
    jres(['success' => false, 'message' => 'Ujian tidak ditemukan.']);
}

$rows = json_decode($rowsJson, true);
if (!is_array($rows) || count($rows) === 0) {
    jres(['success' => false, 'message' => 'Tidak ada data soal untuk diimpor.']);
}

// Lanjutkan nomor urut soal dari yang terakhir (bukan menimpa soal lama)
$stmtOrder = $db->prepare("SELECT COALESCE(MAX(question_order), 0) FROM exam_questions WHERE exam_id=?");
$stmtOrder->execute([$examId]);
$order = (int)$stmtOrder->fetchColumn();

$validAnswers = ['a', 'b', 'c', 'd', 'e', 'f'];
$imported = 0;
$errors   = [];

$insertStmt = $db->prepare("INSERT INTO exam_questions
    (exam_id, question_text, question_image, option_a, option_b, option_c, option_d, option_e, option_f, correct_option, question_order)
    VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($rows as $i => $row) {
    // Baris ke-1 di file adalah header, jadi baris data pertama = baris ke-2
    $rowNum = $i + 2;

    $text = isset($row[1]) ? sanitize((string)$row[1]) : '';
    $a    = isset($row[2]) ? sanitize((string)$row[2]) : '';
    $b    = isset($row[3]) ? sanitize((string)$row[3]) : '';
    $c    = isset($row[4]) ? sanitize((string)$row[4]) : '';
    $d    = isset($row[5]) ? sanitize((string)$row[5]) : '';
    $e    = isset($row[6]) ? sanitize((string)$row[6]) : '';
    $f    = isset($row[7]) ? sanitize((string)$row[7]) : '';
    $ans  = isset($row[8]) ? strtolower(trim((string)$row[8])) : '';

    // Lewati baris yang benar-benar kosong (biasanya baris kosong di akhir file)
    if ($text === '' && $a === '' && $b === '') continue;

    if ($text === '' || $a === '' || $b === '' || $c === '' || $d === '') {
        $errors[] = "Baris $rowNum: pertanyaan dan pilihan A-D wajib diisi.";
        continue;
    }
    if (!in_array($ans, $validAnswers, true)) {
        $errors[] = "Baris $rowNum: jawaban benar harus salah satu dari a/b/c/d/e/f.";
        continue;
    }
    if (($ans === 'e' && $e === '') || ($ans === 'f' && $f === '')) {
        $errors[] = "Baris $rowNum: jawaban benar menunjuk ke pilihan E/F yang kosong.";
        continue;
    }

    $order++;
    $insertStmt->execute([$examId, $text, $a, $b, $c, $d, $e ?: null, $f ?: null, $ans, $order]);
    $imported++;
}

if ($imported === 0) {
    jres(['success' => false, 'message' => 'Tidak ada soal valid yang berhasil diimpor.', 'errors' => $errors]);
}

jres([
    'success' => true,
    'message' => "$imported soal berhasil diimpor.",
    'skipped' => count($errors),
    'errors'  => $errors,
]);
