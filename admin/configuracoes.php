<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db  = getDB();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $campos = [
        'site_nome','header_tipo','header_texto','header_texto_tamanho',
        'header_imagem_largura','header_slogan','header_slogan_tamanho',
        'header_bg_cor','header_texto_cor',
        'rodape_whatsapp','rodape_texto','rodape_bg_cor','rodape_texto_cor',
        'produtos_por_pagina','pix_chave','pix_nome_beneficiario','pix_cidade',
        'mp_access_token','mp_webhook_secret','mp_ambiente','mp_email_pagador',
        'site_cor_primaria','site_cor_secundaria','slideshow_velocidade'
    ];
    foreach ($campos as $campo) {
        $valor = trim($_POST[$campo] ?? '');
        $stmt = $db->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=?");
        $stmt->execute([$campo, $valor, $valor]);
    }

    // Upload logo header
    if (!empty($_FILES['header_imagem']['name']) && $_FILES['header_imagem']['error'] === UPLOAD_ERR_OK) {
        $caminho = uploadImagem($_FILES['header_imagem'], 'logos');
        if ($caminho) {
            $stmt = $db->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('header_imagem',?) ON DUPLICATE KEY UPDATE valor=?");
            $stmt->execute([$caminho, $caminho]);
        } else {
            $err = 'Erro ao enviar imagem do cabeçalho.';
        }
    }

    if (!$err) $msg = 'Configurações salvas com sucesso!';
}

