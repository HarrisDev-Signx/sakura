<?php
/**
 * choukai_kerjakan.php — Halaman siswa mengerjakan latihan Choukai
 * (dengarkan audio, jawab soal pilihan ganda)
 */
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
if (!$user) { session_destroy(); header('Location: index.php'); exit; }

$db = getDB();
$choukaiId = (int)($_GET['id'] ?? 0);

// Ambil data materi
$stmt = $db->prepare("SELECT * FROM choukai WHERE id = ? AND status = 'published'");
$stmt->execute([$choukaiId]);
$choukai = $stmt->fetch();
if (!$choukai) { header('Location: choukai.php'); exit; }

// Ambil soal-soalnya
$stmt = $db->prepare("SELECT * FROM choukai_questions WHERE choukai_id = ? ORDER BY id ASC");
$stmt->execute([$choukaiId]);
$questions = $stmt->fetchAll();

$message = '';
$result  = null;

// ============================================================
// POST: submit jawaban
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_jawaban') {
    if (empty($questions)) {
        $message = 'Belum ada soal untuk latihan ini.';
    } else {
        // Buat attempt baru setiap kali submit (mendukung "Ulangi Latihan")
        // Kolom "nilai" di tabel choukai_attempts NOT NULL tanpa default,
        // jadi wajib diisi placeholder dulu (0), baru di-UPDATE nilai
        // sebenarnya setelah selesai dihitung di bawah.
        $stmt = $db->prepare("INSERT INTO choukai_attempts (choukai_id, user_id, nilai, started_at, status) VALUES (?, ?, 0, NOW(), 'in_progress')");
        $stmt->execute([$choukaiId, $user['id']]);
        $attemptId = (int)$db->lastInsertId();

        $totalCorrect = 0;
        $insertAns = $db->prepare("INSERT INTO choukai_answers (attempt_id, question_id, selected_option, is_correct) VALUES (?, ?, ?, ?)");

        foreach ($questions as $q) {
            $selected  = $_POST['jawaban'][$q['id']] ?? null;
            $selected  = in_array($selected, ['a', 'b', 'c', 'd'], true) ? $selected : null;
            $isCorrect = ($selected !== null && $selected === $q['kunci_jawaban']) ? 1 : 0;
            if ($isCorrect) $totalCorrect++;
            $insertAns->execute([$attemptId, $q['id'], $selected, $isCorrect]);
        }

        $totalQuestions = count($questions);
        // Kolom nilai di database bertipe INT (bukan desimal), jadi dibulatkan ke bilangan bulat
        $nilai = (int)round(($totalCorrect / $totalQuestions) * 100);

        $db->prepare("UPDATE choukai_attempts SET finished_at = NOW(), nilai = ?, status = 'finished' WHERE id = ?")
           ->execute([$nilai, $attemptId]);

        $result = [
            'nilai'          => $nilai,
            'total_correct'  => $totalCorrect,
            'total_questions'=> $totalQuestions,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — 聴解 <?= htmlspecialchars($choukai['judul']) ?></title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .choukai-card { background: var(--card-bg, #fff); border: 1.5px solid var(--card-border, #e2e8f0); border-radius: 18px; padding: 20px; margin-bottom: 16px; }
    .audio-player { width: 100%; margin: 12px 0; border-radius: 30px; }
    .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: .75rem; font-weight: 700; }

    .soal-block { border-top: 1px solid var(--card-border, #e2e8f0); padding-top: 18px; margin-top: 18px; }
    .soal-number { font-size: .78rem; font-weight: 700; color: var(--torii); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
    .soal-text { font-size: .98rem; font-weight: 700; margin-bottom: 12px; }
    .opsi-label {
      display: flex; align-items: center; gap: 10px;
      padding: 12px 14px; min-height: 44px; border-radius: 12px;
      border: 1.5px solid var(--card-border, #e2e8f0);
      margin-bottom: 8px; cursor: pointer; font-size: .88rem;
      transition: border-color .15s, background .15s;
    }
    .opsi-label:hover { border-color: var(--torii); }
    .opsi-label input { width: 18px; height: 18px; flex-shrink: 0; cursor: pointer; }
    .opsi-label.checked { border-color: var(--torii); background: rgba(183,75,75,.06); }

    .btn-finish {
      width: 100%; padding: 13px; min-height: 48px; border-radius: 10px; border: none;
      background: linear-gradient(135deg, var(--torii, #b74b4b), #a03020);
      color: #fff; font-family: 'Cinzel', serif; font-size: .9rem; font-weight: 700; letter-spacing: .02em;
      cursor: pointer; margin-top: 20px;
    }
    .btn-finish:hover { opacity: .92; }

    .result-box { text-align: center; padding: 30px 20px; }
    .result-score { font-size: 2.6rem; font-weight: 900; color: var(--torii); margin: 10px 0; }
    .result-sub { color: var(--mist); font-size: .88rem; margin-bottom: 20px; }

    .transcript-box {
      background: rgba(0,0,0,.03); border-radius: 12px; padding: 16px;
      font-size: .85rem; line-height: 1.7; margin-top: 14px; white-space: pre-wrap;
    }

    .alert-warn {
      padding: 12px 16px; border-radius: 12px; margin-bottom: 16px;
      background: rgba(183,75,75,.08); color: var(--torii); border: 1px solid rgba(183,75,75,.25);
      font-size: .88rem;
    }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="topbar-brand">桜 Sakura — 聴解</div>
    <a href="choukai.php" class="ann-bell-btn">&larr; Daftar Choukai</a>
  </header>

  <main class="dashboard-main" style="padding-top: 80px; max-width: 600px; margin: 0 auto;">

    <?php if ($result): ?>
      <!-- ═══ HASIL ═══ -->
      <div class="choukai-card result-box">
        <div style="font-size:.85rem; color:var(--mist);">Latihan Selesai</div>
        <div class="result-score"><?= $result['nilai'] ?></div>
        <div class="result-sub">
          Benar <?= $result['total_correct'] ?> dari <?= $result['total_questions'] ?> soal
        </div>
        <div style="display:flex; gap:10px; justify-content:center; margin-top:10px;">
          <a href="choukai_kerjakan.php?id=<?= $choukai['id'] ?>" class="bab-btn bab-btn-primary" style="padding:10px 18px; font-size:.85rem; text-decoration:none;">Ulangi Latihan</a>
          <a href="choukai.php" class="bab-btn" style="padding:10px 18px; font-size:.85rem; text-decoration:none; border:1.5px solid var(--card-border);">Kembali</a>
        </div>
      </div>

      <?php if (!empty($choukai['transcript_text'])): ?>
      <div class="choukai-card">
        <div style="font-weight:700; font-size:.9rem; margin-bottom:6px;">文字起こし (Moji Okoshi) - Transkrip</div>
        <div class="transcript-box"><?= htmlspecialchars($choukai['transcript_text']) ?></div>
      </div>
      <?php endif; ?>

    <?php else: ?>
      <!-- ═══ FORM PENGERJAAN ═══ -->
      <div class="choukai-card">
        <h2 style="font-size:1.2rem; font-weight:800; margin:0 0 4px;"><?= htmlspecialchars($choukai['judul']) ?></h2>
        <?php if (!empty($choukai['deskripsi'])): ?>
          <p style="font-size:.85rem; color:var(--mist); margin:0 0 10px;"><?= htmlspecialchars($choukai['deskripsi']) ?></p>
        <?php endif; ?>

        <audio controls class="audio-player">
          <source src="<?= htmlspecialchars($choukai['audio_url']) ?>" type="audio/mpeg">
          Browser kamu tidak mendukung pemutar audio.
        </audio>
      </div>

      <?php if ($message): ?>
        <div class="alert-warn"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <?php if (empty($questions)): ?>
        <div class="choukai-card" style="text-align:center; color:var(--mist);">
          Materi ini belum memiliki soal.
        </div>
      <?php else: ?>
        <form method="POST">
          <input type="hidden" name="action" value="submit_jawaban">
          <div class="choukai-card">
            <?php foreach ($questions as $i => $q): ?>
              <div class="soal-block" style="<?= $i === 0 ? 'border-top:none; margin-top:0; padding-top:0;' : '' ?>">
                <div class="soal-number">Soal <?= $i + 1 ?> dari <?= count($questions) ?></div>
                <div class="soal-text"><?= htmlspecialchars($q['pertanyaan']) ?></div>

                <?php foreach (['a', 'b', 'c', 'd'] as $opt): ?>
                  <?php $optText = $q['opsi_' . $opt]; ?>
                  <?php if ($optText === null || $optText === '') continue; ?>
                  <label class="opsi-label">
                    <input type="radio" name="jawaban[<?= $q['id'] ?>]" value="<?= $opt ?>" required>
                    <span><strong><?= strtoupper($opt) ?>.</strong> <?= htmlspecialchars($optText) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>

            <button type="submit" class="btn-finish">Selesai &amp; Lihat Nilai</button>
          </div>
        </form>
      <?php endif; ?>
    <?php endif; ?>

  </main>

  <script src="js/theme.js"></script>
  <script>
    // Highlight pilihan yang lagi dicentang biar keliatan jelas
    document.querySelectorAll('input[type="radio"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        const name = this.name;
        document.querySelectorAll(`input[name="${CSS.escape(name)}"]`).forEach(function (r) {
          r.closest('.opsi-label').classList.toggle('checked', r.checked);
        });
      });
    });
  </script>
</body>
</html>
