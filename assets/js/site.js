/* ============================================================
   SITE.JS — JavaScript do site público
   Slideshow, modal de compra, PIX, partículas
   ============================================================ */

'use strict';

// ── PARTÍCULAS DO HERO ──────────────────────────────────────
(function createParticles() {
  const container = document.getElementById('particles');
  if (!container) return;
  const count = window.innerWidth < 768 ? 12 : 22;
  for (let i = 0; i < count; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    const size = Math.random() * 60 + 10;
    p.style.cssText = `
      width:${size}px; height:${size}px;
      left:${Math.random() * 100}%;
      animation-duration:${Math.random() * 12 + 8}s;
      animation-delay:${Math.random() * 10}s;
    `;
    container.appendChild(p);
  }
})();

// ── SLIDESHOWS ─────────────────────────────────────────────
class Slideshow {
  constructor(card) {
    this.card    = card;
    this.slides  = Array.from(card.querySelectorAll('.slide'));
    this.dots    = Array.from(card.querySelectorAll('.dot'));
    this.current = 0;
    this.timer   = null;

    if (this.slides.length <= 1) return;

    card.querySelector('.slide-prev')?.addEventListener('click', e => {
      e.stopPropagation();
      this.go(this.current - 1);
      this.resetTimer();
    });

    card.querySelector('.slide-next')?.addEventListener('click', e => {
      e.stopPropagation();
      this.go(this.current + 1);
      this.resetTimer();
    });

    this.dots.forEach((dot, i) => {
      dot.addEventListener('click', e => {
        e.stopPropagation();
        this.go(i);
        this.resetTimer();
      });
    });

    // Pausar no hover
    card.querySelector('.produto-slideshow').addEventListener('mouseenter', () => this.stop());
    card.querySelector('.produto-slideshow').addEventListener('mouseleave', () => this.start());

    // Touch swipe
    let startX = 0;
    const ss = card.querySelector('.produto-slideshow');
    ss.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
    ss.addEventListener('touchend', e => {
      const diff = startX - e.changedTouches[0].clientX;
      if (Math.abs(diff) > 40) {
        this.go(diff > 0 ? this.current + 1 : this.current - 1);
        this.resetTimer();
      }
    });

    this.start();
  }

  go(idx) {
    this.slides[this.current].classList.remove('active');
    this.dots[this.current]?.classList.remove('active');
    this.current = ((idx % this.slides.length) + this.slides.length) % this.slides.length;
    this.slides[this.current].classList.add('active');
    this.dots[this.current]?.classList.add('active');
  }

  start() {
    this.timer = setInterval(() => this.go(this.current + 1), SLIDE_SPEED);
  }

  stop() {
    clearInterval(this.timer);
  }

  resetTimer() {
    this.stop();
    this.start();
  }
}

document.querySelectorAll('.produto-card').forEach(card => new Slideshow(card));

// ── MODAL DE COMPRA ─────────────────────────────────────────
let dadosCompra = {};

function abrirCompra(produtoId, produtoNome, valorImpl, valorAnual) {
  // Verificar se o toggle mensal está ativo para este produto
  var prod     = typeof PRODUTOS_DATA !== 'undefined'
                   ? PRODUTOS_DATA.find(function(p) { return p.id === produtoId; })
                   : null;
  var temMensal  = prod && prod.valor_mensal && prod.valor_mensal > 0;
  var modoMensal = false;
  if (temMensal) {
    var toggleEl = document.querySelector('#toggle-' + produtoId + ' .toggle-btn-active');
    modoMensal = toggleEl && toggleEl.dataset.mode === 'mensal';
  }

  var valorManutencao = modoMensal ? prod.valor_mensal : valorAnual;
  var labelManutencao = modoMensal ? 'Manutenção mensal' : 'Manutenção anual';
  var infoManutencao  = modoMensal
    ? 'Você pagará ' + formatBRL(valorManutencao) + ' de manutenção mensal. A 1ª cobrança será em <strong>31 dias</strong>.'
    : 'Você pagará ' + formatBRL(valorManutencao) + ' de manutenção anual com vencimento em 366 dias.';

  dadosCompra = { produtoId, produtoNome, valorImpl, valorAnual: valorManutencao, modoMensal };

  document.getElementById('compProdutoId').value   = produtoId;
  document.getElementById('compProdutoNome').value = produtoNome;
  document.getElementById('compValorImpl').value   = valorImpl;
  document.getElementById('compValorAnual').value  = valorManutencao;

  document.getElementById('resumoProduto').innerHTML = `
    <strong><i class="fas fa-box-open"></i> ${produtoNome}</strong><br>
    <span>Implantação: <strong>${formatBRL(valorImpl)}</strong> (pagar agora via PIX)</span><br>
    <span>${labelManutencao}: <strong>${formatBRL(valorManutencao)}</strong> (${modoMensal ? 'cobrado após 31 dias' : 'cobrado após 366 dias'})</span>
  `;

  mostrarStep('stepDados');
  abrirModal('modalCompra');
}

