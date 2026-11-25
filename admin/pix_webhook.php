<?php
// ============================================================
// WEBHOOK MERCADO PAGO — PIX AUTOMÁTICO
// URL: /admin/pix_webhook.php
// Configurar no painel MP: Tópico = payment, Versão = v1
// ============================================================
// IMPORTANTE: este arquivo NÃO exige sessão de admin —
// ele recebe chamadas do servidor do Mercado Pago.
// A segurança é feita por verificação de assinatura HMAC-SHA256.
// ============================================================

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mercadopago.php';

// Registrar qualquer saída indevida para não corromper a resposta
ob_start();

// Sempre responder JSON
header('Content-Type: application/json');

// ----------------------------------------------------------------
// 1. Ler corpo da requisição e headers necessários
// ----------------------------------------------------------------
$rawBody    = file_get_contents('php://input');
$dados      = json_decode($rawBody, true);

$xSignature = $_SERVER['HTTP_X_SIGNATURE']  ?? '';
$xRequestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';

// Logar recebimento (debug — remova em produção se quiser)
error_log('[pix_webhook] Recebido: ' . $rawBody);

// ----------------------------------------------------------------
// 2. Verificar se é notificação de pagamento
// ----------------------------------------------------------------
$tipo    = $dados['type']    ?? ($dados['topic'] ?? '');
$dataId  = (string)($dados['data']['id'] ?? ($dados['id'] ?? ''));

// O MP pode enviar "payment" ou "topic=payment"
if ($tipo !== 'payment' && $tipo !== 'merchant_order') {
    ob_end_clean();
    http_response_code(200); // sempre 200 para o MP não reenviar
    echo json_encode(['ok' => true, 'msg' => 'Tipo ignorado: ' . $tipo]);
    exit;
}

if (empty($dataId)) {
    ob_end_clean();
    http_response_code(200);
    echo json_encode(['ok' => true, 'msg' => 'Sem data.id']);
    exit;
}

// ----------------------------------------------------------------
// 3. Verificar assinatura HMAC (se secret configurado)
// ----------------------------------------------------------------
$webhookSecret = cfg('mp_webhook_secret', '');

if (!empty($webhookSecret) && !empty($xSignature)) {
    $mp = getMercadoPagoApi();
    if ($mp && !$mp->verificarAssinatura($webhookSecret, $xSignature, $xRequestId, $dataId)) {
        ob_end_clean();
        http_response_code(401);
        echo json_encode(['erro' => 'Assinatura inválida']);
        exit;
    }
}

// ----------------------------------------------------------------
// 4. Consultar status do pagamento na API do MP
// ----------------------------------------------------------------
$mp = getMercadoPagoApi();

if (!$mp) {
    ob_end_clean();
    http_response_code(200);
    echo json_encode(['ok' => true, 'msg' => 'MP não configurado']);
    exit;
}

try {
    $pagamento = $mp->consultarPagamento((int)$dataId);
} catch (Exception $e) {
    error_log('[pix_webhook] Erro ao consultar pagamento ' . $dataId . ': ' . $e->getMessage());
    ob_end_clean();
    http_response_code(200); // 200 para não gerar reenvio infinito
    echo json_encode(['ok' => true, 'msg' => 'Erro ao consultar — retentativa não necessária']);
    exit;
}

$statusMp        = $pagamento['status'];         // approved, pending, rejected, etc.
$externalRef     = $pagamento['external_reference']; // nosso txid
$mpPaymentIdReal = (int)$pagamento['id'];

error_log('[pix_webhook] payment_id=' . $mpPaymentIdReal . ' status=' . $statusMp . ' ref=' . $externalRef);

// ----------------------------------------------------------------
// 5. Atualizar pedido no banco de dados
// ----------------------------------------------------------------
if (empty($externalRef)) {
    ob_end_clean();
    http_response_code(200);
    echo json_encode(['ok' => true, 'msg' => 'Sem external_reference']);
    exit;
}

try {
    $db = getDB();

    // Buscar pedido pelo txid (external_reference)
    $stmtBusca = $db->prepare("SELECT id, status FROM pedidos WHERE txid = ? LIMIT 1");
    $stmtBusca->execute([$externalRef]);
    $pedido = $stmtBusca->fetch();

    if (!$pedido) {
        ob_end_clean();
        http_response_code(200);
        echo json_encode(['ok' => true, 'msg' => 'Pedido não encontrado: ' . $externalRef]);
        exit;
    }

    $pedidoId    = (int)$pedido['id'];
    $statusAtual = $pedido['status'];

    // Mapear status do MP para status interno (ENUM: aguardando, pago, cancelado)
    $novoStatus = $statusAtual; // mantém por padrão

    if ($statusMp === 'approved') {
        $novoStatus = 'pago';
    } elseif ($statusMp === 'rejected' || $statusMp === 'cancelled'
           || $statusMp === 'refunded' || $statusMp === 'charged_back') {
        $novoStatus = 'cancelado';
    }
    // pending / in_process → mantém 'aguardando'

    // Só atualizar se houve mudança de status relevante
    if ($novoStatus !== $statusAtual) {
        $stmtUpd = $db->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
        $stmtUpd->execute([$novoStatus, $pedidoId]);
        error_log('[pix_webhook] Pedido #' . $pedidoId . ' atualizado: ' . $statusAtual . ' → ' . $novoStatus);
    }

    // Salvar mp_payment_id e mp_status (migration_008 — try/catch para compatibilidade)
    try {
        $stmtMp = $db->prepare(
            "UPDATE pedidos SET mp_payment_id = ?, mp_status = ?, webhook_confirmado = 1 WHERE id = ?"
        );
        $stmtMp->execute([$mpPaymentIdReal, $statusMp, $pedidoId]);
    } catch (PDOException $e) {
        // Migration 008 não foi aplicada — ignora, não bloqueia
        error_log('[pix_webhook] Migration 008 pendente: ' . $e->getMessage());
    }

    ob_end_clean();
    http_response_code(200);
    echo json_encode([
        'ok'         => true,
        'pedido_id'  => $pedidoId,
        'status_mp'  => $statusMp,
        'status_int' => $novoStatus,
    ]);

} catch (PDOException $e) {
    error_log('[pix_webhook] DB Error: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(200); // 200 mesmo em erro — evitar retentativas infinitas do MP
    echo json_encode(['ok' => true, 'msg' => 'Erro interno registrado']);
}
