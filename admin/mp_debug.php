<?php
// ============================================================
// DEBUG MERCADO PAGO v3 — AUTOCONTIDO
// Acesse: /admin/mp_debug.php
// APAGUE após resolver o problema!
// ============================================================
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

header('Content-Type: text/html; charset=utf-8');

$token  = cfg('mp_access_token', '');
$amb    = cfg('mp_ambiente', 'sandbox');
$email  = cfg('mp_email_pagador', '');

// ── MONTAR BODY ──────────────────────────────────────────────
// date_of_expiration removido — rejeitado pela API do MP neste ambiente
$body = [
    'transaction_amount' => 0.01,
    'description'        => 'Teste debug AppVendas',
    'payment_method_id'  => 'pix',
    'external_reference' => 'DBG' . time(),
    'payer' => [
        'first_name'     => 'Teste',
        'last_name'      => 'Debug',
        'email'          => ($email ?: 'teste@appvendas.com'),
        'identification' => [
            'type'   => 'CPF',
            'number' => '12345678909',
        ],
    ],
];

$bodyJson = json_encode($body);

// ── CHAMAR API ───────────────────────────────────────────────
$raw = '';
$httpCode = 0;
$curlErr  = '';

if (!empty($token) && function_exists('curl_init')) {
    $ch = curl_init('https://api.mercadopago.com/v1/payments');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyJson);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'X-Idempotency-Key: dbg-' . md5($bodyJson),
    ]);
    $raw      = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
}

$decoded   = $raw ? json_decode($raw, true) : [];
$paymentId = $decoded['id']     ?? null;
$status    = $decoded['status'] ?? null;
$qrCode    = $decoded['point_of_interaction']['transaction_data']['qr_code']           ?? null;
$qrBase64  = $decoded['point_of_interaction']['transaction_data']['qr_code_base64']    ?? null;
$msgErr    = $decoded['message'] ?? null;
$cause     = $decoded['cause'][0]['data'] ?? null; // campo "data" tem o valor recebido pelo MP

?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Debug MP v3</title>
<style>
  body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:2rem;font-size:.88rem}
  h2{color:#818cf8}h3{color:#94a3b8;margin:1.5rem 0 .5rem}
  table{border-collapse:collapse;width:100%;margin-bottom:1.5rem;background:#1e293b;border-radius:8px;overflow:hidden}
  th{background:#334155;padding:7px 12px;text-align:left;color:#cbd5e1;font-size:.8rem}
  td{padding:6px 12px;vertical-align:top}
  tr:nth-child(even){background:#263248}
  pre{background:#1e293b;padding:1rem;border-radius:8px;color:#a3e635;white-space:pre-wrap;word-break:break-all;font-size:.8rem}
  .ok{color:#10b981;font-weight:600}.err{color:#ef4444;font-weight:600}.warn{color:#f59e0b;font-weight:600}
  .lbl{color:#94a3b8}
  .btn{display:inline-block;background:#6366f1;color:#fff;padding:.4rem 1rem;border-radius:6px;text-decoration:none;margin-top:1rem;font-size:.85rem}
  .hi{background:#164e63;color:#67e8f9;border-radius:4px;padding:1px 6px}
</style>
</head>
<body>
<h2>🔍 Debug Mercado Pago v3</h2>

<h3>1. Config</h3>
<table><tr><th>Campo</th><th>Valor</th></tr>
<tr><td class="lbl">mp_access_token</td><td><?= $token ? ('<span class="ok">✅ '.htmlspecialchars(substr($token,0,14)).'...'.htmlspecialchars(substr($token,-6)).'</span>') : '<span class="err">❌ VAZIO — configure em Personalização</span>' ?></td></tr>
<tr><td class="lbl">mp_ambiente</td><td><?= htmlspecialchars($amb) ?></td></tr>
<tr><td class="lbl">mp_email_pagador</td><td><?= $email ? htmlspecialchars($email) : '<span class="warn">⚠️ Vazio — usando teste@appvendas.com</span>' ?></td></tr>
<tr><td class="lbl">cURL disponível</td><td><?= function_exists('curl_init') ? '<span class="ok">✅ Sim</span>' : '<span class="err">❌ Não</span>' ?></td></tr>
<tr><td class="lbl">PHP version</td><td><?= phpversion() ?></td></tr>
<tr><td class="lbl">time() UTC agora</td><td><?= gmdate('Y-m-d H:i:s', time()) ?> UTC</td></tr>
</table>

<h3>2. JSON enviado ao MP</h3>
<pre><?= htmlspecialchars(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

<?php if (!empty($token) && function_exists('curl_init')): ?>
<h3>3. Resultado da chamada</h3>
<table><tr><th>Campo</th><th>Valor</th></tr>
<tr><td class="lbl">HTTP Code</td>
    <td><span class="<?= $httpCode===201?'ok':'err' ?>"><?= $httpCode ?> <?= $httpCode===201?'✅ Criado':($httpCode===401?'❌ Token inválido':($httpCode===400?'❌ Bad Request':'❌ Erro')) ?></span></td></tr>
<tr><td class="lbl">cURL Error</td>
    <td><?= $curlErr ? '<span class="err">❌ '.$curlErr.'</span>' : '<span class="ok">✅ Sem erros</span>' ?></td></tr>
<tr><td class="lbl">payment_id</td>
    <td><?= $paymentId ? '<span class="ok">✅ '.$paymentId.'</span>' : '<span class="err">❌ Não gerado</span>' ?></td></tr>
<tr><td class="lbl">status</td>
    <td><?= htmlspecialchars((string)($status??'—')) ?></td></tr>
<tr><td class="lbl">qr_code (copia e cola)</td>
    <td><?= $qrCode ? '<span class="ok">✅ '.strlen($qrCode).' chars</span>' : '<span class="err">❌ Não retornado</span>' ?></td></tr>
<tr><td class="lbl">qr_code_base64</td>
    <td><?= $qrBase64 ? '<span class="ok">✅ '.strlen($qrBase64).' chars</span>' : '<span class="err">❌ Não retornado</span>' ?></td></tr>
<?php if ($msgErr): ?>
<tr><td class="lbl">Mensagem de erro</td><td class="err"><?= htmlspecialchars($msgErr) ?></td></tr>
<?php endif; ?>
<?php if ($cause): ?>
<tr><td class="lbl">data recebido pelo MP</td><td class="warn"><?= htmlspecialchars($cause) ?></td></tr>
<?php endif; ?>
<?php if ($qrBase64): ?>
<tr><td class="lbl">QR Code</td>
    <td><img src="data:image/png;base64,<?= $qrBase64 ?>" style="width:180px;height:180px;background:#fff;padding:8px;border-radius:8px;"></td></tr>
<?php endif; ?>
</table>

<h3>4. Resposta bruta da API</h3>
<pre><?= htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>

<?php else: ?>
<p class="err">⛔ Token não configurado ou cURL indisponível. Não foi feita nenhuma chamada.</p>
<?php endif; ?>

<a href="configuracoes.php" class="btn">← Configurações</a>
<p style="color:#475569;font-size:.75rem;margin-top:2rem">⚠️ Apague este arquivo após resolver: <code>admin/mp_debug.php</code></p>
</body>
</html>
