<?php
// ============================================================
// DIAGNÓSTICO — AppVendas
// ATENÇÃO: DELETE este arquivo após resolver o problema!
// Acesse: https://seudominio.com.br/diagnostico.php
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo '<style>
body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 2rem; }
h2   { color: #6366f1; border-bottom: 1px solid #334155; padding-bottom: .5rem; margin: 1.5rem 0 .75rem; }
.ok  { color: #10b981; font-weight: bold; }
.err { color: #ef4444; font-weight: bold; }
.warn{ color: #f59e0b; font-weight: bold; }
.box { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 1rem; margin: .5rem 0; }
pre  { white-space: pre-wrap; word-break: break-all; font-size: .85rem; }
</style>';

echo '<h1 style="color:#fff">🔍 Diagnóstico AppVendas</h1>';
echo '<p style="color:#94a3b8">Delete este arquivo após corrigir os problemas!</p>';

// ── 1. Versão PHP ─────────────────────────────────────────
echo '<h2>1. Versão PHP</h2>';
$phpVersion = PHP_VERSION;
$phpOk = version_compare($phpVersion, '7.4', '>=');
echo '<div class="box">';
echo 'Versão: <span class="' . ($phpOk ? 'ok' : 'err') . '">' . $phpVersion . '</span> ';
echo $phpOk ? '<span class="ok">✔ OK (7.4+)</span>' : '<span class="err">✘ Requer PHP 7.4 ou superior</span>';
echo '</div>';

// ── 2. Extensões necessárias ──────────────────────────────
echo '<h2>2. Extensões PHP</h2>';
$exts = ['pdo', 'pdo_mysql', 'fileinfo', 'mbstring', 'json', 'session', 'openssl'];
foreach ($exts as $ext) {
    $loaded = extension_loaded($ext);
    echo '<div class="box">' . $ext . ': <span class="' . ($loaded ? 'ok' : 'err') . '">' . ($loaded ? '✔ Carregada' : '✘ NÃO encontrada') . '</span></div>';
}

// ── 3. Config.php ─────────────────────────────────────────
echo '<h2>3. Arquivo config.php</h2>';
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    echo '<div class="box"><span class="err">✘ config.php não encontrado!</span></div>';
} else {
    echo '<div class="box"><span class="ok">✔ config.php encontrado</span></div>';

    require_once $configPath;

    $checks = [
        'DB_HOST'    => defined('DB_HOST')    ? DB_HOST    : null,
        'DB_NAME'    => defined('DB_NAME')    ? DB_NAME    : null,
        'DB_USER'    => defined('DB_USER')    ? DB_USER    : null,
        'DB_PASS'    => defined('DB_PASS')    ? '***'      : null,
        'BASE_URL'   => defined('BASE_URL')   ? BASE_URL   : null,
        'UPLOAD_DIR' => defined('UPLOAD_DIR') ? UPLOAD_DIR : null,
    ];

    foreach ($checks as $k => $v) {
        $ok = !empty($v) && $v !== null;
        echo '<div class="box">' . $k . ': <span class="' . ($ok ? 'ok' : 'err') . '">';
        echo $ok ? htmlspecialchars($v) . ' ✔' : '✘ NÃO definido ou vazio';
        echo '</span></div>';
    }

    // Verificar BASE_URL padrão não alterada
    if (defined('BASE_URL') && strpos(BASE_URL, 'localhost') !== false) {
        echo '<div class="box"><span class="warn">⚠ BASE_URL ainda aponta para localhost! Altere para seu domínio real.</span></div>';
    }

    // Verificar usuário padrão não alterado
    if (defined('DB_USER') && DB_USER === 'root') {
        echo '<div class="box"><span class="warn">⚠ DB_USER ainda é "root"! No Hostinger o usuário é gerado pelo painel.</span></div>';
    }
}

// ── 4. Conexão com banco ──────────────────────────────────
echo '<h2>4. Conexão com MySQL</h2>';
if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo '<div class="box"><span class="ok">✔ Conexão com banco estabelecida com sucesso!</span></div>';

        // Verificar tabelas
        $tabelas = ['admins', 'configuracoes', 'produtos', 'produto_imagens', 'pedidos'];
        foreach ($tabelas as $t) {
            try {
                $pdo->query("SELECT 1 FROM $t LIMIT 1");
                echo '<div class="box">Tabela <b>' . $t . '</b>: <span class="ok">✔ Existe</span></div>';
            } catch (Exception $e) {
                echo '<div class="box">Tabela <b>' . $t . '</b>: <span class="err">✘ NÃO encontrada — execute o schema.sql!</span></div>';
            }
        }

        // Verificar admin padrão
        try {
            $row = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
            echo '<div class="box">Admins cadastrados: <span class="ok">' . $row . '</span></div>';
        } catch (Exception $e) {}

        // Verificar configurações
        try {
            $row = $pdo->query("SELECT COUNT(*) FROM configuracoes")->fetchColumn();
            echo '<div class="box">Configurações cadastradas: <span class="' . ($row > 0 ? 'ok' : 'err') . '">' . $row . ($row == 0 ? ' — execute o schema.sql!' : '') . '</span></div>';
        } catch (Exception $e) {}

    } catch (PDOException $e) {
        echo '<div class="box"><span class="err">✘ Erro de conexão: ' . htmlspecialchars($e->getMessage()) . '</span></div>';
        echo '<div class="box"><span class="warn">Verifique: host, nome do banco, usuário e senha no config.php</span></div>';
    }
} else {
    echo '<div class="box"><span class="err">✘ config.php não carregado corretamente.</span></div>';
}

// ── 5. Pasta uploads ──────────────────────────────────────
echo '<h2>5. Pasta uploads/</h2>';
$uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    echo '<div class="box"><span class="err">✘ Pasta uploads/ não encontrada. Crie a pasta manualmente.</span></div>';
} else {
    echo '<div class="box"><span class="ok">✔ Pasta uploads/ existe</span></div>';
    $writable = is_writable($uploadDir);
    echo '<div class="box">Permissão de escrita: <span class="' . ($writable ? 'ok' : 'err') . '">' . ($writable ? '✔ Gravável' : '✘ SEM permissão de escrita — defina permissão 755') . '</span></div>';
}

