<?php
// Legendary Vero quote form (quote.html): saves, emails the shop, forwards to GHL if a webhook is set.
// 1) saves every lead outside the web root, 2) emails an alert, 3) forwards to GHL if a webhook is set.
header('Content-Type: application/json');
header('X-Robots-Tag: noindex');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }

$cfgFile = __DIR__ . '/config.php';
$cfg = file_exists($cfgFile) ? include $cfgFile : [];

$raw = file_get_contents('php://input');
if (strlen($raw) > 20000) { http_response_code(413); echo '{"ok":false}'; exit; }
$data = json_decode($raw, true);
if (!is_array($data)) { http_response_code(400); echo '{"ok":false,"error":"bad json"}'; exit; }

// honeypot + minimum fields
if (!empty($data['website_url_hp'])) { echo '{"ok":true}'; exit; }
$phone = trim((string)($data['phone'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
if ($phone === '' && $email === '') { http_response_code(422); echo '{"ok":false,"error":"missing contact"}'; exit; }

$clean = [];
foreach ($data as $k => $v) {
  if (!preg_match('/^[a-z0-9_]{1,40}$/i', $k)) continue;
  $clean[$k] = is_scalar($v) ? mb_substr((string)$v, 0, 2000) : null;
}
$clean['received_at'] = gmdate('c');
$clean['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
$clean['user_agent'] = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300);

// 1) save
$dir = dirname(__DIR__, 2) . '/leads';
if (!is_dir($dir)) @mkdir($dir, 0750, true);
@file_put_contents($dir . '/leads.jsonl', json_encode($clean, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);

// 2) email
$cfg['notify_email'] = $cfg['notify_email'] ?? ($cfg['office_email'] ?? 'win@legendaryvero.com');
if (!empty($cfg['notify_email'])) {
  $subject = 'New quote request: ' . ($clean['name'] ?? 'website') . ' (' . ($clean['service'] ?? 'form') . ')';
  $body = '';
  foreach ($clean as $k => $v) { $body .= str_pad($k, 18) . ': ' . $v . "\n"; }
  $from = $cfg['from_email'] ?? 'no-reply@legendaryvero.com';
  @mail($cfg['notify_email'], $subject, $body, "From: Legendary Vero Website <$from>\r\nContent-Type: text/plain; charset=UTF-8");
}

// 3) forward to GHL
$forwarded = false;
if (!empty($cfg['ghl_webhook_url'])) {
  $ch = curl_init($cfg['ghl_webhook_url']);
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($clean), CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
  curl_exec($ch);
  $forwarded = curl_getinfo($ch, CURLINFO_HTTP_CODE) < 300;
  curl_close($ch);
}

echo json_encode(['ok' => true, 'forwarded' => $forwarded]);
