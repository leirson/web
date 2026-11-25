<?php
/**
 * admin/apresentacao.php
 * Serve o arquivo de apresentação (PPTX/PPSX) de um produto de forma segura.
 * Apenas administradores autenticados têm acesso.
 *
 * Uso: apresentacao.php?produto_id=N
 *
 * Segurança aplicada:
 *  - requireAdmin()        → redireciona para login se não autenticado
 *  - produto_id vem do DB  → nunca lemos o caminho direto da URL
 *  - realpath() + strncmp  → proteção contra path traversal
 *  - ob_end_clean()        → garante buffer limpo antes do readfile()
 *  - Content-Disposition: attachment → força download (PPTX não abre inline)
 */

require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

// ── 1. Validar parâmetro ──────────────────────────────────────────────────────
if (!isset($_GET['produto_id']) || !is_numeric($_GET['produto_id'])) {
    http_response_code(400);
    die("Parâmetro inválido.");
}

$produtoId = (int)$_GET['produto_id'];

// ── 2. Buscar caminho no banco ────────────────────────────────────────────────
$db   = getDB();
$stmt = $db->prepare(
    "SELECT p.nome, p.arquivo_apresentacao
     FROM produtos p
     WHERE p.id = ?
     LIMIT 1"
);
$stmt->execute([$produtoId]);
$produto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$produto) {
    http_response_code(404);
    die("Produto não encontrado.");
}

if (empty($produto['arquivo_apresentacao'])) {
    http_response_code(404);
    die("Este produto não possui apresentação cadastrada.");
}

// ── 3. Resolver caminho físico e proteger contra path traversal ──────────────
$uploadDir  = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR;
$targetPath = realpath($uploadDir . $produto['arquivo_apresentacao']);

if ($targetPath === false) {
    http_response_code(404);
    die("Arquivo não encontrado no servidor.");
}

// Garante que o arquivo está DENTRO do diretório de uploads
if (strncmp($targetPath, $uploadDir, strlen($uploadDir)) !== 0) {
    http_response_code(403);
    die("Acesso negado.");
}

if (!is_file($targetPath)) {
    http_response_code(404);
    die("Arquivo não encontrado.");
}

// ── 4. Determinar extensão e MIME correto para o arquivo ─────────────────────
$ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

$mimeMap = [
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
];
$mimeType = isset($mimeMap[$ext]) ? $mimeMap[$ext] : 'application/octet-stream';

// ── 5. Gerar nome amigável para download ─────────────────────────────────────
// Ex.: "Gestão de Estoque" + ".pptx" → "apresentacao_gestao_de_estoque.pptx"
$nomeBase  = preg_replace('/[^a-z0-9]+/', '_', strtolower($produto['nome']));
$nomeBase  = trim($nomeBase, '_');
$nomeAmigavel = 'apresentacao_' . $nomeBase . '.' . $ext;

// ── 6. Servir o arquivo ───────────────────────────────────────────────────────
while (ob_get_level()) {
    ob_end_clean();
}

$fileSize = filesize($targetPath);

header('Content-Type: ' . $mimeType);
// "attachment" → força download (PPTX não tem visualizador nativo no browser)
header('Content-Disposition: attachment; filename="' . $nomeAmigavel . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

readfile($targetPath);
exit;
