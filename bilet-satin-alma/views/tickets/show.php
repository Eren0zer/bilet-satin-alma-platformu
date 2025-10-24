<?php $tk=$tk??[]; ?>
<article>
  <h2>Bilet</h2>
  <p><strong>Firma:</strong> <?= e($tk['firm_name']) ?></p>
  <p><strong>Güzergâh:</strong> <?= e($tk['origin']) ?> → <?= e($tk['destination']) ?></p>
  <p><strong>Tarih/Saat:</strong> <?= e($tk['trip_date']) ?> <?= e($tk['departure_time']) ?></p>
  <p><strong>Koltuk:</strong> <?= (int)$tk['seat_no'] ?></p>
  <p><strong>Durum:</strong> <?= e($tk['status']) ?></p>
  <p><strong>Ödenen:</strong> <?= e(money_fmt($tk['price_paid'])) ?></p>
  <p><strong>Satın Alma:</strong> <?= e($tk['purchased_at']) ?></p>
  <button onclick="window.print()">Yazdır / PDF</button>
  <p><a href="index.php?r=my-tickets">Biletlerime Dön</a></p>
</article>
<style>
@media print {
  nav, header, footer, a[href], button { display: none !important; }
  article { margin: 0; padding: 0; }
}
</style>
