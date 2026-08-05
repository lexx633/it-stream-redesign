<?php
// Мини-эндпоинт сохранения контента лендинга it-win.ru.
// Режим 1 — POST {pass, data}: атомарно перезаписывает content.json (index.html).
// Режим 2 — POST {pass, page, html}: атомарно перезаписывает одну из внутренних
// статических страниц (page — из белого списка ниже), html — весь документ целиком.
// Пароль сверяется по хэшу вне веб-корня. Ставится рядом с index.html.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// хэш пароля лежит в приватном ~/data (вне веб-корня), наружу не отдаётся
$PASS_HASH_FILE = dirname(dirname(__DIR__)) . '/data/.it-win-admin.pass';
$JSON_TARGET = __DIR__ . '/content.json';
// белый список — только эти файлы можно перезаписать в режиме 2, ничего кроме них
$ALLOWED_PAGES = array('it-outsourcing.html', 'skud.html', 'sks.html', 'surveillance.html', 'security.html');

function fail($code, $msg){
  http_response_code($code);
  echo json_encode(array('ok' => false, 'error' => $msg), JSON_UNESCAPED_UNICODE);
  exit;
}

function atomic_write($target, $contents){
  $tmp = $target . '.tmp.' . getmypid();
  if (file_put_contents($tmp, $contents, LOCK_EX) === false) return false;
  if (!rename($tmp, $target)) { @unlink($tmp); return false; }
  return true;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'only POST');

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 4000000) fail(413, 'body too large');
$req = json_decode($raw, true);
if (!is_array($req)) fail(400, 'bad json');

$pass = isset($req['pass']) ? (string)$req['pass'] : '';
$hash = is_file($PASS_HASH_FILE) ? trim(file_get_contents($PASS_HASH_FILE)) : '';
if ($hash === '') fail(500, 'password not configured on server');
if (!password_verify($pass, $hash)) fail(401, 'wrong password');

// ---- режим 2: сохранение сырого HTML внутренней страницы ----
if (isset($req['page']) || isset($req['html'])) {
  $page = isset($req['page']) ? (string)$req['page'] : '';
  if (!in_array($page, $ALLOWED_PAGES, true)) fail(400, 'page not allowed');
  $html = isset($req['html']) ? (string)$req['html'] : '';
  if (strlen($html) < 200 || stripos($html, '<html') === false) fail(400, 'html looks invalid');
  $target = __DIR__ . '/' . $page;
  if (!atomic_write($target, $html)) fail(500, 'write failed');
  echo json_encode(array('ok' => true, 'bytes' => strlen($html)), JSON_UNESCAPED_UNICODE);
  exit;
}

// ---- режим 1: сохранение content.json (index.html) ----
if (!isset($req['data']) || !is_array($req['data'])) fail(400, 'no data');

$json = json_encode($req['data'],
  JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
if ($json === false) fail(500, 'json encode failed');
if (!atomic_write($JSON_TARGET, $json)) fail(500, 'write failed');

echo json_encode(array('ok' => true, 'bytes' => strlen($json)), JSON_UNESCAPED_UNICODE);