function abrirModal(id) {
  document.getElementById(id).classList.add('active');
  document.body.style.overflow = 'hidden';
}

function fecharModal(id) {
  document.getElementById(id).classList.remove('active');
  document.body.style.overflow = '';
}

// Fechar ao clicar fora
document.getElementById('modalCompra').addEventListener('click', function(e) {
  if (e.target === this) fecharModal('modalCompra');
});

// Fechar com ESC — tratado globalmente mais abaixo

function mostrarStep(id) {
  document.querySelectorAll('.step').forEach(s => s.classList.add('hidden'));
  document.getElementById(id).classList.remove('hidden');
}

// ── MÁSCARA CPF/CNPJ ────────────────────────────────────────
const docInput = document.getElementById('clienteDoc');
if (docInput) {
  docInput.addEventListener('input', function() {
    let v = this.value.replace(/\D/g, '').substring(0, 14);
    if (v.length <= 11) {
      v = v.replace(/(\d{3})(\d)/, '$1.$2')
           .replace(/(\d{3})(\d)/, '$1.$2')
           .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    } else {
      v = v.replace(/(\d{2})(\d)/, '$1.$2')
           .replace(/(\d{3})(\d)/, '$1.$2')
           .replace(/(\d{3})(\d)/, '$1/$2')
           .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
    }
    this.value = v;
  });
}

// ── MÁSCARA WHATSAPP ────────────────────────────────────────
const waInput = document.getElementById('clienteWa');
if (waInput) {
  waInput.addEventListener('input', function() {
    let v = this.value.replace(/\D/g, '').substring(0, 11);
    if (v.length <= 10) {
      v = v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    } else {
      v = v.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
    }
    this.value = v;
  });
}

