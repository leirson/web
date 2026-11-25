/* ============================================================
   ADMIN.JS — JavaScript do painel administrativo
   Sidebar toggle, interações gerais
   ============================================================ */

'use strict';

// ── SIDEBAR TOGGLE ──────────────────────────────────────────
function toggleSidebar() {
  const sidebar  = document.getElementById('sidebar');
  const overlay  = document.getElementById('sidebarOverlay');
  const isOpen   = sidebar.classList.toggle('open');
  overlay.classList.toggle('open', isOpen);
}

// Fechar ao redimensionar para desktop
window.addEventListener('resize', () => {
  if (window.innerWidth > 768) {
    document.getElementById('sidebar')?.classList.remove('open');
    document.getElementById('sidebarOverlay')?.classList.remove('open');
  }
});

// ── AUTO HIDE ALERTS ────────────────────────────────────────
document.querySelectorAll('.alert').forEach(alert => {
  setTimeout(() => {
    alert.style.transition = 'opacity .5s ease';
    alert.style.opacity    = '0';
    setTimeout(() => alert.remove(), 500);
  }, 5000);
});

// ── CONFIRM DELETE ──────────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm)) e.preventDefault();
  });
});

// ── ACTIVE SIDEBAR LINK ─────────────────────────────────────
(function() {
  const current = window.location.pathname.split('/').pop();
  document.querySelectorAll('.sidebar-menu a').forEach(a => {
    if (a.getAttribute('href') === current) a.classList.add('active');
  });
})();
