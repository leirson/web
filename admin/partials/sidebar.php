<?php require_once __DIR__ . '/../../includes/functions.php'; requireAdmin(); ?>
<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <i class="fas fa-rocket"></i>
    <span>AppVendas</span>
  </div>
  <ul class="sidebar-menu">
    <li class="menu-section">Principal</li>
    <li><a href="dashboard.php" <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'class="active"' : '' ?>>
      <i class="fas fa-gauge-high"></i><span>Dashboard</span></a></li>
    <li class="menu-section">Catálogo</li>
    <li><a href="produtos.php" <?= in_array(basename($_SERVER['PHP_SELF']),['produtos.php','produto_form.php']) ? 'class="active"' : '' ?>>
      <i class="fas fa-box-open"></i><span>Produtos</span></a></li>
    <li class="menu-section">Vendas</li>
    <li><a href="pedidos.php" <?= basename($_SERVER['PHP_SELF']) === 'pedidos.php' ? 'class="active"' : '' ?>>
      <i class="fas fa-shopping-cart"></i><span>Pedidos</span></a></li>
    <li class="menu-section">Configurações</li>
    <li><a href="configuracoes.php" <?= basename($_SERVER['PHP_SELF']) === 'configuracoes.php' ? 'class="active"' : '' ?>>
      <i class="fas fa-sliders"></i><span>Personalização</span></a></li>
    <li><a href="conta.php" <?= basename($_SERVER['PHP_SELF']) === 'conta.php' ? 'class="active"' : '' ?>>
      <i class="fas fa-user-gear"></i><span>Minha Conta</span></a></li>
    <li class="menu-divider"></li>
    <li><a href="logout.php"><i class="fas fa-right-from-bracket"></i><span>Sair</span></a></li>
  </ul>
  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>" target="_blank"><i class="fas fa-eye"></i> Ver site</a>
  </div>
</nav>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
