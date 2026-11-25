<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db  = getDB();
$msg = '';
$err = '';
$produto = [
    'id' => 0, 'nome' => '', 'descricao' => '',
    'valor_implantacao' => '', 'valor_anual' => '', 'valor_mensal' => '',
    'link_exemplo' => '', 'link_apresentacao' => '', 'link_video' => '',
    'arquivo_zip' => '', 'arquivo_manual' => '', 'arquivo_apresentacao' => '',
    'ativo' => 1, 'ordem' => 0
];
$imagens = [];
$editando = false;

// Carregar para edição
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM produtos WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$_GET['id']]);
    $row = $stmt->fetch();
    if ($row) {
        // Mescla com defaults para garantir que colunas de migrations novas existam
        $produto  = array_merge($produto, $row);
        $editando = true;
        $stmt2    = $db->prepare("SELECT * FROM produto_imagens WHERE produto_id = ? ORDER BY ordem ASC");
        $stmt2->execute([$produto['id']]);
        $imagens  = $stmt2->fetchAll();
    }
}

// Salvar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $id          = (int)($_POST['id'] ?? 0);
    $nome        = trim($_POST['nome'] ?? '');
    $desc        = trim($_POST['descricao'] ?? '');
    $impl        = (float)str_replace(['.', ','], ['', '.'], $_POST['valor_implantacao'] ?? '0');
    $anual       = (float)str_replace(['.', ','], ['', '.'], $_POST['valor_anual']       ?? '0');
    $mensal      = (float)str_replace(['.', ','], ['', '.'], $_POST['valor_mensal']      ?? '0');
    $linkExemplo      = trim($_POST['link_exemplo']      ?? '');
    $linkApresentacao = trim($_POST['link_apresentacao'] ?? '');
    $linkVideo        = trim($_POST['link_video']         ?? '');
    $ativo            = isset($_POST['ativo']) ? 1 : 0;
    $ordem            = (int)($_POST['ordem'] ?? 0);

    // ── Excluir Apresentação PPTX (ação isolada) ──────────────────────────
    if (isset($_POST['excluir_apresentacao']) && $id > 0) {
        try {
            $stmtA = $db->prepare("SELECT arquivo_apresentacao FROM produtos WHERE id = ? LIMIT 1");
            $stmtA->execute([$id]);
            $apresAtual = $stmtA->fetchColumn();
            if ($apresAtual) {
                $apresPath = UPLOAD_DIR . $apresAtual;
                if (file_exists($apresPath)) unlink($apresPath);
            }
            $db->prepare("UPDATE produtos SET arquivo_apresentacao='' WHERE id=?")->execute([$id]);
        } catch (PDOException $e) {
            // Coluna ainda não existe — ignorar
        }
        header("Location: produto_form.php?id=$id&msg=" . urlencode('Apresentação PPTX excluída com sucesso.'));
        exit;
    }

    // ── Excluir Manual PDF (ação isolada) ──────────────────────────────────
    if (isset($_POST['excluir_manual']) && $id > 0) {
        $stmtM = $db->prepare("SELECT arquivo_manual FROM produtos WHERE id = ? LIMIT 1");
        $stmtM->execute([$id]);
        $manualAtual = $stmtM->fetchColumn();
        if ($manualAtual) {
            $manualPath = UPLOAD_DIR . $manualAtual;
            if (file_exists($manualPath)) unlink($manualPath);
        }
        $db->prepare("UPDATE produtos SET arquivo_manual='' WHERE id=?")->execute([$id]);
        header("Location: produto_form.php?id=$id&msg=" . urlencode('Manual PDF excluído com sucesso.'));
        exit;
    }

    // ── Excluir ZIP (ação isolada) ─────────────────────────────────────────
    if (isset($_POST['excluir_zip']) && $id > 0) {
        $stmtZip = $db->prepare("SELECT arquivo_zip FROM produtos WHERE id = ? LIMIT 1");
        $stmtZip->execute([$id]);
        $zipAtual = $stmtZip->fetchColumn();
        if ($zipAtual) {
            $zipPath = UPLOAD_DIR . $zipAtual;
            if (file_exists($zipPath)) unlink($zipPath);
        }
        $db->prepare("UPDATE produtos SET arquivo_zip='' WHERE id=?")->execute([$id]);
        header("Location: produto_form.php?id=$id&msg=" . urlencode('Arquivo ZIP excluído com sucesso.'));
        exit;
    }

    // ── Salvar ordens e descrições das imagens existentes (AJAX inline) ───
    // Se vier apenas a ação de reordenar/descrever sem salvar o produto inteiro
    if (isset($_POST['acao']) && $_POST['acao'] === 'salvar_imagens' && $id > 0) {
        // Salvar descrições
        if (!empty($_POST['img_desc']) && is_array($_POST['img_desc'])) {
            foreach ($_POST['img_desc'] as $imgId => $descImg) {
                $imgId  = (int)$imgId;
                $descImg = trim($descImg);
                $db->prepare("UPDATE produto_imagens SET descricao=? WHERE id=? AND produto_id=?")
                   ->execute([$descImg, $imgId, $id]);
            }
        }
        // Salvar ordens
        if (!empty($_POST['img_ordem']) && is_array($_POST['img_ordem'])) {
            foreach ($_POST['img_ordem'] as $imgId => $ordemImg) {
                $imgId    = (int)$imgId;
                $ordemImg = (int)$ordemImg;
                $db->prepare("UPDATE produto_imagens SET ordem=? WHERE id=? AND produto_id=?")
                   ->execute([$ordemImg, $imgId, $id]);
            }
        }
        header("Location: produto_form.php?id=$id&msg=" . urlencode('Imagens atualizadas com sucesso.'));
        exit;
    }

    // ── Validações ─────────────────────────────────────────────────────────
    if ($linkExemplo && !filter_var($linkExemplo, FILTER_VALIDATE_URL)) {
        $err = 'O link do exemplo deve ser uma URL válida (ex: https://exemplo.com).';
    } elseif ($linkApresentacao && !filter_var($linkApresentacao, FILTER_VALIDATE_URL)) {
        $err = 'O link da apresentação deve ser uma URL válida (ex: https://docs.google.com/...).'; 
    } elseif ($linkVideo && !filter_var($linkVideo, FILTER_VALIDATE_URL)) {
        $err = 'O link do vídeo deve ser uma URL válida (ex: https://youtube.com/...).'; 
    } elseif (!$nome) {
        $err = 'O nome do produto é obrigatório.';
    } else {

        // ── Upload PPTX/PPSX Apresentação ──────────────────────────────────
        $novoApres = null;
        if (!empty($_FILES['arquivo_apresentacao']['name']) && $_FILES['arquivo_apresentacao']['error'] !== UPLOAD_ERR_NO_FILE) {
            $apresResult = uploadPptx($_FILES['arquivo_apresentacao']);
            if ($apresResult === false) {
                $err = 'Erro no upload da Apresentação. Verifique se é um arquivo .pptx ou .ppsx válido (máx. 100MB).';
            } else {
                $novoApres = $apresResult;
            }
        }

        // ── Upload PDF Manual ──────────────────────────────────────────────
        $novoManual = null;
        if (!$err && !empty($_FILES['arquivo_manual']['name']) && $_FILES['arquivo_manual']['error'] !== UPLOAD_ERR_NO_FILE) {
            $pdfResult = uploadPdf($_FILES['arquivo_manual']);
            if ($pdfResult === false) {
                $err = 'Erro no upload do Manual. Verifique se é um arquivo .pdf válido (máx. 50MB).';
            } else {
                $novoManual = $pdfResult;
            }
        }

        // ── Upload ZIP ─────────────────────────────────────────────────────
        $novoZip = null;
        if (!$err && !empty($_FILES['arquivo_zip']['name']) && $_FILES['arquivo_zip']['error'] !== UPLOAD_ERR_NO_FILE) {
            $zipResult = uploadZip($_FILES['arquivo_zip']);
            if ($zipResult === false) {
                $err = 'Erro no upload do ZIP. Verifique se é um arquivo .zip válido (máx. 200MB).';
            } else {
                $novoZip = $zipResult;
            }
        }

        if (!$err) {
            // ── INSERT / UPDATE produto ────────────────────────────────────
            if ($id > 0) {
                // Buscar arquivos atuais para gerenciar substituição
                // Usa SELECT seguro: colunas novas em try/catch separado
                $stmtAtual = $db->prepare("SELECT arquivo_zip, arquivo_manual FROM produtos WHERE id = ? LIMIT 1");
                $stmtAtual->execute([$id]);
                $atual = $stmtAtual->fetch();
                $atual['arquivo_apresentacao'] = '';
                try {
                    $stmtApres = $db->prepare("SELECT arquivo_apresentacao FROM produtos WHERE id = ? LIMIT 1");
                    $stmtApres->execute([$id]);
                    $rowApres = $stmtApres->fetch();
                    if ($rowApres) $atual['arquivo_apresentacao'] = $rowApres['arquivo_apresentacao'] ?? '';
                } catch (PDOException $e) {
                    // Coluna ainda não existe — ignorar
                }

                // Deletar ZIP antigo se novo for enviado
                if ($novoZip !== null && !empty($atual['arquivo_zip'])) {
                    $p = UPLOAD_DIR . $atual['arquivo_zip'];
                    if (file_exists($p)) unlink($p);
                }
                // Deletar Manual antigo se novo for enviado
                if ($novoManual !== null && !empty($atual['arquivo_manual'])) {
                    $p = UPLOAD_DIR . $atual['arquivo_manual'];
                    if (file_exists($p)) unlink($p);
                }
                // Deletar Apresentação antiga se nova for enviada
                if ($novoApres !== null && !empty($atual['arquivo_apresentacao'])) {
                    $p = UPLOAD_DIR . $atual['arquivo_apresentacao'];
                    if (file_exists($p)) unlink($p);
                }

                // UPDATE principal (colunas existentes desde migration_001 até 004)
                // Tenta com link_apresentacao e arquivo_manual; se falhar, usa versão reduzida
                try {
                    $stmt = $db->prepare("UPDATE produtos SET nome=?,descricao=?,valor_implantacao=?,valor_anual=?,link_exemplo=?,link_apresentacao=?,link_video=?,arquivo_zip=?,arquivo_manual=?,ativo=?,ordem=? WHERE id=?");
                    $stmt->execute([
                        $nome, $desc, $impl, $anual, $linkExemplo, $linkApresentacao, $linkVideo,
                        $novoZip    ?? $atual['arquivo_zip'],
                        $novoManual ?? $atual['arquivo_manual'],
                        $ativo, $ordem, $id
                    ]);
                } catch (PDOException $e) {
                    // Fallback sem colunas de migrations opcionais
                    $stmt = $db->prepare("UPDATE produtos SET nome=?,descricao=?,valor_implantacao=?,valor_anual=?,link_exemplo=?,arquivo_zip=?,ativo=?,ordem=? WHERE id=?");
                    $stmt->execute([
                        $nome, $desc, $impl, $anual, $linkExemplo,
                        $novoZip ?? $atual['arquivo_zip'],
                        $ativo, $ordem, $id
                    ]);
                }
                // Colunas novas (migration_005 e migration_006) — atualiza se existirem
                try {
                    $stmtExtra = $db->prepare("UPDATE produtos SET valor_mensal=?,arquivo_apresentacao=? WHERE id=?");
                    $stmtExtra->execute([$mensal, $novoApres ?? $atual['arquivo_apresentacao'] ?? '', $id]);
                } catch (PDOException $e) {
                    // Colunas ainda não existem no banco — ignorar até migration ser executada
                }
                $produtoId = $id;
                $msg = 'Produto atualizado com sucesso.';
            } else {
                // INSERT principal — tenta com colunas opcionais, fallback sem elas
                try {
                    $stmt = $db->prepare("INSERT INTO produtos (nome,descricao,valor_implantacao,valor_anual,link_exemplo,link_apresentacao,link_video,arquivo_zip,arquivo_manual,ativo,ordem) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                    $stmt->execute([$nome, $desc, $impl, $anual, $linkExemplo, $linkApresentacao, $linkVideo, $novoZip ?? '', $novoManual ?? '', $ativo, $ordem]);
                } catch (PDOException $e) {
                    $stmt = $db->prepare("INSERT INTO produtos (nome,descricao,valor_implantacao,valor_anual,link_exemplo,arquivo_zip,ativo,ordem) VALUES (?,?,?,?,?,?,?,?)");
                    $stmt->execute([$nome, $desc, $impl, $anual, $linkExemplo, $novoZip ?? '', $ativo, $ordem]);
                }
                $produtoId = $db->lastInsertId();
                // Colunas novas (migration_005 e migration_006) — atualiza se existirem
                try {
                    $stmtExtra = $db->prepare("UPDATE produtos SET valor_mensal=?,arquivo_apresentacao=? WHERE id=?");
                    $stmtExtra->execute([$mensal, $novoApres ?? '', $produtoId]);
                } catch (PDOException $e) {
                    // Colunas ainda não existem no banco — ignorar até migration ser executada
                }
                $msg = 'Produto criado com sucesso.';
            }

            // ── Salvar descrições das imagens existentes (enviadas junto ao form) ──
            if (!empty($_POST['img_desc']) && is_array($_POST['img_desc'])) {
                foreach ($_POST['img_desc'] as $imgId => $descImg) {
                    $imgId  = (int)$imgId;
                    $descImg = trim($descImg);
                    $db->prepare("UPDATE produto_imagens SET descricao=? WHERE id=? AND produto_id=?")
                       ->execute([$descImg, $imgId, $produtoId]);
                }
            }

            // ── Salvar ordens das imagens (via input hidden populados pelo JS) ──
            if (!empty($_POST['img_ordem']) && is_array($_POST['img_ordem'])) {
                foreach ($_POST['img_ordem'] as $imgId => $ordemImg) {
                    $imgId    = (int)$imgId;
                    $ordemImg = (int)$ordemImg;
                    $db->prepare("UPDATE produto_imagens SET ordem=? WHERE id=? AND produto_id=?")
                       ->execute([$ordemImg, $imgId, $produtoId]);
                }
            }

            // ── Upload de novas imagens ────────────────────────────────────
            if (!empty($_FILES['imagens']['name'][0])) {
                $stmtMax = $db->prepare("SELECT COALESCE(MAX(ordem),0) as mx FROM produto_imagens WHERE produto_id = ?");
                $stmtMax->execute([$produtoId]);
                $maxOrdem = (int)$stmtMax->fetchColumn();

                foreach ($_FILES['imagens']['name'] as $i => $fname) {
                    if ($_FILES['imagens']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $fileItem = [
                        'name'     => $_FILES['imagens']['name'][$i],
                        'type'     => $_FILES['imagens']['type'][$i],
                        'tmp_name' => $_FILES['imagens']['tmp_name'][$i],
                        'error'    => $_FILES['imagens']['error'][$i],
                        'size'     => $_FILES['imagens']['size'][$i],
                    ];
                    $caminho = uploadImagem($fileItem, 'produtos');
                    if ($caminho) {
                        $maxOrdem++;
                        // Pegar descrição da nova imagem (se enviada)
                        $descNovaImg = trim($_POST['nova_img_desc'][$i] ?? '');
                        $stmtImg = $db->prepare("INSERT INTO produto_imagens (produto_id, caminho, descricao, ordem) VALUES (?,?,?,?)");
                        $stmtImg->execute([$produtoId, $caminho, $descNovaImg, $maxOrdem]);
                    }
                }
            }

            // ── Excluir imagem individual ──────────────────────────────────
            if (!empty($_POST['excluir_img']) && is_numeric($_POST['excluir_img'])) {
                $imgId = (int)$_POST['excluir_img'];
                $stmtI = $db->prepare("SELECT caminho FROM produto_imagens WHERE id = ? AND produto_id = ?");
                $stmtI->execute([$imgId, $produtoId]);
                $imgRow = $stmtI->fetch();
                if ($imgRow) {
                    deletarArquivo($imgRow['caminho']);
                    $db->prepare("DELETE FROM produto_imagens WHERE id = ?")->execute([$imgId]);
                }
            }

            // ── Recarregar ────────────────────────────────────────────────
            $stmt = $db->prepare("SELECT * FROM produtos WHERE id = ? LIMIT 1");
            $stmt->execute([$produtoId]);
            $rowReload = $stmt->fetch();
            if ($rowReload) $produto = array_merge($produto, $rowReload);
            $editando = true;
            $stmt2    = $db->prepare("SELECT * FROM produto_imagens WHERE produto_id = ? ORDER BY ordem ASC");
            $stmt2->execute([$produtoId]);
            $imagens  = $stmt2->fetchAll();
        }
    }
}

// Mensagem via GET
if (isset($_GET['msg']) && !$msg) {
    $msg = sanitize($_GET['msg']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $editando ? 'Editar' : 'Novo' ?> Produto — Admin</title>
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
        <h1><i class="fas fa-<?= $editando ? 'pencil' : 'plus' ?>"></i> <?= $editando ? 'Editar' : 'Novo' ?> Produto</h1>
        <p><a href="produtos.php">← Voltar para produtos</a></p>
      </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-circle-check"></i> <?= sanitize($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><i class="fas fa-triangle-exclamation"></i> <?= sanitize($err) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="formProduto">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="id" value="<?= (int)$produto['id'] ?>">

      <div class="form-grid">

        <!-- ── Informações do Produto ───────────────────────────────────── -->
        <div class="card form-card">
          <div class="card-header"><h2><i class="fas fa-info-circle"></i> Informações do Produto</h2></div>
          <div class="card-body">
            <div class="form-group">
              <label>Nome do Produto *</label>
              <input type="text" name="nome" value="<?= sanitize($produto['nome']) ?>" required placeholder="Ex: Sistema de Vendas PDV">
            </div>
            <div class="form-group">
              <label>Descrição</label>
              <textarea name="descricao" rows="5" placeholder="Descreva as funcionalidades do aplicativo..."><?= sanitize($produto['descricao']) ?></textarea>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Valor de Implantação (R$) *</label>
                <input type="text" name="valor_implantacao" value="<?= number_format((float)$produto['valor_implantacao'], 2, ',', '.') ?>" required placeholder="0,00" class="money-input">
              </div>
              <div class="form-group">
                <label>Manutenção Anual (R$) *</label>
                <input type="text" name="valor_anual" value="<?= number_format((float)$produto['valor_anual'], 2, ',', '.') ?>" required placeholder="0,00" class="money-input">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Manutenção Mensal (R$) <small class="form-hint" style="display:inline;">(opcional — ativa toggle Mensal/Anual no site)</small></label>
                <input type="text" name="valor_mensal" value="<?= number_format((float)($produto['valor_mensal'] ?? 0), 2, ',', '.') ?>" placeholder="0,00" class="money-input">
              </div>
              <div class="form-group" style="align-self:flex-end;">
                <p class="form-hint"><i class="fas fa-circle-info"></i> Deixe <strong>0,00</strong> para exibir apenas a opção anual no site.</p>
              </div>
            </div>
            <div class="form-group">
              <label><i class="fas fa-link"></i> Link do Exemplo (opcional)</label>
              <div class="input-link-wrap">
                <input type="url" name="link_exemplo" value="<?= sanitize($produto['link_exemplo'] ?? '') ?>" placeholder="https://exemplo.meusite.com.br">
                <?php if (!empty($produto['link_exemplo'])): ?>
                <a href="<?= sanitize($produto['link_exemplo']) ?>" target="_blank" class="btn btn-sm btn-outline" title="Testar link">
                  <i class="fas fa-arrow-up-right-from-square"></i> Testar
                </a>
                <?php endif; ?>
              </div>
              <small class="form-hint">Se preenchido, aparece o botão "Ver Exemplo" no modal de detalhes do produto.</small>
            </div>
            <div class="form-group">
              <label><i class="fab fa-youtube"></i> Link do Vídeo de Demonstração (opcional)</label>
              <div class="input-link-wrap">
                <input type="url" name="link_video" value="<?= sanitize($produto['link_video'] ?? '') ?>" placeholder="https://youtube.com/watch?v=...">
                <?php if (!empty($produto['link_video'])): ?>
                <a href="<?= sanitize($produto['link_video']) ?>" target="_blank" class="btn btn-sm btn-outline" title="Testar link">
                  <i class="fas fa-arrow-up-right-from-square"></i> Testar
                </a>
                <?php endif; ?>
              </div>
              <small class="form-hint">YouTube, Vimeo ou qualquer URL de vídeo. Aparece como botão vermelho "Vídeo Demo" no card e no modal do produto.</small>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Ordem de Exibição</label>
                <input type="number" name="ordem" value="<?= (int)$produto['ordem'] ?>" min="0">
              </div>
              <div class="form-group form-check-group">
                <label class="checkbox-label">
                  <input type="checkbox" name="ativo" <?= $produto['ativo'] ? 'checked' : '' ?>>
                  <span>Produto ativo (visível no site)</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Apresentação PPTX/PPSX ou Link Google Slides -->
        <div class="card form-card">
          <div class="card-header"><h2><i class="fas fa-file-powerpoint"></i> Apresentação do Produto</h2></div>
          <div class="card-body">

            <!-- Campo de link (Google Slides, OneDrive, etc.) -->
            <div class="form-group">
              <label><i class="fas fa-link"></i> Link da Apresentação (Google Slides, OneDrive…)</label>
              <div class="input-link-wrap">
                <input type="url" name="link_apresentacao"
                       value="<?= sanitize($produto['link_apresentacao'] ?? '') ?>"
                       placeholder="https://docs.google.com/presentation/d/...">
                <?php if (!empty($produto['link_apresentacao'])): ?>
                <a href="<?= sanitize($produto['link_apresentacao']) ?>" target="_blank" class="btn btn-sm btn-outline" title="Testar link">
                  <i class="fas fa-arrow-up-right-from-square"></i> Testar
                </a>
                <?php endif; ?>
              </div>
              <small class="form-hint">
                <i class="fas fa-circle-info"></i>
                Se preenchido, o botão "Apresentação" abrirá este link (tem prioridade sobre o arquivo PPTX abaixo).
              </small>
            </div>

            <div class="form-hint" style="margin:4px 0 16px;padding:10px 14px;background:#f5f3ff;border-radius:8px;border-left:3px solid #7c3aed;">
              <strong>ou</strong> envie um arquivo PPTX/PPSX para download direto:
            </div>

            <?php
              $apresAtualPath = $produto['arquivo_apresentacao'] ?? '';
              $apresFisico    = $apresAtualPath ? UPLOAD_DIR . $apresAtualPath : '';
              $apresExiste    = $apresFisico && file_exists($apresFisico);
              $apresTamanho   = $apresExiste ? formatBytes(filesize($apresFisico)) : '';
              $apresNome      = $apresExiste ? basename($apresFisico) : '';
              $apresExt       = $apresExiste ? strtoupper(pathinfo($apresFisico, PATHINFO_EXTENSION)) : '';
            ?>
            <?php if ($apresExiste): ?>
            <div class="zip-info-box" style="border-color:#c4b5fd;background:#faf5ff;">
              <div class="zip-info-inner">
                <i class="fas fa-file-powerpoint" style="font-size:1.8rem;color:#7c3aed;flex-shrink:0;"></i>
                <div>
                  <strong>Arquivo atual (<?= $apresExt ?>):</strong><br>
                  <span class="zip-filename"><?= sanitize($apresNome) ?></span>
                  <span class="zip-size">(<?= $apresTamanho ?>)</span>
                </div>
              </div>
              <div class="zip-actions">
                <a href="apresentacao.php?produto_id=<?= (int)$produto['id'] ?>" class="btn btn-sm btn-outline" target="_blank">
                  <i class="fas fa-download"></i> Baixar
                </a>
                <button type="submit" name="excluir_apresentacao" value="1" class="btn btn-sm btn-danger"
                        onclick="return confirm('Excluir a Apresentação PPTX deste produto?')">
                  <i class="fas fa-trash"></i> Excluir
                </button>
              </div>
            </div>
            <p class="form-hint" style="margin-top:8px;"><i class="fas fa-circle-info"></i> Para substituir, envie um novo arquivo abaixo.</p>
            <?php else: ?>
            <p class="form-hint" style="margin-bottom:14px;"><i class="fas fa-circle-info"></i> Nenhum arquivo PPTX cadastrado.</p>
            <?php endif; ?>
            <div class="form-group" style="margin-top:12px;">
              <label><i class="fas fa-upload"></i> <?= $apresExiste ? 'Substituir arquivo PPTX/PPSX' : 'Enviar arquivo PPTX/PPSX' ?></label>
              <div class="zip-upload-area" id="pptxUploadArea" style="border-color:#c4b5fd;">
                <i class="fas fa-file-powerpoint" style="font-size:2rem;color:#7c3aed;"></i>
                <p style="margin:8px 0 4px;">Clique para selecionar ou arraste o arquivo</p>
                <span style="font-size:0.8rem;color:#9ca3af;">.pptx ou .ppsx — máx. 100MB</span>
                <input type="file" name="arquivo_apresentacao" id="pptxInput" accept=".pptx,.ppsx" class="upload-input">
              </div>
              <div id="pptxPreview" class="zip-preview-selected" style="display:none;border-color:#c4b5fd;background:#faf5ff;">
                <i class="fas fa-file-powerpoint" style="color:#7c3aed;font-size:1.4rem;"></i>
                <div style="flex:1;min-width:0;">
                  <span id="pptxPreviewName" style="font-weight:600;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                  <span id="pptxPreviewSize" class="zip-size"></span>
                </div>
                <button type="button" onclick="clearPptxPreview()" class="btn btn-sm btn-danger" title="Remover seleção">
                  <i class="fas fa-xmark"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Manual PDF -->
        <div class="card form-card">
          <div class="card-header"><h2><i class="fas fa-file-pdf"></i> Manual do Produto (PDF)</h2></div>
          <div class="card-body">
            <?php
              $manualAtualPath = $produto['arquivo_manual'] ?? '';
              $manualFisico    = $manualAtualPath ? UPLOAD_DIR . $manualAtualPath : '';
              $manualExiste    = $manualFisico && file_exists($manualFisico);
              $manualTamanho   = $manualExiste ? formatBytes(filesize($manualFisico)) : '';
              $manualNome      = $manualExiste ? basename($manualFisico) : '';
            ?>
            <?php if ($manualExiste): ?>
            <div class="zip-info-box" style="border-color:#fca5a5;background:#fff7f7;">
              <div class="zip-info-inner">
                <i class="fas fa-file-pdf" style="font-size:1.8rem;color:#ef4444;flex-shrink:0;"></i>
                <div>
                  <strong>Manual atual:</strong><br>
                  <span class="zip-filename"><?= sanitize($manualNome) ?></span>
                  <span class="zip-size">(<?= $manualTamanho ?>)</span>
                </div>
              </div>
              <div class="zip-actions">
                <a href="manual.php?produto_id=<?= (int)$produto['id'] ?>" class="btn btn-sm btn-outline" target="_blank">
                  <i class="fas fa-eye"></i> Visualizar
                </a>
                <button type="submit" name="excluir_manual" value="1" class="btn btn-sm btn-danger"
                        onclick="return confirm('Excluir o Manual PDF deste produto?')">
                  <i class="fas fa-trash"></i> Excluir PDF
                </button>
              </div>
            </div>
            <p class="form-hint" style="margin-top:8px;"><i class="fas fa-circle-info"></i> Para substituir, envie um novo PDF abaixo. O arquivo atual será deletado automaticamente.</p>
            <?php else: ?>
            <p class="form-hint" style="margin-bottom:14px;"><i class="fas fa-circle-info"></i> Nenhum manual cadastrado. Envie um PDF para exibir o botão "Manual" no modal do site.</p>
            <?php endif; ?>
            <div class="form-group" style="margin-top:12px;">
              <label><i class="fas fa-upload"></i> <?= $manualExiste ? 'Substituir PDF' : 'Enviar Manual PDF' ?></label>
              <div class="zip-upload-area" id="pdfUploadArea" style="border-color:#fca5a5;">
                <i class="fas fa-file-pdf" style="font-size:2rem;color:#ef4444;"></i>
                <p style="margin:8px 0 4px;">Clique para selecionar ou arraste o arquivo .pdf</p>
                <span style="font-size:0.8rem;color:#9ca3af;">Apenas .pdf — máx. 50MB</span>
                <input type="file" name="arquivo_manual" id="pdfInput" accept=".pdf" class="upload-input">
              </div>
              <div id="pdfPreview" class="zip-preview-selected" style="display:none;border-color:#fca5a5;background:#fff7f7;">
                <i class="fas fa-file-pdf" style="color:#ef4444;font-size:1.4rem;"></i>
                <div style="flex:1;min-width:0;">
                  <span id="pdfPreviewName" style="font-weight:600;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                  <span id="pdfPreviewSize" class="zip-size"></span>
                </div>
                <button type="button" onclick="clearPdfPreview()" class="btn btn-sm btn-danger" title="Remover seleção">
                  <i class="fas fa-xmark"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Arquivo ZIP -->
        <div class="card form-card">
          <div class="card-header"><h2><i class="fas fa-file-zipper"></i> Arquivo ZIP do Produto</h2></div>
          <div class="card-body">
            <?php
              $zipAtualPath = $produto['arquivo_zip'] ?? '';
              $zipFisico    = $zipAtualPath ? UPLOAD_DIR . $zipAtualPath : '';
              $zipExiste    = $zipFisico && file_exists($zipFisico);
              $zipTamanho   = $zipExiste ? formatBytes(filesize($zipFisico)) : '';
              $zipNome      = $zipExiste ? basename($zipFisico) : '';
            ?>
            <?php if ($zipExiste): ?>
            <div class="zip-info-box">
              <div class="zip-info-inner">
                <i class="fas fa-file-zipper zip-icon"></i>
                <div>
                  <strong>Arquivo atual:</strong><br>
                  <span class="zip-filename"><?= sanitize($zipNome) ?></span>
                  <span class="zip-size">(<?= $zipTamanho ?>)</span>
                </div>
              </div>
              <div class="zip-actions">
                <a href="download.php?produto_id=<?= (int)$produto['id'] ?>" class="btn btn-sm btn-outline" target="_blank">
                  <i class="fas fa-download"></i> Baixar
                </a>
                <button type="submit" name="excluir_zip" value="1" class="btn btn-sm btn-danger"
                        onclick="return confirm('Excluir o arquivo ZIP deste produto? Esta ação não pode ser desfeita.')">
                  <i class="fas fa-trash"></i> Excluir ZIP
                </button>
              </div>
            </div>
            <p class="form-hint" style="margin-top:8px;"><i class="fas fa-circle-info"></i> Para substituir, envie um novo arquivo abaixo. O arquivo atual será deletado automaticamente.</p>
            <?php else: ?>
            <p class="form-hint" style="margin-bottom:14px;"><i class="fas fa-circle-info"></i> Nenhum arquivo ZIP cadastrado. Envie abaixo para disponibilizar o download (apenas para administradores).</p>
            <?php endif; ?>
            <div class="form-group" style="margin-top:12px;">
              <label><i class="fas fa-upload"></i> <?= $zipExiste ? 'Substituir ZIP' : 'Enviar arquivo ZIP' ?></label>
              <div class="zip-upload-area" id="zipUploadArea">
                <i class="fas fa-file-zipper" style="font-size:2rem;color:#6b7280;"></i>
                <p style="margin:8px 0 4px;">Clique para selecionar ou arraste o arquivo .zip</p>
                <span style="font-size:0.8rem;color:#9ca3af;">Apenas .zip — máx. 200MB</span>
                <input type="file" name="arquivo_zip" id="zipInput" accept=".zip" class="upload-input">
              </div>
              <div id="zipPreview" class="zip-preview-selected" style="display:none;">
                <i class="fas fa-file-zipper" style="color:#059669;font-size:1.4rem;"></i>
                <div style="flex:1;min-width:0;">
                  <span id="zipPreviewName" style="font-weight:600;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>
                  <span id="zipPreviewSize" class="zip-size"></span>
                </div>
                <button type="button" onclick="clearZipPreview()" class="btn btn-sm btn-danger" title="Remover seleção">
                  <i class="fas fa-xmark"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Imagens do Produto ──────────────────────────────────────── -->
        <div class="card form-card img-card-full">
          <div class="card-header">
            <h2><i class="fas fa-images"></i> Imagens do Produto</h2>
            <?php if (!empty($imagens)): ?>
            <span class="form-hint" style="margin:0;">
              <i class="fas fa-grip-dots-vertical"></i> Arraste para reordenar
            </span>
            <?php endif; ?>
          </div>
          <div class="card-body">

            <?php if (!empty($imagens)): ?>
            <!-- Grade de imagens existentes — sortable -->
            <div class="img-sortable-list" id="imgSortable">
              <?php foreach ($imagens as $imgIdx => $img): ?>
              <div class="img-sortable-item" data-id="<?= $img['id'] ?>">
                <!-- Hidden inputs de ordem (atualizados pelo JS ao arrastar) -->
                <input type="hidden" name="img_ordem[<?= $img['id'] ?>]" value="<?= $imgIdx + 1 ?>" class="inp-ordem">

                <div class="img-sortable-handle" title="Arraste para reordenar">
                  <i class="fas fa-grip-vertical"></i>
                </div>

                <div class="img-sortable-preview">
                  <img src="<?= UPLOAD_URL . sanitize($img['caminho']) ?>" alt="Imagem <?= $imgIdx+1 ?>">
                  <span class="img-sortable-num"><?= $imgIdx + 1 ?></span>
                </div>

                <div class="img-sortable-body">
                  <div class="form-group" style="margin-bottom:0;">
                    <label style="font-size:.75rem;">Legenda / Descrição da imagem</label>
                    <input type="text"
                           name="img_desc[<?= $img['id'] ?>]"
                           value="<?= sanitize($img['descricao'] ?? '') ?>"
                           placeholder="Ex: Tela principal, Dashboard, Relatório..."
                           maxlength="255"
                           class="inp-desc">
                  </div>
                </div>

                <button type="submit" name="excluir_img" value="<?= $img['id'] ?>"
                        class="img-sortable-delete"
                        onclick="return confirm('Remover esta imagem permanentemente?')"
                        title="Remover imagem">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Área de upload de novas imagens -->
            <div class="upload-area" id="uploadArea">
              <i class="fas fa-cloud-arrow-up"></i>
              <p>Arraste imagens aqui ou clique para selecionar</p>
              <span>JPG, PNG, GIF, WEBP — máx. 5MB cada — múltiplos arquivos</span>
              <input type="file" name="imagens[]" id="imgInput" multiple accept="image/*" class="upload-input">
            </div>

            <!-- Preview de novas imagens (com campo de descrição por imagem) -->
            <div id="previewContainer" class="img-sortable-list mt-1"></div>

          </div>
        </div>

      </div><!-- /.form-grid -->

      <div class="form-actions">
        <a href="produtos.php" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Salvar Produto</button>
      </div>
    </form>
  </div>
</div>

<!-- SortableJS CDN (drag-and-drop nativo, sem jQuery) -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
// ═══════════════════════════════════════════════════════════
//  REORDENAMENTO DRAG-AND-DROP das imagens existentes
// ═══════════════════════════════════════════════════════════
const sortableList = document.getElementById('imgSortable');

if (sortableList) {
  Sortable.create(sortableList, {
    handle:    '.img-sortable-handle',
    animation: 200,
    ghostClass:'sortable-ghost',
    chosenClass:'sortable-chosen',
    onEnd: function() {
      // Atualizar numeração visual e inputs hidden de ordem
      const items = sortableList.querySelectorAll('.img-sortable-item');
      items.forEach(function(item, idx) {
        const numBadge = item.querySelector('.img-sortable-num');
        const inpOrdem = item.querySelector('.inp-ordem');
        if (numBadge) numBadge.textContent = idx + 1;
        if (inpOrdem) inpOrdem.value = idx + 1;
      });
    }
  });
}

// ═══════════════════════════════════════════════════════════
//  PREVIEW de novas imagens com campo de descrição
// ═══════════════════════════════════════════════════════════
const imgInput      = document.getElementById('imgInput');
const previewWrap   = document.getElementById('previewContainer');
const uploadArea    = document.getElementById('uploadArea');

uploadArea.addEventListener('click', () => imgInput.click());
uploadArea.addEventListener('dragover', e => { e.preventDefault(); uploadArea.classList.add('drag-over'); });
uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('drag-over'));
uploadArea.addEventListener('drop', e => {
  e.preventDefault();
  uploadArea.classList.remove('drag-over');
  imgInput.files = e.dataTransfer.files;
  renderNewPreviews(e.dataTransfer.files);
});
imgInput.addEventListener('change', () => renderNewPreviews(imgInput.files));

function renderNewPreviews(files) {
  previewWrap.innerHTML = '';
  Array.from(files).forEach(function(file, idx) {
    if (!file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = function(e) {
      const item = document.createElement('div');
      item.className = 'img-sortable-item img-sortable-item--new';
      item.innerHTML =
        '<div class="img-sortable-preview">' +
          '<img src="' + e.target.result + '" alt="Preview">' +
          '<span class="img-badge img-sortable-num">Novo</span>' +
        '</div>' +
        '<div class="img-sortable-body">' +
          '<div class="form-group" style="margin-bottom:0;">' +
            '<label style="font-size:.75rem;">Legenda / Descrição desta imagem</label>' +
            '<input type="text" name="nova_img_desc[' + idx + ']"' +
                   ' placeholder="Ex: Tela principal, Dashboard..." maxlength="255" class="inp-desc">' +
          '</div>' +
        '</div>' +
        '<div class="img-sortable-new-badge"><i class="fas fa-star"></i> Nova</div>';
      previewWrap.appendChild(item);
    };
    reader.readAsDataURL(file);
  });
}

// ═══════════════════════════════════════════════════════════
//  ZIP — preview e área clicável
// ═══════════════════════════════════════════════════════════
const zipInput   = document.getElementById('zipInput');
const zipArea    = document.getElementById('zipUploadArea');
const zipPreview = document.getElementById('zipPreview');
const zipPName   = document.getElementById('zipPreviewName');
const zipPSize   = document.getElementById('zipPreviewSize');

if (zipArea && zipInput) {
  zipArea.addEventListener('click', () => zipInput.click());
  zipArea.addEventListener('dragover', e => { e.preventDefault(); zipArea.classList.add('drag-over'); });
  zipArea.addEventListener('dragleave', () => zipArea.classList.remove('drag-over'));
  zipArea.addEventListener('drop', e => {
    e.preventDefault();
    zipArea.classList.remove('drag-over');
    if (e.dataTransfer.files.length) {
      zipInput.files = e.dataTransfer.files;
      showZipPreview(e.dataTransfer.files[0]);
    }
  });
  zipInput.addEventListener('change', () => {
    if (zipInput.files.length) showZipPreview(zipInput.files[0]);
  });
}

function showZipPreview(file) {
  if (!file) return;
  zipPName.textContent = file.name;
  zipPSize.textContent = fmtSize(file.size);
  zipArea.style.display    = 'none';
  zipPreview.style.display = 'flex';
}

function clearZipPreview() {
  zipInput.value = '';
  zipArea.style.display    = 'flex';
  zipPreview.style.display = 'none';
}

function fmtSize(bytes) {
  if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
  if (bytes >= 1024)    return (bytes / 1024).toFixed(1) + ' KB';
  return bytes + ' B';
}

// ═══════════════════════════════════════════════════════════
//  Preview PDF Manual
// ═══════════════════════════════════════════════════════════
const pdfInput   = document.getElementById('pdfInput');
const pdfArea    = document.getElementById('pdfUploadArea');
const pdfPreview = document.getElementById('pdfPreview');
const pdfPName   = document.getElementById('pdfPreviewName');
const pdfPSize   = document.getElementById('pdfPreviewSize');

if (pdfArea && pdfInput) {
  pdfArea.addEventListener('click', () => pdfInput.click());
  pdfArea.addEventListener('dragover', e => { e.preventDefault(); pdfArea.classList.add('drag-over'); });
  pdfArea.addEventListener('dragleave', () => pdfArea.classList.remove('drag-over'));
  pdfArea.addEventListener('drop', e => {
    e.preventDefault();
    pdfArea.classList.remove('drag-over');
    if (e.dataTransfer.files.length) {
      pdfInput.files = e.dataTransfer.files;
      showPdfPreview(e.dataTransfer.files[0]);
    }
  });
  pdfInput.addEventListener('change', () => {
    if (pdfInput.files.length) showPdfPreview(pdfInput.files[0]);
  });
}

function showPdfPreview(file) {
  if (!file) return;
  pdfPName.textContent = file.name;
  pdfPSize.textContent = fmtSize(file.size);
  pdfArea.style.display    = 'none';
  pdfPreview.style.display = 'flex';
}

function clearPdfPreview() {
  pdfInput.value = '';
  pdfArea.style.display    = 'flex';
  pdfPreview.style.display = 'none';
}

// ═══════════════════════════════════════════════════════════
//  Preview PPTX/PPSX Apresentação
// ═══════════════════════════════════════════════════════════
const pptxInput   = document.getElementById('pptxInput');
const pptxArea    = document.getElementById('pptxUploadArea');
const pptxPreview = document.getElementById('pptxPreview');
const pptxPName   = document.getElementById('pptxPreviewName');
const pptxPSize   = document.getElementById('pptxPreviewSize');

if (pptxArea && pptxInput) {
  pptxArea.addEventListener('click', () => pptxInput.click());
  pptxArea.addEventListener('dragover', e => { e.preventDefault(); pptxArea.classList.add('drag-over'); });
  pptxArea.addEventListener('dragleave', () => pptxArea.classList.remove('drag-over'));
  pptxArea.addEventListener('drop', e => {
    e.preventDefault();
    pptxArea.classList.remove('drag-over');
    if (e.dataTransfer.files.length) {
      pptxInput.files = e.dataTransfer.files;
      showPptxPreview(e.dataTransfer.files[0]);
    }
  });
  pptxInput.addEventListener('change', () => {
    if (pptxInput.files.length) showPptxPreview(pptxInput.files[0]);
  });
}

function showPptxPreview(file) {
  if (!file) return;
  pptxPName.textContent = file.name;
  pptxPSize.textContent = fmtSize(file.size);
  pptxArea.style.display    = 'none';
  pptxPreview.style.display = 'flex';
}

function clearPptxPreview() {
  pptxInput.value = '';
  pptxArea.style.display    = 'flex';
  pptxPreview.style.display = 'none';
}

// ═══════════════════════════════════════════════════════════
//  Máscara monetária
// ═══════════════════════════════════════════════════════════
document.querySelectorAll('.money-input').forEach(function(inp) {
  inp.addEventListener('input', function() {
    let v = this.value.replace(/\D/g, '');
    v = (parseInt(v) / 100).toFixed(2);
    this.value = isNaN(v) ? '0,00' : v.replace('.', ',');
  });
});
</script>
</body>
</html>
