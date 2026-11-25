<?php
/**
 * admin/download.php — Download seguro de arquivo ZIP (apenas administradores)
 *
 * Uso: download.php?produto_id=N
 *
 * Segurança:
 *  - Apenas admin autenticado pode acessar
 *  - Valida produto_id numérico
 *  - Busca o caminho no banco (não aceita path direto via GET)
 *  - Verifica que o arquivo existe dentro de UPLOAD_DIR/zips/
 *  - Usa readfile() para envio — o arquivo NUNCA é exposto via URL pública
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db = getDB();

// ── Validar parâmetro ──────────────────────────────────────────────────────
if (empty($_GET['produto_id']) || !is_numeric($_GET['produto_id'])) {
    http_response_code(400);
    die('Parâmetro inválido.');
}

$produtoId = (int)$_GET['produto_id'];

// ── Buscar caminho no banco ────────────────────────────────────────────────
$stmt = $db->prepare("SELECT nome, arquivo_zip FROM produtos WHERE id = ? LIMIT 1");
$stmt->execute([$produtoId]);
$produto = $stmt->fetch();

if (!$produto) {
    http_response_code(404);
    die('Produto não encontrado.');
}

if (empty($produto['arquivo_zip'])) {
    http_response_code(404);
    die('Este produto não possui arquivo ZIP cadastrado.');
}

// ── Montar caminho físico e validar ───────────────────────────────────────
$zipRelativo = $produto['arquivo_zip'];           // ex: zips/zip_abc123.zip
$zipFisico   = UPLOAD_DIR . $zipRelativo;         // caminho absoluto no servidor

// Garantir que o caminho resolve DENTRO de UPLOAD_DIR (evita path traversal)
$realUpload = realpath(UPLOAD_DIR);
$realZip    = realpath($zipFisico);

if ($realZip === false || strpos($realZip, $realUpload) !== 0) {
    http_response_code(403);
    die('Acesso negado.');
}

if (!file_exists($realZip) || !is_readable($realZip)) {
    http_response_code(404);
    die('Arquivo não encontrado no servidor. Pode ter sido deletado manualmente.');
}

// ── Construir nome de download amigável ───────────────────────────────────
$nomeBase    = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $produto['nome']);
$nomeBase    = trim($nomeBase, '_');
$nomeBase    = $nomeBase ?: 'produto_' . $produtoId;
$nomeDownload = $nomeBase . '.zip';

// ── Enviar arquivo ────────────────────────────────────────────────────────
$tamanho = filesize($realZip);

header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $nomeDownload . '"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . $tamanho);
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Expires: 0');

// Limpar qualquer buffer de saída antes de enviar o arquivo
if (ob_get_level()) {
    ob_end_clean();
}

readfile($realZip);
exit;
