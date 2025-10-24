<?php
function csrf_get_token(): string {
  if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
  return $_SESSION['csrf_token'];
}
function csrf_field(): string {
  return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars(csrf_get_token(),ENT_QUOTES,'UTF-8').'">';
}
?>
