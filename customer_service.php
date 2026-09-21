<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
if (!$user || $user['role'] === 'admin') {
    header('Location: beranda.php');
    exit;
}

$db = getDB();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($subject === '' || $message === '') {
        $error = 'Subjek dan pesan kendala tidak boleh kosong.';
    } else {
        $stmt = $db->prepare("INSERT INTO customer_service (user_id, subject, message, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$user['id'], $subject, $message]);
        $success = 'Kendala berhasil dikirim ke Admin! Silakan tunggu balasan.';
    }
}

// Ambil riwayat tiket CS user ini
$stmtTickets = $db->prepare("SELECT * FROM customer_service WHERE user_id = ? ORDER BY created_at DESC");
$stmtTickets->execute([$user['id']]);
$tickets = $stmtTickets->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Pusat Bantuan (CS)</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .cs-page { max-width: 800px; margin: 0 auto; padding: 80px 16px 40px; }
    .cs-card { background: var(--card-bg); border: 1.5px solid var(--card-border); border-radius: 18px; padding: 24px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,.06); }
    .cs-input { width: 100%; box-sizing: border-box; padding: 12px 14px; border-radius: 12px; border: 1.5px solid var(--card-border); background: var(--input-bg); color: var(--text); font-size: .9rem; margin-bottom: 14px; font-family: inherit; }
    .cs-input:focus { outline: none; border-color: var(--torii); }
    .ticket-item { border-bottom: 1px solid var(--card-border); padding: 16px 0; }
    .ticket-item:last-child { border-bottom: none; }
    .badge-status { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight: 700; }
    .status-pending { background: rgba(196,147,38,.15); color: var(--gold); }
    .status-answered { background: rgba(74,124,89,.15); color: var(--bamboo); }
    .admin-reply-box { background: rgba(183,75,75,.05); border-left: 3px solid var(--torii); padding: 12px 14px; border-radius: 0 10px 10px 0; margin-top: 10px; font-size: .88rem; }
  </style>
</head>
<body class="dashboard-page">
  <div class="asanoha-bg"></div>
  <header class="topbar">
    <div class="topbar-brand">桜 Sakura — Bantuan</div>
    <a href="beranda.php" class="du-back-btn" style="color:var(--torii); text-decoration:none; font-weight:700;">← Beranda</a>
  </header>

  <main class="cs-page fade-up">
    <div style="text-align:center; margin-bottom:24px;">
      <span style="font-size:2rem; color:var(--torii);">💬</span>
      <h1 style="font-size:1.4rem; font-weight:800; margin:5px 0;">Customer Service & Kendala</h1>
      <p style="font-size:.88rem; color:var(--mist);">Ada kendala login, tugas, atau ujian? Sampaikan di sini kepada Administrator.</p>
    </div>

    <?php if ($success): ?>
      <div style="padding:12px; background:rgba(74,124,89,.12); color:var(--bamboo); border-radius:10px; margin-bottom:16px; font-weight:600;">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div style="padding:12px; background:rgba(183,75,75,.1); color:var(--torii); border-radius:10px; margin-bottom:16px; font-weight:600;">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Form Kirim Kendala -->
    <div class="cs-card">
      <h3 style="margin-top:0; font-size:1.05rem; margin-bottom:14px;">📝 Buat Tiket Kendala Baru</h3>
      <form method="POST">
        <label style="display:block; font-size:.82rem; font-weight:700; margin-bottom:5px;">Subjek / Topik Kendala</label>
        <input type="text" name="subject" class="cs-input" placeholder="Contoh: Nilai ujian tidak muncul / Error saat submit tugas" required>

        <label style="display:block; font-size:.82rem; font-weight:700; margin-bottom:5px;">Detail Kendala</label>
        <textarea name="message" class="cs-input" style="resize:vertical; min-height:100px;" placeholder="Jelaskan kendala kamu secara jelas..." required></textarea>

        <button type="submit" style="padding:10px 22px; background:linear-gradient(135deg,var(--torii),#d97070); color:#fff; border:none; border-radius:12px; font-weight:700; cursor:pointer;">Kirim Kendala 🚀</button>
      </form>
    </div>

    <!-- Riwayat Tiket -->
    <div class="cs-card">
      <h3 style="margin-top:0; font-size:1.05rem; margin-bottom:14px;">📋 Riwayat Tiket Bantuan Kamu</h3>
      <?php if (empty($tickets)): ?>
        <p style="color:var(--mist); font-size:.9rem; text-align:center; padding:20px 0;">Belum ada riwayat kendala yang dikirim.</p>
      <?php else: ?>
        <?php foreach ($tickets as $t): ?>
          <div class="ticket-item">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
              <strong style="font-size:.95rem;"><?= htmlspecialchars($t['subject']) ?></strong>
              <span class="badge-status <?= $t['status'] === 'answered' ? 'status-answered' : 'status-pending' ?>">
                <?= $t['status'] === 'answered' ? '✅ Sudah Dibalas' : '⏳ Menunggu Admin' ?>
              </span>
            </div>
            <p style="font-size:.88rem; color:var(--text); margin:6px 0; line-height:1.4;"><?= nl2br(htmlspecialchars($t['message'])) ?></p>
            <div style="font-size:.73rem; color:var(--mist);">📅 <?= date('d M Y, H:i', strtotime($t['created_at'])) ?></div>

            <?php if (!empty($t['admin_reply'])): ?>
              <div class="admin-reply-box">
                <div style="font-weight:700; color:var(--torii); margin-bottom:4px; font-size:.8rem;">⛩ Balasan Administrator:</div>
                <?= nl2br(htmlspecialchars($t['admin_reply'])) ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>
  <script src="js/theme.js"></script>
</body>
</html>