// ── 6. Arquivos críticos ──────────────────────────────────
echo '<h2>6. Arquivos críticos</h2>';
$files = [
    'index.php',
    'checkout.php',
    'config.php',
    'includes/functions.php',
    'admin/login.php',
    'admin/dashboard.php',
    'admin/produtos.php',
    'admin/produto_form.php',
    'admin/pedidos.php',
    'admin/configuracoes.php',
    'admin/partials/sidebar.php',
    'admin/partials/topbar.php',
    'assets/css/site.css',
    'assets/css/admin.css',
    'assets/js/site.js',
    'assets/js/admin.js',
];
foreach ($files as $f) {
    $exists = file_exists(__DIR__ . '/' . $f);
    echo '<div class="box">' . $f . ': <span class="' . ($exists ? 'ok' : 'err') . '">' . ($exists ? '✔ OK' : '✘ NÃO encontrado') . '</span></div>';
}

// ── 7. PHP.ini relevante ──────────────────────────────────
echo '<h2>7. Configurações PHP relevantes</h2>';
$configs = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size'       => ini_get('post_max_size'),
    'max_execution_time'  => ini_get('max_execution_time'),
    'memory_limit'        => ini_get('memory_limit'),
    'session.save_path'   => ini_get('session.save_path'),
    'display_errors'      => ini_get('display_errors'),
];
foreach ($configs as $k => $v) {
    echo '<div class="box">' . $k . ': <span style="color:#a5b4fc">' . htmlspecialchars($v) . '</span></div>';
}

echo '<br><hr style="border-color:#334155"><p style="color:#ef4444;font-weight:bold">⚠ DELETE este arquivo (diagnostico.php) após resolver os problemas!</p>';
