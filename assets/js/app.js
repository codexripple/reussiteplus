/**
 * RÉUSSITE+ — app.js
 * Global JavaScript utilities
 */

// ─── Flash messages auto-hide ─────────────────────────────────
document.querySelectorAll('.alert').forEach(el => {
  if (el.dataset.autohide !== 'false') {
    setTimeout(() => {
      el.style.transition = 'opacity .5s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    }, 5000);
  }
});

// ─── CSRF token helper ────────────────────────────────────────
function getCsrfToken() {
  return document.querySelector('[name="csrf_token"]')?.value || '';
}

// ─── Mobile sidebar toggle ────────────────────────────────────
function toggleSidebar() {
  const s = document.getElementById('sidebar');
  const o = document.getElementById('sidebarOverlay');
  if (!s) return;
  s.classList.toggle('open');
  if (o) o.classList.toggle('active');
}
function closeSidebar() {
  const s = document.getElementById('sidebar');
  const o = document.getElementById('sidebarOverlay');
  if (s) s.classList.remove('open');
  if (o) o.classList.remove('active');
}

// Show mobile menu button on small screens
(function() {
  const btn = document.getElementById('menuToggle');
  function checkWidth() {
    if (!btn) return;
    btn.style.display = window.innerWidth <= 768 ? 'flex' : 'none';
  }
  checkWidth();
  window.addEventListener('resize', checkWidth);
})();

// ─── Notification badge polling (toutes les 60s) ──────────────
async function refreshNotifBadge() {
  try {
    const r = await fetch('/reussiteplus/api/notifications.php?count=1');
    const d = await r.json();
    const badge = document.querySelector('.notif-badge');
    if (badge) {
      if (d.count > 0) {
        badge.textContent = d.count;
        badge.style.display = 'inline-flex';
      } else {
        badge.style.display = 'none';
      }
    }
  } catch (_) {}
}
if (document.querySelector('.notif-badge')) {
  refreshNotifBadge();
  setInterval(refreshNotifBadge, 60000);
}

// ─── Confirm dangerous actions ────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.dataset.confirm || 'Confirmer cette action ?')) {
      e.preventDefault();
    }
  });
});

// ─── Copy to clipboard helper ────────────────────────────────
function copyText(text, btn) {
  navigator.clipboard?.writeText(text).then(() => {
    const orig = btn?.textContent;
    if (btn) { btn.textContent = '✓ Copié !'; setTimeout(() => btn.textContent = orig, 2000); }
  });
}

// ─── Password visibility toggle ──────────────────────────────
document.querySelectorAll('.pwd-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = document.querySelector(btn.dataset.target);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? '👁' : '🙈';
  });
});

// ─── Tooltip via title fallback ──────────────────────────────
// (browsers handle title natively; no extra lib needed)

// ─── Auto-submit filter forms on select change ───────────────
document.querySelectorAll('[data-autosubmit]').forEach(el => {
  el.addEventListener('change', () => el.closest('form')?.submit());
});
