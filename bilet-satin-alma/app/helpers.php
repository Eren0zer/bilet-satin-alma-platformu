<?php
function redirect(string $r,array $p=[]):never{$q=http_build_query(array_merge(['r'=>$r],$p));header('Location: index.php?'.$q);exit;}
function render(string $v,array $p=[]):void{extract($p,EXTR_SKIP);$f=BASE_PATH.'/views/'.$v.'.php';include BASE_PATH.'/views/layout/header.php';if(is_file($f))include $f;include BASE_PATH.'/views/layout/footer.php';}
function e($v){return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
function post($k,$d=null){return $_POST[$k]??$d;}
function getv($k,$d=null){return $_GET[$k]??$d;}
function money_fmt($a){return number_format((float)$a,2,',','.').' ₺';}
?>