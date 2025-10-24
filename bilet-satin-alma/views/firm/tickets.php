<h2>Firma Biletleri</h2>

<?php if(getv('ok')): ?><mark>İşlem başarılı.</mark><?php endif; ?>
<?php if(getv('error')): ?><mark><?= e(getv('error')) ?></mark><?php endif; ?>

<table>
  <thead>
    <tr>
      <th>Sefer</th>
      <th>Tarih</th>
      <th>Saat</th>
      <th>Koltuk</th>
      <th>Yolcu</th>
      <th>Ödenen</th>
      <th>Durum</th>
      <th>İşlem</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach(($tickets??[]) as $t): ?>
      <?php
        $can=false;
        if($t['status']==='active'){
          $d=DateTime::createFromFormat('Y-m-d H:i',$t['trip_date'].' '.$t['departure_time']);
          $can=(new DateTime('now')) < (clone $d)->modify('-1 hour');
        }
      ?>
      <tr>
        <td><?= e($t['origin']) ?> → <?= e($t['destination']) ?></td>
        <td><?= e($t['trip_date']) ?></td>
        <td><?= e($t['departure_time']) ?></td>
        <td><?= (int)$t['seat_no'] ?></td>
        <td><?= e($t['user_name']) ?> <small>(<?= e($t['user_email']) ?>)</small></td>
        <td><?= e(money_fmt($t['price_paid'])) ?></td>
        <td><?= e($t['status']) ?><?= $t['status']==='canceled' && $t['canceled_at'] ? ' • '.e($t['canceled_at']) : '' ?></td>
        <td>
          <?php if($can): ?>
            <form method="post" action="index.php?r=firm-ticket-cancel" onsubmit="return confirm('İptal edilsin mi?')" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <button type="submit">İptal Et</button>
            </form>
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if(empty($tickets)): ?>
      <tr><td colspan="8">Kayıt yok.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
