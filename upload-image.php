<?php
// Универсальная загрузка картинки для лендинга it-win.ru.
// POST multipart/form-data: pass, file, kind (необязательно).
//   kind=logo    — фиксированное имя uploads/logo.<ext>, перезаписывает предыдущую версию.
//   kind=gallery (по умолчанию) — уникальное имя, старые файлы не трогает
//   (аватары отзывов, произвольные картинки из медиатеки).
// Пароль — тот же хэш, что проверяет save.php.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$PASS_HASH_FILE = dirname(dirname(__DIR__)) . '/data/.it-win-admin.pass';
$UPLOAD_DIR = __DIR__ . '/uploads';
$MAX_BYTES = 2 * 1024 * 1024; // 2 MB
// SVG сознательно не разрешаем — может нести <script>, тут не нужно.
$ALLOWED = array(
  'image/png'  => 'png',
  'image/jpeg' => 'jpg',
  'image/webp' => 'webp',
);

function fail($code, $msg){
  http_response_code($code);
  echo json_encode(array('ok' => false, 'error' => $msg), JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'only POST');

$pass = isset($_POST['pass']) ? (string)$_POST['pass'] : '';
$hash = is_file($PASS_HASH_FILE) ? trim(file_get_contents($PASS_HASH_FILE)) : '';
if ($hash === '') fail(500, 'password not configured on server');
if (!password_verify($pass, $hash)) fail(401, 'wrong password');

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) fail(400, 'no file');
$f = $_FILES['file'];
if ($f['size'] > $MAX_BYTES) fail(413, 'file too large (max 2MB)');

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $f['tmp_name']) : '';
if ($finfo) finfo_close($finfo);
if (!isset($ALLOWED[$mime])) fail(400, 'unsupported type: ' . $mime);
$ext = $ALLOWED[$mime];

if (!is_dir($UPLOAD_DIR)) { @mkdir($UPLOAD_DIR, 0755, true); }
if (!is_dir($UPLOAD_DIR) || !is_writable($UPLOAD_DIR)) fail(500, 'upload dir not writable');

$kind = isset($_POST['kind']) ? (string)$_POST['kind'] : 'gallery';

if ($kind === 'logo') {
  // старые версии лого с другим расширением больше не нужны
  foreach (glob($UPLOAD_DIR . '/logo.*') as $old) { @unlink($old); }
  $name = 'logo.' . $ext;
} else {
  $name = 'img-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
}

$target = $UPLOAD_DIR . '/' . $name;
if (!move_uploaded_file($f['tmp_name'], $target)) fail(500, 'move failed');
@chmod($target, 0644);

$v = $kind === 'logo' ? ('?v=' . time()) : '';
echo json_encode(array('ok' => true, 'url' => '/uploads/' . $name . $v), JSON_UNESCAPED_UNICODE);
