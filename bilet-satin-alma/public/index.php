<?php
require_once __DIR__.'/../app/bootstrap.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!isset($_POST['csrf_token']) || $_POST['csrf_token']!==($_SESSION['csrf_token']??'')){
    http_response_code(419); exit('CSRF');
  }
}
$r=$_GET['r']??'home';
switch($r){
case 'home':
  $pdo=db();
  $q=$_GET['q']??'';
  $date=$_GET['date']??'';
  $sql='SELECT t.*,f.name AS firm_name FROM trips t JOIN firms f ON f.id=t.firm_id WHERE 1=1';
  $args=[];
  if($q!==''){$sql.=' AND (t.origin LIKE ? OR t.destination LIKE ?)';$args[]="%$q%";$args[]="%$q%";}
  if($date!==''){$sql.=' AND t.trip_date=?';$args[]=$date;}
  $sql.=' ORDER BY t.trip_date,t.departure_time';
  $st=$pdo->prepare($sql);$st->execute($args);
  $trips=$st->fetchAll(PDO::FETCH_ASSOC);
  render('home/home',['trips'=>$trips,'q'=>$q,'date'=>$date]);
  break;
case 'login':
  if($_SERVER['REQUEST_METHOD']==='POST'){
    $email=trim($_POST['email']??'');$key='login:'.client_ip();
    if(!throttle_allow($key,5,300)){ http_response_code(429); $left=throttle_remaining($key,300); exit('Çok fazla deneme. '.$left.' sn sonra tekrar deneyin.'); }
    if(login_with_password($email,$_POST['password']??'')){ throttle_reset($key); audit('login_success',['email'=>$email]); redirect('home'); }
    throttle_inc($key); audit('login_fail',['email'=>$email]); $error='E-posta veya şifre hatalı.'; render('auth/login',['error'=>$error]);
  } else {render('auth/login');}
  break;

case 'register':
  if($_SERVER['REQUEST_METHOD']==='POST'){
    $name=trim($_POST['name']??'');$email=trim($_POST['email']??'');$pass=$_POST['password']??'';
    $okPass=(bool)preg_match('/^(?=.*[A-Za-z])(?=.*\d).{8,}$/',$pass);
    if($name&&filter_var($email,FILTER_VALIDATE_EMAIL)&&$okPass){
      try{$pdo=db();$st=$pdo->prepare('INSERT INTO users(name,email,password_hash,role,credit) VALUES(?,?,?,?,?)');$st->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT),'user',200]);login_with_password($email,$pass);audit('register_success',['email'=>$email]);redirect('home');}
      catch(Throwable $e){$error='Kayıt başarısız.';audit('register_fail',['email'=>$email]);render('auth/register',['error'=>$error]);}
    } else {$error='Bilgileri kontrol edin. Parola en az 8 karakter ve harf+rakam içermeli.';render('auth/register',['error'=>$error]);}
  } else {render('auth/register');}
  break;

case 'logout':
  logout();redirect('home');break;

case 'ticket':
  $u=require_login();
  $id=(int)($_GET['id']??0);
  $pdo=db();
  $st=$pdo->prepare('SELECT tk.*,t.origin,t.destination,t.trip_date,t.departure_time,f.name AS firm_name FROM tickets tk JOIN trips t ON t.id=tk.trip_id JOIN firms f ON f.id=t.firm_id WHERE tk.id=? AND tk.user_id=?');
  $st->execute([$id,$u['id']]);
  $ticket=$st->fetch(PDO::FETCH_ASSOC);
  if(!$ticket) redirect('my-tickets');
  render('tickets/show',['tk'=>$ticket,'u'=>$u]);
  break;

case 'trip':
  $id=(int)($_GET['id']??0);
  $pdo=db();
  $st=$pdo->prepare('SELECT t.*,f.name AS firm_name FROM trips t JOIN firms f ON f.id=t.firm_id WHERE t.id=?');$st->execute([$id]);
  $trip=$st->fetch(PDO::FETCH_ASSOC);
  if(!$trip){render('trips/detail',['not_found'=>true]);break;}
  $tk=$pdo->prepare('SELECT seat_no FROM tickets WHERE trip_id=? AND status="active"');$tk->execute([$id]);
  $taken=array_map('intval',array_column($tk->fetchAll(PDO::FETCH_ASSOC),'seat_no'));
  render('trips/detail',['trip'=>$trip,'takenSeats'=>$taken]);
  break;
case 'admin-coupons':
  redirect('admin', ['msg' => 'Kupon yönetimi firma paneline taşındı.']);
  break;