$c = cfgAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configurações — Admin</title>
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
        <h1><i class="fas fa-sliders"></i> Personalização do Site</h1>
        <p>Configure a aparência e comportamento do site.</p>
      </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-circle-check"></i> <?= sanitize($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><i class="fas fa-triangle-exclamation"></i> <?= sanitize($err) ?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <!-- CABEÇALHO -->
      <div class="card form-card mb-2">
        <div class="card-header"><h2><i class="fas fa-heading"></i> Cabeçalho</h2></div>
        <div class="card-body">
          <div class="form-row">
            <div class="form-group">
              <label>Nome do Site</label>
              <input type="text" name="site_nome" value="<?= sanitize($c['site_nome'] ?? '') ?>" placeholder="AppVendas">
            </div>
            <div class="form-group">
              <label>Tipo do Cabeçalho</label>
              <select name="header_tipo" id="headerTipo" onchange="toggleHeaderTipo()">
                <option value="texto" <?= ($c['header_tipo'] ?? '') === 'texto' ? 'selected' : '' ?>>Texto</option>
                <option value="imagem" <?= ($c['header_tipo'] ?? '') === 'imagem' ? 'selected' : '' ?>>Imagem (Logo)</option>
              </select>
            </div>
          </div>

          <!-- Opções de texto -->
          <div id="optTexto" class="form-row">
            <div class="form-group">
              <label>Texto do Cabeçalho</label>
              <input type="text" name="header_texto" value="<?= sanitize($c['header_texto'] ?? '') ?>" placeholder="AppVendas">
            </div>
            <div class="form-group">
              <label>Tamanho do Texto (rem)</label>
              <input type="number" name="header_texto_tamanho" value="<?= sanitize($c['header_texto_tamanho'] ?? '2.5') ?>" step="0.1" min="1" max="8">
            </div>
          </div>

          <!-- Opções de imagem -->
          <div id="optImagem" class="form-row" style="display:none">
            <div class="form-group">
              <label>Imagem do Logo</label>
              <?php if (!empty($c['header_imagem'])): ?>
                <div class="img-preview-small">
                  <img src="<?= UPLOAD_URL . sanitize($c['header_imagem']) ?>" alt="Logo atual">
                  <span>Logo atual</span>
                </div>
              <?php endif; ?>
              <input type="file" name="header_imagem" accept="image/*">
            </div>
            <div class="form-group">
              <label>Largura da Imagem (px)</label>
              <input type="number" name="header_imagem_largura" value="<?= sanitize($c['header_imagem_largura'] ?? '200') ?>" min="50" max="600">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Slogan</label>
              <input type="text" name="header_slogan" value="<?= sanitize($c['header_slogan'] ?? '') ?>" placeholder="Soluções digitais para o seu negócio">
            </div>
            <div class="form-group">
              <label>Tamanho do Slogan (rem)</label>
              <input type="number" name="header_slogan_tamanho" value="<?= sanitize($c['header_slogan_tamanho'] ?? '1.2') ?>" step="0.1" min="0.6" max="4">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Cor de Fundo do Cabeçalho</label>
              <div class="color-input-wrap">
                <input type="color" name="header_bg_cor" value="<?= sanitize($c['header_bg_cor'] ?? '#0f172a') ?>">
                <input type="text" class="color-text" value="<?= sanitize($c['header_bg_cor'] ?? '#0f172a') ?>">
              </div>
            </div>
            <div class="form-group">
              <label>Cor do Texto do Cabeçalho</label>
              <div class="color-input-wrap">
                <input type="color" name="header_texto_cor" value="<?= sanitize($c['header_texto_cor'] ?? '#ffffff') ?>">
                <input type="text" class="color-text" value="<?= sanitize($c['header_texto_cor'] ?? '#ffffff') ?>">
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- PRODUTOS -->
      <div class="card form-card mb-2">
        <div class="card-header"><h2><i class="fas fa-box-open"></i> Exibição de Produtos</h2></div>
        <div class="card-body">
          <div class="form-row">
            <div class="form-group">
              <label>Produtos por Página</label>
              <input type="number" name="produtos_por_pagina" value="<?= sanitize($c['produtos_por_pagina'] ?? '6') ?>" min="1" max="100">
            </div>
            <div class="form-group">
              <label>Velocidade do Slideshow (ms)</label>
              <input type="number" name="slideshow_velocidade" value="<?= sanitize($c['slideshow_velocidade'] ?? '4000') ?>" min="1000" max="15000" step="500">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Cor Primária do Site</label>
              <div class="color-input-wrap">
                <input type="color" name="site_cor_primaria" value="<?= sanitize($c['site_cor_primaria'] ?? '#6366f1') ?>">
                <input type="text" class="color-text" value="<?= sanitize($c['site_cor_primaria'] ?? '#6366f1') ?>">
              </div>
            </div>
            <div class="form-group">
              <label>Cor Secundária do Site</label>
              <div class="color-input-wrap">
                <input type="color" name="site_cor_secundaria" value="<?= sanitize($c['site_cor_secundaria'] ?? '#8b5cf6') ?>">
                <input type="text" class="color-text" value="<?= sanitize($c['site_cor_secundaria'] ?? '#8b5cf6') ?>">
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- RODAPÉ -->
      <div class="card form-card mb-2">
        <div class="card-header"><h2><i class="fas fa-shoe-prints"></i> Rodapé</h2></div>
        <div class="card-body">
          <div class="form-row">
            <div class="form-group">
              <label>WhatsApp (somente números com DDD e DDI)</label>
              <input type="text" name="rodape_whatsapp" value="<?= sanitize($c['rodape_whatsapp'] ?? '') ?>" placeholder="5511999999999">
            </div>
            <div class="form-group">
              <label>Texto do Rodapé</label>
              <input type="text" name="rodape_texto" value="<?= sanitize($c['rodape_texto'] ?? '') ?>" placeholder="Entre em contato conosco...">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Cor de Fundo do Rodapé</label>
              <div class="color-input-wrap">
                <input type="color" name="rodape_bg_cor" value="<?= sanitize($c['rodape_bg_cor'] ?? '#0f172a') ?>">
                <input type="text" class="color-text" value="<?= sanitize($c['rodape_bg_cor'] ?? '#0f172a') ?>">
              </div>
            </div>
            <div class="form-group">
              <label>Cor do Texto do Rodapé</label>
              <div class="color-input-wrap">
                <input type="color" name="rodape_texto_cor" value="<?= sanitize($c['rodape_texto_cor'] ?? '#ffffff') ?>">
                <input type="text" class="color-text" value="<?= sanitize($c['rodape_texto_cor'] ?? '#ffffff') ?>">
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- PIX ESTÁTICO (fallback) -->
      <div class="card form-card mb-2">
        <div class="card-header"><h2><i class="fas fa-qrcode"></i> PIX Estático (Fallback)</h2></div>
        <div class="card-body">
          <p style="font-size:.85rem;color:#64748b;margin-bottom:1rem;"><i class="fas fa-info-circle"></i> Usado apenas quando o Mercado Pago <strong>não estiver configurado</strong>. Gera QR Code manual sem confirmação automática.</p>
          <div class="form-row">
            <div class="form-group">
              <label>Chave PIX</label>
              <input type="text" name="pix_chave" value="<?= sanitize($c['pix_chave'] ?? '') ?>" placeholder="CPF, CNPJ, e-mail, telefone ou chave aleatória">
            </div>
            <div class="form-group">
              <label>Nome do Beneficiário</label>
              <input type="text" name="pix_nome_beneficiario" value="<?= sanitize($c['pix_nome_beneficiario'] ?? '') ?>" placeholder="Nome (max 25 caracteres)">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Cidade do Beneficiário</label>
              <input type="text" name="pix_cidade" value="<?= sanitize($c['pix_cidade'] ?? '') ?>" placeholder="São Paulo">
            </div>
          </div>
        </div>
      </div>

      <!-- MERCADO PAGO PIX AUTOMÁTICO -->
      <div class="card form-card mb-2">
        <div class="card-header"><h2><i class="fas fa-bolt"></i> Mercado Pago — PIX Automático</h2></div>
        <div class="card-body">
          <p style="font-size:.85rem;color:#64748b;margin-bottom:1rem;">
            <i class="fas fa-circle-info"></i>
            Quando configurado, o sistema criará cobranças PIX reais via API do Mercado Pago com confirmação automática via webhook.<br>
            Obtenha suas credenciais em <a href="https://www.mercadopago.com.br/developers/panel" target="_blank" style="color:#6366f1;">mercadopago.com.br/developers/panel</a>.
          </p>

          <div class="form-row">
            <div class="form-group" style="flex:2">
              <label>Access Token <span style="font-size:.8rem;color:#94a3b8;">(TEST-xxx para sandbox / APP_USR-xxx para produção)</span></label>
              <input type="text" name="mp_access_token"
                value="<?= sanitize($c['mp_access_token'] ?? '') ?>"
                placeholder="TEST-0000000000000000-000000-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-000000000"
                autocomplete="off">
            </div>
            <div class="form-group">
              <label>Ambiente</label>
              <select name="mp_ambiente">
                <option value="sandbox" <?= ($c['mp_ambiente'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox (Testes)</option>
                <option value="producao" <?= ($c['mp_ambiente'] ?? '') === 'producao' ? 'selected' : '' ?>>Produção</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group" style="flex:2">
              <label>Webhook Secret <span style="font-size:.8rem;color:#94a3b8;">(Secret key do webhook para validar assinatura)</span></label>
              <input type="text" name="mp_webhook_secret"
                value="<?= sanitize($c['mp_webhook_secret'] ?? '') ?>"
                placeholder="Gerado ao configurar o webhook no painel MP"
                autocomplete="off">
            </div>
            <div class="form-group">
              <label>E-mail padrão do pagador <span style="font-size:.8rem;color:#94a3b8;">(obrigatório pela API)</span></label>
              <input type="email" name="mp_email_pagador"
                value="<?= sanitize($c['mp_email_pagador'] ?? '') ?>"
                placeholder="pagador@exemplo.com">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>URL do Webhook (configure no painel MP)</label>
              <div style="display:flex;align-items:center;gap:.5rem;">
                <input type="text" readonly
                  value="<?= sanitize(BASE_URL) ?>/admin/pix_webhook.php"
                  style="background:#f1f5f9;color:#475569;cursor:pointer;"
                  onclick="this.select();document.execCommand('copy');mostrarCopiado(this);">
                <button type="button" class="btn btn-sm btn-secondary" onclick="copiarWebhook()"><i class="fas fa-copy"></i> Copiar</button>
              </div>
              <small style="color:#64748b;margin-top:.25rem;display:block;">Tópico: <strong>payment</strong> — Versão: <strong>v1</strong></small>
            </div>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-floppy-disk"></i> Salvar Todas as Configurações</button>
      </div>
    </form>
  </div>
</div>

<script>
function copiarWebhook() {
  const input = document.querySelector('input[value*="pix_webhook"]');
  if (!input) return;
  input.select();
  document.execCommand('copy');
  mostrarCopiado(input);
}
function mostrarCopiado(el) {
  const orig = el.style.background;
  el.style.background = '#d1fae5';
  setTimeout(() => el.style.background = orig, 1200);
}
function toggleHeaderTipo() {
  const tipo = document.getElementById('headerTipo').value;
  document.getElementById('optTexto').style.display  = tipo === 'texto'  ? '' : 'none';
  document.getElementById('optImagem').style.display = tipo === 'imagem' ? '' : 'none';
}
toggleHeaderTipo();

// Sincronizar color picker com input text
document.querySelectorAll('.color-input-wrap').forEach(wrap => {
  const colorIn = wrap.querySelector('input[type=color]');
  const textIn  = wrap.querySelector('.color-text');
  colorIn.addEventListener('input', () => textIn.value = colorIn.value);
  textIn.addEventListener('input', () => {
    if (/^#[0-9A-Fa-f]{6}$/.test(textIn.value)) colorIn.value = textIn.value;
  });
});
</script>
</body>
</html>