// ── ENVIO DO FORMULÁRIO ─────────────────────────────────────
async function irParaPagamento(e) {
  e.preventDefault();

  const btn = e.target.querySelector('button[type=submit]');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';

  const payload = {
    produto_id:    document.getElementById('compProdutoId').value,
    cliente_nome:  document.getElementById('clienteNome').value.trim(),
    cliente_doc:   document.getElementById('clienteDoc').value.trim(),
    cliente_estab: document.getElementById('clienteEstab').value.trim(),
    cliente_wa:    document.getElementById('clienteWa').value.trim(),
  };

  try {
    const res  = await fetch(CHECKOUT_URL, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload),
    });
    const data = await res.json();

    if (data.erro) {
      showAlert(data.erro, 'danger');
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-arrow-right"></i> Continuar para Pagamento';
      return;
    }

    // Preencher tela PIX
    document.getElementById('pixProdNome').textContent  = data.produto_nome;
    document.getElementById('pixValImpl').textContent   = formatBRL(data.valor_implantacao);
    document.getElementById('pixValAnual').textContent  = formatBRL(data.valor_anual);
    document.getElementById('pixAvisoAnual').innerHTML  =
      dadosCompra.modoMensal
        ? `Você pagará ${formatBRL(data.valor_anual)} de manutenção <strong>mensal</strong>. A 1ª cobrança será em <strong>31 dias</strong> após ativação do sistema.`
        : `Você pagará ${formatBRL(data.valor_anual)} de manutenção anual com vencimento em <strong>${data.vencimento_anual}</strong>. Após o pagamento da implantação, você receberá orientações para ativação.`;

    // ── GERAR QR CODE ───────────────────────────────────────────
    const qrcodeEl = document.getElementById('qrcodeImg');
    qrcodeEl.innerHTML = '';

    // Remover badge anterior se existir
    const badgeAntigo = document.getElementById('pixBadgeAuto');
    if (badgeAntigo) badgeAntigo.remove();

    // Log para debug (visível no F12 → Console)
    console.log('[PIX] pix_automatico:', data.pix_automatico);
    console.log('[PIX] qr_code_base64 length:', (data.qr_code_base64||'').length);
    console.log('[PIX] qr_code_text length:', (data.qr_code_text||'').length);
    if (data.mp_erro) console.warn('[PIX] MP Erro:', data.mp_erro);

    if (data.pix_automatico && data.qr_code_base64) {
      // ✅ Mercado Pago: QR Code real (imagem base64 enviada pela API)
      document.getElementById('pixPayload').value = data.qr_code_text || '';
      qrcodeEl.innerHTML = `<img src="data:image/png;base64,${data.qr_code_base64}"
        alt="QR Code PIX" style="width:220px;height:220px;display:block;margin:0 auto;border-radius:8px;">`;

      // Badge "PIX Automático"
      const badge = document.createElement('div');
      badge.id = 'pixBadgeAuto';
      badge.innerHTML = '<i class="fas fa-bolt"></i> PIX Automático — confirmação automática';
      badge.style.cssText = 'background:#d1fae5;color:#065f46;border-radius:6px;padding:.4rem .8rem;font-size:.8rem;text-align:center;margin-bottom:.5rem;';
      qrcodeEl.parentNode.insertBefore(badge, qrcodeEl);

    } else if (data.pix_automatico && data.qr_code_text) {
      // ✅ MP: sem imagem base64, mas tem texto copia-e-cola — gerar QR via lib local
      document.getElementById('pixPayload').value = data.qr_code_text;
      if (typeof QRCode !== 'undefined') {
        new QRCode(qrcodeEl, {
          text:         data.qr_code_text,
          width:        220,
          height:       220,
          colorDark:    '#000000',
          colorLight:   '#ffffff',
          correctLevel: QRCode.CorrectLevel.M,
        });
      }
      const badge = document.createElement('div');
      badge.id = 'pixBadgeAuto';
      badge.innerHTML = '<i class="fas fa-bolt"></i> PIX Automático — confirmação automática';
      badge.style.cssText = 'background:#d1fae5;color:#065f46;border-radius:6px;padding:.4rem .8rem;font-size:.8rem;text-align:center;margin-bottom:.5rem;';
      qrcodeEl.parentNode.insertBefore(badge, qrcodeEl);

    } else if (data.pix_payload) {
      // ⚙️ Fallback: payload PIX estático (EMV/BR Code)
      document.getElementById('pixPayload').value = data.pix_payload;
      if (typeof QRCode !== 'undefined') {
        new QRCode(qrcodeEl, {
          text:         data.pix_payload,
          width:        220,
          height:       220,
          colorDark:    '#000000',
          colorLight:   '#ffffff',
          correctLevel: QRCode.CorrectLevel.M,
        });
      }
      // Aviso se MP estava configurado mas falhou
      if (data.mp_erro) {
        const badge = document.createElement('div');
        badge.id = 'pixBadgeAuto';
        badge.innerHTML = '<i class="fas fa-triangle-exclamation"></i> PIX Manual (MP indisponível)';
        badge.style.cssText = 'background:#fef3c7;color:#92400e;border-radius:6px;padding:.4rem .8rem;font-size:.8rem;text-align:center;margin-bottom:.5rem;';
        qrcodeEl.parentNode.insertBefore(badge, qrcodeEl);
      }

    } else {
      // ⚠️ Nada configurado — mostrar aviso
      document.getElementById('pixPayload').value = data.pix_chave || 'Chave PIX não configurada';
      qrcodeEl.innerHTML = `
        <div style="padding:2rem;text-align:center;color:#94a3b8;">
          <i class="fas fa-triangle-exclamation" style="font-size:2.5rem;display:block;margin-bottom:.75rem;color:#f59e0b;"></i>
          <p style="font-size:.85rem;">QR Code indisponível.<br>Use a chave PIX abaixo.</p>
        </div>`;
    }

    mostrarStep('stepPix');

  } catch (err) {
    showAlert('Erro de conexão. Verifique sua internet e tente novamente.', 'danger');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-arrow-right"></i> Continuar para Pagamento';
  }
}

