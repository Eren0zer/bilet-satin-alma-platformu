<style>
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
@media (max-width:768px){.form-grid{grid-template-columns:1fr}}
input,select,button{width:100%}
.actions{display:flex;gap:8px;align-items:center}
.row-actions{display:flex;gap:8px}
details{margin:0}
details>div{padding:12px 0}
table td, table th{vertical-align:top}
</style>

<h2>Firma Paneli</h2>

<article>
  <h3>Yeni Sefer Ekle</h3>
  <form method="post" action="index.php?r=firm">
    <?= csrf_field() ?>
    <div class="form-grid">
      <label>Kalkış
        <input name="origin" placeholder="İstanbul" required>
      </label>
      <label>Varış
        <input name="destination" placeholder="Ankara" required>
      </label>
      <label>Tarih
        <input name="trip_date" type="date" required>
      </label>
      <label>Saat
        <input name="departure_time" type="time" required>
      </label>
      <label>Fiyat
        <input name="price" type="number" step="0.01" placeholder="450" required>
      </label>
      <label>Koltuk
        <input name="seat_count" type="number" min="1" value="40" required>
      </label>
    </div>
    <button type="submit">Sefer Ekle</button>
  </form>
</article>

<h3>Seferler</h3>
<table>
  <thead>
    <tr>
      <th>Güzergâh</th>
      <th>Tarih</th>
      <th>Saat</th>
      <th>Fiyat</th>
      <th>Koltuk</th>
      <th>İşlem</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($trips as $t): ?>
      <tr>
        <td><?= e($t['origin']) ?> → <?= e($t['destination']) ?></td>
        <td><?= e($t['trip_date']) ?></td>
        <td><?= e($t['departure_time']) ?></td>
        <td><?= e(money_fmt($t['price'])) ?></td>
        <td><?= (int)$t['seat_count'] ?></td>
        <td class="actions">
          <details>
            <summary>Düzenle</summary>
            <div>
              <form method="post" action="index.php?r=firm-trip-update">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <div class="form-grid">
                  <label>Kalkış
                    <input name="origin" value="<?= e($t['origin']) ?>" required>
                  </label>
                  <label>Varış
                    <input name="destination" value="<?= e($t['destination']) ?>" required>
                  </label>
                  <label>Tarih
                    <input name="trip_date" type="date" value="<?= e($t['trip_date']) ?>" required>
                  </label>
                  <label>Saat
                    <input name="departure_time" type="time" value="<?= e($t['departure_time']) ?>" required>
                  </label>
                  <label>Fiyat
                    <input name="price" type="number" step="0.01" value="<?= e($t['price']) ?>" required>
                  </label>
                  <label>Koltuk
                    <input name="seat_count" type="number" min="1" value="<?= (int)$t['seat_count'] ?>" required>
                  </label>
                </div>
                <button type="submit">Kaydet</button>
              </form>
            </div>
          </details>
          <form method="post" action="index.php?r=firm-trip-delete" onsubmit="return confirm('Silinsin mi?')" style="margin:0">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <button type="submit">Sil</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$trips): ?>
      <tr><td colspan="6">Sefer yok.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
