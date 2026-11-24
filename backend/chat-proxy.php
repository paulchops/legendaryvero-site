<?php
/**
 * ASharp Agency — Chat Proxy v2 (knowledge-base powered)
 * ---------------------------------------------------------
 * Same job as v1 (keeps API keys off the page, books into GHL, alerts on-call),
 * but the AI now runs on the compiled knowledge base:
 *
 *   system = [ kb/shared_full.md   (identical for every client of the vertical → cached 1 hour, shared)
 *              kb/client_block.md  (this client's facts → cached 5 min)
 *              SESSION block       (channel + local time + office OPEN/CLOSED, computed here) ]
 *
 * Safety rules enforced in CODE (found by automated testing):
 *   - create_booking needs hazard_level; urgent_hazard / life_safety bookings are refused until
 *     alert_on_call has run in this conversation.
 *   - take_message needs name, phone and reason.
 *   - Replies that leak a placeholder ({{...}}, "client.x", "not set") are regenerated once, then replaced.
 *
 * Files next to this one:
 *   config.php               secrets + integration ids (unchanged from v1; new keys optional, see README-v2)
 *   kb/shared_full.md        from kb/build/_shared/<vertical>/
 *   kb/client_block.md       from kb/build/<client>/
 *   kb/client_runtime.json   from kb/build/<client>/  (name, phone, hours, timezone, plan...)
 *
 * The widget should send {messages, conversation_id}. With conversation_id the full conversation
 * (including tool calls) is kept server-side, outside the web root. Without it, v1 behaviour.
 * Revert: put chat-proxy-v1.php back as chat-proxy.php.
 */

header('Content-Type: application/json');
$config = require __DIR__ . '/config.php';

