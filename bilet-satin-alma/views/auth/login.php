<article><h2>Giriş Yap</h2>
<?php if(!empty($error)):?><mark><?= e($error) ?></mark><?php endif;?>
<form method="post" action="index.php?r=login">
  <?= csrf_field() ?>
  <label>E-posta <input name="email" type="email" required></label>
  <label>Şifre <input name="password" type="password" required></label>
  <button type="submit">Giriş</button>
</form>
<small>Örnek: admin@local / admin123 • firma@local / firma123 • user@local / user123</small>
</article>
