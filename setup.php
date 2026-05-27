<?php
$htpasswdPath = __DIR__ . '/.htpasswd';
$htaccessPath = __DIR__ . '/.htaccess';
$done  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $error = 'Usuário: 3 a 20 caracteres (letras, números e _)';
    } elseif (strlen($password) < 8) {
        $error = 'A senha deve ter pelo menos 8 caracteres.';
    } elseif ($password !== $confirm) {
        $error = 'As senhas não coincidem.';
    } else {
        // Gera hash SHA1 para .htpasswd
        $hash = '{SHA}' . base64_encode(sha1($password, true));
        file_put_contents($htpasswdPath, "$username:$hash\n");
        chmod($htpasswdPath, 0640);

        // Escreve .htaccess com Basic Auth + segurança
        $htaccess = 'Options -Indexes

# Força HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Proteção por senha
AuthType Basic
AuthName "HT Painel — Acesso Restrito"
AuthUserFile "' . $htpasswdPath . '"
Require valid-user

# Cabeçalhos de segurança
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# Protege arquivos sensíveis
<Files ".htpasswd">
    Require all denied
</Files>
<Files "setup.php">
    Require all denied
</Files>
';
        file_put_contents($htaccessPath, $htaccess);

        $done = true;
        // Remove este arquivo ao final da requisição
        register_shutdown_function(fn() => @unlink(__FILE__));
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HT Painel — Configuração</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1rem}
.card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:2rem;width:100%;max-width:420px;box-shadow:0 10px 40px rgba(0,0,0,.08)}
.logo{text-align:center;margin-bottom:1.8rem}
.logo h1{font-size:1.6rem;font-weight:800;color:#0f172a}.logo h1 span{color:#3b82f6}
.logo p{color:#64748b;font-size:.82rem;margin-top:.3rem}
.card h2{font-size:1rem;font-weight:700;margin-bottom:.3rem}
.card>p{color:#64748b;font-size:.8rem;margin-bottom:1.5rem}
.field{margin-bottom:1rem}
.field label{display:block;font-size:.7rem;font-weight:700;color:#64748b;letter-spacing:.5px;text-transform:uppercase;margin-bottom:.35rem}
.field input{width:100%;padding:.65rem .9rem;border:1px solid #e2e8f0;border-radius:8px;font-size:.9rem;outline:none;background:#f8fafc;transition:border .2s}
.field input:focus{border-color:#3b82f6}
.btn{width:100%;padding:.75rem;background:#3b82f6;color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:700;cursor:pointer;margin-top:.5rem;transition:opacity .2s}
.btn:hover{opacity:.88}
.err{background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:7px;padding:.65rem .9rem;color:#ef4444;font-size:.8rem;margin-bottom:1rem}
.ok{text-align:center}
.ok .icon{font-size:3rem;margin-bottom:1rem}
.ok h2{font-size:1.2rem;margin-bottom:.5rem}
.ok p{color:#64748b;font-size:.85rem;margin-bottom:1.5rem}
.ok a{display:inline-block;padding:.7rem 1.8rem;background:#3b82f6;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;font-size:.9rem}
.note{text-align:center;color:#94a3b8;font-size:.7rem;margin-top:1.2rem}
</style>
</head>
<body>
<div>
<div class="logo"><h1>HT <span>Hospitalar</span></h1><p>Configuração de Acesso ao Painel</p></div>
<div class="card">
<?php if ($done): ?>
  <div class="ok">
    <div class="icon">✅</div>
    <h2>Proteção ativada!</h2>
    <p>O painel está protegido por senha. Este arquivo de configuração foi removido automaticamente.</p>
    <a href="./">Acessar o Painel</a>
  </div>
<?php else: ?>
  <h2>Criar senha de acesso</h2>
  <p>Esta senha protege a entrada no painel. Use algo forte que só você conheça.</p>
  <?php if ($error): ?>
    <div class="err"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST" autocomplete="off">
    <div class="field">
      <label>Usuário</label>
      <input name="username" type="text" placeholder="ex: admin" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="off" required>
    </div>
    <div class="field">
      <label>Senha (mínimo 8 caracteres)</label>
      <input name="password" type="password" placeholder="••••••••" required>
    </div>
    <div class="field">
      <label>Confirmar senha</label>
      <input name="confirm" type="password" placeholder="••••••••" required>
    </div>
    <button class="btn" type="submit">🔐 Ativar Proteção</button>
  </form>
<?php endif; ?>
</div>
<p class="note">Este arquivo é removido automaticamente após a configuração.</p>
</div>
</body>
</html>