// ---------------- CORS (unchanged from v1) ----------------
$allowedOrigins = $config['allowed_origin'] ?? [];
if (is_string($allowedOrigins)) $allowedOrigins = [$allowedOrigins];
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($requestOrigin !== '' && in_array($requestOrigin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Test-Token, X-Test-Clock');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => 'POST only']); exit; }

// ---------------- Knowledge base ----------------
$kbDir = $config['kb_dir'] ?? (__DIR__ . '/kb');
$shared = @file_get_contents($kbDir . '/shared_full.md');
$clientBlock = @file_get_contents($kbDir . '/client_block.md');
$runtime = json_decode((string) @file_get_contents($kbDir . '/client_runtime.json'), true);
if ($shared === false || $clientBlock === false || !is_array($runtime)) {
    http_response_code(500);
    error_log('chat-proxy v2: knowledge base files missing in ' . $kbDir);
    echo json_encode(['error' => 'assistant not configured']);
    exit;
}
$tz = new DateTimeZone($runtime['timezone'] ?? ($config['timezone'] ?? 'America/New_York'));
$businessPhone = $runtime['phone'] ?? ($config['business_phone'] ?? '');

// ---------------- Test mode (tools/run_tests.py --live) ----------------
// Only when config.php has a 'test_token' AND the request sends the same X-Test-Token header:
// tools are simulated (no real GHL bookings, texts or calls) and the tool log is returned.
// Token may arrive as a header or in the JSON body (some hosts/CDNs strip custom headers).
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
$sentToken = (string) ($_SERVER['HTTP_X_TEST_TOKEN'] ?? ($input['test_token'] ?? ''));
$testMode = !empty($config['test_token']) && $sentToken !== '' && hash_equals((string) $config['test_token'], $sentToken);
$testClock = (string) ($_SERVER['HTTP_X_TEST_CLOCK'] ?? ($input['test_clock'] ?? ''));
$toolLog = [];

// ---------------- Storage (outside the web root) ----------------
$dataDir = $config['data_dir'] ?? (dirname($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . '/chat-data');
if (!is_dir($dataDir)) @mkdir($dataDir, 0750, true);

// ---------------- Rate limit: 40 requests / 10 min per IP ----------------
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
// Office IPs exempt from rate limit + demo daily cap (Paul 2026-10-13)
$exemptCidrs = ['207.141.78.0/27'];
$ipExempt = false;
$ipLong = ip2long($ip);
if ($ipLong !== false) foreach ($exemptCidrs as $c) { [$net, $bits] = explode('/', $c); $mask = -1 << (32 - (int) $bits); if ((($ipLong & $mask) === (ip2long($net) & $mask))) { $ipExempt = true; break; } }
$rlFile = $dataDir . '/rl_' . md5($ip) . '.json';
$now = time();
$hits = array_values(array_filter((array) json_decode((string) @file_get_contents($rlFile), true), fn($t) => $t > $now - 600));
if (!$testMode && !$ipExempt && count($hits) >= 40) { http_response_code(429); echo json_encode(['error' => 'Too many messages — please call us.']); exit; }
$hits[] = $now; @file_put_contents($rlFile, json_encode($hits));

// ---------------- Demo daily cap (demo sites only; runtime demo_limits) ----------------
// Per visitor (IP) and per site, counted per calendar day in the business timezone.
// Over the cap: a friendly reply (HTTP 200) pointing to the phone, so the chat window shows it normally.
if (!$testMode && !$ipExempt && !empty($runtime['demo'])) {
    $dl = (array) ($runtime['demo_limits'] ?? []);
    $capIp = (int) ($dl['chat_messages_per_visitor_per_day'] ?? 30);
    $capSite = (int) ($dl['chat_messages_per_site_per_day'] ?? 300);
    $dayFile = $dataDir . '/day_' . (new DateTime('now', $tz))->format('Ymd') . '.json';
    $fh = @fopen($dayFile, 'c+');
    $over = false;
    if ($fh && flock($fh, LOCK_EX)) {
        $day = json_decode((string) stream_get_contents($fh), true);
        if (!is_array($day)) $day = ['total' => 0, 'ips' => []];
        $k = md5($ip);
        $mine = (int) ($day['ips'][$k] ?? 0);
        if ($mine >= $capIp || (int) $day['total'] >= $capSite) { $over = true; $day['blocked'] = (int) ($day['blocked'] ?? 0) + 1; }
        else { $day['ips'][$k] = $mine + 1; $day['total'] = (int) $day['total'] + 1; }
        ftruncate($fh, 0); rewind($fh); fwrite($fh, json_encode($day)); fflush($fh); flock($fh, LOCK_UN);
    }
    if ($fh) fclose($fh);
    if ($over) {
        $es = strpos((string) ($_SERVER['HTTP_REFERER'] ?? ''), '/es/') !== false;
        $reply = $es
            ? "Gracias por probar nuestro chat de demostración. Llegó al límite de mensajes de hoy. Para seguir, llame al {$businessPhone} y hable con nuestra recepcionista con IA."
            : "Thanks for trying our demo chat! You've reached today's message limit. To keep going, call {$businessPhone} and talk to our AI receptionist.";
        echo json_encode(['reply' => $reply, 'booked' => false, 'limited' => true]); exit;
    }
}

// ---------------- Input & conversation state ----------------
$browserMessages = $input['messages'] ?? null;
if (!is_array($browserMessages) || count($browserMessages) === 0) {
    http_response_code(400); echo json_encode(['error' => 'messages array required']); exit;
}
$convId = $input['conversation_id'] ?? '';
$convFile = null;
$state = ['messages' => [], 'alerted' => false, 'booked' => false, 'contact_id' => null, 'channel' => 'chat'];
if (is_string($convId) && preg_match('/^[A-Za-z0-9-]{8,64}$/', $convId)) {
    $convFile = $dataDir . '/conv_' . $convId . '.json';
    $saved = json_decode((string) @file_get_contents($convFile), true);
    if (is_array($saved)) $state = array_merge($state, $saved);
    $last = end($browserMessages);
    $text = is_array($last) && ($last['role'] ?? '') === 'user' ? trim((string) ($last['content'] ?? '')) : '';
    if ($text === '') { http_response_code(400); echo json_encode(['error' => 'last message must be from the user']); exit; }
    $state['messages'][] = ['role' => 'user', 'content' => mb_substr($text, 0, 2000)];
} else {
    // v1 widget: text-only history from the browser
    foreach (array_slice($browserMessages, -40) as $m) {
        if (!in_array($m['role'] ?? '', ['user', 'assistant'], true)) continue;
        $state['messages'][] = ['role' => $m['role'], 'content' => mb_substr((string) ($m['content'] ?? ''), 0, 2000)];
    }
}
if (count($state['messages']) > 80) $state['messages'] = array_slice($state['messages'], -80);
while ($state['messages'] && ($state['messages'][0]['role'] ?? '') !== 'user') array_shift($state['messages']);

// ---------------- System prompt: shared (1h) + client (5m) + session ----------------
$nowLocal = new DateTime('now', $tz);
if ($testMode && $testClock !== '') {
    try { $nowLocal = new DateTime($testClock, $tz); } catch (Exception $e) { /* keep real time */ }
}
$status = office_status($runtime['hours'] ?? [], $nowLocal);
$session = "# SESSION\nChannel: chat. Local date/time: " . $nowLocal->format('l, F j, Y g:i A') . " (" . $tz->getName() . ").\n";
if ($status === 'OPEN') $session .= "Office status right now: OPEN (business hours).\n";
if ($status === 'CLOSED') $session .= "Office status right now: CLOSED (after hours). For anything that is not an emergency, say the office is closed right now and read the full hours sentence before offering the first available appointment.\n";
$session .= "Chat: reply in 2-4 short sentences, plain text, no markdown, at most one link.\n";
$session .= "This is a website CHAT, not a phone call: never say \"thanks for calling\", \"this call\" or \"on the phone with you\"; say \"thanks for reaching out\" or \"thanks for chatting\" instead. Use the knowledge base phone scripts as content only, reworded for chat.\n";
if (($runtime['plan'] ?? 'book') === 'answer') $session .= "This business takes messages only: never book; use take_message.\n";
$NO_ADDR = (($config['collect_address'] ?? true) === false) || (($runtime['booking']['collect_address'] ?? true) === false);
if ($NO_ADDR) $session .= "Customers come to the shop: never ask for a street address, city or ZIP code. Name and phone are enough to book.\n";
if (staff_list($config)) $session .= "Barbers: " . implode(', ', array_column(staff_list($config), 'name')) . ". Before checking availability, ask once: \"Do you have a favorite barber, or is the first available fine?\" Pass their choice as barber (a name from this list, or any) to check_availability and create_booking, and say the barber's name when you confirm.\n";
$session .= "Booking order: once the customer picks a time, go in this order, one question per message: (1) text consent, (2) email, (3) call create_booking right away and confirm. Never put the consent question in the same message as another question or a list of times.\n";
$session .= "Text consent: before create_booking, once the customer has chosen a time, ask in its own short message: \"Is it okay if we text appointment confirmations and reminders to this number? Msg & data rates may apply, and you can reply STOP anytime.\" (Spanish: \"¿Está bien si le enviamos mensajes de texto con la confirmación y recordatorios a este número? Pueden aplicar tarifas de mensajes y datos; puede responder STOP en cualquier momento.\") Ask this exactly ONCE per chat: if the customer already answered it earlier in this chat (yes or no), do not ask again; reuse that answer. Pass their answer as sms_consent. If they say no, confirm by email or say the office will call; never promise a text to someone who said no.\n";
$session .= "Email: right after the text-consent question, ask once for an email for the confirmation, in one short line: \"What's the best email for your booking confirmation?\" (Spanish: \"¿Cuál es el mejor correo electrónico para enviarle la confirmación?\") Never offer to skip it or say it is optional. Pass it to create_booking as email. Only if the customer refuses or says they don't have one, accept that politely, book without it and don't ask again.\n";
$session .= "Appointment times: the chat shows the windows you offer as tap buttons, so name each window clearly (day + window); do not number them or add instructions about buttons.\n";

$system = [
    ['type' => 'text', 'text' => $shared, 'cache_control' => ['type' => 'ephemeral', 'ttl' => '1h']],
    ['type' => 'text', 'text' => $clientBlock, 'cache_control' => ['type' => 'ephemeral']],
    ['type' => 'text', 'text' => $session],
];
$TECH = (string) ($runtime['tech_noun'] ?? 'technician');
$tools = tool_definitions(($runtime['plan'] ?? 'book') !== 'answer', $TECH, !$NO_ADDR);

// ---------------- Agent loop ----------------
$reply = '';
$offered = [];     // slots from check_availability this turn -> tap buttons in the chat
$replyParts = [];  // everything the AI says in this turn, including text sent alongside tool calls
try {
    for ($round = 0; $round < 6; $round++) {
        $resp = call_anthropic($config, $system, $state['messages'], $tools);
        $text = extract_text($resp);
        $issues = $text !== '' ? review_reply($text, $resp, $state, $status) : [];
        if ($issues) {
            // one rewrite with specific instructions (rules found by automated testing)
            $retrySystem = array_merge($system, [['type' => 'text', 'text' => "Rewrite your last draft before sending. Fix:\n- " . implode("\n- ", $issues)]]);
            $resp = call_anthropic($config, $retrySystem, $state['messages'], $tools);
            $text = extract_text($resp);
            log_event('rewrites', ['conv' => $convId, 'issues' => $issues]);
            // still naming the company the caller mentioned? remove it ourselves (deterministic)
            if (preg_match('/asharp/i', $text)) $text = trim(preg_replace('/\\bASharp(\\s+(Agency|Productions))?\\b/i', 'that company', $text));
            if (leaks($text)) $text = "That's a good question, and I don't want to guess. I'll have the team confirm that and get back to you. What's the best number to reach you?";
            // still skipping the after-hours notice? add it ourselves (deterministic)
            if ($text !== '' && closed_notice_missing($text, $state, $status)) {
                $es = preg_match('/[¿¡ñ]|\b(usted|cita|mañana|gracias|tengo)\b/iu', $text);
                $hs = $es ? ($runtime['hours_sentence_es'] ?? '') : ($runtime['hours_sentence'] ?? '');
                $lead = $es ? 'Nuestra oficina está cerrada en este momento.' : 'Our office is closed right now.';
                $text = trim($lead . ' ' . $hs . ' ' . $text);
            }
        }
        $content = $resp['content'] ?? [];
        // keep the (possibly cleaned) text in the stored history
        $content = array_values(array_filter($content, fn($b) => ($b['type'] ?? '') !== 'text'));
        if ($text !== '') array_unshift($content, ['type' => 'text', 'text' => $text]);
        if (!$content) $content = [['type' => 'text', 'text' => '...']];
        $state['messages'][] = ['role' => 'assistant', 'content' => $content];
        if ($text !== '') $replyParts[] = $text;

        $uses = array_values(array_filter($content, fn($b) => ($b['type'] ?? '') === 'tool_use'));
        if (!$uses) break;
        $results = [];
        foreach ($uses as $u) {
            $out = $testMode ? mock_tool($u['name'], (array) ($u['input'] ?? []), $state, $nowLocal, $runtime)
                             : run_tool($u['name'], (array) ($u['input'] ?? []), $state, $config, $runtime, $tz, $convId);
            $toolLog[] = ['name' => $u['name'], 'input' => $u['input'] ?? [], 'result' => $out];
            if ($u['name'] === 'check_availability' && !empty($out['slots'])) $offered = $out['slots'];
            $results[] = ['type' => 'tool_result', 'tool_use_id' => $u['id'], 'content' => json_encode($out)];
        }
        $state['messages'][] = ['role' => 'user', 'content' => $results];
    }
} catch (Exception $e) {
    error_log('chat-proxy v2: ' . $e->getMessage());
    save_state($convFile, $state);
    http_response_code(502);
    echo json_encode(['error' => 'AI service unavailable']);
    exit;
}
$reply = implode("\n\n", $replyParts);
if ($reply === '') $reply = "Thanks — I've passed that along to the team. Is there anything else I can help with?";
$reply = plain_text($reply);
if (!$testMode && ghl_ready($config) && !empty($state['contact_id'])) sync_transcript_note($config, $state, $reply);
save_state($convFile, $state);
$outJson = ['reply' => $reply, 'booked' => (bool) $state['booked']];
if ($offered && empty($state['booked'])) {
    $ch = slot_choices($offered, $reply);
    if ($ch) $outJson['choices'] = $ch;
}
if ($testMode) $outJson['tools'] = $toolLog;
echo json_encode($outJson);
exit;

// Tap buttons for the windows the reply actually names (day + start time); falls back to the first three.
function slot_choices(array $slots, string $reply): array {
    $es = (bool) preg_match('/[¿¡ñ]|\b(usted|cita|gracias|ventana|llegada|tengo|martes|lunes|jueves|viernes)\b/iu', $reply);
    $daysEs = ['Mon' => 'lun', 'Tue' => 'mar', 'Wed' => 'mié', 'Thu' => 'jue', 'Fri' => 'vie', 'Sat' => 'sáb', 'Sun' => 'dom'];
    $daysEsLong = ['Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles', 'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado', 'Sunday' => 'domingo'];
    $monEs = ['Jan' => 'ene', 'Feb' => 'feb', 'Mar' => 'mar', 'Apr' => 'abr', 'May' => 'may', 'Jun' => 'jun', 'Jul' => 'jul', 'Aug' => 'ago', 'Sep' => 'sep', 'Oct' => 'oct', 'Nov' => 'nov', 'Dec' => 'dic'];
    $hits = [];
    foreach ($slots as $s) {
        if (empty($s['chip']) || empty($s['slot_iso'])) continue;
        try { $d = new DateTime($s['slot_iso']); } catch (Exception $e) { continue; }
        $dayEn = $d->format('l'); $dayEsL = $daysEsLong[$dayEn] ?? '';
        $named = stripos($reply, $dayEn) !== false || ($dayEsL !== '' && mb_stripos($reply, $dayEsL) !== false)
              || stripos($reply, $d->format('M j')) !== false
              || (stripos($reply, 'tomorrow') !== false || mb_stripos($reply, 'mañana') !== false) && $d->format('Y-m-d') === (new DateTime('tomorrow', $d->getTimezone()))->format('Y-m-d')
              || (stripos($reply, 'today') !== false || mb_stripos($reply, 'hoy') !== false) && $d->format('Y-m-d') === (new DateTime('today', $d->getTimezone()))->format('Y-m-d');
        if ($named) $hits[] = $s;   // every window on a day the reply names
    }
    if (!$hits) $hits = array_slice($slots, 0, 3);
    $out = [];
    foreach (array_slice($hits, 0, 4) as $s) {
        $label = $s['chip'];
        $text = ($s['label'] ?? $label) . ' works for me.';
        if ($es) {
            $label = strtr($label, $daysEs + $monEs + ['noon' => 'mediodía']);
            $text = strtr($s['label'] ?? $label, $daysEsLong + $monEs + ['January' => 'enero', 'arrival window' => 'ventana de llegada', ' to ' => ' a ', 'noon' => 'mediodía', ' at ' => ' a las ']);
            $text = 'Me sirve: ' . $text . '.';
        }
        $out[] = ['label' => $label, 'text' => $text];
    }
    return $out;
}

// ===================================================================
// Tools
// ===================================================================

function tool_definitions(bool $canBook, string $tech = 'technician', bool $addr = true): array {
    $lang = ['type' => 'string', 'enum' => ['en', 'es'], 'description' => 'The language the customer is writing in.'];
    $t = [
        ['name' => 'alert_on_call', 'description' => 'Alert the on-call ' . $tech . ' / owner right now (emergencies, urgent hazards, urgent callbacks).',
         'input_schema' => ['type' => 'object', 'properties' => [
            'urgency' => ['type' => 'string', 'enum' => ['life_safety', 'urgent_hazard', 'urgent', 'same_day', 'routine']],
            'summary' => ['type' => 'string'], 'callback_number' => ['type' => 'string'], 'name' => ['type' => 'string'],
            'address' => ['type' => 'string'], 'language' => $lang],
            'required' => ['urgency', 'summary', 'callback_number']]],
        ['name' => 'take_message', 'description' => 'Leave a message for the office / owner and create a follow-up task.',
         'input_schema' => ['type' => 'object', 'properties' => [
            'name' => ['type' => 'string'], 'phone' => ['type' => 'string'], 'reason' => ['type' => 'string'],
            'for' => ['type' => 'string', 'description' => 'office, owner, billing, hiring...'], 'address' => ['type' => 'string'], 'language' => $lang],
            'required' => ['name', 'phone', 'reason', 'language']]],
        ['name' => 'transfer_to_human', 'description' => 'The customer wants a person: request an immediate human callback.',
         'input_schema' => ['type' => 'object', 'properties' => [
            'reason' => ['type' => 'string'], 'callback_number' => ['type' => 'string'], 'name' => ['type' => 'string']],
            'required' => ['reason']]],
    ];
    if ($canBook) {
        array_unshift($t,
            ['name' => 'check_availability', 'description' => 'Get real open appointment slots from the calendar.',
             'input_schema' => ['type' => 'object', 'properties' => [
                'appointment_type' => ['type' => 'string', 'enum' => ['service_call', 'estimate', 'inspection']],
                'preferred_date' => ['type' => 'string', 'description' => 'Optional YYYY-MM-DD to start searching from.'],
                'earliest_time' => ['type' => 'string', 'description' => 'Optional HH:MM (24h). Only return openings at or after this time of day, e.g. 12:00 when the customer asks for an afternoon.'],
                'barber' => ['type' => 'string', 'description' => 'Only for shops with a barber list in the session: the barber name the customer asked for, or any.']],
                'required' => ['appointment_type']]],
            ['name' => 'create_booking', 'description' => 'Book an appointment in one of the slots returned by check_availability.',
             'input_schema' => ['type' => 'object', 'properties' => [
                'appointment_type' => ['type' => 'string', 'enum' => ['service_call', 'estimate', 'inspection']],
                'slot_iso' => ['type' => 'string', 'description' => 'The exact ISO start time of a slot from check_availability.'],
                'name' => ['type' => 'string'], 'phone' => ['type' => 'string'], 'address' => ['type' => 'string'],
                'notes' => ['type' => 'string'],
                'barber' => ['type' => 'string', 'description' => 'Only for shops with a barber list: the same barber name used in check_availability, or any.'],
                'email' => ['type' => 'string', 'description' => 'Customer email for the booking confirmation, exactly as they typed it. Leave out if they skipped it.'],
                'sms_consent' => ['type' => 'boolean', 'description' => 'true only if the customer said yes to the text-consent question in this chat; false if they said no.'],
                'hazard_level' => ['type' => 'string', 'enum' => ['none', 'urgent_no_hazard', 'urgent_hazard', 'life_safety'],
                    'description' => 'Your honest EMERGENCIES level: none for routine work or a problem with no heat, smell, sparks, water or shock; urgent_hazard/life_safety only for real hazards, and those require alert_on_call first.'],
                'language' => $lang],
                'required' => $addr ? ['appointment_type', 'slot_iso', 'name', 'phone', 'address', 'hazard_level', 'sms_consent', 'language'] : ['appointment_type', 'slot_iso', 'name', 'phone', 'hazard_level', 'sms_consent', 'language']]]
        );
    }
    return $t;
}

function mock_tool(string $name, array $a, array &$state, DateTime $now, array $runtime): array {
    // mirrors tools/run_tests.py run_tool(), including the hazard guard
    if ($name === 'alert_on_call') { $state['alerted'] = true; return ['ok' => true, 'status' => 'on-call ' . ($GLOBALS['TECH'] ?? 'technician') . ' alerted by text and phone']; }
    if ($name === 'create_booking') {
        if (in_array($a['hazard_level'] ?? '', ['urgent_hazard', 'life_safety'], true) && empty($state['alerted'])) {
            return ['ok' => false, 'error' => 'Hazard reported: call alert_on_call first, tell the customer the team has been alerted, then you may book a backup slot.'];
        }
        $state['booked'] = true; return ['ok' => true, 'confirmation' => 'TEST-' . random_int(1000, 9999)];
    }
    if ($name === 'check_availability') {
        // three slots on the next business days (Mon-Fri), 9 AM and 1 PM, in the client's time zone
        $slots = []; $d = (clone $now)->modify('+1 day')->setTime(9, 0);
        if (!empty($a['preferred_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $a['preferred_date'])) {
            $p = new DateTime($a['preferred_date'] . ' 09:00', $now->getTimezone());
            if ($p > $d) $d = $p;
        }
        for ($i = 0; $i < 10 && count($slots) < 3; $i++) {
            if ((int) $d->format('N') <= 5) {
                foreach ([9, 13] as $h) {
                    $s = (clone $d)->setTime($h, 0);
                    $slots[] = ['label' => $s->format('l, M j \\a\\t g:i A'), 'slot_iso' => $s->format(DateTime::ATOM)];
                    if (count($slots) >= 3) break;
                }
            }
            $d->modify('+1 day');
        }
        return ['ok' => true, 'slots' => $slots, 'note' => 'Offer two or three of these using their labels' . (!empty($isWin) ? ' as arrival windows (for example "8 AM to noon"), never as a single start time' : '') . '. Only book one of these exact slot_iso values.'];
    }
    if ($name === 'take_message') {
        foreach (['name', 'phone', 'reason'] as $k) if (empty($a[$k])) return ['ok' => false, 'error' => "missing $k — ask the customer for it first"];
        return ['ok' => true, 'status' => 'message saved and task created'];
    }
    if ($name === 'transfer_to_human') return ['ok' => true, 'status' => 'Chat cannot transfer live. A team member has been asked to call the customer back.'];
    return ['ok' => false, 'error' => 'unknown tool'];
}

function run_tool(string $name, array $a, array &$state, array $config, array $runtime, DateTimeZone $tz, string $convId): array {
    try {
        switch ($name) {
            case 'check_availability': return tool_check_availability($a, $config, $runtime, $tz);
            case 'create_booking':     return tool_create_booking($a, $state, $config, $runtime, $tz);
            case 'alert_on_call':      return tool_alert($a, $state, $config, $runtime, $convId);
            case 'take_message':       return tool_message($a, $state, $config, $runtime, $convId);
            case 'transfer_to_human':  return tool_transfer($a, $state, $config, $runtime, $convId);
        }
        return ['ok' => false, 'error' => 'unknown tool'];
    } catch (Exception $e) {
        error_log("chat-proxy v2 tool $name failed: " . $e->getMessage());
        return ['ok' => false, 'error' => 'system unavailable — take a message instead and tell the customer the team will call them back'];
    }
}

function ghl_ready(array $c): bool {
    foreach (['ghl_private_token', 'ghl_location_id', 'ghl_calendar_id'] as $k) {
        if (empty($c[$k]) || stripos((string) $c[$k], 'REPLACE') !== false) return false;
    }
    return true;
}

function tool_check_availability(array $a, array $config, array $runtime, DateTimeZone $tz): array {
    if (!ghl_ready($config)) {
        return ['ok' => false, 'note' => 'Live calendar not connected. Ask for the preferred day and time and use take_message so the office confirms the appointment.'];
    }
    $start = new DateTime('now', $tz);
    if (!empty($a['preferred_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $a['preferred_date'])) {
        $p = new DateTime($a['preferred_date'] . ' 00:00', $tz);
        if ($p > $start) $start = $p;
    }
    $minNotice = (int) ($runtime['booking']['min_notice_hours'] ?? 2);
    $earliest = (new DateTime('now', $tz))->modify("+{$minNotice} hours");
    $end = (clone $start)->modify('+7 days');
    $url = 'https://services.leadconnectorhq.com/calendars/' . rawurlencode($config['ghl_calendar_id']) . '/free-slots?'
        . http_build_query(['startDate' => $start->getTimestamp() * 1000, 'endDate' => $end->getTimestamp() * 1000, 'timezone' => $tz->getName()]
            + (($sb = staff_by_name($config, $a['barber'] ?? null)) ? ['userId' => $sb['user_id']] : []));
    if (staff_list($config) && !empty($a['barber']) && mb_strtolower(trim($a['barber'])) !== 'any' && !$sb)
        return ['ok' => false, 'error' => 'Unknown barber. Barbers here: ' . implode(', ', array_column(staff_list($config), 'name')) . '. Ask the customer to pick one of them or any.'];
    $data = ghl_request($config, 'GET', $url, null, '2021-04-15');
    $slots = [];
    $win = ghl_calendar_minutes($config);   // 4-hour "(windows)" calendars: offer arrival windows, not start times
    $isWin = $win !== null && $win >= 120;
    foreach ($data as $day => $v) {
        if (!is_array($v) || !isset($v['slots'])) continue;
        foreach ($v['slots'] as $iso) {
            $d = new DateTime($iso); $d->setTimezone($tz);
            if ($d < $earliest) continue;
            if (!empty($a['earliest_time']) && preg_match('/^\d{1,2}:\d{2}$/', $a['earliest_time']) && $d->format('H:i') < str_pad($a['earliest_time'], 5, '0', STR_PAD_LEFT)) continue;
            if ($isWin) {
                $e = (clone $d)->modify("+{$win} minutes");
                $t = function (DateTime $x) { return $x->format('i') === '00' ? ($x->format('G') === '12' ? 'noon' : $x->format('g A')) : $x->format('g:i A'); };
                $label = $d->format('l, M j') . ', arrival window ' . $t($d) . ' to ' . $t($e);
            } else {
                $label = $d->format('l, M j \a\t g:i A');
            }
            $chip = $isWin ? ($d->format('D, M j') . ' · ' . $t($d) . '–' . $t($e)) : $d->format('D, M j · g:i A');
            $slots[] = ['label' => $label, 'slot_iso' => $d->format(DateTime::ATOM), 'chip' => $chip];
            if (count($slots) >= 6) break 2;
        }
    }
    if (!$slots) return ['ok' => true, 'slots' => [], 'note' => 'No openings in the next 7 days. Offer to take a message so the office can fit them in.'];
    return ['ok' => true, 'slots' => $slots, 'note' => 'Offer two or three of these using their labels' . (!empty($isWin) ? ' as arrival windows (for example "8 AM to noon"), never as a single start time' : '') . '. Only book one of these exact slot_iso values.'];
}

function tool_create_booking(array $a, array &$state, array $config, array $runtime, DateTimeZone $tz): array {
    $level = $a['hazard_level'] ?? '';
    if (in_array($level, ['urgent_hazard', 'life_safety'], true) && empty($state['alerted'])) {
        return ['ok' => false, 'error' => 'Hazard reported: call alert_on_call first, tell the customer the team has been alerted, then you may book a backup slot.'];
    }
    $noAddr = (($config['collect_address'] ?? true) === false) || (($runtime['booking']['collect_address'] ?? true) === false);
    if ($noAddr) $a['address'] = '';
    foreach ($noAddr ? ['name', 'phone', 'slot_iso'] : ['name', 'phone', 'address', 'slot_iso'] as $k) {
        if (empty($a[$k])) return ['ok' => false, 'error' => "missing $k — ask the customer for it first"];
    }
    try { $start = new DateTime($a['slot_iso']); } catch (Exception $e) {
        return ['ok' => false, 'error' => 'slot_iso is not a valid time; call check_availability and use one of its slot_iso values'];
    }
    if (!ghl_ready($config)) {
        tool_message(['name' => $a['name'], 'phone' => $a['phone'], 'address' => $a['address'], 'for' => 'office',
                      'reason' => 'Booking request (' . ($a['appointment_type'] ?? '') . ') for ' . $start->format('D M j g:i A') . '. ' . ($a['notes'] ?? '')],
                     $state, $config, $runtime, '');
        return ['ok' => false, 'note' => 'Calendar not connected: saved as a booking request. Tell the customer the office will call to confirm the time.'];
    }
    $minutes = (int) ($runtime['booking']['minutes'][$a['appointment_type'] ?? 'service_call'] ?? 60);
    $calMin = ghl_calendar_minutes($config);   // GHL rejects any length other than the calendar's own slot duration
    if ($calMin) $minutes = $calMin;
    $end = (clone $start)->modify("+{$minutes} minutes");
    $contactId = ghl_upsert_contact($config, $a['name'], $a['phone'], ($a['address'] !== '' ? $a['address'] : null), $a['language'] ?? null, $a['email'] ?? null);
    $state['contact_id'] = $contactId;
    ghl_sms_consent($config, $contactId, !empty($a['sms_consent']));
    // Multi-barber: only ever book a barber who is really free at that time (the GHL API itself allows double-booking).
    $assignee = null;
    if (staff_list($config)) {
        $want = staff_by_name($config, $a['barber'] ?? null);
        $pool = $want ? [$want] : staff_list($config);
        if (!$want) shuffle($pool);
        foreach ($pool as $b) { if (staff_free_at($config, $b['user_id'], $start, $tz)) { $assignee = $b; break; } }
        if (!$assignee) return ['ok' => false, 'error' => ($want ? $want['name'] . ' is no longer free at that time' : 'That time was just taken') . '. Call check_availability again and offer other times.'];
    }
    if ($assignee && $contactId) {
        try { ghl_request($config, 'PUT', 'https://services.leadconnectorhq.com/contacts/' . rawurlencode($contactId), ['assignedTo' => $assignee['user_id']]); } catch (Exception $e) { error_log('ghl assign: ' . $e->getMessage()); }
    } else {
        ghl_assign_to_calendar_owner($config, $contactId);
    }
    $title = ucwords(str_replace('_', ' ', $a['appointment_type'] ?? 'service call')) . ' — ' . mb_substr($a['notes'] ?? '', 0, 80);
    $appt = ghl_request($config, 'POST', 'https://services.leadconnectorhq.com/calendars/events/appointments', [
        'calendarId' => $config['ghl_calendar_id'], 'locationId' => $config['ghl_location_id'], 'contactId' => $contactId,
        'title' => $title, 'appointmentStatus' => 'confirmed',
        'startTime' => $start->format(DateTime::ATOM), 'endTime' => $end->format(DateTime::ATOM),
        'address' => ($a['address'] !== '' ? $a['address'] : null),
    ] + ($assignee ? ['assignedUserId' => $assignee['user_id']] : []));
    ghl_note($config, $contactId, "AI booking ({$a['appointment_type']}, hazard: $level, lang: " . ($a['language'] ?? 'en') . "):\n" . ($a['notes'] ?? ''));
    $state['booked'] = true;
    $local = clone $start; $local->setTimezone($tz);
    return ['ok' => true, 'confirmation' => $appt['id'] ?? ($appt['appointment']['id'] ?? 'booked'), 'when' => $local->format('l, M j \a\t g:i A')]
        + ($assignee ? ['barber' => $assignee['name']] : []);
}

function staff_free_at(array $config, string $userId, DateTime $start, DateTimeZone $tz): bool {
    $d0 = (clone $start); $d0->setTimezone($tz); $d0->setTime(0, 0);
    $url = 'https://services.leadconnectorhq.com/calendars/' . rawurlencode($config['ghl_calendar_id']) . '/free-slots?'
        . http_build_query(['startDate' => $d0->getTimestamp() * 1000, 'endDate' => ($d0->getTimestamp() + 86400) * 1000, 'timezone' => $tz->getName(), 'userId' => $userId]);
    try { $data = ghl_request($config, 'GET', $url, null, '2021-04-15'); } catch (Exception $e) { return false; }
    foreach ($data as $v) {
        if (!is_array($v) || !isset($v['slots'])) continue;
        foreach ($v['slots'] as $iso) { if ((new DateTime($iso))->getTimestamp() === $start->getTimestamp()) return true; }
    }
    return false;
}

function ghl_calendar_info(array $config): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $c = ghl_request($config, 'GET', 'https://services.leadconnectorhq.com/calendars/' . rawurlencode($config['ghl_calendar_id']), null, '2021-04-15');
        $cal = $c['calendar'] ?? $c;
        $d = (int) ($cal['slotDuration'] ?? 0);
        $min = $d > 0 ? ((($cal['slotDurationUnit'] ?? 'mins') === 'hours') ? $d * 60 : $d) : null;
        $owner = null;
        foreach (($cal['teamMembers'] ?? []) as $tm) { if (!empty($tm['userId'])) { $owner = $tm['userId']; if (!empty($tm['isPrimary'])) break; } }
        return $cache = ['minutes' => $min, 'owner' => $owner];
    } catch (Exception $e) { error_log('ghl calendar info: ' . $e->getMessage()); return $cache = ['minutes' => null, 'owner' => null]; }
}

function ghl_calendar_minutes(array $config): ?int { return ghl_calendar_info($config)['minutes']; }

// Give the contact to the calendar's staff user, so that user's (restricted) GHL login sees it. Replaces the per-trade "assign" workflows.
// Multi-barber (2026-10-14): $config['barbers'] = [['name' => 'Marco', 'user_id' => '...'], ...] on a round-robin calendar.
function staff_list(array $config): array { return array_values(array_filter($config['barbers'] ?? [], fn($b) => !empty($b['name']) && !empty($b['user_id']))); }
function staff_by_name(array $config, ?string $name): ?array {
    $n = mb_strtolower(trim((string) $name));
    if ($n === '' || $n === 'any') return null;
    foreach (staff_list($config) as $b) if (mb_strtolower($b['name']) === $n) return $b;
    return null;
}
function staff_name_by_id(array $config, ?string $id): ?string {
    foreach (staff_list($config) as $b) if ($b['user_id'] === $id) return $b['name'];
    return null;
}

function ghl_assign_to_calendar_owner(array $config, ?string $contactId): void {
    $owner = ghl_calendar_info($config)['owner'];
    if (!$contactId || !$owner) return;
    try {
        $r = ghl_request($config, 'PUT', 'https://services.leadconnectorhq.com/contacts/' . rawurlencode($contactId), ['assignedTo' => $owner]);
        error_log('chat-proxy assign: contact ' . $contactId . ' assignedTo=' . ($r['contact']['assignedTo'] ?? 'n/a'));
    } catch (Exception $e) { error_log('ghl assign: ' . $e->getMessage()); }
}

function tool_alert(array $a, array &$state, array $config, array $runtime, string $convId): array {
    $state['alerted'] = true; // attempted — the booking guard is satisfied even if a channel fails
    $msg = strtoupper($a['urgency'] ?? 'urgent') . ' — ' . ($runtime['name'] ?? '') . ' website chat: '
        . ($a['name'] ?? 'customer') . ', ' . ($a['callback_number'] ?? '') . ', ' . ($a['address'] ?? '') . '. ' . ($a['summary'] ?? '');
    $sent = [];
    $to = $config['on_call_phone'] ?? '';
    if ($to && stripos($to, 'REPLACE') === false) {
        try { send_sms($config, $to, mb_substr($msg, 0, 1500)); $sent[] = 'sms'; } catch (Exception $e) { error_log('alert sms: ' . $e->getMessage()); }
        if (in_array($a['urgency'] ?? '', ['life_safety', 'urgent_hazard'], true)) {
            // the call carries the details itself, so the alert works even when texting is blocked
            $cb = preg_replace('/\D/', '', (string) ($a['callback_number'] ?? ''));
            if (strlen($cb) === 11 && $cb[0] === '1') $cb = substr($cb, 1);
            $cbSpoken = strlen($cb) === 10 ? implode(' ', str_split(substr($cb, 0, 3))) . ', ' . implode(' ', str_split(substr($cb, 3, 3))) . ', ' . implode(' ', str_split(substr($cb, 6))) : implode(' ', str_split($cb));
            $spoken = (($a['urgency'] ?? '') === 'life_safety' ? 'Emergency' : 'Urgent') . ' website chat for ' . ($runtime['name'] ?? 'the business') . '. '
                . ($a['summary'] ?? 'A customer needs help now.') . ' '
                . (!empty($a['name']) ? 'Customer ' . $a['name'] . '. ' : '') . (!empty($a['address']) ? 'Address ' . $a['address'] . '. ' : '')
                . ($cbSpoken !== '' ? 'Call back ' . $cbSpoken . '.' : '');
            try { place_alert_call($config, $to, mb_substr($spoken, 0, 600)); $sent[] = 'call'; }
            catch (Exception $e) { error_log('alert call: ' . $e->getMessage()); }
        }
    }
    log_event('alerts', ['conv' => $convId, 'input' => $a, 'sent' => $sent]);
    if (!$sent) return ['ok' => false, 'note' => 'The alert could not be delivered automatically. Give the customer the business phone number to call now: ' . ($runtime['phone'] ?? '')];
    $mins = $runtime['on_call_callback_minutes'] ?? null;
    return ['ok' => true, 'status' => 'on-call alerted by ' . implode(' and ', $sent) . ($mins ? " — callback target about $mins minutes" : '')];
}

function tool_message(array $a, array &$state, array $config, array $runtime, string $convId): array {
    foreach (['name', 'phone', 'reason'] as $k) {
        if (empty($a[$k])) return ['ok' => false, 'error' => "missing $k — ask the customer for it first"];
    }
    log_event('messages', ['conv' => $convId, 'input' => $a]);
    if (ghl_ready($config)) {
        $cid = ghl_upsert_contact($config, $a['name'], $a['phone'], $a['address'] ?? null, $a['language'] ?? null);
        $state['contact_id'] = $cid;
        ghl_note($config, $cid, 'AI chat message for ' . ($a['for'] ?? 'office') . ': ' . $a['reason']);
        try {
            ghl_request($config, 'POST', 'https://services.leadconnectorhq.com/contacts/' . rawurlencode($cid) . '/tasks', [
                'title' => 'Call back: ' . mb_substr($a['reason'], 0, 60), 'body' => $a['reason'],
                'dueDate' => (new DateTime('+2 hours'))->format('Y-m-d\TH:i:s\Z'), 'completed' => false]);
        } catch (Exception $e) { error_log('ghl task: ' . $e->getMessage()); }
    }
    if (!empty($config['office_email'])) {
        @mail($config['office_email'], 'Website chat message: ' . $a['name'], $a['reason'] . "\n\nPhone: " . $a['phone'] . "\nFor: " . ($a['for'] ?? 'office'));
    }
    return ['ok' => true, 'status' => 'message saved and follow-up task created'];
}

function tool_transfer(array $a, array &$state, array $config, array $runtime, string $convId): array {
    $to = $config['on_call_phone'] ?? '';
    $sent = false;
    if ($to && stripos($to, 'REPLACE') === false) {
        try { send_sms($config, $to, 'Website chat customer wants a person: ' . ($a['name'] ?? '') . ' ' . ($a['callback_number'] ?? '') . ' — ' . ($a['reason'] ?? '')); $sent = true; }
        catch (Exception $e) { error_log('transfer sms: ' . $e->getMessage()); }
    }
    log_event('transfers', ['conv' => $convId, 'input' => $a, 'sent' => $sent]);
    return ['ok' => true, 'status' => 'Chat cannot transfer live. A team member has been asked to call the customer back' . ($sent ? '' : ' (make sure you have their number and use take_message)') . '.'];
}

// ===================================================================
// Helpers
// ===================================================================

function call_anthropic(array $config, array $system, array $messages, array $tools): array {
    // cache the conversation so far: breakpoint on the last message
    $msgs = $messages;
    $i = count($msgs) - 1;
    if (is_string($msgs[$i]['content'])) $msgs[$i]['content'] = [['type' => 'text', 'text' => $msgs[$i]['content']]];
    $j = count($msgs[$i]['content']) - 1;
    $msgs[$i]['content'][$j]['cache_control'] = ['type' => 'ephemeral'];

    $body = ['model' => $config['anthropic_model'] ?? 'claude-sonnet-5', 'max_tokens' => 600,
             'system' => $system, 'messages' => $msgs, 'tools' => $tools];
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $ch = curl_init($config['anthropic_url'] ?? 'https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 45,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . $config['anthropic_api_key'], 'anthropic-version: 2023-06-01'],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($response !== false && $code < 400) {
            $data = json_decode($response, true);
            log_event('usage', ['model' => $body['model'], 'usage' => $data['usage'] ?? []]);
            return $data;
        }
        if (in_array($code, [429, 500, 502, 503, 529], true) && $attempt < 2) { sleep(1 + $attempt * 2); continue; }
        throw new Exception('Anthropic API error ' . $code . ': ' . ($response === false ? $err : substr($response, 0, 300)));
    }
    throw new Exception('Anthropic API: retries exhausted');
}

function extract_text(array $r): string {
    $t = '';
    foreach ($r['content'] ?? [] as $b) if (($b['type'] ?? '') === 'text') $t .= $b['text'];
    return trim($t);
}

function review_reply(string $text, array $resp, array $state, ?string $status): array {
    $issues = [];
    if (leaks($text)) $issues[] = 'Never mention placeholders or say a value is not set; say the team will confirm instead.';
    // 2026-10-03: the client's own name may contain "ASharp" (asharp-productions); remove it before the check.
    $ownName = (string) ($GLOBALS['runtime']['name'] ?? '');
    $textNoOwn = $ownName !== '' ? str_ireplace($ownName, '', $text) : $text;
    if (preg_match('/asharp/i', $textNoOwn)) $issues[] = 'Do not name ASharp Agency, ASharp Productions or any other company. Say you are the virtual assistant for this business and not affiliated with other companies.';
    if (preg_match('/\b(loose|lost|bad|faulty|failing) (connection|neutral|breaker|wire|wiring|leg)\b|just the bulb|\b(can|could|may|might|usually|often) (point to|mean|indicate|be a sign of|be caused by)\b|probably (a|the|your|just)\b|(could|might|may) be (a|the|your) (bad|loose|faulty|failing|overloaded)|sounds like (a|the|your) (bad|loose|faulty|failing|overloaded)|needs? a dedicated circuit/i', $text)) {
        $issues[] = 'Do not guess the cause (no "probably...", no naming a failed part). Say the ' . ($GLOBALS['TECH'] ?? 'technician') . ' will find the cause on site.';
    }
    if (preg_match('/\b(before|by) (your |the )?(closing|deadline|friday|monday|tuesday|wednesday|thursday|saturday|sunday|tomorrow|tonight|party|event|guests|inspection|move|trip|holiday|weekend)\b|guarantee (it|that|you|the work|completion)|(done|fixed|finished|handled) (today|tonight|tomorrow)/i', $text)) {
        $issues[] = 'Do not promise when the work will be done or finished (no "before your closing", "by Friday", "guarantee"). You may repeat the customer\'s deadline and say it is flagged for the team.';
    }
    if (preg_match('/\\b(search|look (up|online|around)|google|check online|find)\\b[^.?!]{0,60}\\b(installers?|contractors?|compan(y|ies)|electricians?|technicians?|plumbers?|roofers?|providers?|businesses)\\b/i', $text)) {
        $issues[] = 'Do not send the caller elsewhere or suggest searching for other companies. For a service not on our list, say the team will confirm whether they can help and offer to take their name and number for the owner.';
    }
    $prior = '';
    $userText = '';
    foreach ($state['messages'] as $m) {
        if (is_string($m['content'] ?? null)) {
            if ($m['role'] === 'user') $userText .= ' ' . $m['content']; else $prior .= ' ' . $m['content'];
        } elseif (is_array($m['content'] ?? null)) {
            foreach ($m['content'] as $b) if (($b['type'] ?? '') === 'text') { if ($m['role'] === 'user') $userText .= ' ' . $b['text']; else $prior .= ' ' . $b['text']; }
        }
    }
    if (preg_match('/generat|generador/i', $userText) && !preg_match('/outside|outdoors|afuera/i', $prior . ' ' . $text)) {
        $issues[] = 'A portable generator came up: include the generator_safety script (run it outside only, away from doors and windows, never plugged into a wall outlet).';
    }
    if (closed_notice_missing($text, $state, $status)) {
        $issues[] = 'The office is CLOSED right now: first say the office is closed and read the full {{client.hours_sentence}} value, then offer the appointment.';
    }
    return $issues;
}

function closed_notice_missing(string $text, array $state, ?string $status): bool {
    if ($status !== 'CLOSED' || !empty($state['alerted'])) return false;
    $prior = '';
    foreach ($state['messages'] as $m) {
        if (($m['role'] ?? '') !== 'assistant') continue;
        foreach ((array) $m['content'] as $b) if (is_array($b) && ($b['type'] ?? '') === 'text') $prior .= ' ' . $b['text'];
    }
    if (preg_match('/closed|cerrad/i', $prior . ' ' . $text)) return false;
    return (bool) preg_match('/\b(AM|PM|opening|openings|available|availability|book|schedule|appointment|cita)\b/i', $text);
}

function leaks(string $t): bool {
    return (bool) preg_match('/\{\{|\bclient\.[a-z_]|isn\'t set|is not set|NOT SET/i', $t);
}

function plain_text(string $t): string {
    $t = preg_replace('/\*\*(.+?)\*\*/s', '$1', $t);
    $t = preg_replace('/^\s*[-*•]\s+/m', '', $t);
    $t = preg_replace('/^#+\s*/m', '', $t);
    return trim($t);
}

function office_status(array $hours, DateTime $now): ?string {
    $day = strtolower(substr($now->format('D'), 0, 3));
    $span = (string) ($hours[$day] ?? '');
    if ($span === '') return null;
    if (stripos($span, 'closed') !== false) return 'CLOSED';
    $parts = preg_split('/\s*[-–]\s*|\s+to\s+/', $span);
    if (count($parts) !== 2) return null;
    $open = minutes($parts[0]); $close = minutes($parts[1]);
    if ($open === null || $close === null) return null;
    $m = (int) $now->format('G') * 60 + (int) $now->format('i');
    return ($m >= $open && $m < $close) ? 'OPEN' : 'CLOSED';
}

function minutes(string $t): ?int {
    if (!preg_match('/(\d{1,2})(?::(\d\d))?\s*([AaPp])\.?[Mm]/', $t, $m)) return null;
    return ((int) $m[1] % 12 + (strtolower($m[3]) === 'p' ? 12 : 0)) * 60 + (int) ($m[2] ?? 0);
}

function save_state(?string $file, array $state): void {
    if ($file) @file_put_contents($file, json_encode($state), LOCK_EX);
}

function log_event(string $kind, array $data): void {
    global $dataDir;
    $data['ts'] = date('c');
    @file_put_contents($dataDir . '/' . $kind . '.jsonl', json_encode($data) . "\n", FILE_APPEND | LOCK_EX);
}

function ghl_request(array $config, string $method, string $url, ?array $payload, string $version = '2021-07-28'): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json',
                               'Authorization: Bearer ' . $config['ghl_private_token'], 'Version: ' . $version],
    ];
    if ($payload !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $code >= 400) throw new Exception("GHL API error ($code): " . substr((string) $response, 0, 300));
    return json_decode($response, true) ?? [];
}

function ghl_upsert_contact(array $config, string $name, string $phone, ?string $address, ?string $lang = null, ?string $email = null): ?string {
    // lang-es / lang-en tag lets workflows send texts and emails in the customer's language.
    $body = [
        'locationId' => $config['ghl_location_id'], 'name' => $name, 'phone' => $phone,
        'address1' => $address, 'source' => 'Website AI Chat', 'tags' => ['ai-chat', ($lang === 'es' ? 'lang-es' : 'lang-en')],
    ];
    if ($address === null || $address === '') unset($body['address1']);
    $email = trim((string) $email);
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) $body['email'] = $email;
    $r = ghl_request($config, 'POST', 'https://services.leadconnectorhq.com/contacts/upsert', $body);
    return $r['contact']['id'] ?? null;
}

