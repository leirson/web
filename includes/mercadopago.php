<?php
// ============================================================
// INTEGRAÇÃO MERCADO PAGO — PIX AUTOMÁTICO
// Documentação: https://www.mercadopago.com.br/developers
// PHP 8.3 compatível — sem union types, sem match()
// ============================================================

class MercadoPagoApi
{
    private $accessToken;
    private $baseUrl = 'https://api.mercadopago.com';
    private $sandbox;

    /**
     * @param string $accessToken  Token de acesso (TEST-xxx para sandbox, APP_USR-xxx para produção)
     * @param bool   $sandbox      true = ambiente de testes
     */
    public function __construct(string $accessToken, bool $sandbox = false)
    {
        $this->accessToken = $accessToken;
        $this->sandbox     = $sandbox;
    }

    // ----------------------------------------------------------------
    // CRIAR COBRANÇA PIX
    // Retorna array com keys: qr_code, qr_code_base64, payment_id, status
    // Ou lança Exception em caso de erro
    // ----------------------------------------------------------------
    public function criarCobrancaPix(
        float  $valor,
        string $descricao,
        string $clienteNome,
        string $clienteDoc,
        string $clienteEmail,
        string $externalReference,
        int    $expiracaoMinutos = 30
    ): array {
        $docLimpo = preg_replace('/\D/', '', $clienteDoc);
        $tipoDoc  = (strlen($docLimpo) === 11) ? 'CPF' : 'CNPJ';

        // Garantir valor mínimo de R$ 0,01 (MP rejeita 0)
        $valorFinal = max(0.01, round($valor, 2));

        // E-mail: se vazio usa fallback seguro
        $emailFinal = !empty($clienteEmail) ? $clienteEmail : 'comprador@email.com';

        // NOTA: date_of_expiration removido intencionalmente.
        // A API do Mercado Pago rejeita o campo em alguns ambientes/contas
        // independente do formato enviado. O PIX expira automaticamente
        // pelo padrão da conta (geralmente 24h).
        $body = [
            'transaction_amount' => $valorFinal,
            'description'        => substr($descricao, 0, 200),
            'payment_method_id'  => 'pix',
            'external_reference' => $externalReference,
            'payer' => [
                'first_name'     => $this->primeiroNome($clienteNome),
                'last_name'      => $this->sobrenome($clienteNome),
                'email'          => $emailFinal,
                'identification' => [
                    'type'   => $tipoDoc,
                    'number' => $docLimpo,
                ],
            ],
        ];

        $resposta = $this->post('/v1/payments', $body);

        // Checar erro retornado pela API
        if (!isset($resposta['id'])) {
            $msg   = $resposta['message']                       ?? '';
            $cause = $resposta['cause'][0]['description']       ?? '';
            $error = $resposta['error']                         ?? '';
            $detalhe = $msg ?: ($cause ?: ($error ?: 'Erro desconhecido'));
            throw new Exception('MP Error [' . ($resposta['status'] ?? '?') . ']: ' . $detalhe);
        }

        // Status inesperado (não pending/approved)
        $status = $resposta['status'] ?? '';
        if (!in_array($status, ['pending', 'approved', 'in_process'])) {
            $statusDetail = $resposta['status_detail'] ?? '';
            throw new Exception('MP: pagamento com status inesperado: ' . $status . ' / ' . $statusDetail);
        }

        // Extrair dados do QR Code
        $txData   = $resposta['point_of_interaction']['transaction_data'] ?? [];
        $qrCode   = $txData['qr_code']        ?? '';
        $qrBase64 = $txData['qr_code_base64'] ?? '';

        // Se não veio base64 mas veio o texto, logar para diagnóstico
        if (empty($qrCode) && empty($qrBase64)) {
            error_log('[MercadoPagoApi] QR Code vazio na resposta. payment_id=' . $resposta['id']
                . ' status=' . $status . ' point_of_interaction=' . json_encode($resposta['point_of_interaction'] ?? []));
            throw new Exception('MP: QR Code não retornado pela API. Status: ' . $status);
        }

        return [
            'payment_id'     => (int) $resposta['id'],
            'status'         => $status,
            'qr_code'        => $qrCode,
            'qr_code_base64' => $qrBase64,
        ];
    }

