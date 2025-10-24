<h2>Biletlerim</h2>

<?php if (getv('error')): ?>
  <mark><?= e(getv('error')) ?></mark>
<?php endif; ?>

<table>
  <thead>
    <tr>
      <th>Sefer</th>
      <th>Tarih/Saat</th>
      <th>Koltuk</th>
      <th>Durum</th>
      <th>Ödenen</th>
      <th>İşlem</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($tickets as $t): ?>
      <tr>
        <td><?= e($t['origin']) ?> → <?= e($t['destination']) ?></td>
        <td><?= e($t['trip_date']) ?> <?= e($t['departure_time']) ?></td>
        <td><?= (int) $t['seat_no'] ?></td>
        <td><?= e($t['status']) ?></td>
        <td><?= e(money_fmt($t['price_paid'])) ?></td>
        <td>
          <div class="btn-row">
            <a class="btn btn-primary" href="index.php?r=ticket&id=<?= (int)$t['id'] ?>">Bilet</a>
            <a class="btn btn-primary" href="index.php?r=ticket-pdf&id=<?= (int)$t['id'] ?>">PDF</a>

            <?php if ($t['status']==='active'): ?>
              <form class="btn-form" method="post" action="index.php?r=cancel-ticket" data-confirm="İptal edilsin mi?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button type="submit" class="btn btn-danger">İptal</button>
              </form>
            <?php else: ?>
              <span class="btn btn-disabled">İptal</span>
            <?php endif; ?>
          </div>
        </td>


      </tr>
    <?php endforeach; ?>

    <?php if (!$tickets): ?>
      <tr>
        <td colspan="6">Henüz biletiniz yok.</td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>
