<?php
// Список уже загруженных картинок (медиатека) для лендинга it-win.ru.
// POST {pass}. Пароль — тот же хэш, что проверяет save.php/upload-image.php.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$PASS_HASH_FILE = dirname(dirname(__DIR__)) . '/data/.it-win-admin.pass';
$UPLOAD_DIR = __DIR__ . '/uploads';

function fail($code, $msg){
  http_response_code($code);
  echo json_encode(array('ok' => false, 'error' => $msg), JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'only POST');

$raw = file_get_contents('php://input');
$req = json_decode($raw, true);
$pass = is_array($req) && isset($req['pass']) ? (string)$req['pass'] : '';
$hash = is_file($PASS_HASH_FILE) ? trim(file_get_contents($PASS_HASH_FILE)) : '';
if ($hash === '') fail(500, 'password not configured on server');
if (!password_verify($pass, $hash)) fail(401, 'wrong password');

$files = is_dir($UPLOAD_DIR) ? glob($UPLOAD_DIR . '/*.{png,jpg,jpeg,webp}', GLOB_BRACE) : array();
$items = array();
foreach ($files as $f) {
  $items[] = array('url' => '/uploads/' . basename($f), 'mtime' => filemtime($f));
}
usort($items, function($a, $b){ return $b['mtime'] - $a['mtime']; });

echo json_encode(array('ok' => true, 'items' => $items), JSON_UNESCAPED_UNICODE);
