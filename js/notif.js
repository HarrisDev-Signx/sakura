/**
 * js/notif.js — Smart Realtime Notification & Toast Banner
 */
(function () {
  var seenNotifs = JSON.parse(sessionStorage.getItem('seen_notifs') || '[]');

  function createToastContainer() {
    var container = document.getElementById('toastContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toastContainer';
      container.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:10px;max-width:340px;width:calc(100% - 40px);pointer-events:none;';
      document.body.appendChild(container);
    }
    return container;
  }

  function markAnnRead(annId) {
    var fd = new FormData();
    fd.append('action', 'mark_announcement_read');
    fd.append('ann_id', annId);
    fetch('beranda.php', { method: 'POST', body: fd }).catch(function(){});
  }

  function showToast(notif) {
    if (seenNotifs.indexOf(notif.id) !== -1) return;
    seenNotifs.push(notif.id);
    sessionStorage.setItem('seen_notifs', JSON.stringify(seenNotifs));

    var container = createToastContainer();
    var toast = document.createElement('div');
    toast.style.cssText = 'pointer-events:all;background:var(--card-bg, #ffffff);border:1px solid var(--card-border, #e2e8f0);border-left:4px solid var(--torii, #b74b4b);border-radius:14px;padding:14px 16px;box-shadow:0 12px 30px rgba(0,0,0,0.18);transition:all .3s cubic-bezier(.22,1,.36,1);transform:translateY(20px);opacity:0;';

    var html = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">' +
      '<span style="font-size:.78rem;font-weight:800;color:var(--torii, #b74b4b);letter-spacing:.02em;">' + notif.title + '</span>' +
      '<small style="font-size:.72rem;color:var(--text-muted, #888);">' + notif.time + '</small>' +
      '</div>' +
      '<div style="font-size:.88rem;font-weight:600;color:var(--text-main, #333);line-height:1.4;word-break:break-word;">' + notif.message + '</div>';

    if (notif.link) {
      html += '<div style="margin-top:8px;text-align:right;"><a href="' + notif.link + '" class="toast-link-btn" style="display:inline-block;font-size:.75rem;font-weight:700;color:#fff;background:var(--torii, #b74b4b);padding:4px 10px;border-radius:6px;text-decoration:none;">Buka →</a></div>';
    }

    toast.innerHTML = html;
    container.appendChild(toast);

    // Animasi Masuk
    setTimeout(function () {
      toast.style.transform = 'translateY(0)';
      toast.style.opacity = '1';
    }, 50);

    // Auto Klik / Tandai Dibaca jika Pengumuman
    if (notif.type === 'announcement' && notif.ann_id) {
      toast.addEventListener('click', function () {
        markAnnRead(notif.ann_id);
      });
    }

    // Auto Hapus Toast setelah 7 detik
    setTimeout(function () {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(10px)';
      setTimeout(function () {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, 300);
    }, 7000);
  }

  function updateBadgeCount(count) {
    var badge = document.getElementById('annBadge');
    if (badge) {
      if (count > 0) {
        badge.textContent = count;
        badge.style.display = 'flex';
      } else {
        badge.style.display = 'none';
      }
    }
  }

  function fetchNotifications() {
    fetch('notif_stream.php?_=' + new Date().getTime())
      .then(function (res) { return res.json(); })
      .then(function (res) {
        if (res.status === 'ok' && res.data) {
          updateBadgeCount(res.count);
          for (var i = 0; i < res.data.length; i++) {
            showToast(res.data[i]);
          }
        }
      })
      .catch(function () {});
  }

  // Cek saat pertama kali halaman terbuka
  document.addEventListener('DOMContentLoaded', function () {
    fetchNotifications();
    // Auto-check otomatis tiap 5 detik tanpa perlu reload
    setInterval(fetchNotifications, 5000);
  });
})();