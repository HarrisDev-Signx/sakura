<?php
/**
 * notif_stream.php — Realtime Notification Endpoint
 * Sakura App (PHP 7.2 Compatible)
 */
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['status' => 'unauthorized', 'data' => []]);
    exit;
}

$db     = getDB();
$userId = (int)$user['id'];
$role   = $user['role'];

$notifications = array();

if ($role === 'user') {
    // 1. Cek PENGUMUMAN BARU yang belum dibaca
    try {
        $stmtAnn = $db->prepare("
            SELECT a.id, a.message, a.created_at
            FROM announcements a
            LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.user_id = ?
            WHERE a.is_active = 1 AND ar.id IS NULL
            ORDER BY a.created_at DESC
            LIMIT 5
        ");
        $stmtAnn->execute(array($userId));
        $anns = $stmtAnn->fetchAll();

        foreach ($anns as $an) {
            $notifications[] = array(
                'id'      => 'ann_' . $an['id'],
                'type'    => 'announcement',
                'ann_id'  => $an['id'],
                'title'   => 'Pengumuman Baru 📢',
                'message' => $an['message'],
                'link'    => 'beranda.php',
                'time'    => date('H:i', strtotime($an['created_at']))
            );
        }
    } catch (\Exception $e) {}

    // 2. Cek TUGAS BARU dalam 24 jam terakhir
    try {
        $stmtTask = $db->prepare("
            SELECT t.id, t.judul, t.created_at
            FROM tugas t
            LEFT JOIN tugas_targets tg ON tg.tugas_id = t.id
            LEFT JOIN tugas_submissions s ON s.tugas_id = t.id AND s.user_id = ?
            WHERE t.status = 'published'
              AND s.id IS NULL
              AND (t.target_type = 'semua' OR tg.user_id = ?)
              AND t.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            GROUP BY t.id
            ORDER BY t.created_at DESC
            LIMIT 5
        ");
        $stmtTask->execute(array($userId, $userId));
        $newTasks = $stmtTask->fetchAll();

        foreach ($newTasks as $t) {
            $notifications[] = array(
                'id'      => 'task_' . $t['id'],
                'type'    => 'task',
                'title'   => 'Tugas Baru 📝',
                'message' => $t['judul'],
                'link'    => 'tugas_detail.php?id=' . $t['id'],
                'time'    => date('H:i', strtotime($t['created_at']))
            );
        }
    } catch (\Exception $e) {}

    // 3. Cek NILAI KELUAR dalam 24 jam terakhir
    try {
        $stmtGraded = $db->prepare("
            SELECT ts.tugas_id, ts.nilai, ts.graded_at, t.judul
            FROM tugas_submissions ts
            JOIN tugas t ON t.id = ts.tugas_id
            WHERE ts.user_id = ? AND ts.nilai IS NOT NULL
              AND ts.graded_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ORDER BY ts.graded_at DESC
            LIMIT 5
        ");
        $stmtGraded->execute(array($userId));
        $graded = $stmtGraded->fetchAll();

        foreach ($graded as $g) {
            $notifications[] = array(
                'id'      => 'grade_' . $g['tugas_id'],
                'type'    => 'grade',
                'title'   => 'Nilai Keluar ⭐',
                'message' => 'Tugas "' . $g['judul'] . '" dapat nilai ' . $g['nilai'],
                'link'    => 'tugas_detail.php?id=' . $g['tugas_id'],
                'time'    => date('H:i', strtotime($g['graded_at']))
            );
        }
    } catch (\Exception $e) {}

} elseif ($role === 'admin') {
    // Cek Pengumpulan Tugas Baru oleh siswa
    try {
        $stmtSub = $db->prepare("
            SELECT ts.tugas_id, ts.submitted_at, u.name AS student_name, t.judul
            FROM tugas_submissions ts
            JOIN users u ON u.id = ts.user_id
            JOIN tugas t ON t.id = ts.tugas_id
            WHERE ts.submitted_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            ORDER BY ts.submitted_at DESC
            LIMIT 5
        ");
        $stmtSub->execute();
        $subs = $stmtSub->fetchAll();

        foreach ($subs as $s) {
            $notifications[] = array(
                'id'      => 'sub_' . $s['tugas_id'] . '_' . strtotime($s['submitted_at']),
                'type'    => 'submission',
                'title'   => 'Pengumpulan Baru 📥',
                'message' => $s['student_name'] . ' mengumpulkan "' . $s['judul'] . '"',
                'link'    => 'tugas_hasil.php?tugas_id=' . $s['tugas_id'],
                'time'    => date('H:i', strtotime($s['submitted_at']))
            );
        }
    } catch (\Exception $e) {}
}

echo json_encode(array(
    'status' => 'ok',
    'count'  => count($notifications),
    'data'   => $notifications
));
exit;