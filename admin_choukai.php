<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
if (($user['role'] ?? '') !== 'admin') {
    header('Location: beranda.php');
    exit;
}

$db  = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_choukai') {
        $judul     = trim($_POST['judul'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $audioUrl  = trim($_POST['audio_url'] ?? '');
        if ($judul && $audioUrl) {
            $stmt = $db->prepare("INSERT INTO choukai (judul, deskripsi, audio_url) VALUES (?, ?, ?)");
            $stmt->execute([$judul, $deskripsi, $audioUrl]);
            $msg = 'Materi Choukai berhasil disimpan.';
        }
    } elseif ($action === 'add_question') {
        $choukaiId  = (int)($_POST['choukai_id'] ?? 0);
        $pertanyaan = trim($_POST['pertanyaan'] ?? '');
        $opsiA      = trim($_POST['opsi_a'] ?? '');
        $opsiB      = trim($_POST['opsi_b'] ?? '');
        $opsiC      = trim($_POST['opsi_c'] ?? '');
        $opsiD      = trim($_POST['opsi_d'] ?? '');
        $kunci      = $_POST['kunci_jawaban'] ?? 'a';

        if ($choukaiId && $pertanyaan && $opsiA && $opsiB) {
            $stmt = $db->prepare("INSERT INTO choukai_questions (choukai_id, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$choukaiId, $pertanyaan, $opsiA, $opsiB, $opsiC, $opsiD, $kunci]);
            $msg = 'Soal berhasil ditambahkan.';
        }
    } elseif ($action === 'delete_choukai') {
        $delId = (int)($_POST['del_id'] ?? 0);
        if ($delId > 0) {
            $db->prepare("DELETE FROM choukai WHERE id = ?")->execute([$delId]);
            $msg = 'Materi berhasil dihapus.';
        }
    }
}

$listChoukai = $db->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM choukai_questions q WHERE q.choukai_id = c.id) as total_soal 
    FROM choukai c 
    ORDER BY c.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — 聴解 Kelola Choukai</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .admin-container { padding-top: 80px; max-width: 680px; margin: 0 auto; padding-left: 15px; padding-right: 15px; }
    .admin-card { background: var(--card-bg, #ffffff); border: 1.5px solid var(--card-border, #e2e8f0); border-radius: 18px; padding: 22px; margin-bottom: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); }
    .card-title { font-size: 1.08rem; font-weight: 800; color: var(--torii, #b74b4b); margin-top: 0; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
    .card-title-kanji { font-size: 1.15rem; color: var(--torii, #b74b4b); font-weight: 900; }
    .form-group { margin-bottom: 14px; }
    .form-label { display: block; font-size: .84rem; font-weight: 700; color: var(--text, #333); margin-bottom: 6px; }
    .form-input { width: 100%; box-sizing: border-box; padding: 11px 14px; border-radius: 12px; border: 1.5px solid var(--card-border, #e2e8f0); background: var(--input-bg, #fafafa); color: var(--text, #333); font-family: inherit; font-size: .88rem; transition: border-color .2s; }
    .form-input:focus { outline: none; border-color: var(--torii, #b74b4b); background: #fff; }
    .options-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (max-width: 480px) { .options-grid { grid-template-columns: 1fr; } }
    .btn-submit {
      width: 100%; padding: 12px; min-height: 44px; border-radius: 10px; border: none;
      background: linear-gradient(135deg, var(--torii, #b74b4b), #a03020);
      color: #fff; font-family: 'Cinzel', serif; font-size: .85rem; font-weight: 700; letter-spacing: .02em;
      cursor: pointer; transition: transform .15s, opacity .15s; margin-top: 6px;
    }
    .btn-submit:hover { opacity: .95; transform: translateY(-1px); }
    .btn-submit-green { background: linear-gradient(135deg, var(--bamboo, #4a7c59), #3a6347); }
    .item-row { padding: 14px 0; border-bottom: 1px solid var(--card-border, #eee); display: flex; justify-content: space-between; align-items: center; }
    .item-row:last-child { border-bottom: none; }
  </style>
</head>
<body>
  <div class="page-loader" id="pageLoader"><span class="loader-kanji">桜</span></div>
  <div class="asanoha-bg"></div>

  <header class="topbar">
    <div class="topbar-brand">桜 Admin — <span style="color:var(--torii);">聴解</span> Kelola Choukai</div>
    <a href="beranda.php" class="ann-bell-btn" style="text-decoration:none;">
      <span style="margin-right:4px;">戻る</span>Kembali
    </a>
  </header>

  <main class="admin-container">
    <?php if ($msg): ?>
      <div style="padding:12px 16px; background:rgba(74,124,89,.12); color:var(--bamboo, #4a7c59); border:1px solid rgba(74,124,89,.3); border-radius:12px; margin-bottom:18px; font-weight:700; font-size:.88rem;">
        <?= htmlspecialchars($msg) ?>
      </div>
    <?php endif; ?>

    <!-- FORM TAMBAH MATERI CHOUKAI -->
    <div class="admin-card">
      <h3 class="card-title">
        <span class="card-title-kanji">追加</span> Tambah Materi Choukai
      </h3>
      <form method="POST">
        <input type="hidden" name="action" value="add_choukai">
        <div class="form-group">
          <label class="form-label">Judul Materi</label>
          <input type="text" name="judul" class="form-input" placeholder="Contoh: Choukai Bab 1 — Percakapan di Sekolah" required>
        </div>
        
        <div class="form-group">
          <label class="form-label">Deskripsi Singkat</label>
          <textarea name="deskripsi" class="form-input" rows="2" placeholder="Penjelasan singkat mengenai materi listening..."></textarea>
        </div>
        
        <div class="form-group">
          <label class="form-label">URL File Audio (MP3 / Direct Link)</label>
          <input type="url" name="audio_url" class="form-input" placeholder="https://domain.com/audio.mp3" required>
        </div>
        
        <button type="submit" class="btn-submit">
          <span style="margin-right:4px;">保存</span>Simpan Materi
        </button>
      </form>
    </div>

    <!-- FORM TAMBAH SOAL -->
    <?php if (!empty($listChoukai)): ?>
    <div class="admin-card">
      <h3 class="card-title">
        <span class="card-title-kanji">問題</span> Tambah Soal
      </h3>
      <form method="POST">
        <input type="hidden" name="action" value="add_question">
        
        <div class="form-group">
          <label class="form-label">Pilih Materi Choukai</label>
          <select name="choukai_id" class="form-input" required>
            <?php foreach ($listChoukai as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['judul']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Pertanyaan Soal</label>
          <textarea name="pertanyaan" class="form-input" rows="2" placeholder="Tuliskan pertanyaan dari audio..." required></textarea>
        </div>

        <div class="options-grid">
          <div class="form-group">
            <label class="form-label">Opsi A</label>
            <input type="text" name="opsi_a" class="form-input" required>
          </div>
          <div class="form-group">
            <label class="form-label">Opsi B</label>
            <input type="text" name="opsi_b" class="form-input" required>
          </div>
          <div class="form-group">
            <label class="form-label">Opsi C</label>
            <input type="text" name="opsi_c" class="form-input" required>
          </div>
          <div class="form-group">
            <label class="form-label">Opsi D</label>
            <input type="text" name="opsi_d" class="form-input" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Kunci Jawaban</label>
          <select name="kunci_jawaban" class="form-input">
            <option value="a">A</option>
            <option value="b">B</option>
            <option value="c">C</option>
            <option value="d">D</option>
          </select>
        </div>

        <button type="submit" class="btn-submit btn-submit-green">
          <span style="margin-right:4px;">追加</span>Tambah Soal
        </button>
      </form>
    </div>
    <?php endif; ?>

    <!-- DAFTAR MATERI -->
    <div class="admin-card">
      <h3 class="card-title" style="color:var(--text, #333);">
        <span class="card-title-kanji" style="color:var(--bamboo, #4a7c59);">一覧</span> Daftar Materi Choukai
      </h3>
      <?php if (empty($listChoukai)): ?>
        <p style="color:var(--mist, #888); font-size:.85rem; margin:10px 0 0;">Belum ada materi dibuat.</p>
      <?php else: ?>
        <?php foreach ($listChoukai as $item): ?>
          <div class="item-row">
            <div>
              <strong style="display:block; font-size:.90rem; color:var(--text, #333);"><?= htmlspecialchars($item['judul']) ?></strong>
              <small style="color:var(--mist, #888); font-size:.78rem;"><?= $item['total_soal'] ?> Soal &nbsp;·&nbsp; <?= date('d M Y', strtotime($item['created_at'])) ?></small>
            </div>
            <form method="POST" onsubmit="return confirm('Hapus materi ini beserta seluruh soalnya?')" style="margin:0;">
              <input type="hidden" name="action" value="delete_choukai">
              <input type="hidden" name="del_id" value="<?= $item['id'] ?>">
              <button type="submit" style="background:rgba(183,75,75,.08); border:1px solid rgba(183,75,75,.2); border-radius:8px; color:var(--torii, #b74b4b); cursor:pointer; font-weight:700; font-size:.78rem; padding:5px 10px;">
                <span style="margin-right:3px;">削除</span>Hapus
              </button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>

  <script src="js/theme.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const loader = document.getElementById('pageLoader');
      if (loader) {
        loader.style.transition = 'opacity 0.3s ease';
        loader.style.opacity = '0';
        setTimeout(function() {
          loader.style.display = 'none';
        }, 300);
      }
    });
  </script>
</body>
</html>