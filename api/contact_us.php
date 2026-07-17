<?php
/**
 * Contact Us API
 * - Saves message to DB (contact_messages table)
 * - Sends email via Gmail SMTP (SSL, port 465) — no library needed
 * - Falls back to PHP mail() if SMTP fails
 * - Output-buffered so PHP notices never corrupt the JSON response
 */
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

/* ── helpers ─────────────────────────────────────────────────────────── */
function jsonOut(bool $ok, string $msg): void {
    ob_end_clean();
    echo json_encode(['success' => $ok, 'message' => $msg]);
    exit;
}

function logError(string $context, string $detail): void {
    $log = date('Y-m-d H:i:s') . " | {$context} | {$detail}\n";
    @file_put_contents(__DIR__ . '/../logs/contact_errors.log', $log, FILE_APPEND | LOCK_EX);
}

/* ── method guard ─────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(false, 'Invalid request method.');
}

/* ── input ────────────────────────────────────────────────────────────── */
$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$phone   = trim($_POST['phone']   ?? '');
$message = trim($_POST['message'] ?? '');

/* ── validation ───────────────────────────────────────────────────────── */
if (empty($name) || empty($email) || empty($message)) {
    jsonOut(false, 'Name, email, and message are required.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonOut(false, 'Please enter a valid email address.');
}
if (strlen($message) < 5) {
    jsonOut(false, 'Your message is too short.');
}

/* ── DB: save message ─────────────────────────────────────────────────── */
$dbSaved = false;
try {
    require_once __DIR__ . '/../config/db.php';
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS contact_messages (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(120)  NOT NULL,
        email      VARCHAR(150)  NOT NULL,
        phone      VARCHAR(50)   DEFAULT NULL,
        message    TEXT          NOT NULL,
        created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $db->prepare(
        "INSERT INTO contact_messages (name, email, phone, message) VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$name, $email, $phone, $message]);
    $dbSaved = true;
} catch (Throwable $e) {
    logError('DB', $e->getMessage());
}

/* ── Gmail SMTP config ────────────────────────────────────────────────── */
$gmailUser = 'eainppkyaing@gmail.com';
$gmailPass = 'oldabtjgiavlspbs';
$toEmail   = 'eainppkyaing@gmail.com';

$emailSent = false;

if ($gmailPass && $gmailPass !== 'YOUR_APP_PASSWORD_HERE') {
    $emailSent = smtpSend(
        $gmailUser,
        $gmailPass,
        $toEmail,
        "Sweet Heaven — Contact from {$name}",
        buildEmailBody($name, $email, $phone, $message),
        $email
    );
    if (!$emailSent) {
        logError('SMTP', 'smtpSend returned false');
    }
}

/* ── Fallback: PHP mail() ────────────────────────────────────────────── */
if (!$emailSent) {
    $subject  = "Sweet Heaven — Contact from {$name}";
    $headers  = "From: Sweet Heaven <{$gmailUser}>\r\n";
    $headers .= "Reply-To: {$email}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $emailSent = @mail($toEmail, $subject, buildEmailBody($name, $email, $phone, $message), $headers);
    if (!$emailSent) {
        logError('MAIL', 'PHP mail() also failed');
    }
}

/* ── respond ──────────────────────────────────────────────────────────── */
if ($emailSent) {
    jsonOut(true, 'Thank you! Your message has been received. We will get back to you soon.');
} elseif ($dbSaved) {
    jsonOut(true, 'Thank you! Your message has been saved. We will get back to you soon.');
} else {
    jsonOut(false, 'Sorry, something went wrong. Please try again later.');
}

/* ════════════════════════════════════════════════════════════════════════
   Helper functions
   ════════════════════════════════════════════════════════════════════════ */

function buildEmailBody(string $name, string $email, string $phone, string $message): string
{
    $phoneLine = $phone ? "Phone   : {$phone}\r\n" : '';
    return "You have a new contact message from the Sweet Heaven website.\r\n\r\n"
         . "Name    : {$name}\r\n"
         . "Email   : {$email}\r\n"
         . $phoneLine
         . "Message :\r\n{$message}\r\n\r\n"
         . "---\r\nSent via the Contact Us form.";
}

/**
 * Gmail SMTP send over SSL (port 465), no external library.
 * Returns true on success, false on any failure.
 */
function smtpSend(
    string $user, string $pass,
    string $to,   string $subject,
    string $body, string $replyTo = ''
): bool {
    $host = 'ssl://smtp.gmail.com';
    $port = 465;
    $timeout = 10;

    $sock = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if (!$sock) {
        logError('SMTP', "fsockopen failed: {$errstr} ({$errno})");
        return false;
    }

    // Set stream timeout to prevent hanging
    stream_set_timeout($sock, $timeout);

    $read = function() use ($sock) {
        $line = fgets($sock, 512);
        return $line !== false ? $line : '';
    };

    $send = function(string $cmd) use ($sock, $read): string {
        fwrite($sock, $cmd . "\r\n");
        return $read();
    };

    // Read greeting
    $greeting = $read();
    if (strpos($greeting, '220') === false) {
        fclose($sock);
        return false;
    }

    // EHLO
    $send("EHLO sweetheaven.local");
    $ehlo = '';
    while (true) {
        $line = $read();
        $ehlo .= $line;
        // Last line of multi-line response has space at position 4
        if (strlen($line) >= 4 && $line[3] === ' ') break;
        if ($line === '') break; // connection dropped
    }

    // AUTH LOGIN
    $send("AUTH LOGIN");
    $send(base64_encode($user));
    $send(base64_encode($pass));
    $authResp = $read();
    if (strpos($authResp, '235') === false) {
        fclose($sock);
        return false;
    }

    // Envelope
    $send("MAIL FROM:<{$user}>");  $read();
    $send("RCPT TO:<{$to}>");      $read();
    $send("DATA");                 $read();

    // Headers + body
    $replyLine = $replyTo ? "Reply-To: {$replyTo}\r\n" : '';
    $msg = "From: Sweet Heaven <{$user}>\r\n"
         . "To: {$to}\r\n"
         . $replyLine
         . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
         . "MIME-Version: 1.0\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n"
         . "\r\n"
         . $body
         . "\r\n.\r\n";
    fwrite($sock, $msg);

    // Read DATA response (could be multi-line)
    $dataResp = '';
    while (true) {
        $line = $read();
        $dataResp .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') break;
        if ($line === '') break;
    }

    $send("QUIT");
    fclose($sock);

    return strpos($dataResp, '250') !== false;
}
