<h2>Bakiye Yükle</h2>
<?php if(getv('ok')):?><mark>Yükleme başarılı.</mark><?php endif;?>
<?php if(getv('error')):?><mark><?= e(getv('error')) ?></mark><?php endif;?>
<form method="post" action="index.php?r=wallet">
  <?= csrf_field() ?>
  <label>Tutar (₺)
    <input name="amount" type="number" step="0.01" min="1" required>
  </label>
  <button type="submit">Yükle</button>
</form>
<h3>Son İşlemler</h3>
<table>
  <thead><tr><th>Tarih</th><th>Tutar</th><th>Tür</th><th>Açıklama</th></tr></thead>
  <tbody>
  <?php foreach(($txs??[]) as $t): ?>
    <tr>
      <td><?= e($t['created_at']) ?></td>
      <td><?= e(money_fmt($t['amount'])) ?></td>
      <td><?= e($t['type']) ?></td>
      <td><?= e($t['reason']??'') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if(empty($txs)): ?>
    <tr><td colspan="4">Kayıt yok.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
