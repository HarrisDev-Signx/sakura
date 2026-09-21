<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$db   = getDB();

// Ambil daftar materi Choukai
$stmt = $db->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM choukai_questions q WHERE q.choukai_id = c.id) as total_soal,
           ca.nilai, ca.finished_at
    FROM choukai c
    LEFT JOIN choukai_attempts ca ON ca.choukai_id = c.id AND ca.user_id = {$user['id']}
    WHERE c.status = 'published'
    ORDER BY c.created_at DESC
");
$listChoukai = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — 聴解 Choukai (Listening)</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .choukai-card { background: var(--card-bg, #fff); border: 1.5px solid var(--card-border, #e2e8f0); border-radius: 18px; padding: 20px; margin-bottom: 16px; }
    .audio-player { width: 100%; margin: 12px 0; border-radius: 30px; }
    .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: .75rem; font-weight: 700; }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="topbar-brand">桜 Sakura — 聴解</div>
    <a href="beranda.php" class="ann-bell-btn">← Beranda</a>
  </header>

  <main class="dashboard-main" style="padding-top: 80px; max-width: 600px; margin: 0 auto;">
    <div style="margin-bottom: 20px;">
      <h2 style="font-size: 1.4rem; font-weight: 800;"><span style="color:var(--torii);">聴解</span> Choukai (Latihan Listening)</h2>
      <p style="font-size: .85rem; color: var(--mist);">Dengarkan audio percakapan bahasa Jepang dan jawab pertanyaannya.</p>
    </div>

    <?php if (empty($listChoukai)): ?>
      <div class="choukai-card" style="text-align:center; color: var(--mist);">
        Belum ada materi Choukai yang tersedia.
      </div>
    <?php else: ?>
      <?php foreach ($listChoukai as $item): ?>
      <div class="choukai-card">
        <div style="display:flex; justify-content:space-between; align-items:start;">
          <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0;"><?= htmlspecialchars($item['judul']) ?></h3>
          <?php if ($item['nilai'] !== null): ?>
            <span class="status-badge" style="background:rgba(74,124,89,.15); color:var(--bamboo);">Selesai: <?= $item['nilai'] ?> Poin</span>
          <?php else: ?>
            <span class="status-badge" style="background:rgba(183,75,75,.1); color:var(--torii);">Belum Dikerjakan</span>
          <?php endif; ?>
        </div>
        
        <p style="font-size: .85rem; color: var(--mist); margin: 8px 0;"><?= htmlspecialchars($item['deskripsi']) ?></p>
        
        <audio controls class="audio-player">
          <source src="<?= htmlspecialchars($item['audio_url']) ?>" type="audio/mpeg">
          Browser kamu tidak mendukung pemutar audio.
        </audio>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
          <span style="font-size: .78rem; color: var(--mist);"><?= $item['total_soal'] ?> Pertanyaan</span>
          <a href="choukai_kerjakan.php?id=<?= $item['id'] ?>" class="bab-btn bab-btn-primary" style="padding: 8px 16px; font-size: .82rem; text-decoration:none;">
            <?= $item['nilai'] !== null ? 'Ulangi Latihan' : 'Mulai Jawab' ?>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>
  <script src="js/theme.js"></script>
</body>
</html>