case 'admin-coupon-toggle':
  $u=require_role('admin');
  $pdo=db();
  $id=(int)($_GET['id']??0);
  if($id>0){
    $a=(int)$pdo->query('SELECT active FROM coupons WHERE id='.(int)$id)->fetchColumn();
    $pdo->prepare('UPDATE coupons SET active=? WHERE id=?')->execute([$a?0:1,$id]);
  }
  redirect('admin-coupons');
  break;

case 'admin-coupon-delete':
  $u=require_role('admin');
  $pdo=db();
  $id=(int)($_GET['id']??0);
  if($id>0){
    $pdo->prepare('DELETE FROM coupons WHERE id=?')->execute([$id]);
  }
  redirect('admin-coupons');
  break;

case 'wallet':
  $u=require_login(); if($u['role']!=='user') redirect('home');
  $pdo=db();
  if($_SERVER['REQUEST_METHOD']==='POST'){
    $amount=(float)($_POST['amount']??0);
    if($amount>0){
      $pdo->beginTransaction();
      $pdo->prepare('UPDATE users SET credit=credit+? WHERE id=?')->execute([$amount,$u['id']]);
      $pdo->prepare('INSERT INTO wallet_tx(user_id,amount,type,reason) VALUES(?,?,"credit","Bakiye yükleme")')->execute([$u['id'],$amount]);
      $pdo->commit(); redirect('wallet',['ok'=>1]);
    } else {
      redirect('wallet',['error'=>'Geçersiz tutar']);
    }
  } else {
    $st=$pdo->prepare('SELECT * FROM wallet_tx WHERE user_id=? ORDER BY created_at DESC LIMIT 10');
    $st->execute([$u['id']]);
    $txs=$st->fetchAll(PDO::FETCH_ASSOC);
    render('wallet/index',['u'=>$u,'txs'=>$txs]);
  }
  break;

