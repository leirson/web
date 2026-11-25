<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db  = getDB();
$msg = '';

// ── Excluir pedido ────────────────────────────────────────
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    startSession();
    $token = $_GET['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        die("Token de segurança inválido.");
    }
    $pid = (int)$_GET['excluir'];
    $db->prepare("DELETE FROM pedidos WHERE id = ?")->execute([$pid]);
    header("Location: pedidos.php?msg=" . urlencode("Pedido #$pid excluído com sucesso."));
    exit;
}

// ── Atualizar status ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alterar_status'])) {
    verifyCsrf();
    $pid     = (int)$_POST['pedido_id'];
    $nstatus = $_POST['novo_status'] ?? '';
    if (in_array($nstatus, ['aguardando','pago','cancelado'])) {
        $db->prepare("UPDATE pedidos SET status = ? WHERE id = ?")->execute([$nstatus, $pid]);
        header("Location: pedidos.php?msg=" . urlencode("Status do pedido #$pid atualizado."));
        exit;
    }
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];

// ── Filtros ───────────────────────────────────────────────
$status = $_GET['status'] ?? '';
$busca  = trim($_GET['busca'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where  = '1=1';
$params = [];
if ($status && in_array($status, ['aguardando','pago','cancelado'])) {
    $where   .= ' AND p.status = ?';
    $params[] = $status;
}
if ($busca) {
    $where .= ' AND (p.cliente_nome LIKE ? OR p.cliente_documento LIKE ? OR p.cliente_whatsapp LIKE ? OR p.produto_nome LIKE ?)';
    $b = "%$busca%";
    array_push($params, $b, $b, $b, $b);
}

$stmtTotal = $db->prepare("SELECT COUNT(*) FROM pedidos p WHERE $where");
$stmtTotal->execute($params);
$totalRows  = (int)$stmtTotal->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

$stmt = $db->prepare("SELECT p.* FROM pedidos p WHERE $where ORDER BY p.criado_em DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$pedidos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pedidos — Admin</title>
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
      <div>
        <h1><i class="fas fa-shopping-cart"></i> Pedidos</h1>
        <p>Todos os pedidos realizados no site.</p>
      </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-circle-check"></i> <?= sanitize($msg) ?></div><?php endif; ?>

    <!-- Filtros -->
    <div class="card filter-card">
      <form method="GET" class="filter-form">
        <div class="form-group">
          <label>Buscar</label>
          <input type="text" name="busca" value="<?= sanitize($busca) ?>" placeholder="Nome, CPF/CNPJ, WhatsApp...">
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status">
            <option value="">Todos</option>
            <option value="aguardando" <?= $status === 'aguardando' ? 'selected' : '' ?>>Aguardando</option>
            <option value="pago" <?= $status === 'pago' ? 'selected' : '' ?>>Pago</option>
            <option value="cancelado" <?= $status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
          </select>
        </div>
        <div class="form-group form-group-btn">
          <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
          <a href="pedidos.php" class="btn btn-outline">Limpar</a>
        </div>
      </form>
    </div>

    <div class="card">
      <div class="card-header">
        <h2>Total: <?= $totalRows ?> pedido(s)</h2>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Cliente</th>
              <th>Doc</th>
              <th>Estabelecimento</th>
              <th>WhatsApp</th>
              <th>Produto</th>
              <th>Implantação</th>
              <th>Anual</th>
              <th>Vencimento</th>
              <th>Status</th>
              <th>Data</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pedidos as $p): ?>
            <tr>
              <td><?= $p['id'] ?></td>
              <td><strong><?= sanitize($p['cliente_nome']) ?></strong></td>
              <td><?= sanitize(formatDoc($p['cliente_documento'])) ?></td>
              <td><?= sanitize($p['cliente_estabelecimento']) ?></td>
              <td>
                <a href="https://wa.me/<?= preg_replace('/\D/','',$p['cliente_whatsapp']) ?>" target="_blank" class="link-wa">
                  <i class="fab fa-whatsapp"></i> <?= sanitize($p['cliente_whatsapp']) ?>
                </a>
              </td>
              <td><?= sanitize($p['produto_nome']) ?></td>
              <td><?= formatMoney((float)$p['valor_implantacao']) ?></td>
              <td><?= formatMoney((float)$p['valor_anual']) ?></td>
              <td><?= date('d/m/Y', strtotime($p['vencimento_anual'])) ?></td>
              <td>
                <span class="badge badge-<?= $p['status'] ?>">
                  <?php $statusLabel = ['pago'=>'Pago','aguardando'=>'Aguardando','cancelado'=>'Cancelado']; echo isset($statusLabel[$p['status']]) ? $statusLabel[$p['status']] : $p['status']; ?>
                </span>
              </td>
              <td><?= date('d/m/Y H:i', strtotime($p['criado_em'])) ?></td>
              <td>
                <div class="actions">
                  <button class="btn btn-sm btn-outline" onclick="openStatusModal(<?= $p['id'] ?>, '<?= $p['status'] ?>')" title="Alterar status">
                    <i class="fas fa-pen-to-square"></i>
                  </button>
                  <a href="pedidos.php?excluir=<?= $p['id'] ?>&csrf_token=<?= csrfToken() ?>"
                     class="btn btn-sm btn-danger"
                     onclick="return confirm('Excluir pedido #<?= $p['id'] ?> de <?= addslashes(sanitize($p['cliente_nome'])) ?>? Esta ação não pode ser desfeita.')"
                     title="Excluir pedido">
                    <i class="fas fa-trash"></i>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($pedidos)): ?>
            <tr><td colspan="13" class="text-center text-muted">Nenhum pedido encontrado.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <!-- Paginação -->
      <?php if ($totalPages > 1): ?>
      <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=<?= $i ?>&status=<?= urlencode($status) ?>&busca=<?= urlencode($busca) ?>"
             class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal alterar status -->
<div class="modal-overlay" id="statusModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3><i class="fas fa-pen-to-square"></i> Alterar Status</h3>
      <button onclick="closeStatusModal()" class="modal-close"><i class="fas fa-xmark"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="pedido_id" id="modalPedidoId">
      <input type="hidden" name="alterar_status" value="1">
      <div class="modal-body">
        <div class="form-group">
          <label>Novo Status</label>
          <select name="novo_status" id="modalStatus">
            <option value="aguardando">Aguardando</option>
            <option value="pago">Pago</option>
            <option value="cancelado">Cancelado</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" onclick="closeStatusModal()" class="btn btn-outline">Cancelar</button>
        <button type="submit" class="btn btn-primary">Salvar</button>
      </div>
    </form>
  </div>
</div>
<script>
function openStatusModal(id, status) {
  document.getElementById('modalPedidoId').value = id;
  document.getElementById('modalStatus').value = status;
  document.getElementById('statusModal').classList.add('active');
}
function closeStatusModal() {
  document.getElementById('statusModal').classList.remove('active');
}
</script>
</body>
</html>
