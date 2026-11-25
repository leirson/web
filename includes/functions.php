<?php
// ============================================================
// CONEXÃO COM O BANCO DE DADOS (PDO)
// ============================================================
require_once __DIR__ . '/../config.php';

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Erro de conexão: " . $e->getMessage());
            } else {
                die("Erro interno do servidor. Tente novamente mais tarde.");
            }
        }
    }
    return $pdo;
}

// ============================================================
// FUNÇÕES AUXILIARES GLOBAIS
// ============================================================

function cfg(string $chave, string $padrao = ''): string {
    static $cache = [];
    if (!isset($cache[$chave])) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT valor FROM configuracoes WHERE chave = ? LIMIT 1");
            $stmt->execute([$chave]);
            $row = $stmt->fetch();
            $cache[$chave] = $row ? (string)$row['valor'] : $padrao;
        } catch (Exception $e) {
            return $padrao;
        }
    }
    return $cache[$chave];
}

function cfgAll(): array {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT chave, valor FROM configuracoes");
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['chave']] = $row['valor'];
        }
        return $result;
    } catch (Exception $e) {
        return [];
    }
}

function sanitize(string $str): string {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

function formatMoney(float $value): string {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function formatDoc(string $doc): string {
    $doc = preg_replace('/\D/', '', $doc);
    if (strlen($doc) === 11) {
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
    } elseif (strlen($doc) === 14) {
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
    }
    return $doc;
}

function redirect(string $url): void {
    header("Location: " . $url);
    exit;
}

function isAdmin(): bool {
    startSession();
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function requireAdmin(): void {
    if (!isAdmin()) {
        redirect(BASE_URL . '/admin/login.php');
    }
}

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function csrfToken(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    startSession();
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die("Token de segurança inválido.");
    }
}

function uploadImagem(array $file, string $subdir = 'produtos') {
    $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > $maxSize) return false;

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowedMimes)) return false;

    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $ext = isset($extMap[$mime]) ? $extMap[$mime] : 'jpg';

    $dir = UPLOAD_DIR . $subdir . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $filename = uniqid('img_', true) . '.' . $ext;
    $destino  = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destino)) return false;

    return $subdir . '/' . $filename;
}

function deletarArquivo(string $caminho): void {
    $full = UPLOAD_DIR . $caminho;
    if (file_exists($full)) {
        unlink($full);
    }
}

// ============================================================
// UPLOAD DE ARQUIVO ZIP (privado — só admin pode baixar)
// ============================================================
function uploadZip(array $file) {
    $maxSize = 200 * 1024 * 1024; // 200MB

    if ($file['error'] !== UPLOAD_ERR_OK)   return false;
    if ($file['size'] > $maxSize)            return false;

    // Validar MIME real
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'application/zip',
        'application/x-zip',
        'application/x-zip-compressed',
        'application/octet-stream',
    ];

    // Validar extensão também (dupla verificação)
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'zip') return false;
    if (!in_array($mime, $allowedMimes)) return false;

    $dir = UPLOAD_DIR . 'zips/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Criar .htaccess de proteção se não existir
    $htaccess = $dir . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Order allow,deny\nDeny from all\n");
    }

    // Nome único — não expõe o nome original
    $filename = 'zip_' . uniqid('', true) . '.zip';
    $destino  = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destino)) return false;

    return 'zips/' . $filename;
}

function formatBytes(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

// ============================================================
// UPLOAD DE APRESENTAÇÃO PPTX/PPSX (admin/apresentacao.php)
// ============================================================
function uploadPptx(array $file) {
    $maxSize = 100 * 1024 * 1024; // 100 MB

    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > $maxSize)         return false;

    // Validar extensão (.pptx ou .ppsx)
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extsPermitidas = ['pptx', 'ppsx'];
    if (!in_array($ext, $extsPermitidas)) return false;

    // Validar MIME real via finfo
    // PPTX/PPSX são arquivos ZIP (OOXML), portanto reportam MIME ZIP
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
        'application/vnd.openxmlformats-officedocument.presentationml.slideshow',    // .ppsx
        'application/zip',
        'application/x-zip',
        'application/x-zip-compressed',
        'application/octet-stream',
    ];
    if (!in_array($mime, $allowedMimes)) return false;

    $dir = UPLOAD_DIR . 'apresentacoes/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Criar .htaccess de proteção se não existir
    $htaccess = $dir . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Order allow,deny\nDeny from all\n");
    }

    // Nome único — não expõe o nome original; preserva extensão correta
    $filename = 'apresentacao_' . uniqid('', true) . '.' . $ext;
    $destino  = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destino)) return false;

    return 'apresentacoes/' . $filename;
}

// ============================================================
// UPLOAD DE PDF (manual do produto — público via admin/manual.php)
// ============================================================
function uploadPdf(array $file) {
    $maxSize = 50 * 1024 * 1024; // 50 MB

    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > $maxSize)         return false;

    // Validar extensão
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') return false;

    // Validar MIME real via finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // PDFs válidos reportam 'application/pdf' ou 'application/x-pdf'
    $allowedMimes = ['application/pdf', 'application/x-pdf'];
    if (!in_array($mime, $allowedMimes)) return false;

    $dir = UPLOAD_DIR . 'manuais/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Nome único — não expõe o nome original
    $filename = 'manual_' . uniqid('', true) . '.pdf';
    $destino  = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destino)) return false;

    return 'manuais/' . $filename;
}

// ============================================================
// GERADOR DE PAYLOAD PIX (EMV/QRIS)
// ============================================================
function gerarPixPayload(string $chave, string $nomeBeneficiario, string $cidade, float $valor, string $txid = ''): string {
    $nomeBeneficiario = substr(preg_replace('/[^A-Za-z0-9 ]/', '', $nomeBeneficiario), 0, 25);
    $cidade           = substr(preg_replace('/[^A-Za-z0-9 ]/', '', $cidade), 0, 15);
    $txid             = empty($txid) ? '***' : substr(preg_replace('/[^A-Za-z0-9]/', '', $txid), 0, 25);
    $valorStr         = number_format($valor, 2, '.', '');

    $merchantAccount = pixField('00', 'BR.GOV.BCB.PIX') . pixField('01', $chave);
    $merchantAccountStr = pixField('26', $merchantAccount);

    $payload =
        pixField('00', '01') .
        $merchantAccountStr .
        pixField('52', '0000') .
        pixField('53', '986') .
        pixField('54', $valorStr) .
        pixField('58', 'BR') .
        pixField('59', $nomeBeneficiario) .
        pixField('60', $cidade) .
        pixField('62', pixField('05', $txid));

    $payload .= pixField('63', pixCRC16($payload . '6304'));
    return $payload;
}

function pixField(string $id, string $value): string {
    return $id . str_pad(strlen($value), 2, '0', STR_PAD_LEFT) . $value;
}

function pixCRC16(string $str): string {
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($str); $i++) {
        $crc ^= ord($str[$i]) << 8;
        for ($j = 0; $j < 8; $j++) {
            if ($crc & 0x8000) {
                $crc = ($crc << 1) ^ 0x1021;
            } else {
                $crc <<= 1;
            }
            $crc &= 0xFFFF;
        }
    }
    return strtoupper(dechex($crc));
}
