<?php
// ============================================================
// DIAGNÓSTICO DE IMAGENS — Delete após usar!
// Acesse: https://seudominio.com/diag_imagens.php
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

echo '<style>
body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:2rem}
h2{color:#6366f1;border-bottom:1px solid #334155;padding-bottom:.5rem;margin:1.5rem 0 .75rem}
.ok{color:#10b981;font-weight:bold}
.err{color:#ef4444;font-weight:bold}
.warn{color:#f59e0b;font-weight:bold}
.box{background:#1e293b;border:1px solid #334155;border-radius:8px;padding:.75rem 1rem;margin:.4rem 0;font-size:.85rem}
img.thumb{width:120px;height:80px;object-fit:cover;border-radius:4px;border:2px solid #334155;margin-top:.5rem;display:block}
</style>';

echo '<h1 style="color:#fff">🖼️ Diagnóstico de Imagens</h1>';

$db = getDB();

// ── 1. Caminhos configurados ──────────────────────────────
echo '<h2>1. Caminhos configurados</h2>';
echo '<div class="box">UPLOAD_DIR (servidor): <span class="ok">' . UPLOAD_DIR . '</span></div>';
echo '<div class="box">UPLOAD_URL (web): <span class="ok">' . UPLOAD_URL . '</span></div>';
echo '<div class="box">BASE_URL: <span class="' . (strpos(BASE_URL,'localhost') !== false ? 'err' : 'ok') . '">' . BASE_URL . '</span>';
if (strpos(BASE_URL,'localhost') !== false) echo ' <span class="err">← TROCAR pelo domínio real!</span>';
echo '</div>';

// ── 2. Pasta uploads/ ────────────────────────────────────
echo '<h2>2. Pastas de upload</h2>';
$pastas = [
    UPLOAD_DIR,
    UPLOAD_DIR . 'produtos/',
    UPLOAD_DIR . 'logos/',
];
foreach ($pastas as $pasta) {
    $existe   = is_dir($pasta);
    $gravavel = $existe && is_writable($pasta);
    $perm     = $existe ? substr(sprintf('%o', fileperms($pasta)), -4) : '---';
    echo '<div class="box">';
    echo '<b>' . $pasta . '</b><br>';
    echo 'Existe: <span class="' . ($existe ? 'ok' : 'err') . '">' . ($existe ? '✔ Sim' : '✘ NÃO — crie a pasta') . '</span> | ';
    echo 'Gravável: <span class="' . ($gravavel ? 'ok' : 'err') . '">' . ($gravavel ? '✔ Sim' : '✘ NÃO') . '</span> | ';
    echo 'Permissão: <span class="' . ($perm === '0755' || $perm === '0775' ? 'ok' : 'warn') . '">' . $perm . '</span>';
    echo '</div>';
}

// ── 3. Imagens no banco ──────────────────────────────────
echo '<h2>3. Imagens cadastradas no banco</h2>';
$imgs = $db->query("SELECT pi.id, pi.produto_id, pi.caminho, p.nome as produto_nome
                    FROM produto_imagens pi
                    LEFT JOIN produtos p ON pi.produto_id = p.id
                    ORDER BY pi.produto_id, pi.ordem")->fetchAll();

if (empty($imgs)) {
    echo '<div class="box"><span class="warn">⚠ Nenhuma imagem cadastrada no banco ainda.</span></div>';
} else {
    echo '<div class="box">Total de imagens no banco: <span class="ok">' . count($imgs) . '</span></div>';
    foreach ($imgs as $img) {
        $caminhoFisico = UPLOAD_DIR . $img['caminho'];
        $urlWeb        = UPLOAD_URL . $img['caminho'];
        $existe        = file_exists($caminhoFisico);
        $perm          = $existe ? substr(sprintf('%o', fileperms($caminhoFisico)), -4) : '---';
        $tamanho       = $existe ? round(filesize($caminhoFisico) / 1024, 1) . ' KB' : '---';

        echo '<div class="box">';
        echo '<b>Produto: ' . htmlspecialchars($img['produto_nome']) . '</b> (ID imagem: ' . $img['id'] . ')<br>';
        echo 'Caminho no banco: <code>' . htmlspecialchars($img['caminho']) . '</code><br>';
        echo 'Caminho físico: <code>' . htmlspecialchars($caminhoFisico) . '</code><br>';
        echo 'Arquivo existe: <span class="' . ($existe ? 'ok' : 'err') . '">' . ($existe ? '✔ Sim (' . $tamanho . ')' : '✘ NÃO ENCONTRADO') . '</span><br>';
        if ($existe) {
            echo 'Permissão: <span class="' . ($perm >= '0644' ? 'ok' : 'warn') . '">' . $perm . '</span><br>';
        }
        echo 'URL gerada: <code>' . htmlspecialchars($urlWeb) . '</code><br>';
        if ($existe) {
            echo '<img class="thumb" src="' . htmlspecialchars($urlWeb) . '" alt="thumb" onerror="this.style.border=\'2px solid #ef4444\';this.insertAdjacentHTML(\'afterend\',\'<span class=err> ✘ URL inacessível pelo navegador</span>\')">';
        }
        echo '</div>';
    }
}

// ── 4. .htaccess bloqueando uploads? ────────────────────
echo '<h2>4. Verificar .htaccess na pasta uploads/</h2>';
$htaccessUpload = UPLOAD_DIR . '.htaccess';
if (file_exists($htaccessUpload)) {
    echo '<div class="box"><span class="warn">⚠ Existe um .htaccess dentro de uploads/ — conteúdo:</span><br><pre>' . htmlspecialchars(file_get_contents($htaccessUpload)) . '</pre></div>';
} else {
    echo '<div class="box"><span class="ok">✔ Nenhum .htaccess dentro de uploads/ (normal)</span></div>';
}

// ── 5. .htaccess raiz — regras que bloqueiam uploads ────
echo '<h2>5. Regras do .htaccess raiz que afetam uploads/</h2>';
$htaccessRaiz = __DIR__ . '/.htaccess';
if (file_exists($htaccessRaiz)) {
    $conteudo = file_get_contents($htaccessRaiz);
    // Procurar regras que bloqueiam uploads
    if (preg_match('/uploads.*\[F/i', $conteudo) || preg_match('/\[F.*uploads/i', $conteudo)) {
        echo '<div class="box"><span class="err">✘ .htaccess pode estar bloqueando acesso à pasta uploads/!</span></div>';
    } else {
        echo '<div class="box"><span class="ok">✔ Nenhuma regra de bloqueio para uploads/ encontrada</span></div>';
    }
} else {
    echo '<div class="box"><span class="warn">⚠ Sem .htaccess na raiz</span></div>';
}

// ── 6. Teste de URL direto ───────────────────────────────
echo '<h2>6. Teste de acesso direto às imagens</h2>';
if (!empty($imgs)) {
    $primeira = $imgs[0];
    $url = UPLOAD_URL . $primeira['caminho'];
    echo '<div class="box">';
    echo 'Clique no link abaixo — se abrir a imagem, a URL está correta:<br><br>';
    echo '<a href="' . htmlspecialchars($url) . '" target="_blank" style="color:#6366f1">' . htmlspecialchars($url) . '</a>';
    echo '</div>';
} else {
    echo '<div class="box"><span class="warn">Sem imagens para testar.</span></div>';
}

echo '<br><hr style="border-color:#334155"><p style="color:#ef4444;font-weight:bold">⚠ DELETE este arquivo (diag_imagens.php) após resolver!</p>';
