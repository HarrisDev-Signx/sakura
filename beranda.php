<?php
require_once 'config.php';
requireLogin();

// ── AJAX: jawab pertanyaan onboarding paham hiragana/katakana ──
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'kana_set_paham') {
    $db    = getDB();
    $cuser = getCurrentUser();
    $jenis = $_POST['jenis'] ?? ''; // 'hiragana' | 'katakana'
    $paham = ($_POST['paham'] ?? '1') === '1';
    if (!$cuser || !in_array($jenis, ['hiragana', 'katakana'], true)) {
        echo json_encode(['ok' => false, 'error' => 'Permintaan tidak valid']);
        exit;
    }
    if ($paham) {
        $col   = $jenis . '_status';
        $colTs = $jenis . '_updated_at';
        $stmt = $db->prepare("UPDATE users SET `$col` = 'sudah_paham', `$colTs` = NOW() WHERE id = ?");
        $stmt->execute([$cuser['id']]);
    }
    $stmtSeen = $db->prepare("UPDATE users SET kana_onboarding_asked_at = NOW() WHERE id = ? AND kana_onboarding_asked_at IS NULL");
    $stmtSeen->execute([$cuser['id']]);
    echo json_encode(['ok' => true, 'status' => $paham ? 'sudah_paham' : 'belum_dijawab']);
    exit;
}

