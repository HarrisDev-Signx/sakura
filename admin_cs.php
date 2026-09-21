<?php
require_once 'config.php';
requireAdmin();

$db = getDB();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply_ticket') {
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $reply    = trim($_POST['admin_reply'] ?? '');

    if ($ticketId > 0 && $reply !== '') {
        $stmt = $db->prepare("UPDATE customer_service SET admin_reply = ?, status = 'answered' WHERE id = ?");
        $stmt->execute([$reply, $ticketId]);
        $success = 'Balasan berhasil dikirim ke member! ✉️';
    }
}

// Ambil semua tiket CS dari member
$tickets = $db->query("
    SELECT cs.*, u.name AS member_name, u.email AS member_email, u.nis AS member_nis
    FROM customer_service cs
    JOIN users u ON u.id = cs.user_id
    ORDER BY (cs.status = 'pending') DESC, cs.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Kelola Customer Service</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .acs-page { max-width: 950px; margin: 0 auto; padding: 80px 16px 40px; }
    .acs-card { background: var(--card-bg); border: 1.5px solid var(--card-border); border-radius: 18px; padding: 24px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,.06); }
    .ticket-box { border: 1.5px solid var(--card-border); border-radius: 14px; padding: 18px; margin-bottom: 16px; background: var(--input-bg); }
    .badge-status { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: .72rem; font-weight: 700; }
    .status-pending { background: rgba(196,147,38,.15); color: var(--gold); }
    .status-answered { background: rgba(74,124,89,.15); color: var(--bamboo); }
    .reply-textarea { width: 100%; box-sizing: border-box; padding: 10px; border-radius: 10px; border: 1px solid var(--card-border); background: var(--card-bg); color: var(--text); font-size: .88rem; margin-top: 8px; resize: vertical; min-height: 80px; font-family: inherit; }
  </style>
</head>
<body class="dashboard-page">
  <div class="asanoha-bg"></div>
  <header class="topbar">
    <div class="topbar-brand">桜 Sakura — Admin CS</div>
    <a href="beranda.php" class="du-back-btn" style="color:var(--torii); text-decoration:none; font-weight:700;">← Beranda</a>
  </header>

  <main class="acs-page fade-up">
    <div style="text-align:center; margin-bottom:24px;">
      <span style="font-size:2rem; color:var(--torii);">💬</span>
      <h1 style="font-size:1.4rem; font-weight:800; margin:5px 0;">Kelola Pusat Bantuan (Customer Service)</h1>
      <p style="font-size:.88rem; color:var(--mist);">Tinjau dan balas kendala yang dilaporkan oleh member.</p>
    </div>

    <?php if ($success): ?>
      <div style="padding:12px; background:rgba(74,124,89,.12); color:var(--bamboo); border-radius:10px; margin-bottom:16px; font-weight:600;">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="acs-card">
      <h3 style="margin-top:0; font-size:1.05rem; margin-bottom:16px;">📥 Daftar Tiket Masuk (<?= count($tickets) ?>)</h3>
      <?php if (empty($tickets)): ?>
        <p style="color:var(--mist); text-align:center; padding:20px 0;">Belum ada tiket kendala dari member.</p>
      <?php else: ?>
        <?php foreach ($tickets as $t): ?>
          <div class="ticket-box">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
              <div>
                <strong style="font-size:1rem; color:var(--text);"><?= htmlspecialchars($t['subject']) ?></strong>
                <div style="font-size:.78rem; color:var(--mist); margin-top:2px;">
                  Dari: <strong><?= htmlspecialchars($t['member_name']) ?></strong> (NIS: <?= htmlspecialchars($t['member_nis'] ?? '-') ?> · <?= htmlspecialchars($t['member_email']) ?>)
                </div>
              </div>
              <span class="badge-status <?= $t['status'] === 'answered' ? 'status-answered' : 'status-pending' ?>">
                <?= $t['status'] === 'answered' ? '✅ Terjawab' : '⏳ Pending' ?>
              </span>
            </div>

            <p style="font-size:.9rem; color:var(--text); margin:10px 0; background:var(--card-bg); padding:10px; border-radius:8px; border:1px solid var(--card-border);"><?= nl2br(htmlspecialchars($t['message'])) ?></p>
            <div style="font-size:.73rem; color:var(--mist); margin-bottom:10px;">📅 Dikirim pada: <?= date('d M Y, H:i', strtotime($t['created_at'])) ?></div>

            <!-- Form Balas Admin -->
            <form method="POST">
              <input type="hidden" name="action" value="reply_ticket">
              <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
              <textarea name="admin_reply" class="reply-textarea" placeholder="Tulis balasan untuk member ini..." required><?= htmlspecialchars($t['admin_reply'] ?? '') ?></textarea>
              <div style="text-align:right; margin-top:8px;">
                <button type="submit" style="padding:8px 16px; background:var(--torii); color:#fff; border:none; border-radius:10px; font-weight:700; cursor:pointer; font-size:.85rem;">Kirim Balasan ✉️</button>
              </div>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>
  <script src="js/theme.js"></script>
</body>
</html>