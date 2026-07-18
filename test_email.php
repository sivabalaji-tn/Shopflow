<?php
/**
 * TamizhMart — Gmail SMTP Test
 * URL: http://localhost/tamizhmart/test_mail.php
 * DELETE THIS FILE after testing!
 */

// ── Same path as notifications.php uses ──────────────────────
$src = __DIR__ . '/vendor/phpmailer/src/';

echo "<h2>TamizhMart — Mail Test</h2>";
echo "<pre>";

// Step 1: Check files exist
echo "=== Step 1: PHPMailer Files ===\n";
$files = ['Exception.php', 'PHPMailer.php', 'SMTP.php'];
$all_found = true;
foreach ($files as $f) {
    $path = $src . $f;
    if (file_exists($path)) {
        echo "✅ Found: vendor/phpmailer/src/$f\n";
    } else {
        echo "❌ MISSING: vendor/phpmailer/src/$f\n";
        $all_found = false;
    }
}

if (!$all_found) {
    echo "\n❌ STOP — Copy the 3 files from PHPMailer/src/ into vendor/phpmailer/src/\n";
    echo "</pre>"; exit;
}

// Step 2: Load PHPMailer
echo "\n=== Step 2: Loading PHPMailer ===\n";
require_once $src . 'Exception.php';
require_once $src . 'PHPMailer.php';
require_once $src . 'SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
echo "✅ PHPMailer loaded\n";

// ════════════════════════════════════════════
// !! FILL THESE IN !!
$YOUR_GMAIL    = 'sivathetechie24@gmail.com';   // your Gmail
$YOUR_PASSWORD = 'yjqz ofcg htvl qxfu';   // 16-char App Password
$SEND_TEST_TO  = 'sivabalaji800@gmail.com';  // where to receive test mail
// ════════════════════════════════════════════

if (str_contains($YOUR_GMAIL, 'your_gmail')) {
    echo "\n❌ STOP — Fill in YOUR_GMAIL, YOUR_PASSWORD, and SEND_TEST_TO above\n";
    echo "</pre>"; exit;
}

// Step 3: Try sending
echo "\n=== Step 3: Sending Test Email ===\n";
echo "From: $YOUR_GMAIL\n";
echo "To:   $SEND_TEST_TO\n\n";

try {
    $mail = new PHPMailer(true);

    // Debug output — shows every SMTP step
    $mail->SMTPDebug  = SMTP::DEBUG_SERVER;
    $mail->Debugoutput = function($str, $level) {
        echo htmlspecialchars($str) . "\n";
    };

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $YOUR_GMAIL;
    $mail->Password   = $YOUR_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($YOUR_GMAIL, 'TamizhMart Test');
    $mail->addAddress($SEND_TEST_TO);

    $mail->isHTML(true);
    $mail->Subject = 'TamizhMart — Test Email ✅';
    $mail->Body    = '<h2 style="color:green">It works!</h2><p>Your TamizhMart email is set up correctly.</p>';
    $mail->AltBody = 'It works! TamizhMart email is set up correctly.';

    $mail->send();
    echo "\n✅ SUCCESS — Check your inbox at $SEND_TEST_TO\n";

} catch (Exception $e) {
    echo "\n❌ FAILED — Error: " . $mail->ErrorInfo . "\n";
    echo "\nCommon fixes:\n";
    echo "  - Wrong App Password? Go to myaccount.google.com/apppasswords and create a new one\n";
    echo "  - 2-Step Verification not on? Enable it at myaccount.google.com/security\n";
    echo "  - Using your normal Gmail password? Must use App Password, not your login password\n";
    echo "  - XAMPP blocking port 587? Try changing Port to 465 and SMTPSecure to PHPMailer::ENCRYPTION_SMTPS\n";
}

echo "</pre>";
echo "<br><b style='color:red'>⚠️ DELETE this file from your server after testing!</b>";
?>