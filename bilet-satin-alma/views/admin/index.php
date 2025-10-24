<h2>Admin Paneli</h2>

<?php if(getv('ok')): ?>
  <div style="padding:10px;border:1px solid #16a34a;border-radius:8px;margin-bottom:12px">
    <?= e(getv('msg') ?: 'İşlem başarılı') ?>
    <?php if(getv('fa_email')): ?>
      • Giriş: <strong><?= e(getv('fa_email')) ?></strong><?php if(getv('fa_pass')): ?> / <strong><?= e(getv('fa_pass')) ?></strong><?php endif; ?>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php if(getv('error')): ?>
  <div style="padding:10px;border:1px solid #dc2626;border-radius:8px;margin-bottom:12px"><?= e(getv('error')) ?></div>
<?php endif; ?>


<form method="post" action="index.php?r=admin" style="margin-bottom:16px">
  <?= csrf_field() ?>
  <label>Yeni Firma Adı <input name="new_firm" required placeholder="Yavuzlar"></label>
  <button type="submit">Ekle ve Otomatik Yetkili Oluştur</button>
</form>

<?php $pdo=db(); $firms=$pdo->query('SELECT * FROM firms ORDER BY name')->fetchAll(PDO::FETCH_ASSOC); ?>

<h3>Firmalar</h3>
<table>
  <thead>
    <tr><th>Ad</th><th>Yetkililer</th><th style="width:360px">İşlem</th></tr>
  </thead>
  <tbody>
    <?php foreach($firms as $f): ?>
      <?php
        $st=$pdo->prepare('SELECT u.id,u.email FROM firm_admins fa JOIN users u ON u.id=fa.user_id WHERE fa.firm_id=? ORDER BY u.email');
        $st->execute([(int)$f['id']]);
        $admins=$st->fetchAll(PDO::FETCH_ASSOC);
      ?>
      <tr>
        <td><?= e($f['name']) ?></td>
        <td>
          <?php if($admins): ?>
            <ul style="margin:0;padding-left:18px">
              <?php foreach($admins as $a): ?>
                <li>
                  <?= e($a['email']) ?>
                  <form method="post" action="index.php?r=admin-firm-remove-admin" style="display:inline" onsubmit="return confirm('Yetkili kaldırılsın mı?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="firm_id" value="<?= (int)$f['id'] ?>">
                    <input type="hidden" name="user_id" value="<?= (int)$a['id'] ?>">
                    <button type="submit">Kaldır</button>
                  </form>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
        <td>
          <details style="display:inline-block;margin-right:8px">
            <summary>Düzenle</summary>
            <form method="post" action="index.php?r=admin-firm-update">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
              <input name="name" value="<?= e($f['name']) ?>" required>
              <button type="submit">Kaydet</button>
            </form>
          </details>

          <form method="post" action="index.php?r=admin-firm-delete" onsubmit="return confirm('Silinsin mi?')" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <button type="submit">Sil</button>
          </form>

          <details style="display:inline-block;margin-left:8px">
            <summary>Yetkili Ekle</summary>
            <form method="post" action="index.php?r=admin-firm-add-admin">
              <?= csrf_field() ?>
              <input type="hidden" name="firm_id" value="<?= (int)$f['id'] ?>">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;max-width:420px">
                <label>E-posta (boş bırak otomatik)
                  <input name="email" placeholder="opsiyonel">
                </label>
                <label>Parola (manuel ekleyeceksen)
                  <input name="password" type="password" placeholder="min 6">
                </label>
              </div>
              <button type="submit">Ekle</button>
            </form>
          </details>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if(empty($firms)): ?><tr><td colspan="3">Kayıt yok.</td></tr><?php endif; ?>
  </tbody>
</table>
