<?php
declare(strict_types=1);
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);
session_start();
date_default_timezone_set('Europe/Istanbul');
define('BASE_PATH',dirname(__DIR__));
define('DATA_PATH',BASE_PATH.'/data');
define('DB_FILE',DATA_PATH.'/database.sqlite');
require_once BASE_PATH.'/app/helpers.php';
require_once BASE_PATH.'/app/db.php';
require_once BASE_PATH.'/app/auth.php';
require_once BASE_PATH.'/app/csrf.php';
require_once BASE_PATH.'/app/security.php';
sec_headers();
if(!is_dir(DATA_PATH))mkdir(DATA_PATH,0777,true);
$pdo=db();
migrate($pdo);
seed($pdo);
?>