// Record the chat's text-consent answer: tag sms-consent-yes / sms-consent-no; "no" also turns on GHL's SMS do-not-disturb.
function ghl_sms_consent(array $config, ?string $contactId, bool $yes): void {
    if (!$contactId) return;
    $base = 'https://services.leadconnectorhq.com/contacts/' . rawurlencode($contactId);
    try { ghl_request($config, 'POST', $base . '/tags', ['tags' => [$yes ? 'sms-consent-yes' : 'sms-consent-no']]); }
    catch (Exception $e) { error_log('ghl consent tag: ' . $e->getMessage()); }
    if (!$yes) {
        try { ghl_request($config, 'PUT', $base, ['dndSettings' => ['SMS' => ['status' => 'active', 'message' => 'Declined texts in website chat']]]); }
        catch (Exception $e) { error_log('ghl sms dnd: ' . $e->getMessage()); }
    }
}

function ghl_note(array $config, ?string $contactId, string $body): void {
    if (!$contactId) return;
    try {
        ghl_request($config, 'POST', 'https://services.leadconnectorhq.com/contacts/' . rawurlencode($contactId) . '/notes', ['body' => $body]);
    } catch (Exception $e) { error_log('ghl note: ' . $e->getMessage()); }
}

function send_sms(array $config, string $to, string $body): void {
    if (empty($config['twilio_account_sid']) || empty($config['twilio_auth_token']) || empty($config['twilio_from_number'])) {
        throw new Exception('Twilio not configured');
    }
    twilio_request($config, 'https://api.twilio.com/2010-04-01/Accounts/' . $config['twilio_account_sid'] . '/Messages.json',
        http_build_query(['To' => $to, 'From' => $config['twilio_from_number'], 'Body' => $body]));
}

