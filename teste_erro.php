<?php
// Teste de erro - delete após usar
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo '<h2>Passo 1: PHP funcionando</h2>';

require_once __DIR__ . '/config.php';
echo '<h2>Passo 2: config.php carregado</h2>';
echo 'BASE_URL: ' . BASE_URL . '<br>';

require_once __DIR__ . '/includes/functions.php';
echo '<h2>Passo 3: functions.php carregado</h2>';

$db = getDB();
echo '<h2>Passo 4: Banco conectado</h2>';

$c = cfgAll();
echo '<h2>Passo 5: Configurações carregadas (' . count($c) . ' registros)</h2>';

echo '<h2>Passo 6: Testando query de produtos</h2>';
$stmt = $db->query("SELECT COUNT(*) FROM produtos WHERE ativo = 1");
echo 'Total produtos: ' . $stmt->fetchColumn() . '<br>';

echo '<h2 style="color:green">✔ Tudo OK! O erro está no HTML/template do index.php</h2>';
echo '<p>Agora acesse: <a href="index.php">index.php direto</a></p>';
