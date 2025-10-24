<?php
/**
 * Güvenlik başlıkları, rate-limit ve audit log fonksiyonları
 * XAMPP ortamında PHP 8+ için test edilmiştir.
 */

function sec_headers(): void {
  if (headers_sent()) return;

  // === CSP Nonce üret ===
  $nonce = base64_encode(random_bytes(16));
  $GLOBALS['CSP_NONCE'] = $nonce;

  // === Güvenlik başlıkları ===
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: DENY');
  header('Referrer-Policy: no-referrer');
  header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

  // HTTPS ortamında HSTS aktif et
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
  }
}

/**
 * CSP nonce’ını sayfa içinde kullanmak için
 */
function csp_nonce(): string {
  return $GLOBALS['CSP_NONCE'] ?? '';
}

/**
 * İstemci IP adresini döndür
 */
function client_ip(): string {
  if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    return trim($parts[0]);
  }
  return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Basit rate-limit mekanizması (oturum temelli)
 * @param string $key   anahtar (örn. "login")
 * @param int    $max   izin verilen istek sayısı
 * @param int    $window saniye cinsinden pencere süresi
 */
function throttle_allow(string $key, int $max, int $window): bool {
  $now = time();
  $k = 'thr_'.$key;
  $d = $_SESSION[$k] ?? ['start' => $now, 'count' => 0];

  if ($now - ($d['start'] ?? 0) >= $window)
    $d = ['start' => $now, 'count' => 0];

  $_SESSION[$k] = $d;
  return ($d['count'] ?? 0) < $max;
}
function throttle_inc(string $key): void {
  $k = 'thr_'.$key;
  $d = $_SESSION[$k] ?? ['start' => time(), 'count' => 0];
  $d['count'] = ($d['count'] ?? 0) + 1;
  $_SESSION[$k] = $d;
}
function throttle_reset(string $key): void { unset($_SESSION['thr_'.$key]); }
function throttle_remaining(string $key,int $window): int {
  $k = 'thr_'.$key;
  $d = $_SESSION[$k] ?? null;
  if (!$d) return 0;
  $left = ($d['start'] + $window) - time();
  return $left > 0 ? $left : 0;
}

/**
 * Kullanıcı eylemlerini audit log tablosuna yazar
 */
function audit(string $action, array $payload = []): void {
  $pdo = db();
  $uid = $_SESSION['uid'] ?? null;
  $ip  = client_ip();
  $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';
  $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

  $st = $pdo->prepare('INSERT INTO audit_log(user_id,action,ip,ua,payload) VALUES(?,?,?,?,?)');
  $st->execute([$uid, $action, $ip, $ua, $json]);
}
