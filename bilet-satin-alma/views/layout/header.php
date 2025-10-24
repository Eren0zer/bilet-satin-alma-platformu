<?php $u = current_user(); ?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Bilet Platformu</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">

  <style nonce="<?= function_exists('csp_nonce') ? e(csp_nonce()) : '' ?>">
  :root{--seat:44px;--gap:10px;--aisle:16px}
  .seat{box-sizing:border-box;position:relative;display:grid;place-items:center;width:var(--seat);height:var(--seat);aspect-ratio:1/1;border:2px solid #9ca3af;border-radius:8px;margin:6px;cursor:pointer;user-select:none;transition:border-color .15s}
  .seat input{position:absolute;inset:0;opacity:0;pointer-events:none}
  .seat span{display:grid;place-items:center;width:100%;height:100%;border-radius:6px;font-size:14px;font-weight:600;font-variant-numeric:tabular-nums;transition:background .15s,color .15s}
  .seat:hover{border-color:#6b7280}
  .seat input:checked+span{background:#2563eb;color:#fff}
  .taken{background:#e5e7eb;color:#6b7280;border-color:#cbd5e1;cursor:not-allowed}
  .taken span{background:transparent}
  .taken input{display:none}
  .seat-map{padding:8px 0;margin:8px 0}
  .seat-grid{display:grid;grid-auto-flow:column;grid-auto-columns:max-content;column-gap:18px;justify-content:center}
  .seat-col{display:grid;grid-template-rows:var(--seat) var(--seat) var(--aisle) var(--seat) var(--seat);align-items:center}
  .aisle-h{height:var(--aisle)}
  .center-row{display:flex;justify-content:center;margin-top:8px;gap:var(--gap)}
  .bus-front,.bus-back{font-weight:600;opacity:.8;margin:6px 4px}
  .ghost{border-color:transparent;background:transparent;pointer-events:none}

  /* Bilet | PDF | İptal butonlarını yan yana ve eşit genişlikte kutular yap */
  .btn-row{display:flex;gap:8px;align-items:stretch}
  .btn-row .btn,
  .btn-row .btn-form button,
  .btn-row .btn-disabled{
    flex:1;text-align:center;padding:10px 12px;border-radius:8px;
    border:1px solid #374151;background:#111827;color:#e5e7eb;
  }
  .btn-row .btn:hover{background:#1f2937}
  .btn-row .btn-primary{background:#0b77c2;border-color:#0b77c2}
  .btn-row .btn-primary:hover{filter:brightness(1.08)}
  .btn-row .btn-danger{background:#b91c1c;border-color:#b91c1c}
  .btn-row .btn-danger:hover{filter:brightness(1.08)}
  .btn-row .btn-disabled{opacity:.55;pointer-events:none}
  .btn-row .btn-form{margin:0;display:contents}
  </style>
</head>

<body>
<header class="container">
  <nav>
    <ul>
      <li><strong><a href="index.php?r=home">Bilet Platformu</a></strong></li>
    </ul>
    <ul>
      <?php if ($u): ?>

        <?php if ($u['role'] === 'user'): ?>
          <li>Bakiye: <strong><?= e(money_fmt($u['credit'])) ?></strong></li>
          <li><a href="index.php?r=my-tickets">Biletlerim</a></li>
          <li><a href="index.php?r=wallet">Bakiye Yükle</a></li>
        <?php endif; ?>

        <?php if ($u['role'] === 'firm_admin'): ?>
            <li><a href="index.php?r=firm">Firma Paneli</a></li>
            <li><a href="index.php?r=firm-tickets">Biletler</a></li>
            <li><a href="index.php?r=firm-coupons">Kuponlar</a></li>
        <?php endif; ?>

        <?php if ($u['role'] === 'admin'): ?>
          <li><a href="index.php?r=admin">Admin</a></li>
          <li><a href="index.php?r=admin-users">Kullanıcılar</a></li>
        <?php endif; ?>

        <li><a href="index.php?r=logout">Çıkış</a></li>

      <?php else: ?>
        <li><a href="index.php?r=login">Giriş</a></li>
        <li><a href="index.php?r=register">Kayıt Ol</a></li>
      <?php endif; ?>
    </ul>
  </nav>
</header>

<main class="container">


    