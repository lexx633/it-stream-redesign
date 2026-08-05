<?php
// Разовая настройка пароля редактора. Запускается на сервере по SSH и удаляется.
// Пароль генерится здесь же — в чат/логи Claude он не попадает.
$home = getenv('HOME');
$hashFile  = $home . '/data/.it-win-admin.pass';
$plainFile = $home . '/data/it-win-admin-password.txt';

if (is_file($hashFile) && trim(file_get_contents($hashFile)) !== '') {
  echo "ALREADY_SET (пароль уже настроен, не трогаю)\n";
  exit;
}

$pass = bin2hex(random_bytes(9));            // 18 hex-символов
$hash = password_hash($pass, PASSWORD_DEFAULT);

file_put_contents($hashFile, $hash);
chmod($hashFile, 0600);

file_put_contents($plainFile,
  "Пароль редактора it-win.ru (admin.html):\n\n    $pass\n\n" .
  "Введите его в админке в поле «Пароль».\n" .
  "Сменить пароль: перезапишите ~/data/.it-win-admin.pass новым хэшем\n" .
  "  php -r 'echo password_hash(\"НОВЫЙ\", PASSWORD_DEFAULT);' > ~/data/.it-win-admin.pass\n");
chmod($plainFile, 0600);

echo "PASS_SET ok (len=" . strlen($pass) . ", hash_len=" . strlen($hash) . ")\n";
echo "plaintext -> $plainFile (chmod 600)\n";
