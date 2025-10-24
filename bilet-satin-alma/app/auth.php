<?php
function current_user(): ?array {
  if (!isset($_SESSION['uid'])) return null;
  $pdo = db();
  $s = $pdo->prepare('SELECT * FROM users WHERE id=?');
  $s->execute([$_SESSION['uid']]);
  $u = $s->fetch(PDO::FETCH_ASSOC);
  return $u ?: null;
}

function require_login():array{
  $u=current_user();
  if(!$u || (isset($u['active']) && !$u['active'])) redirect('login');
  return $u;
}


function require_role(string $r): array {
  $u = require_login();
  if ($u['role'] !== $r && !($r === 'firm_admin' && $u['role'] === 'admin')) redirect('home');
  return $u;
}
function login_with_password(string $e, string $p): bool {
  $email = trim($e);
  $pdo = db();

  // (Opsiyonel) Rate limit: 60 sn'de 5 deneme
  if (function_exists('throttle_allow') && function_exists('client_ip')) {
    if (!throttle_allow('login_'.client_ip(), 5, 60)) {
      // isteğe bağlı: audit('login_rate_limited', ['email'=>$email]);
      return false;
    }
  }

  // Sadece aktif kullanıcıyı ara (SQLite için LOWER ile case-insensitive)
  $s = $pdo->prepare('SELECT * FROM users WHERE lower(email)=lower(?) AND COALESCE(active,1)=1');
  $s->execute([$email]);
  $u = $s->fetch(PDO::FETCH_ASSOC);

  $ok = false;
  if ($u && password_verify($p, $u['password_hash'])) {
    // Gerekirse hash’i modern algoritma ile yenile
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
      $new = password_hash($p, PASSWORD_DEFAULT);
      $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$new, (int)$u['id']]);
    }
    $ok = true;
  }

  if ($ok) {
    if (function_exists('throttle_reset') && function_exists('client_ip')) {
      throttle_reset('login_'.client_ip());
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    unset($_SESSION['csrf_token']);

    if (function_exists('audit')) audit('login_success', ['email'=>$email, 'uid'=>(int)$u['id']]);
    return true;
  } else {
    if (function_exists('throttle_inc') && function_exists('client_ip')) {
      throttle_inc('login_'.client_ip());
    }
    if (function_exists('audit')) audit('login_fail', ['email'=>$email]);
    return false;
  }
}



function logout(): void {
  $_SESSION = [];
  session_destroy();
}
?>
