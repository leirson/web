<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db  = getDB();
$msg = '';
$err = '';

// ── Excluir produto ────────────────────────────────────────────────────────
if (isset($_GET['excluir']) && is_numeric($_GET['excluir'])) {
    startSession();
    $token = $_GET['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        die("Token de segurança inválido.");
    }
    $id = (int)$_GET['excluir'];

    // Deletar imagens físicas
    $imgs = $db->prepare("SELECT caminho FROM produto_imagens WHERE produto_id = ?");
    $imgs->execute([$id]);
    foreach ($imgs->fetchAll() as $img) {
        deletarArquivo($img['caminho']);
    }

    // Deletar ZIP e PDF manual físicos (se existirem)
    $stmtArqs = $db->prepare("SELECT arquivo_zip, arquivo_manual FROM produtos WHERE id = ? LIMIT 1");
    $stmtArqs->execute([$id]);
    $arqs = $stmtArqs->fetch(PDO::FETCH_ASSOC);
    $arqs['arquivo_apresentacao'] = '';
    try {
        $stmtApres = $db->prepare("SELECT arquivo_apresentacao FROM produtos WHERE id = ? LIMIT 1");
        $stmtApres->execute([$id]);
        $rowA = $stmtApres->fetch(PDO::FETCH_ASSOC);
        if ($rowA) $arqs['arquivo_apresentacao'] = $rowA['arquivo_apresentacao'] ?? '';
    } catch (PDOException $e) {
        // Coluna ainda não existe — ignorar
    }

    if (!empty($arqs['arquivo_zip'])) {
        $zipFisico = UPLOAD_DIR . $arqs['arquivo_zip'];
        if (file_exists($zipFisico)) unlink($zipFisico);
    }
    if (!empty($arqs['arquivo_manual'])) {
        $pdfFisico = UPLOAD_DIR . $arqs['arquivo_manual'];
        if (file_exists($pdfFisico)) unlink($pdfFisico);
    }
    if (!empty($arqs['arquivo_apresentacao'])) {
        $apresFisico = UPLOAD_DIR . $arqs['arquivo_apresentacao'];
        if (file_exists($apresFisico)) unlink($apresFisico);
    }

    // Deletar registros do banco
    $db->prepare("DELETE FROM produto_imagens WHERE produto_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM produtos WHERE id = ?")->execute([$id]);

    header("Location: produtos.php?msg=" . urlencode("Produto #$id excluído com sucesso."));
    exit;
}

// Mensagem via GET (após redirect)
if (isset($_GET['msg'])) {
    $msg = sanitize($_GET['msg']);
}

