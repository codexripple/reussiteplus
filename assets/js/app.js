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
const sidebar  = document.querySelector('.sidebar');
const menuBtn  = document.getElementById('menuToggle');
const overlay  = document.getElementById('sidebarOverlay');

function openSidebar() {
  sidebar?.classList.add('open');
  overlay?.classList.add('active');
  document.body.style.overflow = 'hidden'; // empêche le scroll du fond
  menuBtn?.setAttribute('aria-expanded', 'true');
}
function closeSidebar() {
  sidebar?.classList.remove('open');
  overlay?.classList.remove('active');
  document.body.style.overflow = '';
  menuBtn?.setAttribute('aria-expanded', 'false');
}

if (menuBtn && sidebar) {
  menuBtn.addEventListener('click', () => {
    sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
  });
}
if (overlay) {
  overlay.addEventListener('click', closeSidebar);
}

// Fermer sidebar quand on clique sur un lien nav (mobile)
document.querySelectorAll('.nav-item').forEach(link => {
  link.addEventListener('click', () => {
    if (window.innerWidth <= 768) closeSidebar();
  });
});

// Fermer avec Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeSidebar();
});

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

// ─── Dark mode ───────────────────────────────────────────────
function initTheme() {
  const saved = localStorage.getItem('theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);
  updateThemeBtn(saved);
}

function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme') || 'light';
  const next    = current === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('theme', next);
  updateThemeBtn(next);
}

function updateThemeBtn(theme) {
  const btn = document.getElementById('themeToggle');
  if (btn) {
    btn.innerHTML = theme === 'dark'
      ? '<i data-lucide="sun"></i>'
      : '<i data-lucide="moon"></i>';
    lucide?.createIcons({ nodes: [btn] });
  }
}

initTheme();

// Initialize Lucide icons
document.addEventListener('DOMContentLoaded', () => {
  if (typeof lucide !== 'undefined') lucide.createIcons();
});
