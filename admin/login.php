<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (isAdmin()) {
    redirect(BASE_URL . '/admin/dashboard.php');
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if ($email && $senha) {
        try {
            $db   = getDB();
            $stmt = $db->prepare("SELECT id, nome, senha FROM admins WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($senha, $admin['senha'])) {
                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_nome'] = $admin['nome'];
                redirect(BASE_URL . '/admin/dashboard.php');
            } else {
                $erro = 'E-mail ou senha inválidos.';
            }
        } catch (Exception $e) {
            $erro = 'Erro ao realizar login. Tente novamente.';
        }
    } else {
        $erro = 'Preencha todos os campos.';
    }
}

$token = csrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .login-card {
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 20px;
    padding: 2.5rem;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 25px 50px rgba(0,0,0,0.5);
  }
  .login-logo {
    text-align: center;
    margin-bottom: 2rem;
  }
  .login-logo i {
    font-size: 3rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }
  .login-logo h1 {
    color: #fff;
    font-size: 1.5rem;
    font-weight: 700;
    margin-top: 0.5rem;
  }
  .login-logo p {
    color: rgba(255,255,255,0.5);
    font-size: 0.85rem;
    margin-top: 0.25rem;
  }
  .form-group { margin-bottom: 1.25rem; }
  .form-group label {
    display: block;
    color: rgba(255,255,255,0.7);
    font-size: 0.85rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
  }
  .form-group input {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 10px;
    color: #fff;
    font-size: 0.95rem;
    font-family: 'Inter', sans-serif;
    transition: border-color 0.2s, background 0.2s;
    outline: none;
  }
  .form-group input:focus {
    border-color: #6366f1;
    background: rgba(99,102,241,0.1);
  }
  .form-group input::placeholder { color: rgba(255,255,255,0.3); }
  .btn-login {
    width: 100%;
    padding: 0.85rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none;
    border-radius: 10px;
    color: #fff;
    font-size: 1rem;
    font-weight: 600;
    font-family: 'Inter', sans-serif;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.1s;
    margin-top: 0.5rem;
  }
  .btn-login:hover { opacity: 0.9; transform: translateY(-1px); }
  .btn-login:active { transform: translateY(0); }
  .alerta-erro {
    background: rgba(239,68,68,0.15);
    border: 1px solid rgba(239,68,68,0.4);
    border-radius: 10px;
    color: #fca5a5;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  .back-link {
    text-align: center;
    margin-top: 1.5rem;
  }
  .back-link a {
    color: rgba(255,255,255,0.4);
    text-decoration: none;
    font-size: 0.85rem;
    transition: color 0.2s;
  }
  .back-link a:hover { color: rgba(255,255,255,0.8); }
</style>
</head>
<body>
<div class="login-card">
  <div class="login-logo">
    <i class="fas fa-shield-halved"></i>
    <h1>Área Administrativa</h1>
    <p>Faça login para continuar</p>
  </div>
  <?php if ($erro): ?>
  <div class="alerta-erro"><i class="fas fa-circle-exclamation"></i> <?= sanitize($erro) ?></div>
  <?php endif; ?>
  <form method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?= $token ?>">
    <div class="form-group">
      <label for="email"><i class="fas fa-envelope"></i> E-mail</label>
      <input type="email" id="email" name="email" placeholder="admin@seusite.com" required
             value="<?= sanitize($_POST['email'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label for="senha"><i class="fas fa-lock"></i> Senha</label>
      <input type="password" id="senha" name="senha" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn-login"><i class="fas fa-right-to-bracket"></i> Entrar</button>
  </form>
  <div class="back-link">
    <a href="<?= BASE_URL ?>"><i class="fas fa-arrow-left"></i> Voltar ao site</a>
  </div>
</div>
</body>
</html>
