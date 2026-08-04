<?php
// Приём заявок из всплывающих форм (попапов) сайта it-win.ru.
// POST { name, phone, comment, page, url } -> письмо на info@it-win.ru + лог-бэкап вне веб-корня.
// Обязательное поле — только телефон (см. валидацию на клиенте и здесь же на сервере).
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function fail($code, $msg){
  http_response_code($code);
  echo json_encode(array('ok' => false, 'error' => $msg), JSON_UNESCAPED_UNICODE);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'only POST');

function clean_line($s){ return trim(str_replace(array("\r", "\n"), ' ', (string)$s)); }

$name    = clean_line($_POST['name'] ?? '');
$phone   = clean_line($_POST['phone'] ?? '');
$comment = trim((string)($_POST['comment'] ?? ''));
$page    = clean_line($_POST['page'] ?? '');
$url     = clean_line($_POST['url'] ?? '');

$digits = preg_replace('/\D/', '', $phone);
if (strlen($digits) < 10) fail(400, 'phone required');

// лог-бэкап вне веб-корня — на случай если mail() на хостинге не долетит
$LOG_DIR = dirname(dirname(__DIR__)) . '/data';
if (is_dir($LOG_DIR) && is_writable($LOG_DIR)) {
  $logLine = date('Y-m-d H:i:s') . " | " . ($name !== '' ? $name : '-') . " | $phone | "
    . str_replace("\n", ' ', $comment) . " | $page | $url\n";
  @file_put_contents($LOG_DIR . '/leads.log', $logLine, FILE_APPEND | LOCK_EX);
}

$to = 'info@it-win.ru';
$subject = '=?UTF-8?B?' . base64_encode('Заявка с сайта it-win.ru' . ($page !== '' ? " — $page" : '')) . '?=';

$body  = "Имя: " . ($name !== '' ? $name : '-') . "\n";
$body .= "Телефон: $phone\n";
$body .= "Комментарий: " . ($comment !== '' ? $comment : '-') . "\n";
$body .= "Страница: " . ($page !== '' ? $page : '-') . "\n";
$body .= "URL: " . ($url !== '' ? $url : '-') . "\n";
$body .= "Дата: " . date('Y-m-d H:i:s') . "\n";

$headers  = "From: no-reply@it-win.ru\r\n";
$headers .= "Reply-To: no-reply@it-win.ru\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

@mail($to, $subject, $body, $headers);

// не роняем UX даже если mail() молча не сработал — лид уже в логе
echo json_encode(array('ok' => true), JSON_UNESCAPED_UNICODE);