// ── AJAX: simpan profil club (admin only) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'save_club_profile') {
    $cuser = getCurrentUser();
    if (($cuser['role'] ?? '') !== 'admin') {
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $type      = in_array($_POST['profile_type'] ?? '', ['ketua','guru']) ? $_POST['profile_type'] : '';
    $name      = trim($_POST['profile_name'] ?? '');
    $photo     = trim($_POST['profile_photo'] ?? '');
    $bgColor   = trim($_POST['profile_bg_color'] ?? '#ffffff');
    if ($type && $name) {
        $clubFile = __DIR__ . '/data/club_profiles.json';
        @mkdir(dirname($clubFile), 0755, true);
        $profiles = [];
        if (file_exists($clubFile)) {
            $profiles = json_decode(file_get_contents($clubFile), true) ?: [];
        }
        $existingPhoto = $profiles[$type]['photo'] ?? '';
        $profiles[$type] = [
            'name'     => $name,
            'photo'    => $photo !== '' ? $photo : $existingPhoto,
            'bg_color' => $bgColor
        ];
        file_put_contents($clubFile, json_encode($profiles, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Nama tidak boleh kosong']);
    }
    exit;
}

// ── AJAX: tandai pengumuman sudah dibaca ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'mark_announcement_read') {
    $db  = getDB();
    $uid = getCurrentUser()['id'] ?? 0;
    $aid = (int)($_POST['ann_id'] ?? 0);
    if ($uid && $aid) {
        try {
            $db->prepare("INSERT IGNORE INTO announcement_reads (announcement_id, user_id) VALUES (?,?)")
               ->execute([$aid, $uid]);
        } catch (\Exception $e) { /* ignore duplicate */ }
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ── AJAX: polling pengumuman baru (user) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_GET['action'])
    && $_GET['action'] === 'poll_announcements') {
    $db    = getDB();
    $cuser = getCurrentUser();
    $uid   = $cuser['id'] ?? 0;
    $isAdminPoll = ($cuser['role'] ?? '') === 'admin';

    try {
        if ($isAdminPoll) {
            $rows = $db->query(
                "SELECT a.*, u.name AS sender_name
                 FROM announcements a
                 JOIN users u ON u.id = a.created_by
                 WHERE a.is_active = 1
                 ORDER BY a.created_at DESC
                 LIMIT 5"
            )->fetchAll();
            echo json_encode(['ok' => true, 'announcements' => $rows], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $db->prepare("
            SELECT a.id, a.message, a.created_at
            FROM announcements a
            LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.user_id = ?
            WHERE a.is_active = 1 AND ar.id IS NULL
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$uid]);
        $rows = $stmt->fetchAll();
        echo json_encode(['ok' => true, 'announcements' => $rows, 'unread' => count($rows)], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (\Exception $e) {
        echo json_encode(['ok' => false, 'announcements' => [], 'unread' => 0]);
        exit;
    }
}
// ── END AJAX ────────────────────────────────────────────────

$user = getCurrentUser();
if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$isAdmin = $user['role'] === 'admin';
$initial = strtoupper(mb_substr($user['name'], 0, 1));

$hiraganaStatus      = 'belum_dijawab';
$katakanaStatus      = 'belum_dijawab';
$kanaOnboardingAsked = null;
if (array_key_exists('hiragana_status', $user) && array_key_exists('katakana_status', $user) && array_key_exists('kana_onboarding_asked_at', $user)) {
    $hiraganaStatus      = $user['hiragana_status'] ?? 'belum_dijawab';
    $katakanaStatus      = $user['katakana_status'] ?? 'belum_dijawab';
    $kanaOnboardingAsked = $user['kana_onboarding_asked_at'] ?? null;
} else {
    $dbKana = getDB();
    $stmtKana = $dbKana->prepare("SELECT hiragana_status, katakana_status, kana_onboarding_asked_at FROM users WHERE id = ?");
    $stmtKana->execute([$user['id']]);
    $rowKana = $stmtKana->fetch();
    if ($rowKana) {
        $hiraganaStatus      = $rowKana['hiragana_status'] ?? 'belum_dijawab';
        $katakanaStatus      = $rowKana['katakana_status'] ?? 'belum_dijawab';
        $kanaOnboardingAsked = $rowKana['kana_onboarding_asked_at'] ?? null;
    }
}

$showKanaOnboarding = !$isAdmin
    && $kanaOnboardingAsked === null
    && ($hiraganaStatus === 'belum_dijawab' || $katakanaStatus === 'belum_dijawab');
$joinDate = date('d F Y', strtotime($user['created_at']));

$totalUsers = 0;
$totalAdmins = 0;
if ($isAdmin) {
    $db = getDB();
    $totalUsers  = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    $totalAdmins = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    $totalAll    = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
}

$pendingExams = 0;
$pendingTugas = 0;
$pendingKotoba = 0;
$pendingCS = 0;
$db = getDB();

if ($isAdmin) {
    try {
        $pendingCS = (int)$db->query("SELECT COUNT(*) FROM customer_service WHERE status = 'pending'")->fetchColumn();
    } catch (\Exception $e) { $pendingCS = 0; }
} else {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM exams e
            LEFT JOIN exam_attempts a ON a.exam_id = e.id AND a.user_id = ?
            WHERE e.status = 'published' AND (a.id IS NULL || a.status != 'finished')
        ");
        $stmt->execute([$user['id']]);
        $pendingExams = (int)$stmt->fetchColumn();

        $stmt2 = $db->prepare("
            SELECT COUNT(*) FROM tugas t
            LEFT JOIN tugas_submissions s ON s.tugas_id = t.id AND s.user_id = ?
            WHERE t.status = 'published' AND s.id IS NULL
        ");
        $stmt2->execute([$user['id']]);
        $pendingTugas = (int)$stmt2->fetchColumn();

        $stmt3 = $db->prepare("
            SELECT COUNT(*) FROM kotoba_quiz q
            WHERE q.status = 'published'
              AND NOT EXISTS (
                  SELECT 1 FROM kotoba_quiz_attempts a
                  WHERE a.quiz_id = q.id AND a.user_id = ? AND a.status = 'finished'
              )
        ");
        $stmt3->execute([$user['id']]);
        $pendingKotoba = (int)$stmt3->fetchColumn();

        $stmtCS = $db->prepare("SELECT COUNT(*) FROM customer_service WHERE user_id = ? AND status = 'answered'");
        $stmtCS->execute([$user['id']]);
        $pendingCS = (int)$stmtCS->fetchColumn();
    } catch (\Exception $e) {}
}

// ── PENGUMUMAN HANDLER ──────────────────────────────────────
$announceSuccess = '';
$announceError   = '';

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_announcement') {
    $msg = trim($_POST['announcement_message'] ?? '');
    if (strlen($msg) < 3) {
        $announceError = 'Pesan terlalu pendek (minimal 3 karakter).';
    } elseif (strlen($msg) > 500) {
        $announceError = 'Pesan terlalu panjang (maksimal 500 karakter).';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO announcements (message, created_by) VALUES (?, ?)");
            $stmt->execute([$msg, $user['id']]);
            $announceSuccess = 'Pengumuman berhasil dikirim.';
        } catch (\Exception $e) {
            $announceError = 'Gagal menyimpan pengumuman. Pastikan tabel SQL sudah dibuat.';
        }
    }
}

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_announcement') {
    $annId = (int)($_POST['ann_id'] ?? 0);
    $msg   = trim($_POST['announcement_message'] ?? '');
    if ($annId > 0 && strlen($msg) >= 3) {
        try {
            $stmt = $db->prepare("UPDATE announcements SET message = ? WHERE id = ?");
            $stmt->execute([$msg, $annId]);
            $announceSuccess = 'Pengumuman berhasil diperbarui.';
        } catch (\Exception $e) {
            $announceError = 'Gagal memperbarui pengumuman.';
        }
    }
}

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_announcement') {
    $delId = (int)($_POST['ann_id'] ?? 0);
    if ($delId > 0) {
        try {
            $db->prepare("DELETE FROM announcement_reads WHERE announcement_id = ?")->execute([$delId]);
            $db->prepare("DELETE FROM announcements WHERE id = ?")->execute([$delId]);
        } catch (\Exception $e) {}
    }
    header('Location: beranda.php');
    exit;
}

$recentAnnouncements = [];
if ($isAdmin) {
    try {
        $recentAnnouncements = $db->query(
            "SELECT a.*, u.name AS sender_name
             FROM announcements a
             JOIN users u ON u.id = a.created_by
             WHERE a.is_active = 1
             ORDER BY a.created_at DESC
             LIMIT 5"
        )->fetchAll();
    } catch (\Exception $e) { $recentAnnouncements = []; }
}

$newAnnouncements = [];
if (!$isAdmin) {
    try {
        $stmt = $db->prepare("
            SELECT a.id, a.message, a.created_at
            FROM announcements a
            LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.user_id = ?
            WHERE a.is_active = 1 AND ar.id IS NULL
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$user['id']]);
        $newAnnouncements = $stmt->fetchAll();
    } catch (\Exception $e) { $newAnnouncements = []; }
}
$unreadCount = count($newAnnouncements);

