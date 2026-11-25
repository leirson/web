<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();

// Estatísticas
$totalProdutos = $db->query("SELECT COUNT(*) FROM produtos WHERE ativo = 1")->fetchColumn();
$totalPedidos  = $db->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
$pedidosPagos  = $db->query("SELECT COUNT(*) FROM pedidos WHERE status = 'pago'")->fetchColumn();
$totalRecebido = $db->query("SELECT COALESCE(SUM(valor_implantacao),0) FROM pedidos WHERE status = 'pago'")->fetchColumn();
$pedidosRecentes = $db->query("SELECT p.*, pr.nome AS produto_nome FROM pedidos p LEFT JOIN produtos pr ON p.produto_id = pr.id ORDER BY p.criado_em DESC LIMIT 8")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="main-content">
  <?php include __DIR__ . '/partials/topbar.php'; ?>
  <div class="page-body">
    <div class="page-header">
      <h1><i class="fas fa-gauge-high"></i> Dashboard</h1>
      <p>Bem-vindo, <?= sanitize($_SESSION['admin_nome']) ?>!</p>
    </div>

    <div class="stats-grid">
      <div class="stat-card stat-purple">
        <div class="stat-icon"><i class="fas fa-box-open"></i></div>
        <div class="stat-info">
          <span class="stat-value"><?= $totalProdutos ?></span>
          <span class="stat-label">Produtos Ativos</span>
        </div>
      </div>
      <div class="stat-card stat-blue">
        <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
        <div class="stat-info">
          <span class="stat-value"><?= $totalPedidos ?></span>
          <span class="stat-label">Total de Pedidos</span>
        </div>
      </div>
      <div class="stat-card stat-green">
        <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
        <div class="stat-info">
          <span class="stat-value"><?= $pedidosPagos ?></span>
          <span class="stat-label">Pedidos Pagos</span>
        </div>
      </div>
      <div class="stat-card stat-amber">
        <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
        <div class="stat-info">
          <span class="stat-value"><?= formatMoney((float)$totalRecebido) ?></span>
          <span class="stat-label">Total Recebido</span>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2><i class="fas fa-clock-rotate-left"></i> Pedidos Recentes</h2>
        <a href="pedidos.php" class="btn btn-sm btn-outline">Ver todos</a>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Cliente</th>
              <th>Produto</th>
              <th>Implantação</th>
              <th>Status</th>
              <th>Data</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pedidosRecentes as $p): ?>
            <tr>
              <td><?= $p['id'] ?></td>
              <td><?= sanitize($p['cliente_nome']) ?></td>
              <td><?= sanitize($p['produto_nome']) ?></td>
              <td><?= formatMoney((float)$p['valor_implantacao']) ?></td>
              <td>
                <span class="badge badge-<?= $p['status'] ?>">
                  <?php $statusLabel = ['pago'=>'Pago','aguardando'=>'Aguardando','cancelado'=>'Cancelado']; echo isset($statusLabel[$p['status']]) ? $statusLabel[$p['status']] : $p['status']; ?>
                </span>
              </td>
              <td><?= date('d/m/Y H:i', strtotime($p['criado_em'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($pedidosRecentes)): ?>
            <tr><td colspan="6" class="text-center text-muted">Nenhum pedido encontrado.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