// ── Listar produtos ────────────────────────────────────────────────────────
$produtos = $db->query(
    "SELECT p.*, COUNT(pi.id) AS total_imagens
     FROM produtos p
     LEFT JOIN produto_imagens pi ON p.id = pi.produto_id
     GROUP BY p.id ORDER BY p.ordem ASC, p.criado_em DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Produtos — Admin</title>
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
        <h1><i class="fas fa-box-open"></i> Produtos</h1>
        <p>Gerencie os aplicativos disponíveis para venda.</p>
      </div>
      <a href="produto_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Produto</a>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-circle-check"></i> <?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><i class="fas fa-triangle-exclamation"></i> <?= $err ?></div><?php endif; ?>

    <div class="card">
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nome</th>
              <th>Implantação</th>
              <th>Anual</th>
              <th>Imagens</th>
              <th>ZIP</th>
              <th>PDF</th>
              <th>PPTX</th>
              <th>Status</th>
              <th>Ordem</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($produtos as $p): ?>
            <?php
              // Verificar se o ZIP existe fisicamente
              $temZip    = !empty($p['arquivo_zip']);
              $zipFisico = $temZip ? UPLOAD_DIR . $p['arquivo_zip'] : '';
              $zipOk     = $temZip && file_exists($zipFisico);
            ?>
            <tr>
              <td><?= $p['id'] ?></td>
              <td><strong><?= sanitize($p['nome']) ?></strong></td>
              <td><?= formatMoney((float)$p['valor_implantacao']) ?></td>
              <td><?= formatMoney((float)$p['valor_anual']) ?></td>
              <td><span class="badge badge-info"><?= $p['total_imagens'] ?> foto(s)</span></td>
              <td>
                <?php if ($zipOk): ?>
                  <a href="download.php?produto_id=<?= $p['id'] ?>"
                     class="badge badge-zip" title="Baixar ZIP" target="_blank">
                    <i class="fas fa-file-zipper"></i> ZIP
                  </a>
                <?php elseif ($temZip): ?>
                  <span class="badge badge-cancelado" title="Arquivo registrado mas não encontrado no servidor">
                    <i class="fas fa-triangle-exclamation"></i> Ausente
                  </span>
                <?php else: ?>
                  <span class="badge badge-neutral">—</span>
                <?php endif; ?>
              </td>
              <?php
                $temPdf    = !empty($p['arquivo_manual']);
                $pdfFisico = $temPdf ? UPLOAD_DIR . $p['arquivo_manual'] : '';
                $pdfOk     = $temPdf && file_exists($pdfFisico);
              ?>
              <td>
                <?php if ($pdfOk): ?>
                  <a href="manual.php?produto_id=<?= $p['id'] ?>"
                     class="badge badge-pdf" title="Visualizar Manual PDF" target="_blank">
                    <i class="fas fa-file-pdf"></i> PDF
                  </a>
                <?php elseif ($temPdf): ?>
                  <span class="badge badge-cancelado" title="Arquivo registrado mas não encontrado no servidor">
                    <i class="fas fa-triangle-exclamation"></i> Ausente
                  </span>
                <?php else: ?>
                  <span class="badge badge-neutral">—</span>
                <?php endif; ?>
              </td>
              <?php
                $temPptx    = !empty($p['arquivo_apresentacao']);
                $pptxFisico = $temPptx ? UPLOAD_DIR . $p['arquivo_apresentacao'] : '';
                $pptxOk     = $temPptx && file_exists($pptxFisico);
              ?>
              <td>
                <?php if ($pptxOk): ?>
                  <a href="apresentacao.php?produto_id=<?= $p['id'] ?>"
                     class="badge badge-pptx" title="Baixar Apresentação PPTX" target="_blank">
                    <i class="fas fa-file-powerpoint"></i> PPTX
                  </a>
                <?php elseif ($temPptx): ?>
                  <span class="badge badge-cancelado" title="Arquivo registrado mas não encontrado no servidor">
                    <i class="fas fa-triangle-exclamation"></i> Ausente
                  </span>
                <?php else: ?>
                  <span class="badge badge-neutral">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($p['ativo']): ?>
                  <span class="badge badge-pago">Ativo</span>
                <?php else: ?>
                  <span class="badge badge-cancelado">Inativo</span>
                <?php endif; ?>
              </td>
              <td><?= $p['ordem'] ?></td>
              <td class="actions">
                <a href="produto_form.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline" title="Editar">
                  <i class="fas fa-pencil"></i>
                </a>
                <a href="produtos.php?excluir=<?= $p['id'] ?>&csrf_token=<?= csrfToken() ?>"
                   class="btn btn-sm btn-danger"
                   onclick="return confirm('Excluir produto #<?= $p['id'] ?> (<?= sanitize(addslashes($p['nome'])) ?>)?\n\nIsso irá remover permanentemente:\n• Todas as imagens\n• O arquivo ZIP (se houver)\n• O manual PDF (se houver)\n• A apresentação PPTX (se houver)\n• O registro do banco de dados')"
                   title="Excluir produto">
                  <i class="fas fa-trash"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($produtos)): ?>
            <tr>
              <td colspan="11" class="text-center text-muted">
                Nenhum produto cadastrado. <a href="produto_form.php">Adicionar produto</a>.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</body>
</html>
