/**
 * RÉUSSITE+ — app.js
 */

document.addEventListener('DOMContentLoaded', () => {

  // ─── Lucide icons ─────────────────────────────────────────
  if (typeof lucide !== 'undefined') lucide.createIcons();

  // ─── Flash messages auto-hide ─────────────────────────────
  document.querySelectorAll('.alert').forEach(el => {
    if (el.dataset.autohide === 'false') return;
    setTimeout(() => {
      el.style.transition = 'opacity .5s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    }, 5000);
  });

  // ─── Mobile sidebar ───────────────────────────────────────
  const sidebar = document.querySelector('.sidebar');
  const menuBtn = document.getElementById('menuToggle');
  const overlay = document.getElementById('sidebarOverlay');

  function openSidebar() {
    sidebar?.classList.add('open');
    overlay?.classList.add('active');
    document.body.style.overflow = 'hidden';
    menuBtn?.setAttribute('aria-expanded', 'true');
  }
  function closeSidebar() {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('active');
    document.body.style.overflow = '';
    menuBtn?.setAttribute('aria-expanded', 'false');
  }

  menuBtn?.addEventListener('click', () => {
    sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
  });
  overlay?.addEventListener('click', closeSidebar);

  document.querySelectorAll('.nav-item').forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 768) closeSidebar();
    });
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeSidebar();
  });

  // ─── Sidebar collapse desktop ─────────────────────────────
  const collapseBtn = document.getElementById('sidebarCollapseBtn');
  const COLLAPSE_KEY = 'rp_sidebar_collapsed';

  // Génère les tooltips depuis les labels
  document.querySelectorAll('.nav-item').forEach(item => {
    const label = item.querySelector('.nav-label');
    if (label && !item.dataset.tooltip) {
      item.dataset.tooltip = label.textContent.trim();
    }
  });

  function applySidebarCollapse(animate) {
    if (!sidebar || window.innerWidth <= 768) return;
    const isCollapsed = localStorage.getItem(COLLAPSE_KEY) === '1';
    if (!animate) sidebar.classList.add('no-transition');
    sidebar.classList.toggle('collapsed', isCollapsed);
    if (!animate) {
      sidebar.offsetHeight; // force reflow
      sidebar.classList.remove('no-transition');
    }
  }

  collapseBtn?.addEventListener('click', () => {
    if (window.innerWidth <= 768) return;
    const willCollapse = !sidebar.classList.contains('collapsed');
    localStorage.setItem(COLLAPSE_KEY, willCollapse ? '1' : '0');
    applySidebarCollapse(true);
  });

  applySidebarCollapse(false);
  window.addEventListener('resize', () => applySidebarCollapse(false));

  // ─── Dark mode ────────────────────────────────────────────
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    const btn = document.getElementById('themeToggle');
    if (btn) {
      btn.innerHTML = theme === 'dark'
        ? '<i data-lucide="sun"></i>'
        : '<i data-lucide="moon"></i>';
      if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [btn] });
    }
  }

  const savedTheme = localStorage.getItem('theme') || 'light';
  applyTheme(savedTheme);

  document.getElementById('themeToggle')?.addEventListener('click', () => {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    const next = current === 'dark' ? 'light' : 'dark';
    localStorage.setItem('theme', next);
    applyTheme(next);
  });

  // ─── Notification badge polling ───────────────────────────
  async function refreshNotifBadge() {
    try {
      const r = await fetch('/reussiteplus/api/notifications.php?count=1');
      const d = await r.json();
      const badge = document.querySelector('.notif-badge');
      if (badge) {
        badge.textContent = d.count > 0 ? d.count : '';
        badge.style.display = d.count > 0 ? 'inline-flex' : 'none';
      }
    } catch (_) {}
  }
  if (document.querySelector('.notif-badge')) {
    refreshNotifBadge();
    setInterval(refreshNotifBadge, 60_000);
  }

  // ─── Confirm actions dangereuses ──────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm || 'Confirmer cette action ?')) {
        e.preventDefault();
      }
    });
  });

  // ─── Copy to clipboard ────────────────────────────────────
  window.copyText = (text, btn) => {
    navigator.clipboard?.writeText(text).then(() => {
      const orig = btn?.textContent;
      if (btn) { btn.textContent = '✓ Copié !'; setTimeout(() => btn.textContent = orig, 2000); }
    });
  };

  // ─── Password visibility toggle ───────────────────────────
  document.querySelectorAll('.pwd-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.querySelector(btn.dataset.target);
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      btn.textContent = input.type === 'password' ? '👁' : '🙈';
    });
  });

  // ─── Auto-submit sur changement de select ─────────────────
  document.querySelectorAll('[data-autosubmit]').forEach(el => {
    el.addEventListener('change', () => el.closest('form')?.submit());
  });

});

// ─── CSRF token helper (global) ───────────────────────────
function getCsrfToken() {
  return document.querySelector('[name="csrf_token"]')?.value || '';
}