function voltarDados() {
  mostrarStep('stepDados');
  const btn = document.querySelector('#formDados button[type=submit]');
  if (btn) {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-arrow-right"></i> Continuar para Pagamento';
  }
}

// ── COPIAR PIX ──────────────────────────────────────────────
function copiarPix() {
  const inp  = document.getElementById('pixPayload');
  const msg  = document.getElementById('copyMsg');
  inp.select();
  try {
    navigator.clipboard.writeText(inp.value).then(() => {
      msg.textContent = '✓ Copiado com sucesso!';
      setTimeout(() => { msg.textContent = ''; }, 3000);
    });
  } catch (e) {
    document.execCommand('copy');
    msg.textContent = '✓ Copiado!';
    setTimeout(() => { msg.textContent = ''; }, 3000);
  }
}

// ── HELPERS ─────────────────────────────────────────────────
function formatBRL(v) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);
}

// ── TOGGLE MENSAL / ANUAL ────────────────────────────────────
function alternarPlano(produtoId, modo) {
  if (typeof PRODUTOS_DATA === 'undefined') return;
  var prod = PRODUTOS_DATA.find(function(p) { return p.id === produtoId; });
  if (!prod) return;

  // Atualizar visual dos botões
  var toggle = document.getElementById('toggle-' + produtoId);
  if (toggle) {
    toggle.querySelectorAll('.toggle-btn').forEach(function(btn) {
      btn.classList.toggle('toggle-btn-active', btn.dataset.mode === modo);
    });
  }

  // Mostrar/ocultar blocos de preço
  var blocoAnual   = document.getElementById('bloco-anual-'   + produtoId);
  var blocoMensal  = document.getElementById('bloco-mensal-'  + produtoId);
  if (blocoAnual)  blocoAnual.style.display  = modo === 'anual'   ? '' : 'none';
  if (blocoMensal) blocoMensal.style.display = modo === 'mensal'  ? '' : 'none';
}

// ── Toggle mensal/anual dentro do MODAL DE DETALHES ──────────
function detAlternarPlano(produtoId, modo) {
  // Atualizar toggle dentro do modal
  var detToggle = document.getElementById('det-toggle-' + produtoId);
  if (detToggle) {
    detToggle.querySelectorAll('.toggle-btn').forEach(function(btn) {
      btn.classList.toggle('toggle-btn-active', btn.dataset.mode === modo);
    });
  }
  // Mostrar/ocultar blocos
  var blocoA = document.getElementById('det-bloco-anual-'  + produtoId);
  var blocoM = document.getElementById('det-bloco-mensal-' + produtoId);
  if (blocoA) blocoA.style.display = modo === 'anual'  ? '' : 'none';
  if (blocoM) blocoM.style.display = modo === 'mensal' ? '' : 'none';

  // Sincronizar toggle no card (se existir)
  alternarPlano(produtoId, modo);
}