$examStats = ['total_done' => 0, 'avg_score' => 0, 'highest' => 0, 'lowest' => 0, 'history' => [], 'chart' => []];
if (!$isAdmin) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) AS total_done, AVG(score) AS avg_score, MAX(score) AS highest, MIN(score) AS lowest FROM exam_attempts WHERE user_id = ? AND status = 'finished'");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        if ($row && (int)$row['total_done'] > 0) {
            $examStats['total_done'] = (int)$row['total_done'];
            $examStats['avg_score']  = round((float)$row['avg_score'], 1);
            $examStats['highest']    = round((float)$row['highest'], 1);
            $examStats['lowest']     = round((float)$row['lowest'], 1);
        }

        $stmt = $db->prepare("SELECT e.title, a.score, a.total_correct, a.total_questions, a.finished_at FROM exam_attempts a JOIN exams e ON e.id = a.exam_id WHERE a.user_id = ? AND a.status = 'finished' ORDER BY a.finished_at DESC LIMIT 5");
        $stmt->execute([$user['id']]);
        $examStats['history'] = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT e.title, a.score, a.finished_at FROM exam_attempts a JOIN exams e ON e.id = a.exam_id WHERE a.user_id = ? AND a.status = 'finished' ORDER BY a.finished_at DESC LIMIT 10");
        $stmt->execute([$user['id']]);
        $examStats['chart'] = array_reverse($stmt->fetchAll());
    } catch (\Exception $e) {}
}

$tugasStats = ['total_done' => 0, 'avg_score' => 0, 'highest' => 0, 'lowest' => 0, 'history' => []];
if (!$isAdmin) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) AS total_done, AVG(nilai) AS avg_score, MAX(nilai) AS highest, MIN(nilai) AS lowest FROM tugas_submissions WHERE user_id = ? AND nilai IS NOT NULL");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        if ($row && (int)$row['total_done'] > 0) {
            $tugasStats['total_done'] = (int)$row['total_done'];
            $tugasStats['avg_score']  = round((float)$row['avg_score'], 1);
            $tugasStats['highest']    = round((float)$row['highest'], 1);
            $tugasStats['lowest']     = round((float)$row['lowest'], 1);
        }

        $stmt = $db->prepare("SELECT t.judul, s.nilai, s.feedback, s.graded_at FROM tugas_submissions s JOIN tugas t ON t.id = s.tugas_id WHERE s.user_id = ? AND s.nilai IS NOT NULL ORDER BY s.graded_at DESC LIMIT 5");
        $stmt->execute([$user['id']]);
        $tugasStats['history'] = $stmt->fetchAll();
    } catch (\Exception $e) {}
}

$overallStats = ['total_items' => 0, 'avg_score' => 0, 'total_score' => 0, 'exam_count' => 0, 'exam_total' => 0, 'tugas_count' => 0, 'tugas_total' => 0];
if (!$isAdmin) {
    $overallStats['exam_count']  = $examStats['total_done'];
    $overallStats['tugas_count'] = $tugasStats['total_done'];
    $overallStats['total_items'] = $overallStats['exam_count'] + $overallStats['tugas_count'];
    if ($overallStats['exam_count'] > 0) $overallStats['exam_total'] = round($examStats['avg_score'] * $overallStats['exam_count'], 1);
    if ($overallStats['tugas_count'] > 0) $overallStats['tugas_total'] = round($tugasStats['avg_score'] * $overallStats['tugas_count'], 1);
    $overallStats['total_score'] = round($overallStats['exam_total'] + $overallStats['tugas_total'], 1);
    if ($overallStats['total_items'] > 0) $overallStats['avg_score'] = round($overallStats['total_score'] / $overallStats['total_items'], 1);
}

