<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mercadopago.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

$dados = json_decode(file_get_contents('php://input'), true);

$produtoId    = (int)($dados['produto_id'] ?? 0);
$clienteNome  = trim($dados['cliente_nome']  ?? '');
$clienteDoc   = trim($dados['cliente_doc']   ?? '');
$clienteEstab = trim($dados['cliente_estab'] ?? '');
$clienteWa    = trim($dados['cliente_wa']    ?? '');

if (!$produtoId || !$clienteNome || !$clienteDoc || !$clienteEstab || !$clienteWa) {
    echo json_encode(['erro' => 'Preencha todos os campos obrigatórios.']);
    exit;
}

// Limpar e validar documento
$docLimpo = preg_replace('/\D/', '', $clienteDoc);
if (strlen($docLimpo) !== 11 && strlen($docLimpo) !== 14) {
    echo json_encode(['erro' => 'CPF ou CNPJ inválido.']);
    exit;
}

try {
    $db = getDB();

    // ----------------------------------------------------------------
    // 1. Buscar produto
    // ----------------------------------------------------------------
    $stmt = $db->prepare("SELECT * FROM produtos WHERE id = ? AND ativo = 1 LIMIT 1");
    $stmt->execute([$produtoId]);
    $produto = $stmt->fetch();

    if (!$produto) {
        echo json_encode(['erro' => 'Produto não encontrado.']);
        exit;
    }

    $valorImpl = (float)$produto['valor_implantacao'];

    // ----------------------------------------------------------------
    // 2. Gerar txid único (referência interna)
    // ----------------------------------------------------------------
    $txid = 'AV' . strtoupper(substr(md5(uniqid('', true)), 0, 20));

    // ----------------------------------------------------------------
    // 3. Calcular vencimento anual
    // ----------------------------------------------------------------
    $vencimento = date('Y-m-d', strtotime('+366 days'));

    // ----------------------------------------------------------------
    // 4. Salvar pedido com status 'aguardando'
    // ----------------------------------------------------------------
    $stmt2 = $db->prepare("INSERT INTO pedidos
        (produto_id, produto_nome, valor_implantacao, valor_anual, vencimento_anual,
         cliente_nome, cliente_documento, cliente_estabelecimento, cliente_whatsapp, status, txid)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $stmt2->execute([
        $produto['id'],
        $produto['nome'],
        $valorImpl,
        (float)$produto['valor_anual'],
        $vencimento,
        $clienteNome,
        $clienteDoc,
        $clienteEstab,
        $clienteWa,
        'aguardando',
        $txid,
    ]);

    $pedidoId = (int)$db->lastInsertId();

    // ----------------------------------------------------------------
    // 5. Tentar PIX automático via Mercado Pago
    // ----------------------------------------------------------------
    $mp = getMercadoPagoApi(); // null se não configurado

    $pixPayload    = '';
    $pixChave      = cfg('pix_chave', '');
    $qrCodeBase64  = '';
    $qrCodeText    = '';  // "copia e cola"
    $mpPaymentId   = null;
    $modoAuto      = false;
    $mpErro        = '';

    if ($mp !== null && $valorImpl > 0) {
        try {
            // E-mail padrão do pagador (obrigatório pelo MP)
            $emailPagador = cfg('mp_email_pagador', 'pagador@appvendas.com');

            $cobranca = $mp->criarCobrancaPix(
                $valorImpl,
                'Implantação — ' . $produto['nome'],
                $clienteNome,
                $docLimpo,
                $emailPagador,
                $txid,          // external_reference = nosso txid
                30              // expira em 30 minutos
            );

            $mpPaymentId  = $cobranca['payment_id'];
            $qrCodeBase64 = $cobranca['qr_code_base64'];
            $qrCodeText   = $cobranca['qr_code'];   // string "copia e cola"
            $modoAuto     = true;

            // Salvar mp_payment_id no pedido (migration_008)
            try {
                $stmtMp = $db->prepare("UPDATE pedidos SET mp_payment_id = ?, mp_status = ? WHERE id = ?");
                $stmtMp->execute([$mpPaymentId, 'pending', $pedidoId]);
            } catch (PDOException $e) {
                // Migration não rodou ainda — ignorar, não bloqueia o checkout
            }

        } catch (Exception $e) {
            // Falha na API do MP — cai no fallback estático
            $modoAuto    = false;
            $mpPaymentId = null;
            $mpErro      = $e->getMessage();
            error_log('[checkout.php] Mercado Pago falhou: ' . $mpErro);
        }
    }

    // ----------------------------------------------------------------
    // 6. Fallback: payload PIX estático (EMV/BR Code)
    // ----------------------------------------------------------------
    if (!$modoAuto) {
        $pixNome   = cfg('pix_nome_beneficiario', 'AppVendas');
        $pixCidade = cfg('pix_cidade', 'São Paulo');

        if ($pixChave && $valorImpl > 0) {
            $pixPayload = gerarPixPayload($pixChave, $pixNome, $pixCidade, $valorImpl, $txid);
        }
    }

    // ----------------------------------------------------------------
    // 7. Resposta final
    // ----------------------------------------------------------------
    echo json_encode([
        'sucesso'             => true,
        'pedido_id'           => $pedidoId,
        'txid'                => $txid,
        'produto_nome'        => $produto['nome'],
        'valor_implantacao'   => $valorImpl,
        'valor_anual'         => (float)$produto['valor_anual'],
        'vencimento_anual'    => date('d/m/Y', strtotime($vencimento)),

        // Modo automático (Mercado Pago)
        'pix_automatico'      => $modoAuto,
        'mp_payment_id'       => $mpPaymentId,
        'mp_erro'             => $mpErro,        // vazio se OK, mensagem de erro se falhou
        'qr_code_base64'      => $qrCodeBase64,
        'qr_code_text'        => $qrCodeText,    // copia e cola

        // Modo manual (fallback)
        'pix_payload'         => $pixPayload,
        'pix_chave'           => $pixChave,
    ]);

} catch (Exception $e) {
    error_log('[checkout.php] Erro: ' . $e->getMessage());
    echo json_encode(['erro' => 'Erro interno. Tente novamente.']);
}
