<?php
/**
 * admin/manual.php
 * Serve o manual PDF de um produto de forma segura (apenas admins autenticados).
 *
 * Uso: manual.php?produto_id=N
 *
 * Segurança aplicada:
 *  - requireAdmin()        → redireciona para login se não autenticado
 *  - produto_id vem do DB  → nunca lemos o caminho direto da URL
 *  - realpath() + strncmp  → proteção contra path traversal
 *  - ob_end_clean()        → garante buffer limpo antes do readfile()
 *  - Content-Disposition: inline → PDF abre no browser (não força download)
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
    "SELECT p.nome, p.arquivo_manual
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

if (empty($produto['arquivo_manual'])) {
    http_response_code(404);
    die("Este produto não possui manual PDF cadastrado.");
}

// ── 3. Resolver caminho físico e proteger contra path traversal ──────────────
$uploadDir  = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR;
$targetPath = realpath($uploadDir . $produto['arquivo_manual']);

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

// ── 4. Gerar nome amigável para exibição no browser ───────────────────────────
// Ex.: "Gestão de Estoque" → "manual_gestao_de_estoque.pdf"
$nomeAmigavel = 'manual_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($produto['nome']));
$nomeAmigavel = trim($nomeAmigavel, '_') . '.pdf';

// ── 5. Servir o arquivo ───────────────────────────────────────────────────────
// Limpar qualquer output já gerado (evita corromper o binário)
while (ob_get_level()) {
    ob_end_clean();
}

$fileSize = filesize($targetPath);

header('Content-Type: application/pdf');
// "inline" → abre no visualizador do browser; troque por "attachment" para forçar download
header('Content-Disposition: inline; filename="' . $nomeAmigavel . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

readfile($targetPath);
exit;
