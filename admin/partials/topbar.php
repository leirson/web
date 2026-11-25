<?php require_once __DIR__ . '/../../includes/functions.php'; requireAdmin(); ?>
<div class="topbar">
  <button class="sidebar-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
  </button>
  <div class="topbar-right">
    <span class="admin-name"><i class="fas fa-user-circle"></i> <?= sanitize($_SESSION['admin_nome'] ?? 'Admin') ?></span>
    <a href="logout.php" class="btn-logout" title="Sair"><i class="fas fa-right-from-bracket"></i></a>
  </div>
</div>
