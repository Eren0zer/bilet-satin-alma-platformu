<article><h2>Kayıt Ol</h2>
<?php if(!empty($error)):?><mark><?= e($error) ?></mark><?php endif;?>
<form method="post" action="index.php?r=register">
  <?= csrf_field() ?>
  <label>Ad Soyad <input name="name" required></label>
  <label>E-posta <input name="email" type="email" required></label>
  <label>Şifre <input name="password" type="password" minlength="6" required></label>
  <button type="submit">Kayıt Ol</button>
</form>
</article>