function showAlert(msg, type) {
  const existing = document.querySelector('.site-alert');
  if (existing) existing.remove();

  const div = document.createElement('div');
  div.className = `site-alert site-alert-${type}`;
  div.style.cssText = `
    position:fixed; top:1rem; right:1rem; z-index:9999;
    background:${type === 'danger' ? '#fee2e2' : '#d1fae5'};
    color:${type === 'danger' ? '#991b1b' : '#065f46'};
    border:1px solid ${type === 'danger' ? '#fecaca' : '#a7f3d0'};
    border-radius:10px; padding:.875rem 1.25rem;
    font-size:.875rem; font-weight:500;
    box-shadow:0 4px 20px rgba(0,0,0,.15);
    max-width:340px; animation:slideInRight .3s ease;
  `;
  div.innerHTML = `<i class="fas fa-${type === 'danger' ? 'circle-exclamation' : 'circle-check'}"></i> ${msg}`;
  document.body.appendChild(div);
  setTimeout(() => div.remove(), 5000);
}

// ── SMOOTH SCROLL ────────────────────────────────────────────
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', function(e) {
    const target = document.querySelector(this.getAttribute('href'));
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
});

// ── MODAL DETALHES FULLSCREEN ────────────────────────────────
const detModal     = document.getElementById('modalDetalhes');
const detClose     = document.getElementById('detClose');
const detSlidesWrap= document.getElementById('detSlidesWrap');
const detDotsWrap  = document.getElementById('detDotsWrap');
const detCounter   = document.getElementById('detCounter');
const detNome      = document.getElementById('detNome');
const detPrecos    = document.getElementById('detPrecos');
const detDesc      = document.getElementById('detDesc');
const detBtnComprar= document.getElementById('detBtnComprar');
const detPrev      = document.getElementById('detPrev');
const detNext      = document.getElementById('detNext');

let detSlideshow = null; // instância do slideshow fullscreen

// ── Classe slideshow reutilizável para o modal detalhes ──────
class DetSlideshow {
  constructor(slidesWrap, dotsWrap, counterEl, speed) {
    this.slides         = Array.from(slidesWrap.querySelectorAll('.det-slide'));
    this.dots           = Array.from(dotsWrap.querySelectorAll('.det-dot'));
    this.counter        = counterEl;
    this.speed          = speed || 4000;
    this.current        = 0;
    this.timer          = null;
    this.onSlideChange  = null; // callback externo (para legenda)

    this.updateCounter();
    if (this.slides.length > 1) this.start();
  }

  go(idx) {
    this.slides[this.current].classList.remove('active');
    this.dots[this.current] && this.dots[this.current].classList.remove('active');
    this.current = ((idx % this.slides.length) + this.slides.length) % this.slides.length;
    this.slides[this.current].classList.add('active');
    this.dots[this.current] && this.dots[this.current].classList.add('active');
    this.updateCounter();
    // Disparar callback de legenda (se definido)
    if (typeof this.onSlideChange === 'function') this.onSlideChange(this.current);
  }

  updateCounter() {
    if (this.counter) {
      this.counter.textContent = this.slides.length > 1
        ? (this.current + 1) + ' / ' + this.slides.length
        : '';
    }
  }

  start() {
    this.stop();
    this.timer = setInterval(() => this.go(this.current + 1), this.speed);
  }

  stop() {
    clearInterval(this.timer);
    this.timer = null;
  }

  resetTimer() { this.start(); }
}

