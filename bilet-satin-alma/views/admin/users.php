<h2>Kullanıcı Yönetimi</h2>

<?php if(getv('ok')): ?>
  <div style="padding:10px;border:1px solid #16a34a;border-radius:8px;margin-bottom:12px"><?= e(getv('msg') ?: 'İşlem başarılı') ?></div>
<?php endif; ?>
<?php if(getv('error')): ?>
  <div style="padding:10px;border:1px solid #dc2626;border-radius:8px;margin-bottom:12px"><?= e(getv('error')) ?></div>
<?php endif; ?>

<table>
  <thead>
    <tr>
      <th>ID</th>
      <th>Ad</th>
      <th>E-posta</th>
      <th>Rol</th>
      <th>Bakiye</th>
      <th>Durum</th>
      <th>Oluşturulma</th>
      <th>İşlem</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach(($users??[]) as $x): ?>
      <?php
        $isAdmin = ($x['role']==='admin');
        $isActive = (int)$x['active']===1;
        $disableToggle = false;
        if($isAdmin && $isActive && isset($active_admins) && (int)$active_admins<=1) $disableToggle=true;
      ?>
      <tr>
        <td><?= (int)$x['id'] ?></td>
        <td><?= e($x['name']) ?></td>
        <td><?= e($x['email']) ?></td>
        <td><?= e($x['role']) ?></td>
        <td><?= e(money_fmt($x['credit'])) ?></td>
        <td><?= $isActive ? 'Aktif' : 'Pasif' ?></td>
        <td><?= e($x['created_at']) ?></td>
        <td>
          <?php if($disableToggle): ?>
            <span class="btn btn-disabled">Son admin</span>
          <?php else: ?>
            <form method="post" action="index.php?r=admin-user-toggle" style="display:inline" onsubmit="return confirm('İşlem onaylansın mı?')">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
              <button type="submit" class="btn <?= $isActive ? 'btn-danger':'btn-primary' ?>">
                <?= $isActive ? 'Pasifleştir' : 'Aktifleştir' ?>
              </button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if(empty($users)): ?>
      <tr><td colspan="8">Kayıt yok.</td></tr>
    <?php endif; ?>
  </tbody>
</table>