function place_alert_call(array $config, string $to, string $message): void {
    if (empty($config['twilio_account_sid']) || empty($config['twilio_auth_token']) || empty($config['twilio_from_number'])) {
        throw new Exception('Twilio not configured');
    }
    $e = htmlspecialchars($message, ENT_XML1, 'UTF-8');
    $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response><Say>' . $e . '</Say><Pause length="1"/><Say>' . $e . '</Say></Response>';
    twilio_request($config, 'https://api.twilio.com/2010-04-01/Accounts/' . $config['twilio_account_sid'] . '/Calls.json',
        http_build_query(['To' => $to, 'From' => $config['twilio_from_number'], 'Twiml' => $twiml]));
}

function twilio_request(array $config, string $url, string $payload): void {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_USERPWD => $config['twilio_account_sid'] . ':' . $config['twilio_auth_token'], CURLOPT_POSTFIELDS => $payload]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $code >= 300) throw new Exception("Twilio API error ($code): " . substr((string) $response, 0, 200));
}

// Full chat transcript on the GHL contact: one note per conversation, created once and
// updated after every reply, so the office sees exactly what was said (added 2026-09-29).
function sync_transcript_note(array $config, array &$state, string $lastReply): void {
    $lines = [];
    foreach ($state['messages'] as $m) {
        $role = $m['role'] ?? '';
        $c = $m['content'] ?? '';
        if (is_string($c)) { $t = trim($c); }
        else {
            $t = '';
            foreach ((array) $c as $b) if (($b['type'] ?? '') === 'text') $t .= ' ' . ($b['text'] ?? '');
            $t = trim($t);
        }
        if ($t === '' || $t === '...') continue;
        $lines[] = ($role === 'user' ? 'Customer: ' : 'AI: ') . $t;
    }
    if (!$lines) return;
    $body = "Website chat transcript (updated " . (isset($GLOBALS['tz']) ? new DateTime('now', $GLOBALS['tz']) : new DateTime())->format('M j, g:i A') . "):\n\n" . implode("\n\n", $lines);
    $body = mb_substr($body, 0, 60000);
    $cid = rawurlencode($state['contact_id']);
    try {
        if (!empty($state['transcript_note_id'])) {
            ghl_request($config, 'PUT', 'https://services.leadconnectorhq.com/contacts/' . $cid . '/notes/' . rawurlencode($state['transcript_note_id']), ['body' => $body]);
        } else {
            $r = ghl_request($config, 'POST', 'https://services.leadconnectorhq.com/contacts/' . $cid . '/notes', ['body' => $body]);
            $state['transcript_note_id'] = $r['note']['id'] ?? null;
        }
    } catch (Exception $e) { error_log('ghl transcript note: ' . $e->getMessage()); }
}