// ── Abrir modal detalhes ─────────────────────────────────────
function abrirDetalhes(produtoId) {
  if (typeof PRODUTOS_DATA === 'undefined') return;

  const prod = PRODUTOS_DATA.find(function(p) { return p.id === produtoId; });
  if (!prod) return;

  // Parar slideshow anterior
  if (detSlideshow) { detSlideshow.stop(); detSlideshow = null; }

  // ── Montar slides ──
  detSlidesWrap.innerHTML = '';
  dotsWrap: {
    detDotsWrap.innerHTML = '';
  }

  if (prod.imagens && prod.imagens.length > 0) {
    prod.imagens.forEach(function(imgObj, i) {
      // Suporte a formato antigo (string) e novo (objeto {url, descricao})
      var url  = typeof imgObj === 'string' ? imgObj : imgObj.url;
      var desc = typeof imgObj === 'string' ? '' : (imgObj.descricao || '');

      // Slide
      var slide = document.createElement('div');
      slide.className = 'det-slide' + (i === 0 ? ' active' : '');
      var img = document.createElement('img');
      img.src = url;
      img.alt = desc || (prod.nome + ' - imagem ' + (i + 1));
      // Não usar loading='lazy': imagens são injetadas dinamicamente
      // e devem carregar imediatamente quando o modal é aberto
      slide.appendChild(img);

      detSlidesWrap.appendChild(slide);

      // Dot
      var dot = document.createElement('button');
      dot.className = 'det-dot' + (i === 0 ? ' active' : '');
      dot.setAttribute('aria-label', 'Imagem ' + (i + 1));
      dot.addEventListener('click', function() {
        detSlideshow && detSlideshow.go(i);
        detSlideshow && detSlideshow.resetTimer();
      });
      detDotsWrap.appendChild(dot);
    });

    // Mostrar/ocultar setas
    detPrev.style.display = prod.imagens.length > 1 ? '' : 'none';
    detNext.style.display = prod.imagens.length > 1 ? '' : 'none';
    detCounter.style.display = prod.imagens.length > 1 ? '' : 'none';

  } else {
    // Sem imagens — placeholder
    var slide = document.createElement('div');
    slide.className = 'det-slide active';
    slide.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#475569;font-size:4rem;"><i class="fas fa-image"></i></div>';
    detSlidesWrap.appendChild(slide);
    detPrev.style.display = 'none';
    detNext.style.display = 'none';
    detCounter.style.display = 'none';
  }

  // ── Iniciar slideshow ──
  detSlideshow = new DetSlideshow(detSlidesWrap, detDotsWrap, detCounter, SLIDE_SPEED);

  // ── Preencher informações ──
  detNome.textContent = prod.nome;

  // Construir bloco de preços com toggle mensal/anual (se disponível)
  var temMensalModal = prod.valor_mensal && prod.valor_mensal > 0;
  // Verificar modo atual do toggle no card (se existir)
  var modoAtualModal = 'anual';
  if (temMensalModal) {
    var toggleCardEl = document.querySelector('#toggle-' + prod.id + ' .toggle-btn-active');
    if (toggleCardEl) modoAtualModal = toggleCardEl.dataset.mode || 'mensal';
    else modoAtualModal = 'mensal'; // default: mensal quando disponível
  }

  var precosHtml = '';
  if (temMensalModal) {
    precosHtml +=
      '<div class="preco-toggle" id="det-toggle-' + prod.id + '" style="margin-bottom:.6rem;">' +
        '<button class="toggle-btn ' + (modoAtualModal === 'mensal' ? 'toggle-btn-active' : '') + '" data-mode="mensal"' +
                ' onclick="detAlternarPlano(' + prod.id + ', \'mensal\')">' +
          'Mensal' +
        '</button>' +
        '<button class="toggle-btn ' + (modoAtualModal === 'anual' ? 'toggle-btn-active' : '') + '" data-mode="anual"' +
                ' onclick="detAlternarPlano(' + prod.id + ', \'anual\')">' +
          'Anual' +
        '</button>' +
      '</div>';
  }
  precosHtml +=
    '<div class="det-preco-item">' +
      '<span class="det-preco-label"><i class="fas fa-bolt"></i> Implantação</span>' +
      '<span class="det-preco-valor det-preco-impl">' + formatBRL(prod.valor_implantacao) + '</span>' +
      '<span class="det-preco-small">pago uma única vez</span>' +
    '</div>';
  // Bloco anual
  precosHtml +=
    '<div class="det-preco-item" id="det-bloco-anual-' + prod.id + '"' +
         (temMensalModal && modoAtualModal !== 'anual' ? ' style="display:none;"' : '') + '>' +
      '<span class="det-preco-label"><i class="fas fa-calendar-check"></i> Manutenção Anual</span>' +
      '<span class="det-preco-valor det-preco-anual">' + formatBRL(prod.valor_anual) + '</span>' +
      '<span class="det-preco-small">renovação anual</span>' +
    '</div>';
  // Bloco mensal (somente se disponível)
  if (temMensalModal) {
    precosHtml +=
      '<div class="det-preco-item" id="det-bloco-mensal-' + prod.id + '"' +
           (modoAtualModal !== 'mensal' ? ' style="display:none;"' : '') + '>' +
        '<span class="det-preco-label"><i class="fas fa-calendar-day"></i> Manutenção Mensal</span>' +
        '<span class="det-preco-valor det-preco-mensal">' + formatBRL(prod.valor_mensal) + '</span>' +
        '<span class="det-preco-small">cobrado após 31 dias</span>' +
      '</div>';
  }
  detPrecos.innerHTML = precosHtml;

  // Legenda da imagem atual (atualizada ao trocar slide)
  var detImgCaption = document.getElementById('detImgCaption');
  function updateDetCaption(idx) {
    if (!detImgCaption) return;
    var cur = prod.imagens[idx !== undefined ? idx : (detSlideshow ? detSlideshow.current : 0)];
    var d = cur ? (typeof cur === 'string' ? '' : (cur.descricao || '')) : '';
    detImgCaption.textContent = d;
    detImgCaption.style.display = d ? '' : 'none';
  }
  // Guardar ref da função no slideshow para o timer interno chamar também
  detSlideshow.onSlideChange = updateDetCaption;
  updateDetCaption(0);

  detDesc.innerHTML = prod.descricao
    ? prod.descricao.replace(/\n/g, '<br>')
    : '<em style="color:#94a3b8">Sem descrição disponível.</em>';

  // ── Botão comprar ──
  detBtnComprar.onclick = function() {
    fecharDetalhes();
    abrirCompra(prod.id, prod.nome, prod.valor_implantacao, prod.valor_anual);
  };

  // ── Botão Ver Exemplo ──
  var btnExemplo = document.getElementById('detBtnExemplo');
  if (prod.link_exemplo && prod.link_exemplo.trim() !== '') {
    btnExemplo.href = prod.link_exemplo;
    btnExemplo.classList.remove('hidden');
  } else {
    btnExemplo.href = '#';
    btnExemplo.classList.add('hidden');
  }

  // ── Botão Manual PDF ──
  var btnManual = document.getElementById('detBtnManual');
  if (btnManual) {
    if (prod.manual_url && prod.manual_url.trim() !== '') {
      btnManual.href = prod.manual_url;
      btnManual.classList.remove('hidden');
    } else {
      btnManual.href = '#';
      btnManual.classList.add('hidden');
    }
  }

  // ── Botão Apresentação ──
  var btnApresentacao = document.getElementById('detBtnApresentacao');
  if (btnApresentacao) {
    if (prod.link_apresentacao && prod.link_apresentacao.trim() !== '') {
      btnApresentacao.href = prod.link_apresentacao;
      btnApresentacao.classList.remove('hidden');
    } else {
      btnApresentacao.href = '#';
      btnApresentacao.classList.add('hidden');
    }
  }

  // ── Botão PPTX ──
  var btnPptx = document.getElementById('detBtnPptx');
  if (btnPptx) {
    if (prod.pptx_url && prod.pptx_url.trim() !== '') {
      btnPptx.href = prod.pptx_url;
      btnPptx.classList.remove('hidden');
    } else {
      btnPptx.href = '#';
      btnPptx.classList.add('hidden');
    }
  }

  // ── Botão Vídeo Demo ──
  var btnVideo = document.getElementById('detBtnVideo');
  if (btnVideo) {
    if (prod.video_url && prod.video_url.trim() !== '') {
      btnVideo.onclick = function() { abrirVideo(prod.video_url, prod.nome); };
      btnVideo.classList.remove('hidden');
    } else {
      btnVideo.onclick = null;
      btnVideo.classList.add('hidden');
    }
  }

  // ── Abrir modal ──
  detModal.classList.add('active');
  document.body.style.overflow = 'hidden';

  // Rolar painel de info para o topo
  var panel = document.getElementById('detInfoPanel');
  if (panel) panel.scrollTop = 0;
}

