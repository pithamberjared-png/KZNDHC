<?php
// Handles the membership application form on join.html and emails it.

// ---- Settings -------------------------------------------------------------
$RECIPIENT = 'jpithamber@gmail.com';   // where applications are sent (testing)
$SUBJECT   = 'New KZNDHC Membership Application';
// From address must be on the site's own domain or Gmail will flag/reject it.
$host = preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
$host = preg_replace('/[^a-z0-9.\-]/i', '', $host);
$FROM = 'noreply@' . $host;
// ---------------------------------------------------------------------------

$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

function respond($ok, $message, $isAjax) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code($ok ? 200 : 400);
        echo json_encode(['ok' => $ok, 'message' => $message]);
    } else {
        header('Location: join.html?status=' . ($ok ? 'sent' : 'error') . '#apply');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request.', $isAjax);
}

// Honeypot: real users never fill this hidden field.
if (!empty($_POST['website'])) {
    respond(true, 'Thank you for your application!', $isAjax);
}

function field($key) {
    return trim(str_replace(["\r", "\n"], ' ', (string)($_POST[$key] ?? '')));
}

$fields = [
    'Personal Details' => [
        'name'      => ['Name', true],
        'surname'   => ['Surname', true],
        'id_number' => ['ID Number', true],
        'cellphone' => ['Cellphone', true],
        'email'     => ['Email', true],
    ],
    'Practice Details' => [
        'practice_number'  => ['Practice Number', true],
        'hpcsa_number'     => ['HPCSA Number', true],
        'mp_number'        => ['MP Number', false],
        'practice_address' => ['Practice Address', true],
        'guild'            => ['Guild / Local IPA', false],
    ],
    'Debit Order Details' => [
        'bank_name'      => ['Bank Name', true],
        'branch_code'    => ['Branch Code', true],
        'account_number' => ['Account Number', true],
    ],
];

$values = [];
foreach ($fields as $section => $items) {
    foreach ($items as $key => [$label, $required]) {
        // Keep line breaks in the address, flatten everything else.
        $v = $key === 'practice_address'
            ? trim((string)($_POST[$key] ?? ''))
            : field($key);
        if ($required && $v === '') {
            respond(false, "Please fill in: $label.", $isAjax);
        }
        if (mb_strlen($v) > 1000) {
            respond(false, "$label is too long.", $isAjax);
        }
        $values[$key] = $v;
    }
}

if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please enter a valid email address.', $isAjax);
}
if (empty($_POST['terms'])) {
    respond(false, 'Please accept the terms and conditions.', $isAjax);
}

// Build HTML email
$e = fn($s) => nl2br(htmlspecialchars($s, ENT_QUOTES, 'UTF-8'));
$rows = '';
foreach ($fields as $section => $items) {
    $rows .= '<tr><td colspan="2" style="background:#0B1143;color:#fff;padding:8px 12px;font-weight:bold">'
           . $e($section) . '</td></tr>';
    foreach ($items as $key => [$label]) {
        $rows .= '<tr><td style="padding:6px 12px;border-bottom:1px solid #eee;width:40%;color:#555">'
               . $e($label) . '</td><td style="padding:6px 12px;border-bottom:1px solid #eee">'
               . ($values[$key] !== '' ? $e($values[$key]) : '<em style="color:#999">—</em>')
               . '</td></tr>';
    }
}
$submitted = date('j F Y, H:i');
$body = '<html><body style="font-family:Arial,sans-serif;font-size:14px;color:#222">'
      . '<h2 style="color:#0B1143">New Membership Application</h2>'
      . '<p>Submitted ' . $e($submitted) . ' via the KZNDHC website. '
      . 'The applicant agreed to the terms and the monthly debit order.</p>'
      . '<table cellspacing="0" style="border-collapse:collapse;width:100%;max-width:640px">'
      . $rows . '</table></body></html>';

$applicant = $values['name'] . ' ' . $values['surname'];
$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: KZNDHC Website <' . $FROM . '>',
    'Reply-To: ' . $applicant . ' <' . $values['email'] . '>',
    'X-Mailer: PHP/' . phpversion(),
];

$sent = mail(
    $RECIPIENT,
    '=?UTF-8?B?' . base64_encode("$SUBJECT - $applicant") . '?=',
    $body,
    implode("\r\n", $headers),
    '-f' . $FROM
);

if ($sent) {
    respond(true, 'Thank you for your application! We will be in touch shortly.', $isAjax);
}
respond(false, 'Sorry, your application could not be sent. Please try again or contact us directly.', $isAjax);
