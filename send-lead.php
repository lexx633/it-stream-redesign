<?php
// Приём заявок из всплывающих форм (попапов) сайта it-win.ru.
// POST { name, phone, comment, page, url } -> письмо на адрес(а) из content.json (contacts.leads_email) + лог-бэкап вне веб-корня.
// Обязательное поле — только телефон (см. валидацию на клиенте и здесь же на сервере).
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function fail($code, $msg){
  http_response_code($code);
  echo json_encode(array('ok' => false, 'error' => $msg), JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'only POST');

// honeypot: скрытое от людей CSS-ом поле "website" — если заполнено, значит
// заявку прислал бот, а не человек. Отвечаем "успехом" молча, письмо не шлём
// и в лог не пишем, чтобы бот не понял, что его отсеяли, и не менял тактику.
if (trim((string)($_POST['website'] ?? '')) !== '') {
  echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);
  exit;
}

function clean_line($s){ return trim(str_replace(array("\r", "\n"), ' ', (string)$s)); }

$name    = clean_line($_POST['name'] ?? '');
$phone   = clean_line($_POST['phone'] ?? '');
$comment = trim((string)($_POST['comment'] ?? ''));
$page    = clean_line($_POST['page'] ?? '');
$url     = clean_line($_POST['url'] ?? '');
$source  = clean_line($_POST['source'] ?? '');
// необязательные поля калькулятора «Рассчитать стоимость» (сейчас только /surveillance,
// но обрабатываются универсально — на будущее для других форм-калькуляторов)
$objectType = clean_line($_POST['object_type'] ?? '');
$area       = clean_line($_POST['area'] ?? '');
$cameras    = clean_line($_POST['cameras'] ?? '');
$city       = clean_line($_POST['city'] ?? '');
// необязательные поля калькулятора «Рассчитать стоимость защиты» (/security)
$company  = clean_line($_POST['company'] ?? '');
$teamSize = clean_line($_POST['team_size'] ?? '');
$task     = clean_line($_POST['task'] ?? '');

$digits = preg_replace('/\D/', '', $phone);
if (strlen($digits) < 10) fail(400, 'phone required');

// лог-бэкап вне веб-корня — на случай если mail() на хостинге не долетит
$LOG_DIR = dirname(dirname(__DIR__)) . '/data';
if (is_dir($LOG_DIR) && is_writable($LOG_DIR)) {
  $logLine = date('Y-m-d H:i:s') . " | " . ($name !== '' ? $name : '-') . " | $phone | "
    . str_replace("\n", ' ', $comment) . " | $page | $url | $source | $objectType | $area | $cameras | $city | $company | $teamSize | $task\n";
  @file_put_contents($LOG_DIR . '/leads.log', $logLine, FILE_APPEND | LOCK_EX);
}

// адрес(а) получателя — редактируются в админке («Переменные» → «Куда шлём заявки с форм»),
// хранятся в content.json как contacts.leads_email; несколько адресов — через запятую
$to = 'info@it-stream.ru';
$CONTENT_FILE = __DIR__ . '/content.json';
if (is_readable($CONTENT_FILE)) {
  $contentJson = json_decode(file_get_contents($CONTENT_FILE), true);
  if (is_array($contentJson) && !empty($contentJson['contacts.leads_email'])) {
    $to = $contentJson['contacts.leads_email'];
  }
}
$recipients = array_filter(array_map('trim', explode(',', $to)));
if (empty($recipients)) $recipients = array('info@it-stream.ru');

$subject = '=?UTF-8?B?' . base64_encode('Заявка с сайта it-win.ru' . ($page !== '' ? " — $page" : '')) . '?=';

$body  = "Имя: " . ($name !== '' ? $name : '-') . "\n";
$body .= "Телефон: $phone\n";
$body .= "Комментарий: " . ($comment !== '' ? $comment : '-') . "\n";
$body .= "Страница: " . ($page !== '' ? $page : '-') . "\n";
$body .= "URL: " . ($url !== '' ? $url : '-') . "\n";
$body .= "Откуда (раздел/кнопка): " . ($source !== '' ? $source : '-') . "\n";
if ($objectType !== '' || $area !== '' || $cameras !== '' || $city !== '') {
  $body .= "Тип объекта: " . ($objectType !== '' ? $objectType : '-') . "\n";
  $body .= "Площадь: " . ($area !== '' ? $area : '-') . "\n";
  $body .= "Камер (ориентировочно): " . ($cameras !== '' ? $cameras : '-') . "\n";
  $body .= "Город: " . ($city !== '' ? $city : '-') . "\n";
}
if ($company !== '' || $teamSize !== '' || $task !== '') {
  $body .= "Компания: " . ($company !== '' ? $company : '-') . "\n";
  $body .= "Размер команды: " . ($teamSize !== '' ? $teamSize : '-') . "\n";
  $body .= "Основная задача: " . ($task !== '' ? $task : '-') . "\n";
}
$body .= "Дата: " . date('Y-m-d H:i:s') . "\n";

$headers  = "From: no-reply@it-stream.ru\r\n";
$headers .= "Reply-To: no-reply@it-stream.ru\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// каждому получателю — отдельное письмо (в To только он сам, второй адресат не виден)
foreach ($recipients as $rcpt) {
  @mail($rcpt, $subject, $body, $headers);
}

// не роняем UX даже если mail() молча не сработал — лид уже в логе
echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);