// ── Fechar modal detalhes ────────────────────────────────────
function fecharDetalhes() {
  detModal.classList.remove('active');
  document.body.style.overflow = '';
  if (detSlideshow) { detSlideshow.stop(); detSlideshow = null; }
}

// ── MODAL DE VÍDEO ───────────────────────────────────────────

/**
 * Converte URLs de YouTube e Vimeo para URL de embed com autoplay.
 * Qualquer outra URL é usada diretamente (MP4, etc.).
 */
function videoParaEmbed(url) {
  if (!url) return '';

  // YouTube: youtu.be/ID  ou  youtube.com/watch?v=ID  ou  youtube.com/embed/ID
  var ytMatch = url.match(
    /(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|v\/))([A-Za-z0-9_-]{11})/
  );
  if (ytMatch) {
    return 'https://www.youtube.com/embed/' + ytMatch[1] + '?autoplay=1&rel=0&modestbranding=1';
  }

  // Vimeo: vimeo.com/ID  ou  player.vimeo.com/video/ID
  var vmMatch = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
  if (vmMatch) {
    return 'https://player.vimeo.com/video/' + vmMatch[1] + '?autoplay=1&title=0&byline=0';
  }

  // Qualquer outra URL — usa direto
  return url;
}

function abrirVideo(url, titulo) {
  var modal   = document.getElementById('modalVideo');
  var iframe  = document.getElementById('modalVideoIframe');
  var tituloEl= document.getElementById('modalVideoTitulo');
  if (!modal || !iframe) return;

  iframe.src = videoParaEmbed(url);
  if (tituloEl) tituloEl.textContent = titulo || 'Vídeo de Demonstração';

  modal.classList.add('active');
  document.body.style.overflow = 'hidden';
}

