<?php
require_once __DIR__ . '/includes/functions.php';

$db = getDB();
$c  = cfgAll();

// Paginação
$porPagina = max(1, (int)($c['produtos_por_pagina'] ?? 6));
$page      = max(1, (int)($_GET['page'] ?? 1));
$offset    = ($page - 1) * $porPagina;

$totalStmt = $db->query("SELECT COUNT(*) FROM produtos WHERE ativo = 1");
$total     = (int)$totalStmt->fetchColumn();
$totalPages = max(1, ceil($total / $porPagina));

$stmt = $db->prepare("SELECT * FROM produtos WHERE ativo = 1 ORDER BY ordem ASC, criado_em DESC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $porPagina, PDO::PARAM_INT);
$stmt->bindValue(2, $offset,    PDO::PARAM_INT);
$stmt->execute();
$produtos = $stmt->fetchAll();

// Carregar imagens de cada produto
foreach ($produtos as &$prod) {
    $imgStmt = $db->prepare("SELECT * FROM produto_imagens WHERE produto_id = ? ORDER BY ordem ASC");
    $imgStmt->execute([$prod['id']]);
    $prod['imagens'] = $imgStmt->fetchAll();
}
unset($prod);

$corPrimaria   = $c['site_cor_primaria']   ?? '#6366f1';
$corSecundaria = $c['site_cor_secundaria'] ?? '#8b5cf6';
$slideSpeed    = (int)($c['slideshow_velocidade'] ?? 4000);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= sanitize($c['header_slogan'] ?? '') ?> — <?= sanitize($c['site_nome'] ?? 'AppVendas') ?>">
<title><?= sanitize($c['site_nome'] ?? 'AppVendas') ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/site.css">

<style>
  :root {
    --cor-primaria:   <?= sanitize($corPrimaria) ?>;
    --cor-secundaria: <?= sanitize($corSecundaria) ?>;
    --header-bg:      <?= sanitize($c['header_bg_cor']    ?? '#0f172a') ?>;
    --header-color:   <?= sanitize($c['header_texto_cor'] ?? '#ffffff') ?>;
    --footer-bg:      <?= sanitize($c['rodape_bg_cor']    ?? '#0f172a') ?>;
    --footer-color:   <?= sanitize($c['rodape_texto_cor'] ?? '#ffffff') ?>;
  }
</style>
</head>
<body>

<!-- ==================== HEADER ==================== -->
<header class="site-header" style="background:var(--header-bg)">
  <div class="header-inner container">
    <div class="header-brand">
      <?php if (($c['header_tipo'] ?? 'texto') === 'imagem' && !empty($c['header_imagem'])): ?>
        <img src="<?= UPLOAD_URL . sanitize($c['header_imagem']) ?>"
             alt="<?= sanitize($c['site_nome'] ?? 'Logo') ?>"
             style="width:<?= (int)($c['header_imagem_largura'] ?? 200) ?>px; max-width:100%;">
      <?php else: ?>
        <span class="header-text" style="font-size:<?= (float)($c['header_texto_tamanho'] ?? 2.5) ?>rem; color:var(--header-color)">
          <?= sanitize($c['header_texto'] ?? 'AppVendas') ?>
        </span>
      <?php endif; ?>
    </div>
    <?php if (!empty($c['header_slogan'])): ?>
    <p class="header-slogan" style="font-size:<?= (float)($c['header_slogan_tamanho'] ?? 1.2) ?>rem; color:var(--header-color)">
      <?= sanitize($c['header_slogan']) ?>
    </p>
    <?php endif; ?>
  </div>
</header>

<!-- ==================== HERO ==================== -->
<section class="hero-section">
  <div class="hero-particles" id="particles"></div>
  <div class="container hero-content">
    <h2 class="hero-title">Aplicativos Web Profissionais</h2>
    <p class="hero-subtitle">Soluções digitais completas para impulsionar o seu negócio</p>
    <a href="#produtos" class="btn-hero"><i class="fas fa-rocket"></i> Ver Produtos</a>
  </div>
</section>

<!-- ==================== PRODUTOS ==================== -->
<main class="produtos-section" id="produtos">
  <div class="container">
    <div class="section-header">
      <h2><i class="fas fa-box-open"></i> Nossos Aplicativos</h2>
      <p>Escolha o sistema ideal para o seu negócio</p>
    </div>

    <?php if (empty($produtos)): ?>
    <div class="empty-products">
      <i class="fas fa-box-open"></i>
      <p>Nenhum produto disponível no momento.</p>
    </div>
    <?php else: ?>
    <div class="produtos-grid">
      <?php foreach ($produtos as $prod): ?>
      <article class="produto-card" data-id="<?= $prod['id'] ?>">
        <!-- Slideshow de imagens -->
        <div class="produto-slideshow">
          <?php if (!empty($prod['imagens'])): ?>
            <?php foreach ($prod['imagens'] as $idx => $img): ?>
            <div class="slide <?= $idx === 0 ? 'active' : '' ?>">
              <img src="<?= UPLOAD_URL . sanitize($img['caminho']) ?>"
                   alt="<?= sanitize(!empty($img['descricao']) ? $img['descricao'] : $prod['nome'] . ' - imagem ' . ($idx+1)) ?>"
                   loading="lazy">
              <?php if (!empty($img['descricao'])): ?>
              <div class="slide-caption"><?= sanitize($img['descricao']) ?></div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (count($prod['imagens']) > 1): ?>
            <button class="slide-btn slide-prev" aria-label="Anterior"><i class="fas fa-chevron-left"></i></button>
            <button class="slide-btn slide-next" aria-label="Próxima"><i class="fas fa-chevron-right"></i></button>
            <div class="slide-dots">
              <?php foreach ($prod['imagens'] as $idx => $img): ?>
              <span class="dot <?= $idx === 0 ? 'active' : '' ?>" data-index="<?= $idx ?>"></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="slide active slide-placeholder">
              <div class="no-image"><i class="fas fa-image"></i></div>
            </div>
          <?php endif; ?>
        </div>

        <!-- Informações -->
        <div class="produto-info">
          <h3 class="produto-nome"><?= sanitize($prod['nome']) ?></h3>
          <?php if (!empty($prod['descricao'])): ?>
          <p class="produto-desc"><?= nl2br(sanitize($prod['descricao'])) ?></p>
          <?php endif; ?>
          <div class="produto-precos" id="precos-<?= $prod['id'] ?>">
            <?php
              $temMensal = !empty($prod['valor_mensal']) && (float)$prod['valor_mensal'] > 0;
            ?>
            <?php if ($temMensal): ?>
            <!-- Toggle mensal/anual -->
            <div class="preco-toggle" id="toggle-<?= $prod['id'] ?>">
              <button class="toggle-btn toggle-btn-active" data-mode="mensal"
                      onclick="alternarPlano(<?= $prod['id'] ?>, 'mensal')">
                Mensal
              </button>
              <button class="toggle-btn" data-mode="anual"
                      onclick="alternarPlano(<?= $prod['id'] ?>, 'anual')">
                Anual
              </button>
            </div>
            <?php endif; ?>
            <div class="preco-item">
              <span class="preco-label"><i class="fas fa-bolt"></i> Implantação</span>
              <span class="preco-valor preco-impl"><?= formatMoney((float)$prod['valor_implantacao']) ?></span>
              <small>pago apenas uma vez</small>
            </div>
            <div class="preco-divider"></div>
            <!-- Bloco Anual (default quando não tem mensal; ou modo anual) -->
            <div class="preco-item preco-bloco-anual" id="bloco-anual-<?= $prod['id'] ?>"
                 style="<?= $temMensal ? 'display:none;' : '' ?>">
              <span class="preco-label"><i class="fas fa-calendar-check"></i> Manutenção Anual</span>
              <span class="preco-valor preco-anual"><?= formatMoney((float)$prod['valor_anual']) ?></span>
              <small>renovação anual</small>
            </div>
            <?php if ($temMensal): ?>
            <!-- Bloco Mensal (mostrado quando toggle = mensal) -->
            <div class="preco-item preco-bloco-mensal" id="bloco-mensal-<?= $prod['id'] ?>">
              <span class="preco-label"><i class="fas fa-calendar-day"></i> Manutenção Mensal</span>
              <span class="preco-valor preco-mensal"><?= formatMoney((float)$prod['valor_mensal']) ?></span>
              <small>cobrado após 31 dias</small>
            </div>
            <?php endif; ?>
          </div>
          <button class="btn-comprar"
                  onclick="abrirCompra(<?= $prod['id'] ?>, '<?= addslashes(sanitize($prod['nome'])) ?>', <?= (float)$prod['valor_implantacao'] ?>, <?= (float)$prod['valor_anual'] ?>)">
            <i class="fas fa-shopping-cart"></i> Adquirir Agora
          </button>
          <div class="produto-extra-btns">
            <button class="btn-detalhes"
                    onclick="abrirDetalhes(<?= $prod['id'] ?>)">
              <i class="fas fa-magnifying-glass-plus"></i> Mais Detalhes
            </button>
            <?php
              // Link tem prioridade; fallback para arquivo PPTX
              $apresHref  = '';
              $apresLabel = 'Apresentação';
              if (!empty($prod['link_apresentacao'])) {
                  $apresHref = sanitize($prod['link_apresentacao']);
              } elseif (!empty($prod['arquivo_apresentacao'])) {
                  $apresHref = 'admin/apresentacao.php?produto_id=' . (int)$prod['id'];
              }
            ?>
            <?php if ($apresHref): ?>
            <a class="btn-apresentacao"
               href="<?= $apresHref ?>"
               target="_blank" rel="noopener noreferrer">
              <i class="fas fa-file-powerpoint"></i> <?= $apresLabel ?>
            </a>
            <?php endif; ?>
            <?php if (!empty($prod['link_video'])): ?>
            <button class="btn-video"
                    onclick="abrirVideo('<?= addslashes(sanitize($prod['link_video'])) ?>', '<?= addslashes(sanitize($prod['nome'])) ?>')">
              <i class="fab fa-youtube"></i> Vídeo Demo
            </button>
            <?php endif; ?>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <!-- Paginação -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination-wrap">
      <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?>#produtos" class="page-btn"><i class="fas fa-chevron-left"></i></a>
      <?php endif; ?>
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a href="?page=<?= $i ?>#produtos" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page+1 ?>#produtos" class="page-btn"><i class="fas fa-chevron-right"></i></a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</main>

<!-- ==================== FOOTER ==================== -->
<footer class="site-footer" style="background:var(--footer-bg); color:var(--footer-color)">
  <div class="container footer-inner">
    <div class="footer-brand">
      <?php if (($c['header_tipo'] ?? 'texto') === 'imagem' && !empty($c['header_imagem'])): ?>
        <img src="<?= UPLOAD_URL . sanitize($c['header_imagem']) ?>" alt="Logo"
             style="width:<?= min(150, (int)($c['header_imagem_largura'] ?? 150)) ?>px; opacity:.8">
      <?php else: ?>
        <span style="font-size:1.4rem;font-weight:700;"><?= sanitize($c['header_texto'] ?? 'AppVendas') ?></span>
      <?php endif; ?>
    </div>
    <p class="footer-text"><?= sanitize($c['rodape_texto'] ?? '') ?></p>
    <?php if (!empty($c['rodape_whatsapp'])): ?>
    <a href="https://wa.me/<?= preg_replace('/\D/', '', $c['rodape_whatsapp']) ?>"
       target="_blank" class="footer-wa-btn">
      <i class="fab fa-whatsapp"></i> Falar no WhatsApp
    </a>
    <?php endif; ?>
    <p class="footer-copy">&copy; <?= date('Y') ?> <?= sanitize($c['site_nome'] ?? 'AppVendas') ?>. Todos os direitos reservados.</p>
  </div>
</footer>

<!-- ==================== MODAL DE VÍDEO FULLSCREEN ==================== -->
<div class="modal-video" id="modalVideo" role="dialog" aria-modal="true" aria-label="Vídeo de demonstração">
  <div class="modal-video-backdrop" onclick="fecharVideo()"></div>
  <div class="modal-video-inner">
    <div class="modal-video-header">
      <span class="modal-video-titulo" id="modalVideoTitulo"></span>
      <button class="modal-video-close" onclick="fecharVideo()" aria-label="Fechar">
        <i class="fas fa-xmark"></i>
      </button>
    </div>
    <div class="modal-video-frame">
      <iframe id="modalVideoIframe"
              src=""
              frameborder="0"
              allow="autoplay; encrypted-media; fullscreen; picture-in-picture"
              allowfullscreen
              title="Vídeo de demonstração"></iframe>
    </div>
  </div>
</div>

<!-- ==================== MODAL DE COMPRA ==================== -->
<div class="modal-overlay" id="modalCompra">
  <div class="modal-box modal-compra">
    <button class="modal-close-btn" onclick="fecharModal('modalCompra')"><i class="fas fa-xmark"></i></button>
    <div class="modal-steps">
      <!-- Step 1: Dados do cliente -->
      <div class="step" id="stepDados">
        <div class="step-header">
          <div class="step-icon"><i class="fas fa-user"></i></div>
          <h3>Seus Dados</h3>
          <p>Preencha seus dados para continuar</p>
        </div>
        <form id="formDados" onsubmit="irParaPagamento(event)">
          <input type="hidden" id="compProdutoId">
          <input type="hidden" id="compProdutoNome">
          <input type="hidden" id="compValorImpl">
          <input type="hidden" id="compValorAnual">
          <div class="form-group">
            <label>Nome Completo *</label>
            <input type="text" id="clienteNome" required placeholder="Seu nome completo">
          </div>
          <div class="form-group">
            <label>CPF ou CNPJ *</label>
            <input type="text" id="clienteDoc" required placeholder="000.000.000-00" maxlength="18">
          </div>
          <div class="form-group">
            <label>Nome do Estabelecimento *</label>
            <input type="text" id="clienteEstab" required placeholder="Nome da sua empresa/negócio">
          </div>
          <div class="form-group">
            <label>WhatsApp *</label>
            <input type="text" id="clienteWa" required placeholder="(11) 99999-9999">
          </div>
          <div class="produto-resumo" id="resumoProduto"></div>
          <button type="submit" class="btn-primary-full"><i class="fas fa-arrow-right"></i> Continuar para Pagamento</button>
        </form>
      </div>

      <!-- Step 2: Pagamento PIX -->
      <div class="step hidden" id="stepPix">
        <div class="step-header">
          <div class="step-icon step-icon-green"><i class="fas fa-qrcode"></i></div>
          <h3>Pagamento via PIX</h3>
          <p>Escaneie o QR Code ou copie a chave</p>
        </div>
        <div class="pix-container">
          <div class="pix-info-box">
            <div class="pix-prod-name" id="pixProdNome"></div>
            <div class="pix-valores">
              <div class="pix-val-item pix-val-main">
                <span><i class="fas fa-bolt"></i> Implantação (pagar agora)</span>
                <strong id="pixValImpl"></strong>
              </div>
              <div class="pix-val-item">
                <span><i class="fas fa-calendar-check"></i> Manutenção Anual</span>
                <strong id="pixValAnual"></strong>
              </div>
              <div class="pix-val-aviso">
                <i class="fas fa-circle-info"></i>
                <span id="pixAvisoAnual"></span>
              </div>
            </div>
          </div>
          <div class="qrcode-wrap">
            <div id="qrcodeImg"></div>
            <p class="qr-instrucao">Abra o app do banco e escaneie</p>
          </div>
          <div class="pix-copy-wrap">
            <label>Pix Copia e Cola:</label>
            <div class="pix-copy-row">
              <input type="text" id="pixPayload" readonly>
              <button onclick="copiarPix()" class="btn-copy"><i class="fas fa-copy"></i> Copiar</button>
            </div>
            <span id="copyMsg" class="copy-msg"></span>
          </div>
          <div class="pix-steps-info">
            <div class="pix-step-item"><i class="fas fa-1"></i> Abra o app do seu banco</div>
            <div class="pix-step-item"><i class="fas fa-2"></i> Escolha pagar com PIX</div>
            <div class="pix-step-item"><i class="fas fa-3"></i> Escaneie o QR Code ou cole a chave</div>
            <div class="pix-step-item"><i class="fas fa-4"></i> Confirme o pagamento</div>
          </div>
          <button onclick="voltarDados()" class="btn-outline-full"><i class="fas fa-arrow-left"></i> Voltar</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ==================== MODAL DETALHES FULLSCREEN ==================== -->
<div class="modal-detalhes" id="modalDetalhes" role="dialog" aria-modal="true" aria-label="Detalhes do produto">
  <button class="det-close" id="detClose" aria-label="Fechar"><i class="fas fa-xmark"></i></button>

  <!-- Slideshow fullscreen -->
  <div class="det-slideshow" id="detSlideshow">
    <button class="det-slide-btn det-prev" id="detPrev" aria-label="Anterior"><i class="fas fa-chevron-left"></i></button>
    <button class="det-slide-btn det-next" id="detNext" aria-label="Próxima"><i class="fas fa-chevron-right"></i></button>
    <div class="det-slides-wrap" id="detSlidesWrap">
      <!-- slides injetados via JS -->
    </div>
    <div class="det-dots-wrap" id="detDotsWrap"></div>
    <div class="det-counter" id="detCounter"></div>
    <div class="det-img-caption" id="detImgCaption" style="display:none;"></div>
  </div>

  <!-- Painel de informações -->
  <div class="det-info-panel" id="detInfoPanel">
    <div class="det-info-inner">
      <h2 class="det-nome" id="detNome"></h2>
      <div class="det-precos" id="detPrecos"></div>
      <div class="det-desc-wrap">
        <h4><i class="fas fa-list-check"></i> Descrição</h4>
        <p class="det-desc" id="detDesc"></p>
      </div>
      <button class="det-btn-comprar" id="detBtnComprar">
        <i class="fas fa-shopping-cart"></i> Adquirir Agora
      </button>
      <a class="det-btn-manual hidden" id="detBtnManual" href="#" target="_blank" rel="noopener noreferrer">
        <i class="fas fa-file-pdf"></i> Manual do Produto
      </a>
      <a class="det-btn-exemplo hidden" id="detBtnExemplo" href="#" target="_blank" rel="noopener noreferrer">
        <i class="fas fa-arrow-up-right-from-square"></i> Ver Exemplo
      </a>
      <a class="det-btn-apresentacao hidden" id="detBtnApresentacao" href="#" target="_blank" rel="noopener noreferrer">
        <i class="fas fa-tv"></i> Apresentação
      </a>
      <a class="det-btn-pptx hidden" id="detBtnPptx" href="#" target="_blank" rel="noopener noreferrer">
        <i class="fas fa-file-powerpoint"></i> Baixar Apresentação (PPTX)
      </a>
      <button class="det-btn-video hidden" id="detBtnVideo">
        <i class="fab fa-youtube"></i> Vídeo Demo
      </button>
    </div>
  </div>
</div>

<!-- Dados dos produtos para o JS -->
<script>
const PRODUTOS_DATA = <?= json_encode(array_map(function($p) {
    return [
        'id'                => (int)$p['id'],
        'nome'              => $p['nome'],
        'descricao'         => $p['descricao'],
        'valor_implantacao' => (float)$p['valor_implantacao'],
        'valor_anual'       => (float)$p['valor_anual'],
        'link_exemplo'      => $p['link_exemplo']      ?? '',
        'link_apresentacao' => $p['link_apresentacao']  ?? '',
        'manual_url'        => !empty($p['arquivo_manual'])
                                   ? 'admin/manual.php?produto_id=' . (int)$p['id']
                                   : '',
        'pptx_url'          => !empty($p['link_apresentacao'])
                                   ? $p['link_apresentacao']
                                   : (!empty($p['arquivo_apresentacao'])
                                       ? 'admin/apresentacao.php?produto_id=' . (int)$p['id']
                                       : ''),
        'video_url'         => $p['link_video'] ?? '',
        'valor_mensal'      => (float)($p['valor_mensal'] ?? 0),
        'imagens'           => array_map(function($img) {
            return [
                'url'      => UPLOAD_URL . $img['caminho'],
                'descricao'=> $img['descricao'] ?? '',
            ];
        }, $p['imagens']),
    ];
}, $produtos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
  const SLIDE_SPEED  = <?= $slideSpeed ?>;
  const UPLOAD_URL   = '<?= UPLOAD_URL ?>';
  const CHECKOUT_URL = '<?= BASE_URL ?>/checkout.php';
</script>
<script src="assets/js/site.js"></script>
</body>
</html>
