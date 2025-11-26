<?php
require_once __DIR__ . '/utilities.php';

// 你自己收得到的邮箱地址
$testRecipient = "wanghaoran030401@163.com";

$success = send_email(
    $testRecipient,
    "Test Email from PHP",
    "<h1>Test Successful</h1><p>This is a test email sent via PHPMailer + Gmail SMTP.</p>"
);

if ($success) {
    echo "Email Sent Successfully to $testRecipient";
} else {
    echo "Email Sent Failed";
}
