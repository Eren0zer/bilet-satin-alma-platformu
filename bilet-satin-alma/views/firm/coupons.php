<h2>Kuponlarım</h2>

<article>
  <h3>Yeni Kupon</h3>
  <form method="post" action="index.php?r=firm-coupons">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-grid">
      <label>Kod
        <input name="code" placeholder="INDIRIM10" required>
      </label>
      <label>Yüzde
        <input name="percent" type="number" min="1" max="90" value="10" required>
      </label>
      <label>Kullanım Limiti
        <input name="usage_limit" type="number" min="1" value="100" required>
      </label>
      <label>Son Tarih
        <input name="expires_at" type="date">
      </label>
    </div>
    <button type="submit">Ekle</button>
  </form>
</article>

<h3>Kupon Listesi</h3>
<table>
  <thead>
    <tr>
      <th>Kod</th>
      <th>Yüzde</th>
      <th>Limit/Kullanım</th>
      <th>Son Tarih</th>
      <th>Durum</th>
      <th>İşlem</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach(($coupons??[]) as $c): ?>
      <tr>
        <td><?= e($c['code']) ?></td>
        <td><?= (int)$c['percent'] ?>%</td>
        <td><?= (int)$c['used_count'] ?> / <?= (int)$c['usage_limit'] ?></td>
        <td><?= e($c['expires_at'] ?: '—') ?></td>
        <td><?= ((int)$c['active']) ? 'Aktif' : 'Pasif' ?></td>
        <td>
          <details style="display:inline-block;margin-right:8px">
            <summary>Düzenle</summary>
            <form method="post" action="index.php?r=firm-coupons">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <div class="form-grid">
                <label>Yüzde
                  <input name="percent" type="number" min="1" max="90" value="<?= (int)$c['percent'] ?>" required>
                </label>
                <label>Kullanım Limiti
                  <input name="usage_limit" type="number" min="1" value="<?= (int)$c['usage_limit'] ?>" required>
                </label>
                <label>Son Tarih
                  <input name="expires_at" type="date" value="<?= e($c['expires_at']) ?>">
                </label>
              </div>
              <button type="submit">Kaydet</button>
            </form>
          </details>

          <form method="post" action="index.php?r=firm-coupons" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button type="submit"><?= ((int)$c['active']) ? 'Pasifleştir' : 'Aktifleştir' ?></button>
          </form>

          <form method="post" action="index.php?r=firm-coupons" style="display:inline" data-confirm="Silinsin mi?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button type="submit">Sil</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if(empty($coupons)): ?>
      <tr><td colspan="6">Kupon yok.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