case 'buy':
  $u = require_login();
  if ($u['role'] !== 'user') redirect('home');
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('home'); break; }

  $trip_id = (int)($_POST['trip_id'] ?? 0);
  $seat_no = (int)($_POST['seat_no'] ?? 0);
  $coupon  = strtoupper(trim($_POST['coupon'] ?? ''));
  $coupon  = preg_replace('/[^A-Z0-9]/','', $coupon);

  $pdo = db();
  $pdo->beginTransaction();
  try {
    // Sefer
    $st = $pdo->prepare('SELECT * FROM trips WHERE id=?');
    $st->execute([$trip_id]);
    $trip = $st->fetch(PDO::FETCH_ASSOC);
    if(!$trip) throw new Exception('Sefer bulunamadı.');

    if ($seat_no < 1 || $seat_no > (int)$trip['seat_count']) throw new Exception('Geçersiz koltuk.');

    // Koltuk dolu mu?
    $c = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE trip_id=? AND seat_no=? AND status="active"');
    $c->execute([$trip_id, $seat_no]);
    if ((int)$c->fetchColumn() > 0) throw new Exception('Koltuk dolu.');

    // Fiyat + kupon
    $price = (float)$trip['price'];
    $couponRow = null;
    if ($coupon !== '') {
      // Sadece bu seferin firması için (veya firm_id NULL = global) kupon ara
      $cs = $pdo->prepare('SELECT * FROM coupons
                           WHERE code=? AND active=1
                             AND used_count < usage_limit
                             AND (expires_at IS NULL OR date(expires_at) >= date("now"))
                             AND (firm_id IS NULL OR firm_id=?)');
      $cs->execute([$coupon, (int)$trip['firm_id']]);
      $couponRow = $cs->fetch(PDO::FETCH_ASSOC);
      if (!$couponRow) throw new Exception('Kupon geçersiz veya bu firmada geçerli değil.');

      $percent = max(1, min(90, (int)$couponRow['percent']));
      $price   = $price * (100 - $percent) / 100.0;

      // Not: aynı transaction içinde olduğumuz için başarısızlıkta geri alınır
      $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id=?')->execute([(int)$couponRow['id']]);
    }

    // Bakiye kontrolü
    $cu = $pdo->prepare('SELECT credit FROM users WHERE id=?');
    $cu->execute([$u['id']]);
    $cur = (float)$cu->fetchColumn();
    if ($cur < $price) throw new Exception('Yetersiz bakiye.');

    // Tahsilat ve bilet
    $pdo->prepare('UPDATE users SET credit=credit-? WHERE id=?')->execute([$price, $u['id']]);
    $pdo->prepare('INSERT INTO wallet_tx(user_id,amount,type,reason) VALUES(?,?,"debit","Bilet satın alma")')->execute([$u['id'], $price]);
    $pdo->prepare('INSERT INTO tickets(user_id,trip_id,seat_no,price_paid) VALUES(?,?,?,?)')->execute([$u['id'], $trip_id, $seat_no, $price]);

    $pdo->commit();
    audit('buy_success', ['trip_id'=>$trip_id, 'seat_no'=>$seat_no, 'amount'=>$price, 'coupon'=>$coupon ?: null]);
    redirect('my-tickets');
  } catch (Throwable $e) {
    $pdo->rollBack();
    audit('buy_fail', ['trip_id'=>$trip_id, 'seat_no'=>$seat_no, 'coupon'=>$coupon ?: null, 'err'=>$e->getMessage()]);
    redirect('trip', ['id'=>$trip_id, 'error' => $e->getMessage()]);
  }
  break;

case 'admin-users':
  $u=require_role('admin');
  $pdo=db();
  $users=$pdo->query('SELECT id,name,email,role,credit,created_at,COALESCE(active,1) AS active FROM users ORDER BY role DESC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
  $active_admins=(int)$pdo->query('SELECT COUNT(*) FROM users WHERE role="admin" AND COALESCE(active,1)=1')->fetchColumn();
  render('admin/users',['users'=>$users,'active_admins'=>$active_admins,'u'=>$u]);
  break;

case 'admin-user-toggle':
  $u=require_role('admin');
  if($_SERVER['REQUEST_METHOD']!=='POST') redirect('admin-users');
  $pdo=db();
  $id=(int)($_POST['id']??0);
  if($id<=0) redirect('admin-users',['error'=>'Geçersiz kullanıcı']);

  $st=$pdo->prepare('SELECT id,name,email,role,COALESCE(active,1) AS active FROM users WHERE id=?');
  $st->execute([$id]);
  $usr=$st->fetch(PDO::FETCH_ASSOC);
  if(!$usr) redirect('admin-users',['error'=>'Kullanıcı bulunamadı']);

  if((int)$usr['id']===(int)$u['id'] && (int)$usr['active']===1){
    redirect('admin-users',['error'=>'Kendi admin hesabını pasifleştiremezsin']);
  }
  if($usr['role']==='admin' && (int)$usr['active']===1){
    $cnt=(int)$pdo->query('SELECT COUNT(*) FROM users WHERE role="admin" AND COALESCE(active,1)=1')->fetchColumn();
    if($cnt<=1) redirect('admin-users',['error'=>'Son aktif admin pasifleştirilemez']);
  }

  $new=(int)!((int)$usr['active']);
  $pdo->prepare('UPDATE users SET active=? WHERE id=?')->execute([$new,$id]);
  redirect('admin-users',['ok'=>1,'msg'=> $new? 'Hesap aktifleştirildi':'Hesap pasifleştirildi' ]);
  break;

  
case 'apply-coupon':
  $trip_id = (int)($_GET['trip_id'] ?? 0);
  $code = strtoupper(trim($_GET['code'] ?? ''));
  $code = preg_replace('/[^A-Z0-9]/','', $code);

  $pdo = db();
  $st = $pdo->prepare('SELECT firm_id, price FROM trips WHERE id=?');
  $st->execute([$trip_id]);
  $trip = $st->fetch(PDO::FETCH_ASSOC);

  header('Content-Type: application/json; charset=utf-8');

  if (!$trip) { echo json_encode(['ok'=>false,'message'=>'Sefer bulunamadı']); break; }

  $price = (float)$trip['price'];
  if ($code === '') {
    echo json_encode([
      'ok'=>true,
      'discount_percent'=>0,
      'price_original'=>$price,
      'price_discounted'=>$price,
      'price_original_fmt'=>money_fmt($price),
      'price_discounted_fmt'=>money_fmt($price),
      'message'=>''
    ]);
    break;
  }

  $cs = $pdo->prepare('SELECT * FROM coupons
                       WHERE code=? AND active=1
                         AND used_count < usage_limit
                         AND (expires_at IS NULL OR date(expires_at) >= date("now"))
                         AND (firm_id IS NULL OR firm_id=?)');
  $cs->execute([$code, (int)$trip['firm_id']]);
  $cp = $cs->fetch(PDO::FETCH_ASSOC);

  if (!$cp) {
    echo json_encode([
      'ok'=>false,
      'message'=>'Kupon geçersiz veya bu firmada geçerli değil',
      'price_original'=>$price,
      'price_original_fmt'=>money_fmt($price)
    ]);
    break;
  }

  $percent = max(1, min(90, (int)$cp['percent']));
  $new = $price * (100 - $percent) / 100.0;

  echo json_encode([
    'ok'=>true,
    'code'=>$code,
    'discount_percent'=>$percent,
    'price_original'=>$price,
    'price_discounted'=>$new,
    'price_original_fmt'=>money_fmt($price),
    'price_discounted_fmt'=>money_fmt($new)
  ]);
  break;

case 'my-tickets':
  $u=require_login();
  $pdo=db();
  $st=$pdo->prepare('SELECT tk.*,t.origin,t.destination,t.trip_date,t.departure_time FROM tickets tk JOIN trips t ON t.id=tk.trip_id WHERE tk.user_id=? ORDER BY tk.purchased_at DESC');$st->execute([$u['id']]);
  $tickets=$st->fetchAll(PDO::FETCH_ASSOC);
  render('tickets/my',['tickets'=>$tickets,'u'=>$u]);
  break;
  
case 'cancel-ticket':
  $u=require_login();
  if($_SERVER['REQUEST_METHOD']!=='POST'){ redirect('my-tickets'); break; }
  $id=(int)($_POST['id']??0);
  $pdo=db();
  $st=$pdo->prepare('SELECT tk.*,t.trip_date,t.departure_time FROM tickets tk JOIN trips t ON t.id=tk.trip_id WHERE tk.id=? AND tk.user_id=?');
  $st->execute([$id,$u['id']]);
  $tk=$st->fetch(PDO::FETCH_ASSOC);
  if(!$tk || $tk['status']!=='active'){ redirect('my-tickets'); break; }
  $dt=DateTime::createFromFormat('Y-m-d H:i',$tk['trip_date'].' '.$tk['departure_time']);
  $limit=(clone $dt)->modify('-1 hour'); $now=new DateTime('now');
  if($now>=$limit){ redirect('my-tickets',['error'=>'Kalkışa 1 saatten az kala iptal edilemez.']); break; }
  $pdo->beginTransaction();
  $pdo->prepare('UPDATE tickets SET status="canceled",canceled_at=datetime("now") WHERE id=?')->execute([$id]);
  $pdo->prepare('UPDATE users SET credit=credit+? WHERE id=?')->execute([$tk['price_paid'],$u['id']]);
  $pdo->prepare('INSERT INTO wallet_tx(user_id,amount,type,reason,ref_id) VALUES(?,?,"credit","Bilet iptal iadesi",?)')->execute([$u['id'],$tk['price_paid'],$id]);
  $pdo->commit(); audit('cancel_user',['ticket_id'=>$id]); redirect('my-tickets'); 
  break;


case 'admin':
  $u=require_role('admin');
  $pdo=db();

  if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['new_firm'])){
    $name=trim($_POST['new_firm']);
    if($name!==''){
      $pdo->beginTransaction();

      // 1) Firma ekle
      $pdo->prepare('INSERT INTO firms(name) VALUES(?)')->execute([$name]);
      $firm_id=(int)$pdo->lastInsertId();

      // 2) Slug/email üret
      $slug=strtolower($name);
      $slug=strtr($slug,['ç'=>'c','ğ'=>'g','ı'=>'i','ş'=>'s','ö'=>'o','ü'=>'u','Ç'=>'c','Ğ'=>'g','İ'=>'i','Ş'=>'s','Ö'=>'o','Ü'=>'u']);
      $slug=preg_replace('/[^a-z0-9]+/','',$slug);
      if($slug==='') $slug='firma'.substr((string)time(),-4);

      $email=$slug.'@local';
      $i=1;
      $chk=$pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?');
      while(true){
        $chk->execute([$email]);
        if((int)$chk->fetchColumn()===0) break;
        $email=$slug.$i.'@local'; $i++;
      }

      // 3) Parola ve kullanıcı
      $passPlain=$slug.'123';
      $pdo->prepare('INSERT INTO users(name,email,password_hash,role,credit) VALUES(?,?,?,?,0)')
          ->execute([$name.' Yetkilisi',$email,password_hash($passPlain,PASSWORD_DEFAULT),'firm_admin']);
      $user_id=(int)$pdo->lastInsertId();

      // 4) Firma-yetkili eşle
      $pdo->prepare('INSERT INTO firm_admins(user_id,firm_id) VALUES(?,?)')->execute([$user_id,$firm_id]);

      $pdo->commit();

      // Bilgileri ekrana taşımak için
      redirect('admin',['ok'=>1,'firm'=>$name,'fa_email'=>$email,'fa_pass'=>$passPlain]);
    }
  }

  $firms=$pdo->query('SELECT * FROM firms ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
  render('admin/index',['firms'=>$firms,'u'=>$u]);
  break;

case 'firm':
  $u=require_role('firm_admin');
  $pdo=db();
  $fid=$pdo->prepare('SELECT firm_id FROM firm_admins WHERE user_id=?');$fid->execute([$u['id']]);$firm_id=(int)$fid->fetchColumn();
  if(!$firm_id)redirect('home');
  if($_SERVER['REQUEST_METHOD']==='POST'){
    $origin=trim($_POST['origin']??'');$destination=trim($_POST['destination']??'');$trip_date=trim($_POST['trip_date']??'');$departure_time=trim($_POST['departure_time']??'');$price=(float)($_POST['price']??0);$seat_count=(int)($_POST['seat_count']??40);
    if($origin&&$destination&&$trip_date&&$departure_time&&$price>0){$pdo->prepare('INSERT INTO trips(firm_id,origin,destination,trip_date,departure_time,price,seat_count) VALUES(?,?,?,?,?,?,?)')->execute([$firm_id,$origin,$destination,$trip_date,$departure_time,$price,$seat_count]);}
  }
  $st=$pdo->prepare('SELECT * FROM trips WHERE firm_id=? ORDER BY trip_date DESC');$st->execute([$firm_id]);$trips=$st->fetchAll(PDO::FETCH_ASSOC);
  render('firm/index',['trips'=>$trips,'u'=>$u]);
  break;
case 'firm-trip-update':
  $u=require_role('firm_admin');
  $pdo=db();
  $fid=$pdo->prepare('SELECT firm_id FROM firm_admins WHERE user_id=?');$fid->execute([$u['id']]);$firm_id=(int)$fid->fetchColumn();
  if(!$firm_id)redirect('home');
  if($_SERVER['REQUEST_METHOD']==='POST'){
    $id=(int)($_POST['id']??0);
    $chk=$pdo->prepare('SELECT COUNT(*) FROM trips WHERE id=? AND firm_id=?');$chk->execute([$id,$firm_id]);
    if((int)$chk->fetchColumn()===1){
      $origin=trim($_POST['origin']??'');$destination=trim($_POST['destination']??'');$trip_date=trim($_POST['trip_date']??'');$departure_time=trim($_POST['departure_time']??'');$price=(float)($_POST['price']??0);$seat_count=(int)($_POST['seat_count']??40);
      if($origin!==''&&$destination!==''&&$trip_date!==''&&$departure_time!==''&&$price>0&&$seat_count>0){
        $pdo->prepare('UPDATE trips SET origin=?,destination=?,trip_date=?,departure_time=?,price=?,seat_count=? WHERE id=?')->execute([$origin,$destination,$trip_date,$departure_time,$price,$seat_count,$id]);
      }
    }
  }
  redirect('firm');
  break;

case 'firm-trip-delete':
  $u=require_role('firm_admin');
  $pdo=db();
  $fid=$pdo->prepare('SELECT firm_id FROM firm_admins WHERE user_id=?');$fid->execute([$u['id']]);$firm_id=(int)$fid->fetchColumn();
  if(!$firm_id)redirect('home');
  if($_SERVER['REQUEST_METHOD']==='POST'){
    $id=(int)($_POST['id']??0);
    $chk=$pdo->prepare('SELECT COUNT(*) FROM trips WHERE id=? AND firm_id=?');$chk->execute([$id,$firm_id]);
    if((int)$chk->fetchColumn()===1){
      $pdo->prepare('DELETE FROM trips WHERE id=?')->execute([$id]);
    }
  }
  redirect('firm');
  break;

case 'firm-tickets':
  $u=require_role('firm_admin');
  $pdo=db();
  $fid=$pdo->prepare('SELECT firm_id FROM firm_admins WHERE user_id=?');$fid->execute([$u['id']]);$firm_id=(int)$fid->fetchColumn();
  if(!$firm_id) redirect('home');
  $st=$pdo->prepare('SELECT tk.*,u.name AS user_name,u.email AS user_email,t.origin,t.destination,t.trip_date,t.departure_time 
                     FROM tickets tk 
                     JOIN trips t ON t.id=tk.trip_id 
                     JOIN users u ON u.id=tk.user_id 
                     WHERE t.firm_id=? 
                     ORDER BY t.trip_date DESC,t.departure_time DESC, tk.id DESC');
  $st->execute([$firm_id]);
  $tickets=$st->fetchAll(PDO::FETCH_ASSOC);
  render('firm/tickets',['tickets'=>$tickets,'u'=>$u]);
  break;

case 'firm-ticket-cancel':
  $u=require_role('firm_admin');
  if($_SERVER['REQUEST_METHOD']!=='POST') redirect('firm-tickets');
  $pdo=db();
  $fid=$pdo->prepare('SELECT firm_id FROM firm_admins WHERE user_id=?');$fid->execute([$u['id']]);$firm_id=(int)$fid->fetchColumn();
  if(!$firm_id) redirect('home');
  $id=(int)($_POST['id']??0);
  $st=$pdo->prepare('SELECT tk.*,u.id AS u_id,t.trip_date,t.departure_time,t.firm_id FROM tickets tk JOIN trips t ON t.id=tk.trip_id JOIN users u ON u.id=tk.user_id WHERE tk.id=?');
  $st->execute([$id]);
  $tk=$st->fetch(PDO::FETCH_ASSOC);
  if(!$tk || (int)$tk['firm_id']!==$firm_id) redirect('firm-tickets');
  if($tk['status']!=='active') redirect('firm-tickets');
  $dt=DateTime::createFromFormat('Y-m-d H:i',$tk['trip_date'].' '.$tk['departure_time']);
  $limit=(clone $dt)->modify('-1 hour');$now=new DateTime('now');
  if($now>=$limit) redirect('firm-tickets',['error'=>'Kalkışa 1 saatten az kala iptal edilemez.']);
  $pdo->beginTransaction();
  $pdo->prepare('UPDATE tickets SET status="canceled",canceled_at=datetime("now") WHERE id=?')->execute([$id]);
  $pdo->prepare('UPDATE users SET credit=credit+? WHERE id=?')->execute([$tk['price_paid'],$tk['u_id']]);
  $pdo->prepare('INSERT INTO wallet_tx(user_id,amount,type,reason,ref_id) VALUES(?,?,"credit","Firma iptal iadesi",?)')->execute([$tk['u_id'],$tk['price_paid'],$id]);
  $pdo->commit(); audit('cancel_firm',['ticket_id'=>$id]); redirect('firm-tickets',['ok'=>1]);
  break;


case 'admin-firm-update':
  $u=require_role('admin');
  if($_SERVER['REQUEST_METHOD']!=='POST') redirect('admin');
  $pdo=db();
  $id=(int)($_POST['id']??0);
  $name=trim($_POST['name']??'');
  if($id>0 && $name!==''){
    $pdo->prepare('UPDATE firms SET name=? WHERE id=?')->execute([$name,$id]);
    redirect('admin',['ok'=>1,'msg'=>'Firma güncellendi']);
  }
  redirect('admin',['error'=>'Geçersiz veri']);
  break;

case 'admin-firm-delete':
  $u=require_role('admin');
  if($_SERVER['REQUEST_METHOD']!=='POST') redirect('admin');
  $pdo=db();
  $id=(int)($_POST['id']??0);
  if($id>0){
    $pdo->prepare('DELETE FROM firms WHERE id=?')->execute([$id]);
    redirect('admin',['ok'=>1,'msg'=>'Firma silindi']);
  }
  redirect('admin',['error'=>'Geçersiz firma']);
  break;

case 'admin-firm-add-admin':
  $u=require_role('admin');
  if($_SERVER['REQUEST_METHOD']!=='POST') redirect('admin');
  $pdo=db();
  $firm_id=(int)($_POST['firm_id']??0);
  $firm=$pdo->prepare('SELECT * FROM firms WHERE id=?');$firm->execute([$firm_id]);$f=$firm->fetch(PDO::FETCH_ASSOC);
  if(!$f) redirect('admin',['error'=>'Firma bulunamadı']);

  $email=trim($_POST['email']??'');
  $pass=trim($_POST['password']??'');

  if($email===''){
    $slug=strtolower($f['name']);
    $slug=strtr($slug,['ç'=>'c','ğ'=>'g','ı'=>'i','ş'=>'s','ö'=>'o','ü'=>'u','Ç'=>'c','Ğ'=>'g','İ'=>'i','Ş'=>'s','Ö'=>'o','Ü'=>'u']);
    $slug=preg_replace('/[^a-z0-9]+/','',$slug);
    if($slug==='') $slug='firma'.substr((string)time(),-4);
    $email=$slug.'@local';
    $i=1; $chk=$pdo->prepare('SELECT COUNT(*) FROM users WHERE email=?');
    while(true){ $chk->execute([$email]); if((int)$chk->fetchColumn()===0) break; $email=$slug.$i.'@local'; $i++; }
    $pass=$slug.'123';
    $pdo->prepare('INSERT INTO users(name,email,password_hash,role,credit) VALUES(?,?,?,?,0)')
        ->execute([$f['name'].' Yetkilisi',$email,password_hash($pass,PASSWORD_DEFAULT),'firm_admin']);
    $uid=(int)$pdo->lastInsertId();
  } else {
    $s=$pdo->prepare('SELECT * FROM users WHERE email=?');$s->execute([$email]);$urow=$s->fetch(PDO::FETCH_ASSOC);
    if(!$urow){
      if(strlen($pass)<6) redirect('admin',['error'=>'Parola en az 6 karakter']);
      $pdo->prepare('INSERT INTO users(name,email,password_hash,role,credit) VALUES(?,?,?,?,0)')
          ->execute([$f['name'].' Yetkilisi',$email,password_hash($pass,PASSWORD_DEFAULT),'firm_admin']);
      $uid=(int)$pdo->lastInsertId();
    } else {
      if($urow['role']!=='firm_admin') redirect('admin',['error'=>'Kullanıcı rolü firm_admin değil']);
      $uid=(int)$urow['id'];
    }
  }

  $ex=$pdo->prepare('SELECT firm_id FROM firm_admins WHERE user_id=?');$ex->execute([$uid]);$cur=$ex->fetchColumn();
  if($cur && (int)$cur!==$firm_id) redirect('admin',['error'=>'Kullanıcı başka firmaya atanmış']);

  $pdo->prepare('INSERT OR REPLACE INTO firm_admins(user_id,firm_id) VALUES(?,?)')->execute([$uid,$firm_id]);

  redirect('admin',['ok'=>1,'msg'=>'Yetkili eklendi','fa_email'=>$email,'fa_pass'=>$pass]);
  break;

case 'admin-firm-remove-admin':
  $u=require_role('admin');
  if($_SERVER['REQUEST_METHOD']!=='POST') redirect('admin');
  $pdo=db();
  $firm_id=(int)($_POST['firm_id']??0);
  $user_id=(int)($_POST['user_id']??0);
  if($firm_id>0 && $user_id>0){
    $chk=$pdo->prepare('SELECT COUNT(*) FROM firm_admins WHERE firm_id=? AND user_id=?');
    $chk->execute([$firm_id,$user_id]);
    if((int)$chk->fetchColumn()===1){
      $pdo->prepare('DELETE FROM firm_admins WHERE firm_id=? AND user_id=?')->execute([$firm_id,$user_id]);
      redirect('admin',['ok'=>1,'msg'=>'Yetkili kaldırıldı']);
    }
  }
  redirect('admin',['error'=>'Kayıt bulunamadı']);
  break;
  
case 'firm-coupons':
  $u = require_role('firm_admin');
  $pdo = db();
  $fid = $pdo->prepare('SELECT firm_id FROM firm_admins WHERE user_id=?'); $fid->execute([$u['id']]);
  $firm_id = (int)$fid->fetchColumn();
  if(!$firm_id) redirect('home');

  if($_SERVER['REQUEST_METHOD']==='POST'){
    $act = $_POST['action'] ?? '';
    if($act==='create'){
      $code = strtoupper(trim($_POST['code'] ?? ''));
      $percent = (int)($_POST['percent'] ?? 0);
      $limit = (int)($_POST['usage_limit'] ?? 1);
      $exp = trim($_POST['expires_at'] ?? '');
      if($code!=='' && $percent>=1 && $percent<=90 && $limit>=1){
        $pdo->prepare('INSERT INTO coupons(code,percent,usage_limit,used_count,expires_at,active,firm_id) VALUES(?,?,?,?,?,1,?)')
            ->execute([$code,$percent,$limit,0,$exp!==''?$exp:null,$firm_id]);
      }
    } elseif($act==='update'){
      $id = (int)($_POST['id'] ?? 0);
      $percent = (int)($_POST['percent'] ?? 0);
      $limit = (int)($_POST['usage_limit'] ?? 1);
      $exp = trim($_POST['expires_at'] ?? '');
      if($id>0 && $percent>=1 && $percent<=90 && $limit>=1){
        $pdo->prepare('UPDATE coupons SET percent=?, usage_limit=?, expires_at=? WHERE id=? AND firm_id=?')
            ->execute([$percent,$limit,$exp!==''?$exp:null,$id,$firm_id]);
      }
    } elseif($act==='toggle'){
      $id = (int)($_POST['id'] ?? 0);
      if($id>0){
        $a = $pdo->prepare('SELECT active FROM coupons WHERE id=? AND firm_id=?'); $a->execute([$id,$firm_id]);
        $cur = $a->fetchColumn();
        if($cur!==false){
          $pdo->prepare('UPDATE coupons SET active=? WHERE id=? AND firm_id=?')->execute([(int)!$cur,$id,$firm_id]);
        }
      }
    } elseif($act==='delete'){
      $id = (int)($_POST['id'] ?? 0);
      if($id>0){
        $pdo->prepare('DELETE FROM coupons WHERE id=? AND firm_id=?')->execute([$id,$firm_id]);
      }
    }
    redirect('firm-coupons');
  } else {
    $coupons = $pdo->prepare('SELECT * FROM coupons WHERE firm_id=? ORDER BY id DESC');
    $coupons->execute([$firm_id]);
    $rows = $coupons->fetchAll(PDO::FETCH_ASSOC);
    render('firm/coupons',['coupons'=>$rows]);
  }
  break;
 

case 'ticket-pdf':
  $u=require_login();
  $id=(int)($_GET['id']??0);
  $pdo=db();
  $st=$pdo->prepare('SELECT tk.*,t.origin,t.destination,t.trip_date,t.departure_time,f.name AS firm_name FROM tickets tk JOIN trips t ON t.id=tk.trip_id JOIN firms f ON f.id=t.firm_id WHERE tk.id=? AND tk.user_id=?');
  $st->execute([$id,$u['id']]);
  $tk=$st->fetch(PDO::FETCH_ASSOC);
  if(!$tk){redirect('my-tickets');}
  if(!is_file(BASE_PATH.'/vendor/autoload.php')){http_response_code(500);exit('dompdf eksik');}
  require_once BASE_PATH.'/vendor/autoload.php';
  $opts=new Dompdf\Options(); 
  $opts->set('isRemoteEnabled', true); 
  $opts->set('defaultFont','DejaVu Sans');
  $pdf=new Dompdf\Dompdf($opts);
  $html='
  <html><head><meta charset="UTF-8"><style>
  body{font-family:"DejaVu Sans",sans-serif;font-size:12pt}
  .box{border:1px solid #222;padding:16px}
  h1{font-size:18pt;margin:0 0 10px}
  table{width:100%;border-collapse:collapse}
  td{padding:6px 0}
  .muted{color:#666}
  </style></head><body>
  <div class="box">
    <h1>Bilet</h1>
    <table>
      <tr><td><strong>Firma:</strong> '.htmlspecialchars($tk['firm_name'],ENT_QUOTES,'UTF-8').'</td><td class="muted">'.htmlspecialchars($tk['purchased_at'],ENT_QUOTES,'UTF-8').'</td></tr>
      <tr><td><strong>Güzergâh:</strong> '.htmlspecialchars($tk['origin'].' → '.$tk['destination'],ENT_QUOTES,'UTF-8').'</td><td><strong>Koltuk:</strong> '.(int)$tk['seat_no'].'</td></tr>
      <tr><td><strong>Tarih/Saat:</strong> '.htmlspecialchars($tk['trip_date'].' '.$tk['departure_time'],ENT_QUOTES,'UTF-8').'</td><td><strong>Durum:</strong> '.htmlspecialchars($tk['status'],ENT_QUOTES,'UTF-8').'</td></tr>
      <tr><td><strong>Ödenen:</strong> '.htmlspecialchars(number_format((float)$tk['price_paid'],2,',','.').' ₺',ENT_QUOTES,'UTF-8').'</td><td></td></tr>
      <tr><td colspan="2" class="muted">Bilet No: '.(int)$tk['id'].' • Kullanıcı: '.(int)$tk['user_id'].'</td></tr>
    </table>
  </div>
  </body></html>';
  $pdf->loadHtml($html,'UTF-8');
  $pdf->setPaper('A4','portrait');
  $pdf->render();
  $pdf->stream('bilet-'.(int)$tk['id'].'.pdf',['Attachment'=>true]);
  exit;


default:
  http_response_code(404);echo '<h1>404</h1>';
}
?>