$clubProfileFile = __DIR__ . '/data/club_profiles.json';
$clubProfiles = [
    'ketua' => ['name' => '', 'photo' => '', 'bg_color' => '#ffffff'],
    'guru'  => ['name' => '', 'photo' => '', 'bg_color' => '#ffffff']
];
if (file_exists($clubProfileFile)) {
    $loaded = json_decode(file_get_contents($clubProfileFile), true) ?: [];
    foreach(['ketua', 'guru'] as $k) {
        if (isset($loaded[$k])) $clubProfiles[$k] = array_merge($clubProfiles[$k], $loaded[$k]);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Beranda</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { -webkit-overflow-scrolling: touch; overscroll-behavior-y: contain; }
    .dashboard-main { touch-action: pan-y; }
    .topbar { will-change: transform; backface-visibility: hidden; }
    .profile-card, .stats-card-exam, .bottom-action-bar { contain: layout style; backface-visibility: hidden; }
    .fade-up { opacity: 0; transform: translateY(24px); transition: opacity 0.45s ease, transform 0.45s ease; }
    .fade-up.is-visible { opacity: 1; transform: translateY(0); }
    #petals { pointer-events: none; }
    html { scroll-behavior: smooth; }
    
    .topbar-actions { display: flex; align-items: center; gap: 10px; }
    .ann-badge { position: absolute; top: -4px; right: -4px; background: var(--torii, #b74b4b); color: #fff; border-radius: 50%; width: 18px; height: 18px; font-size: .68rem; font-weight: 800; display: flex; align-items: center; justify-content: center; line-height: 1; border: 2px solid var(--card-bg, #fff); }
    .ann-bell-btn { position: relative; display: inline-flex; align-items: center; justify-content: center; width: auto; min-width: 38px; height: 38px; padding: 0 10px; border-radius: 20px; background: rgba(183,75,75,.1); border: 1.5px solid rgba(183,75,75,.25); cursor: pointer; transition: background .18s; font-size: .82rem; font-weight: 700; text-decoration: none; color: var(--text, #333); }
    .ann-bell-btn:hover { background: rgba(183,75,75,.18); }

    /* POP-UP BUBBLE NOTIFIKASI */
    .ann-bubble-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 9000; pointer-events: all; }
    .ann-bubble { position: fixed; bottom: 80px; right: 20px; width: 320px; max-width: calc(100vw - 40px); background: var(--card-bg, #fff); border: 1.5px solid rgba(183,75,75,0.25); border-radius: 18px; box-shadow: 0 10px 35px rgba(0,0,0,.22); padding: 16px; z-index: 9100; pointer-events: all; animation: annSlideIn .3s ease both; }
    @keyframes annSlideIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

    .ann-form-card { background: var(--card-bg, #fff); border: 1.5px solid rgba(183,75,75,.2); border-radius: 18px; padding: 22px; margin-bottom: 0; }
    .ann-textarea { width: 100%; box-sizing: border-box; border: 1.5px solid var(--card-border, #ddd); border-radius: 12px; padding: 12px; font-size: .90rem; background: var(--input-bg, #fafafa); color: var(--text, #333); font-family: inherit; resize: vertical; min-height: 80px; }
    .ann-send-btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 20px; border-radius: 14px; border: none; cursor: pointer; background: linear-gradient(135deg, var(--torii, #b74b4b), #d97070); color: #fff; font-size: .88rem; font-weight: 700; margin-top: 10px; }
    .ann-history-item { display: flex; gap: 10px; align-items: flex-start; padding: 10px 0; border-bottom: 1px solid var(--card-border, #eee); }
    .ann-history-item:last-child { border-bottom: none; }
    .ann-del-btn, .ann-edit-btn { background: none; border: none; cursor: pointer; font-size: .85rem; padding: 4px 6px; border-radius: 6px; transition: background .15s; }
    .ann-del-btn:hover { color: var(--torii); background: rgba(183,75,75,.1); }
    .ann-edit-btn:hover { color: var(--gold); background: rgba(196,147,38,.1); }

    .overall-score-card .overall-total-box { text-align: center; margin: 18px 0 22px; padding: 22px; border-radius: 16px; background: linear-gradient(135deg, rgba(183,75,75,.10), rgba(74,124,89,.10)); border: 1px solid var(--card-border); }
    .overall-score-card .overall-total-number { font-size: 2.6rem; font-weight: 800; line-height: 1.1; color: var(--torii); }
    .overall-score-card .overall-total-label { font-size: .82rem; color: var(--mist); margin-top: 6px; font-weight: 600; }

    .club-section { margin-top: 10px; margin-bottom: 0; }
    .club-section-header { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; padding: 0 2px; }
    .club-section-title { font-size: 1.05rem; font-weight: 800; color: var(--text, #333); }
    .club-section-divider { flex: 1; height: 1.5px; background: linear-gradient(90deg, rgba(183,75,75,.35), transparent); border-radius: 2px; }
    .club-profiles-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media (max-width: 420px) { .club-profiles-grid { grid-template-columns: 1fr; } }
    .club-profile-card { background: var(--card-bg, #fff); border: 1.5px solid rgba(183,75,75,.18); border-radius: 20px; padding: 22px 16px 18px; text-align: center; position: relative; overflow: hidden; transition: transform .2s, box-shadow .2s; }
    .club-profile-card:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(183,75,75,.18); }
    .club-profile-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, var(--torii, #b74b4b), #d97070, var(--gold, #c9a96e)); }
    .club-profile-card.guru-card::before { background: linear-gradient(90deg, var(--bamboo, #4a7c59), #6aab7a, var(--gold, #c9a96e)); }
    .club-profile-photo-wrap { position: relative; display: inline-block; margin-bottom: 12px; }
    .club-profile-photo { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(183,75,75,.3); display: block; }
    .guru-card .club-profile-photo { border-color: rgba(74,124,89,.35); }
    .club-profile-photo-placeholder { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--torii,#b74b4b), #d97070); border: 3px solid rgba(183,75,75,.3); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 800; color: #fff; margin: 0 auto; }
    .guru-card .club-profile-photo-placeholder { background: linear-gradient(135deg, var(--bamboo,#4a7c59), #6aab7a); border-color: rgba(74,124,89,.35); }
    .club-profile-badge { position: absolute; bottom: 0; right: 0; width: 24px; height: 24px; border-radius: 50%; background: var(--torii, #b74b4b); color: #fff; font-size: .75rem; display: flex; align-items: center; justify-content: center; border: 2px solid var(--card-bg, #fff); font-weight: 800; }
    .guru-card .club-profile-badge { background: var(--bamboo, #4a7c59); }
    .club-profile-name { font-size: 1rem; font-weight: 800; color: var(--text, #333); margin-bottom: 5px; line-height: 1.3; }
    .club-profile-role { display: inline-flex; align-items: center; gap: 4px; padding: 4px 12px; border-radius: 20px; font-size: .75rem; font-weight: 700; background: rgba(183,75,75,.1); color: var(--torii, #b74b4b); border: 1px solid rgba(183,75,75,.2); }
    .guru-card .club-profile-role { background: rgba(74,124,89,.1); color: var(--bamboo, #4a7c59); border-color: rgba(74,124,89,.2); }
    .club-edit-btn { position: absolute; top: 10px; right: 10px; width: 28px; height: 28px; border-radius: 50%; background: rgba(183,75,75,.12); border: 1px solid rgba(183,75,75,.25); color: var(--torii, #b74b4b); font-size: .8rem; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background .18s, transform .18s; z-index: 2; text-decoration: none; }
    .club-edit-btn:hover { background: rgba(183,75,75,.22); transform: rotate(15deg) scale(1.1); }
  </style>
</head>
<body class="dashboard-page">
  <div class="page-loader" id="pageLoader"><span class="loader-kanji">桜</span></div>
  <div class="asanoha-bg"></div>
  <div id="petals"></div>

  <header class="topbar">
    <div class="topbar-brand">桜 Sakura</div>
    <div class="topbar-actions">
      <?php if (!$isAdmin): ?>
      <a class="ann-bell-btn" id="annBellBtn" title="Pengumuman" onclick="openAnnBubble(); return false;" href="#">
        <span style="margin-right:3px;">通知</span> Notif
        <span class="ann-badge" id="annBadge" style="<?= $unreadCount > 0 ? '' : 'display:none;' ?>"><?= $unreadCount ?></span>
      </a>
      <?php endif; ?>
      <a class="ann-bell-btn" href="customer_service.php" title="Pusat Bantuan / CS" style="text-decoration:none;">
        <span style="margin-right:3px;">問合</span> CS
        <?php if ($pendingCS > 0): ?>
          <span class="ann-badge"><?= $pendingCS ?></span>
        <?php endif; ?>
      </a>
      <button class="theme-toggle" onclick="toggleTheme()" title="Mode Terang">Mode</button>
    </div>
  </header>

  <main class="dashboard-main">
    <section class="welcome-section fade-up">
      <span class="welcome-kanji"><?= $isAdmin ? '管理' : 'ようこそ' ?></span>
      <h1 class="welcome-title">Selamat Datang, <?= htmlspecialchars($user['name']) ?></h1>
      <p class="welcome-sub"><?= $isAdmin ? 'Anda masuk sebagai Administrator — pengelola sistem Sakura App' : 'Nikmati pengalaman bersama Sakura App' ?></p>
      <div class="section-divider"></div>
    </section>

    <?php if (!$isAdmin && $overallStats['total_items'] > 0): ?>
    <div class="profile-card fade-up delay-1 overall-score-card">
      <div class="stats-header">
        <div>
          <div class="stats-header-title"><span style="margin-right:4px;">成績</span>Total Nilai Keseluruhan</div>
          <div class="stats-header-sub">Gabungan nilai ujian dan tugas yang sudah dinilai</div>
        </div>
      </div>
      <div class="overall-total-box">
        <div class="overall-total-number"><?= number_format($overallStats['total_score'], 1) ?></div>
        <div class="overall-total-label">Total Poin (dari <?= $overallStats['total_items'] ?> item dinilai)</div>
      </div>
      <div class="stats-row exam-stats-row">
        <div class="stat-card"><div class="stat-number"><?= number_format($overallStats['avg_score'], 1) ?></div><div class="stat-label">Rata-rata Keseluruhan</div></div>
        <div class="stat-card"><div class="stat-number"><?= number_format($overallStats['exam_total'], 1) ?></div><div class="stat-label">Total Nilai Ujian (<?= $overallStats['exam_count'] ?>)</div></div>
        <div class="stat-card"><div class="stat-number"><?= number_format($overallStats['tugas_total'], 1) ?></div><div class="stat-label">Total Nilai Tugas (<?= $overallStats['tugas_count'] ?>)</div></div>
      </div>
    </div>
    <?php endif; ?>

    <div class="profile-card <?= $isAdmin ? 'admin-card' : '' ?> fade-up delay-1">
      <div class="profile-header">
        <div class="profile-avatar-lg">
          <?= htmlspecialchars($initial) ?>
          <div class="role-badge <?= $isAdmin ? 'badge-admin' : 'badge-user' ?>"><?= $isAdmin ? '⛩' : '🌸' ?></div>
        </div>
        <div class="profile-meta">
          <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
          <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
          <span class="profile-role-tag <?= $isAdmin ? 'tag-admin' : 'tag-user' ?>"><?= $isAdmin ? 'Administrator' : 'Member' ?></span>
        </div>
      </div>

      <div class="profile-grid">
        <div class="info-item"><div class="info-label">ID Akun</div><div class="info-value">#<?= str_pad($user['id'], 5, '0', STR_PAD_LEFT) ?></div></div>
        <div class="info-item"><div class="info-label">Peran</div><div class="info-value"><?= $isAdmin ? 'Administrator' : 'User' ?></div></div>
        <div class="info-item"><div class="info-label">Bergabung</div><div class="info-value"><?= $joinDate ?></div></div>
        <div class="info-item"><div class="info-label">Status</div><div class="info-value" style="color: var(--bamboo);">● Aktif</div></div>
      </div>

      <?php if (!empty($user['bio'])): ?>
      <div class="profile-bio">"<?= htmlspecialchars($user['bio']) ?>"</div>
      <?php endif; ?>

      <?php if ($isAdmin): ?>
      <div class="stats-row">
        <div class="stat-card"><div class="stat-number"><?= $totalAll ?></div><div class="stat-label">Total Pengguna</div></div>
        <div class="stat-card"><div class="stat-number"><?= $totalUsers ?></div><div class="stat-label">Member</div></div>
        <div class="stat-card"><div class="stat-number"><?= $totalAdmins ?></div><div class="stat-label">Admin</div></div>
      </div>

      <div class="admin-panel-note">
        <p><span style="margin-right:4px;">⛩</span>Anda memiliki akses <strong>Administrator</strong>. Panel manajemen pengguna, konten, dan konfigurasi sistem tersedia untuk Anda.</p>
      </div>

      <!-- FORM PENGUMUMAN (Admin) -->
      <div class="ann-form-card" style="margin-top:18px;">
        <div class="ann-form-header" style="display:flex; align-items:center; gap:10px; margin-bottom:14px;">
          <div>
            <div style="font-weight:700;"><span style="margin-right:5px;">広報</span>Kirim & Kelola Pengumuman</div>
            <div style="font-size:.78rem; color:var(--mist);">Pesan akan muncul sebagai notifikasi gelembung ke semua user</div>
          </div>
        </div>

        <?php if ($announceSuccess): ?>
          <div style="padding:10px; background:rgba(74,124,89,.12); color:var(--bamboo); border-radius:8px; margin-bottom:10px; font-weight:600;"><?= htmlspecialchars($announceSuccess) ?></div>
        <?php endif; ?>
        <?php if ($announceError): ?>
          <div style="padding:10px; background:rgba(183,75,75,.1); color:var(--torii); border-radius:8px; margin-bottom:10px; font-weight:600;"><?= htmlspecialchars($announceError) ?></div>
        <?php endif; ?>

        <form method="POST" action="beranda.php" id="annForm">
          <input type="hidden" name="action" id="annAction" value="send_announcement">
          <input type="hidden" name="ann_id" id="annIdVal" value="">
          <textarea name="announcement_message" class="ann-textarea" placeholder="Tulis pesan pengumuman di sini... (maks. 500 karakter)" maxlength="500" id="annTextarea" required></textarea>
          <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
            <button type="button" id="annCancelEdit" style="display:none; background:#ccc; border:none; padding:8px 14px; border-radius:10px; cursor:pointer; font-weight:700;" onclick="cancelEditAnn()">Batal Edit</button>
            <button type="submit" class="ann-send-btn" id="annSubmitBtn"><span style="margin-right:3px;">送信</span>Kirim Pengumuman</button>
          </div>
        </form>

        <?php if (!empty($recentAnnouncements)): ?>
        <div style="margin-top:18px;">
          <div style="font-weight:700; margin-bottom:8px; font-size:.9rem;"><span style="margin-right:4px;">履歴</span>Riwayat Pengumuman Terakhir</div>
          <?php foreach ($recentAnnouncements as $ann): ?>
          <div class="ann-history-item">
            <div style="width:8px; height:8px; border-radius:50%; background:var(--torii); margin-top:6px; flex-shrink:0;"></div>
            <div style="flex:1; min-width:0;">
              <div style="font-size:.86rem; word-break:break-word;"><?= htmlspecialchars($ann['message']) ?></div>
              <div style="font-size:.72rem; color:var(--mist); margin-top:2px;"><?= date('d M Y, H:i', strtotime($ann['created_at'])) ?> &nbsp;· oleh <?= htmlspecialchars($ann['sender_name']) ?></div>
            </div>
            <div style="display:flex; gap:4px; align-items:center;">
              <button type="button" class="ann-edit-btn" title="Ubah Pengumuman" onclick="editAnnouncement(<?= $ann['id'] ?>, '<?= htmlspecialchars(addslashes($ann['message'])) ?>')">Edit</button>
              <form method="POST" action="beranda.php" style="margin:0;" onsubmit="return confirm('Hapus pengumuman ini?')">
                <input type="hidden" name="action" value="delete_announcement">
                <input type="hidden" name="ann_id" value="<?= $ann['id'] ?>">
                <button type="submit" class="ann-del-btn" title="Hapus pengumuman">Hapus</button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <?php else: ?>
      <div class="admin-panel-note" style="background:rgba(74,124,89,0.08); border-color:rgba(74,124,89,0.25);">
        <p><span style="margin-right:4px;">🌸</span>Selamat datang di <strong style="color:var(--bamboo)">Sakura App</strong>. Jelajahi fitur-fitur yang tersedia untuk kamu.</p>
      </div>
      <?php endif; ?>
    </div>

    <?php if (!$isAdmin): ?>
    <div class="profile-card fade-up delay-2 stats-card-exam">
      <div class="stats-header">
        <div>
          <div class="stats-header-title"><span style="margin-right:4px;">試験</span>Statistik Ujian</div>
          <div class="stats-header-sub">Rekap nilai dari ujian yang sudah kamu kerjakan</div>
        </div>
      </div>
      <?php if ($examStats['total_done'] === 0): ?>
        <div class="admin-panel-note" style="background:rgba(74,124,89,0.08); border-color:rgba(74,124,89,0.25); margin-top: 20px;">
          <p>Belum ada riwayat ujian. Statistik akan muncul setelah kamu menyelesaikan ujian pertama.</p>
        </div>
      <?php else: ?>
      <div class="stats-row exam-stats-row">
        <div class="stat-card"><div class="stat-number"><?= $examStats['total_done'] ?></div><div class="stat-label">Ujian Selesai</div></div>
        <div class="stat-card"><div class="stat-number"><?= number_format($examStats['avg_score'], 1) ?></div><div class="stat-label">Rata-rata Nilai</div></div>
        <div class="stat-card"><div class="stat-number" style="color: var(--bamboo);"><?= number_format($examStats['highest'], 1) ?></div><div class="stat-label">Nilai Tertinggi</div></div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── ACTION BAR (LENGKAP SAMA TOMBOL CHOUKAI) ── -->
    <div class="bottom-action-bar fade-up delay-3">
      <div class="bab-profile">
        <div class="avatar bab-avatar"><?= htmlspecialchars($initial) ?></div>
        <div class="bab-user-info">
          <div class="bab-name"><?= htmlspecialchars($user['name']) ?></div>
          <span class="bab-role <?= $isAdmin ? 'role-admin' : 'role-user' ?>"><?= $isAdmin ? '⛩ Administrator' : '🌸 Member' ?></span>
        </div>
      </div>

      <div class="bab-actions">
        <?php if ($isAdmin): ?>
          <a href="admin_cs.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#d97070,#b74b4b);">
            <span><strong style="margin-right:4px;">問合</strong>Kelola CS / Bantuan <?php if ($pendingCS > 0): ?><span class="nav-badge" style="position:static; display:inline-flex; margin-left:6px;"><?= $pendingCS ?></span><?php endif; ?></span>
          </a>
          <a href="ujian_admin.php" class="bab-btn bab-btn-primary">
            <span><strong style="margin-right:4px;">⛩</strong>Kelola Ujian</span>
          </a>
          <a href="hafalan_admin.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,var(--bamboo),#3a6347);">
            <span><strong style="margin-right:4px;">暗記</strong>Kelola Hafalan</span>
          </a>
          <a href="tugas_admin.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#5558af,#3a3d8a);">
            <span><strong style="margin-right:4px;">課題</strong>Kelola Tugas</span>
          </a>
          <a href="kana.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#b05a1a,#8a3d0e);">
            <span><strong style="margin-right:4px;">仮名</strong>Kana</span>
          </a>
          <a href="kotoba_admin.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#7c3aed,#a855f7);">
            <span><strong style="margin-right:4px;">言葉</strong>Kelola Quiz Kotoba</span>
          </a>
          <a href="admin_tata_bahasa.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#0f7490,#0c5a6e);">
            <span><strong style="margin-right:4px;">文法</strong>Kelola Tata Bahasa</span>
          </a>
          <a href="admin_membaca.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#b5651d,#8a4a13);">
            <span><strong style="margin-right:4px;">読書</strong>Kelola Membaca</span>
          </a>
          <a href="admin_choukai.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);">
            <span><strong style="margin-right:4px;">聴解</strong>Kelola Choukai</span>
          </a>
          <a href="tambah_anggota.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#c9a96e,#8a6d3b);">
            <span><strong style="margin-right:4px;">追加</strong>Tambah Anggota</span>
          </a>
          <a href="data_user.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#0f7490,#0c5a6e);">
            <span><strong style="margin-right:4px;">会員</strong>Data Pengguna</span>
          </a>
          <a href="rangking.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#e07b00,#c96000);">
            <span><strong style="margin-right:4px;">順位</strong>Peringkat</span>
          </a>
        <?php else: ?>
          <a href="customer_service.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#d97070,#b74b4b);">
            <span><strong style="margin-right:4px;">問合</strong>Pusat Bantuan / CS <?php if ($pendingCS > 0): ?><span class="nav-badge" style="position:static; display:inline-flex; margin-left:6px;"><?= $pendingCS ?></span><?php endif; ?></span>
          </a>
          <a href="ujian.php" class="bab-btn bab-btn-primary">
            <span><strong style="margin-right:4px;">試験</strong>Mulai Ujian</span>
          </a>
          <a href="hafalan.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,var(--bamboo),#3a6347);">
            <span><strong style="margin-right:4px;">暗記</strong>Hafalan</span>
          </a>
          <a href="kana.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#b05a1a,#8a3d0e);">
            <span><strong style="margin-right:4px;">仮名</strong>Kana</span>
          </a>
          <a href="tugas.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#5558af,#3a3d8a);">
            <span><strong style="margin-right:4px;">課題</strong>Tugas</span>
          </a>
          <a href="kotoba.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#7c3aed,#a855f7);">
            <span><strong style="margin-right:4px;">言葉</strong>Quiz Kotoba</span>
          </a>
          <a href="tata_bahasa.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#0f7490,#0c5a6e);">
            <span><strong style="margin-right:4px;">文法</strong>Tata Bahasa</span>
          </a>
          <a href="membaca.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#b5651d,#8a4a13);">
            <span><strong style="margin-right:4px;">読書</strong>Membaca</span>
          </a>
          <a href="choukai.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);">
            <span><strong style="margin-right:4px;">聴解</strong>Choukai</span>
          </a>
        <?php endif; ?>
        <button class="bab-btn bab-btn-logout" onclick="handleLogout()">
          <span style="color: brown"><strong style="margin-right:4px;">退出</strong>Keluar 出る</span>
        </button>
      </div>
    </div>

    <!-- CLUB PROFILE SECTION -->
    <div class="club-section fade-up delay-3">
      <div class="club-section-header">
        <div class="club-section-title"><span style="margin-right:5px;">部活</span>Profil Club Sakura</div>
        <div class="club-section-divider"></div>
      </div>
      <div class="club-profiles-grid">
        <div class="club-profile-card" id="clubCardKetua" style="background-color: <?= htmlspecialchars($clubProfiles['ketua']['bg_color'] ?? '#ffffff') ?>;">
          <?php if ($isAdmin): ?>
          <button class="club-edit-btn" onclick="openClubModal('ketua')" title="Edit Profil Ketua">Edit</button>
          <?php endif; ?>
          <div class="club-profile-photo-wrap">
            <?php if (!empty($clubProfiles['ketua']['photo'])): ?>
              <img src="<?= htmlspecialchars($clubProfiles['ketua']['photo']) ?>" alt="Foto Ketua Club" class="club-profile-photo" id="clubPhotoKetua" onerror="this.style.display='none'; document.getElementById('clubPlaceholderKetua').style.display='flex';">
              <div class="club-profile-photo-placeholder" id="clubPlaceholderKetua" style="display:none;"><?= strtoupper(mb_substr($clubProfiles['ketua']['name'] ?: 'K', 0, 1)) ?></div>
            <?php else: ?>
              <div class="club-profile-photo-placeholder" id="clubPlaceholderKetua"><?= strtoupper(mb_substr($clubProfiles['ketua']['name'] ?: 'K', 0, 1)) ?></div>
            <?php endif; ?>
            <div class="club-profile-badge">⛩</div>
          </div>
          <div class="club-profile-name" id="clubNameKetua"><?= !empty($clubProfiles['ketua']['name']) ? htmlspecialchars($clubProfiles['ketua']['name']) : '— Nama Ketua —' ?></div>
          <div class="club-profile-role"><span style="margin-right:3px;">会長</span>Ketua Club</div>
        </div>

        <div class="club-profile-card guru-card" id="clubCardGuru" style="background-color: <?= htmlspecialchars($clubProfiles['guru']['bg_color'] ?? '#ffffff') ?>;">
          <?php if ($isAdmin): ?>
          <button class="club-edit-btn" onclick="openClubModal('guru')" title="Edit Profil Guru" style="color:var(--bamboo,#4a7c59); border-color:rgba(74,124,89,.3); background:rgba(74,124,89,.1);">Edit</button>
          <?php endif; ?>
          <div class="club-profile-photo-wrap">
            <?php if (!empty($clubProfiles['guru']['photo'])): ?>
              <img src="<?= htmlspecialchars($clubProfiles['guru']['photo']) ?>" alt="Foto Guru Klub" class="club-profile-photo" id="clubPhotoGuru" onerror="this.style.display='none'; document.getElementById('clubPlaceholderGuru').style.display='flex';">
              <div class="club-profile-photo-placeholder guru-card" id="clubPlaceholderGuru" style="display:none;"><?= strtoupper(mb_substr($clubProfiles['guru']['name'] ?: 'G', 0, 1)) ?></div>
            <?php else: ?>
              <div class="club-profile-photo-placeholder guru-card" id="clubPlaceholderGuru"><?= strtoupper(mb_substr($clubProfiles['guru']['name'] ?: 'G', 0, 1)) ?></div>
            <?php endif; ?>
            <div class="club-profile-badge" style="background:var(--bamboo,#4a7c59);">先</div>
          </div>
          <div class="club-profile-name" id="clubNameGuru"><?= !empty($clubProfiles['guru']['name']) ? htmlspecialchars($clubProfiles['guru']['name']) : '— Nama Guru —' ?></div>
          <div class="club-profile-role"><span style="margin-right:3px;">先生</span>Guru Klub</div>
        </div>
      </div>
    </div>
  </main>

  <!-- POP-UP OVERLAY & BUBBLE NOTIFIKASI USER -->
  <div class="ann-bubble-overlay" id="annOverlay" style="display:none;" onclick="closeAnnBubble()"></div>
  <div class="ann-bubble" id="annBubble" style="display:none;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:1px solid var(--card-border); padding-bottom:8px;">
      <strong style="font-size:.9rem; color:var(--torii);"><span style="margin-right:4px;">お知らせ</span>Pengumuman</strong>
      <button onclick="closeAnnBubble()" style="background:none; border:none; cursor:pointer; font-size:1.1rem; color:var(--text-muted);">&times;</button>
    </div>
    <div id="annListContent" style="max-height:280px; overflow-y:auto; font-size:.85rem; display:flex; flex-direction:column; gap:10px;">
    </div>
  </div>

  <script src="js/theme.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/notif.js"></script>
  <script>
    function openAnnBubble() {
      const bubble = document.getElementById('annBubble');
      const overlay = document.getElementById('annOverlay');
      const content = document.getElementById('annListContent');
      if (!bubble || !overlay) return;

      fetch('beranda.php?action=poll_announcements')
        .then(r => r.json())
        .then(res => {
          if (res.ok && res.announcements && res.announcements.length > 0) {
            let html = '';
            res.announcements.forEach(a => {
              html += `<div style="padding:10px; background:rgba(0,0,0,0.03); border-radius:10px; border-left:3px solid var(--torii);">
                <div style="font-weight:600; color:var(--text-main); margin-bottom:4px; word-break:break-word;">${a.message}</div>
                <div style="font-size:.72rem; color:var(--text-muted);">${a.created_at}</div>
              </div>`;
              markAnnRead(a.id);
            });
            content.innerHTML = html;
          } else {
            content.innerHTML = '<div style="text-align:center; color:var(--text-muted); padding:15px;">Belum ada pengumuman baru.</div>';
          }
          bubble.style.display = 'block';
          overlay.style.display = 'block';

          const badge = document.getElementById('annBadge');
          if (badge) badge.style.display = 'none';
        })
        .catch(() => {
          content.innerHTML = '<div style="text-align:center; color:var(--torii); padding:10px;">Gagal memuat pengumuman.</div>';
          bubble.style.display = 'block';
          overlay.style.display = 'block';
        });
    }

    function closeAnnBubble() {
      const bubble = document.getElementById('annBubble');
      const overlay = document.getElementById('annOverlay');
      if (bubble) bubble.style.display = 'none';
      if (overlay) overlay.style.display = 'none';
    }

    function markAnnRead(annId) {
      const fd = new FormData();
      fd.append('action', 'mark_announcement_read');
      fd.append('ann_id', annId);
      fetch('beranda.php', { method: 'POST', body: fd }).catch(() => {});
    }

    function editAnnouncement(id, message) {
      document.getElementById('annAction').value = 'edit_announcement';
      document.getElementById('annIdVal').value = id;
      document.getElementById('annTextarea').value = message;
      document.getElementById('annSubmitBtn').innerHTML = '<span style="margin-right:3px;">保存</span>Simpan Perubahan';
      document.getElementById('annCancelEdit').style.display = 'inline-block';
      document.getElementById('annTextarea').focus();
    }
    function cancelEditAnn() {
      document.getElementById('annAction').value = 'send_announcement';
      document.getElementById('annIdVal').value = '';
      document.getElementById('annTextarea').value = '';
      document.getElementById('annSubmitBtn').innerHTML = '<span style="margin-right:3px;">送信</span>Kirim Pengumuman';
      document.getElementById('annCancelEdit').style.display = 'none';
    }
    function handleLogout() {
      const fd = new FormData();
      fd.append('action', 'logout');
      fetch('auth.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => { if (data.redirect) window.location.href = data.redirect; });
    }
  </script>
</body>
</html>