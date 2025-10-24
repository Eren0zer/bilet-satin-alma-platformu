<?php if (!empty($not_found)): ?>
<mark>Sefer bulunamadı.</mark>
<?php else: ?>
<h2><?= e($trip['firm_name']) ?> — <?= e($trip['origin']) ?> → <?= e($trip['destination']) ?></h2>
<p>
  <strong>Tarih/Saat:</strong> <?= e($trip['trip_date']) ?> <?= e($trip['departure_time']) ?>
  • <strong>Fiyat:</strong> <span id="priceTop"><?= e(money_fmt($trip['price'])) ?></span>
  • <strong>Koltuk:</strong> <?= (int)$trip['seat_count'] ?>
</p>
<?php if (getv('error')): ?><mark><?= e(getv('error')) ?></mark><?php endif; ?>

<h3>Koltuk Seç</h3>

<?php
  $u = current_user();
  $canBuy = ($u && $u['role'] === 'user');
  $readonly = !$canBuy;
?>

<?php if ($canBuy): ?>
<form method="post" action="index.php?r=buy">
  <?= csrf_field() ?>
  <input type="hidden" name="trip_id" value="<?= (int)$trip['id'] ?>">
<?php endif; ?>

<div class="seat-map">
  <div class="bus-front"></div>
  <?php
    $seatCount = (int)$trip['seat_count'];
    $colsFull  = intdiv($seatCount, 4);
    $rem       = $seatCount - ($colsFull * 4);
    $taken     = $takenSeats ?? [];

    function seat_cell_out(int $n, array $taken, bool $readonly): void {
      $isTaken = in_array($n, $taken, true);
      if ($readonly) {
        $cls = 'seat' . ($isTaken ? ' taken' : '');
        echo '<div class="'.$cls.'"><span>'.$n.'</span></div>';
      } else {
        $cls = 'seat' . ($isTaken ? ' taken' : '');
        $dis = $isTaken ? ' disabled' : '';
        echo '<label class="'.$cls.'"><input type="radio" name="seat_no" value="'.$n.'"'.$dis.'><span>'.$n.'</span></label>';
      }
    }
  ?>
  <div class="seat-grid">
    <?php for ($c=0; $c<$colsFull; $c++): $b=$c*4; ?>
      <div class="seat-col">
        <?php seat_cell_out($b+4, $taken, $readonly); ?>
        <?php seat_cell_out($b+3, $taken, $readonly); ?>
        <div class="aisle-h"></div>
        <?php seat_cell_out($b+2, $taken, $readonly); ?>
        <?php seat_cell_out($b+1, $taken, $readonly); ?>
      </div>
    <?php endfor; ?>

    <?php if ($rem>0): $b=$colsFull*4; ?>
      <div class="seat-col">
        <?php if ($rem===4) { seat_cell_out($b+4, $taken, $readonly); } else { echo '<div class="seat ghost"></div>'; } ?>
        <?php if ($rem>=3)  { seat_cell_out($b+3, $taken, $readonly); } else { echo '<div class="seat ghost"></div>'; } ?>
        <div class="aisle-h"></div>
        <?php if ($rem>=2)  { seat_cell_out($b+2, $taken, $readonly); } else { echo '<div class="seat ghost"></div>'; } ?>
        <?php if ($rem>=1)  { seat_cell_out($b+1, $taken, $readonly); } else { echo '<div class="seat ghost"></div>'; } ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="bus-back"></div>
</div>

<?php if (!$canBuy): ?>
  <div style="display:flex;gap:12px;align-items:center;margin:8px 0">
    <div class="seat"><span>—</span></div><span>Boş</span>
    <div class="seat taken"><span>—</span></div><span>Dolu</span>
  </div>
  <p>Satın almak için <a href="index.php?r=login">giriş yapın</a> veya <a href="index.php?r=register">kayıt olun</a>.</p>
<?php else: ?>

  <div id="priceBox" style="margin:14px 0;padding:10px;border:1px solid #374151;border-radius:8px">
    <div>Mevcut Fiyat: <strong id="pOrig"><?= e(money_fmt($trip['price'])) ?></strong></div>
    <div id="pDiscRow" style="display:none">Kupon: <strong id="pCode"></strong> • İndirim: <strong id="pPerc"></strong> • Yeni Fiyat: <strong id="pNew"></strong></div>
    <div id="pMsg" style="margin-top:6px;opacity:.9"></div>
  </div>

  <div class="form-grid">
    <label>Kupon Kodu
      <input type="text" name="coupon" id="coupon" placeholder="INDIRIM10" maxlength="24">
    </label>
    <div style="display:flex;align-items:end">
      <button type="button" id="btnCoupon">Uygula</button>
    </div>
  </div>

  <p>Seçilen koltuk: <strong id="selSeat"></strong></p>
  <button type="submit">Satın Al</button>

  <script>
  (function(){
    var tripId = <?= (int)$trip['id'] ?>;
    var origTxt = "<?= e(money_fmt($trip['price'])) ?>";
    function applyCoupon(){
      var code = (document.getElementById('coupon').value || '').toUpperCase().replace(/[^A-Z0-9]/g,'');
      fetch('index.php?r=apply-coupon&trip_id='+tripId+'&code='+encodeURIComponent(code))
        .then(function(r){ return r.json(); })
        .then(function(j){
          var pTop=document.getElementById('priceTop');
          var pOrig=document.getElementById('pOrig');
          var pRow=document.getElementById('pDiscRow');
          var pCode=document.getElementById('pCode');
          var pPerc=document.getElementById('pPerc');
          var pNew=document.getElementById('pNew');
          var pMsg=document.getElementById('pMsg');

          pMsg.textContent='';
          if (!j.ok){
            pRow.style.display='none';
            pOrig.textContent = j.price_original_fmt || origTxt;
            if(pTop) pTop.textContent = j.price_original_fmt || origTxt;
            if (j.message) pMsg.textContent = j.message;
            return;
          }
          if ((j.discount_percent||0) > 0){
            pRow.style.display='';
            pCode.textContent = j.code || code;
            pPerc.textContent = (j.discount_percent)+'%';
            pNew.textContent = j.price_discounted_fmt;
            pOrig.textContent = j.price_original_fmt;
            if(pTop) pTop.textContent = j.price_discounted_fmt;
          } else {
            pRow.style.display='none';
            pOrig.textContent = j.price_original_fmt;
            if(pTop) pTop.textContent = j.price_original_fmt;
          }
        })
        .catch(function(){
          var pMsg=document.getElementById('pMsg');
          if(pMsg) pMsg.textContent='Kupon kontrolü yapılamadı.';
        });
    }
    var btn=document.getElementById('btnCoupon');
    if(btn){ btn.addEventListener('click', applyCoupon); }
    var inp=document.getElementById('coupon');
    if(inp){ inp.addEventListener('keyup', function(e){ if(e.key==='Enter'){ applyCoupon(); } }); }
    document.addEventListener('change',function(e){
      if(e.target && e.target.name==='seat_no'){
        var o=document.getElementById('selSeat'); if(o) o.textContent=e.target.value;
      }
    });
  })();
  </script>

<?php endif; ?>

<?php if ($canBuy): ?></form><?php endif; ?>
<?php endif; ?>