function fecharVideo() {
  var modal  = document.getElementById('modalVideo');
  var iframe = document.getElementById('modalVideoIframe');
  if (!modal) return;

  // Parar o vídeo limpando o src antes de ocultar
  if (iframe) iframe.src = '';
  modal.classList.remove('active');
  document.body.style.overflow = '';
}

// ── Fechar com ESC (global) ─────────────────────────────────
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    fecharVideo();
    fecharDetalhes();
    fecharModal('modalCompra');
  }
});
detPrev.addEventListener('click', function() {
  if (detSlideshow) { detSlideshow.go(detSlideshow.current - 1); detSlideshow.resetTimer(); }
});
detNext.addEventListener('click', function() {
  if (detSlideshow) { detSlideshow.go(detSlideshow.current + 1); detSlideshow.resetTimer(); }
});

// ── Fechar ao clicar fora (no slideshow) ─────────────────────
detModal.addEventListener('click', function(e) {
  if (e.target === detSlidesWrap || e.target === detModal) fecharDetalhes();
});

// ── Fechar com botão X ───────────────────────────────────────
detClose.addEventListener('click', fecharDetalhes);

// ── Setas do slideshow fullscreen ────────────────────────────

// ── Swipe touch no modal fullscreen ──────────────────────────
(function() {
  var startX = 0;
  detSlidesWrap.addEventListener('touchstart', function(e) {
    startX = e.touches[0].clientX;
  }, { passive: true });
  detSlidesWrap.addEventListener('touchend', function(e) {
    var diff = startX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40 && detSlideshow) {
      detSlideshow.go(diff > 0 ? detSlideshow.current + 1 : detSlideshow.current - 1);
      detSlideshow.resetTimer();
    }
  });
})();

// ── Teclado: setas esquerda/direita quando modal aberto ──────
document.addEventListener('keydown', function(e) {
  if (!detModal.classList.contains('active') || !detSlideshow) return;
  if (e.key === 'ArrowLeft')  { detSlideshow.go(detSlideshow.current - 1); detSlideshow.resetTimer(); }
  if (e.key === 'ArrowRight') { detSlideshow.go(detSlideshow.current + 1); detSlideshow.resetTimer(); }
});