    // ----------------------------------------------------------------
    // CONSULTAR STATUS DO PAGAMENTO
    // ----------------------------------------------------------------
    public function consultarPagamento(int $paymentId): array
    {
        $resposta = $this->get('/v1/payments/' . $paymentId);

        return [
            'id'                 => (int) ($resposta['id'] ?? 0),
            'status'             => $resposta['status']        ?? 'unknown',
            'status_detail'      => $resposta['status_detail'] ?? '',
            'external_reference' => $resposta['external_reference'] ?? '',
            'valor'              => (float) ($resposta['transaction_amount'] ?? 0),
        ];
    }

    // ----------------------------------------------------------------
    // VERIFICAR ASSINATURA DO WEBHOOK (X-Signature)
    // Mercado Pago envia: x-signature: ts=...,v1=...
    // ----------------------------------------------------------------
    public function verificarAssinatura(string $secret, string $xSignature, string $xRequestId, string $dataId): bool
    {
        $ts = '';
        $v1 = '';
        foreach (explode(',', $xSignature) as $parte) {
            $parte = trim($parte);
            if (strpos($parte, 'ts=') === 0) {
                $ts = substr($parte, 3);
            } elseif (strpos($parte, 'v1=') === 0) {
                $v1 = substr($parte, 3);
            }
        }

        if (!$ts || !$v1) return false;

        $mensagem = 'id:' . $dataId . ';request-id:' . $xRequestId . ';ts:' . $ts . ';';
        $esperado = hash_hmac('sha256', $mensagem, $secret);

        return hash_equals($esperado, $v1);
    }

    // ----------------------------------------------------------------
    // HTTP HELPERS
    // ----------------------------------------------------------------
    private function post(string $endpoint, array $body): array
    {
        return $this->request('POST', $endpoint, $body);
    }

    private function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint, []);
    }

    private function request(string $method, string $endpoint, array $body): array
    {
        $url = $this->baseUrl . $endpoint;

        // Idempotency Key: hash do body + endpoint (sem time() para ser estável por pedido)
        $idempKey = substr(md5($endpoint . json_encode($body) . $this->accessToken), 0, 36);

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json',
            'X-Idempotency-Key: ' . $idempKey,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AppVendas/1.0 PHP/' . phpversion());

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new Exception('cURL Error: ' . $curlErr);
        }

        if ($raw === false || $raw === '') {
            throw new Exception('Resposta vazia da API (HTTP ' . $httpCode . ')');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new Exception('JSON inválido da API (HTTP ' . $httpCode . '): ' . substr($raw, 0, 200));
        }

        return $decoded;
    }

    // dataISO8601 removida — date_of_expiration não é mais enviado ao MP

    private function primeiroNome(string $nome): string
    {
        $partes = explode(' ', trim($nome));
        $primeiro = trim($partes[0] ?? 'Comprador');
        return $primeiro ?: 'Comprador';
    }

    private function sobrenome(string $nome): string
    {
        $partes = explode(' ', trim($nome));
        if (count($partes) > 1) {
            array_shift($partes);
            $sobre = trim(implode(' ', $partes));
            return $sobre ?: 'Cliente';
        }
        return 'Cliente';
    }
}

// ----------------------------------------------------------------
// FACTORY — retorna instância configurada ou null se não configurado
// ----------------------------------------------------------------
function getMercadoPagoApi()
{
    $token   = cfg('mp_access_token', '');
    $sandbox = cfg('mp_ambiente', 'sandbox') === 'sandbox';

    if (empty(trim($token))) {
        return null;
    }

    return new MercadoPagoApi($token, $sandbox);
}
