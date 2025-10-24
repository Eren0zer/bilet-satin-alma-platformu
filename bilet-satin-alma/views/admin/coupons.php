<h2>Kuponlar</h2>
<details open>
  <summary>Yeni Kupon Ekle</summary>
  <form method="post" action="index.php?r=admin-coupons">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="grid">
      <label>Kod
        <input name="code" placeholder="INDIRIM10" required>
      </label>
      <label>Yüzde
        <input name="percent" type="number" min="1" max="90" value="10" required>
      </label>
      <label>Kullanım Hakkı
        <input name="usage_limit" type="number" min="1" value="100" required>
      </label>
      <label>Son Kullanma
        <input name="expires_at" type="date">
      </label>
    </div>
    <button type="submit">Kaydet</button>
  </form>
</details>
<h3>Mevcut Kuponlar</h3>
<table>
  <thead>
    <tr>
      <th>ID</th><th>Kod</th><th>%</th><th>Limit</th><th>Kullanım</th><th>Bitiş</th><th>Aktif</th><th>İşlem</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach(($coupons??[]) as $c): ?>
      <tr>
        <td><?= (int)$c['id'] ?></td>
        <td><?= e($c['code']) ?></td>
        <td><?= (int)$c['percent'] ?></td>
        <td><?= (int)$c['usage_limit'] ?></td>
        <td><?= (int)$c['used_count'] ?></td>
        <td><?= e($c['expires_at']??'') ?></td>
        <td><?= ((int)$c['active']) ? 'Evet' : 'Hayır' ?></td>
        <td>
          <a href="index.php?r=admin-coupon-toggle&id=<?= (int)$c['id'] ?>"><?= ((int)$c['active']) ? 'Pasifleştir' : 'Aktifleştir' ?></a>
          <a href="index.php?r=admin-coupon-delete&id=<?= (int)$c['id'] ?>" onclick="return confirm('Silinsin mi?')">Sil</a>
        </td>
      </tr>
      <tr>
        <td colspan="8">
          <form method="post" action="index.php?r=admin-coupons" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button type="submit"><?= ((int)$c['active']) ? 'Pasifleştir' : 'Aktifleştir' ?></button>
          </form>
          <form method="post" action="index.php?r=admin-coupons" style="display:inline" onsubmit="return confirm('Silinsin mi?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button type="submit">Sil</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if(empty($coupons)): ?>
      <tr><td colspan="8">Kupon yok.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
