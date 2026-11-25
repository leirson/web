<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$db  = getDB();
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senhaAtual = $_POST['senha_atual'] ?? '';
    $senhaNova  = $_POST['senha_nova'] ?? '';
    $senhaConf  = $_POST['senha_conf'] ?? '';

    if (!$nome || !$email) {
        $err = 'Nome e e-mail são obrigatórios.';
    } else {
        $stmt = $db->prepare("SELECT senha FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();

        if ($senhaNova) {
            if (!password_verify($senhaAtual, $admin['senha'])) {
                $err = 'Senha atual incorreta.';
            } elseif ($senhaNova !== $senhaConf) {
                $err = 'A nova senha e a confirmação não coincidem.';
            } elseif (strlen($senhaNova) < 6) {
                $err = 'A nova senha deve ter pelo menos 6 caracteres.';
            } else {
                $hash = password_hash($senhaNova, PASSWORD_DEFAULT);
                $db->prepare("UPDATE admins SET nome=?, email=?, senha=? WHERE id=?")->execute([$nome, $email, $hash, $_SESSION['admin_id']]);
                $_SESSION['admin_nome'] = $nome;
                $msg = 'Dados e senha atualizados com sucesso.';
            }
        } else {
            $db->prepare("UPDATE admins SET nome=?, email=? WHERE id=?")->execute([$nome, $email, $_SESSION['admin_id']]);
            $_SESSION['admin_nome'] = $nome;
            $msg = 'Dados atualizados com sucesso.';
        }
    }
}

$stmt = $db->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Minha Conta — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="main-content">
  <?php include __DIR__ . '/partials/topbar.php'; ?>
  <div class="page-body">
    <div class="page-header">
      <h1><i class="fas fa-user-gear"></i> Minha Conta</h1>
    </div>

    <?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-circle-check"></i> <?= sanitize($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger"><i class="fas fa-triangle-exclamation"></i> <?= sanitize($err) ?></div><?php endif; ?>

    <div class="card form-card" style="max-width:600px">
      <div class="card-header"><h2><i class="fas fa-id-card"></i> Dados do Administrador</h2></div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <div class="form-group">
            <label>Nome</label>
            <input type="text" name="nome" value="<?= sanitize($admin['nome']) ?>" required>
          </div>
          <div class="form-group">
            <label>E-mail</label>
            <input type="email" name="email" value="<?= sanitize($admin['email']) ?>" required>
          </div>
          <hr style="border-color:rgba(255,255,255,0.08);margin:1.5rem 0">
          <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1rem">
            <i class="fas fa-lock"></i> Deixe em branco para não alterar a senha.
          </p>
          <div class="form-group">
            <label>Senha Atual</label>
            <input type="password" name="senha_atual" placeholder="••••••••">
          </div>
          <div class="form-group">
            <label>Nova Senha</label>
            <input type="password" name="senha_nova" placeholder="••••••••" minlength="6">
          </div>
          <div class="form-group">
            <label>Confirmar Nova Senha</label>
            <input type="password" name="senha_conf" placeholder="••••••••">
          </div>
          <div class="form-actions" style="margin-top:1.5rem">
            <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Salvar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
</body>
</html>
