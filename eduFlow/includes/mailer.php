<?php

/**
 * EduFlow Mailer — PHPMailer wrapper
 */

require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody): array
{
  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host        = MAIL_HOST;
    $mail->SMTPAuth    = true;
    $mail->Username    = MAIL_USER;
    $mail->Password    = MAIL_PASS;
    $mail->SMTPSecure  = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port        = MAIL_PORT;
    $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];

    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress($toEmail, $toName);
    $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);

    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = $subject;
    $mail->Body    = emailWrap($subject, $htmlBody);
    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>', '</li>'], "\n", $htmlBody));

    $mail->send();
    return ['ok' => true];
  } catch (Exception $e) {
    return ['ok' => false, 'error' => $mail->ErrorInfo];
  }
}

function emailWrap(string $title, string $content): string
{
  // FIX 3: date() called as PHP, not as a short tag inside heredoc
  $year = date('Y');
  $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

  return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>{$titleEsc}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 16px;">
  <tr><td align="center">
    <table width="100%" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.07);">
      <tr>
        <td style="background:linear-gradient(135deg,#1e1b4b 0%,#312e81 100%);padding:28px 36px;text-align:center;">
          <div style="display:inline-flex;align-items:center;gap:10px;">
            <div style="width:38px;height:38px;background:rgba(255,255,255,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;">🎓</div>
            <span style="color:#ffffff;font-size:22px;font-weight:800;letter-spacing:-0.5px;">EduFlow</span>
          </div>
        </td>
      </tr>
      <tr>
        <td style="padding:36px 36px 28px;">
          {$content}
        </td>
      </tr>
      <tr>
        <td style="background:#f8fafc;padding:20px 36px;text-align:center;border-top:1px solid #e2e8f0;">
          <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.6;">
            This email was sent by EduFlow LMS. If you did not request this, please ignore it.<br>
            &copy; {$year} EduFlow. All rights reserved.
          </p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

// ── Email Templates ───────────────────────────────────────────

function mailVerificationWithCredentials(
  string $toEmail,
  string $toName,
  string $token,
  string $studentId,
  string $cnic,
  string $plainPassword
): array {
  $link = APP_URL . '/auth/verify-email.php?token=' . urlencode($token);
  $html = '<h2 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#1e293b;">Welcome to EduFlow! 🎓</h2>'
    . '<p style="margin:0 0 20px;font-size:15px;color:#475569;line-height:1.7;">Hi <strong>' . htmlspecialchars($toName) . '</strong>, your account has been created successfully! Please verify your email to activate it, and save your login credentials safely.</p>'
    . '<div style="text-align:center;margin:24px 0;"><a href="' . $link . '" style="display:inline-block;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;text-decoration:none;font-weight:700;font-size:15px;padding:14px 36px;border-radius:10px;">&#x2705; Verify My Email</a></div>'
    . '<div style="background:#f8fafc;border:2px solid #e2e8f0;border-radius:12px;padding:22px 24px;margin:24px 0;">'
    . '<div style="font-size:11px;font-weight:800;color:#94a3b8;letter-spacing:2px;text-transform:uppercase;margin-bottom:16px;">&#x1F510; Your Login Credentials</div>'
    . '<table style="width:100%;border-collapse:collapse;">'
    . '<tr><td style="padding:8px 0;font-size:13px;color:#64748b;font-weight:600;width:40%;">Student ID</td><td style="padding:8px 0;font-size:18px;font-weight:900;color:#4f46e5;font-family:\'Courier New\',monospace;letter-spacing:3px;">' . htmlspecialchars($studentId) . '</td></tr>'
    . '<tr style="border-top:1px solid #e2e8f0;"><td style="padding:8px 0;font-size:13px;color:#64748b;font-weight:600;">Email</td><td style="padding:8px 0;font-size:13px;font-weight:700;color:#1e293b;">' . htmlspecialchars($toEmail) . '</td></tr>'
    . '<tr style="border-top:1px solid #e2e8f0;"><td style="padding:8px 0;font-size:13px;color:#64748b;font-weight:600;">CNIC</td><td style="padding:8px 0;font-size:13px;font-weight:700;color:#1e293b;">' . htmlspecialchars($cnic) . '</td></tr>'
    . '<tr style="border-top:1px solid #e2e8f0;"><td style="padding:8px 0;font-size:13px;color:#64748b;font-weight:600;">Password</td><td style="padding:8px 0;font-size:13px;font-weight:700;color:#1e293b;font-family:\'Courier New\',monospace;">' . htmlspecialchars($plainPassword) . '</td></tr>'
    . '</table></div>'
    . '<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:14px 18px;font-size:13px;color:#9a3412;margin-bottom:16px;">&#x26A0;&#xFE0F; <strong>Keep this email safe.</strong> These are your personal login credentials. Do not share them with anyone.</div>'
    . '<p style="font-size:12px;color:#94a3b8;text-align:center;margin:0;">Verification link expires in <strong>24 hours</strong>. Button not working? <a href="' . $link . '" style="color:#6366f1;">Click here</a></p>';

  return sendMail($toEmail, $toName, 'Your EduFlow Account — Login Credentials', $html);
}

function mailVerification(string $toEmail, string $toName, string $token): array
{
  $link = APP_URL . '/auth/verify-email.php?token=' . urlencode($token);
  $html = '<h2 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#1e293b;">Verify your email address &#x1F4E7;</h2>'
    . '<p style="margin:0 0 20px;font-size:15px;color:#475569;line-height:1.7;">Hi <strong>' . htmlspecialchars($toName) . '</strong>, welcome to EduFlow! Please verify your email to activate your account.</p>'
    . '<div style="text-align:center;margin:28px 0;"><a href="' . $link . '" style="display:inline-block;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;text-decoration:none;font-weight:700;font-size:15px;padding:14px 36px;border-radius:10px;">&#x2705; Verify My Email</a></div>'
    . '<p style="margin:0 0 8px;font-size:13px;color:#94a3b8;text-align:center;">Button not working? Copy and paste this link:</p>'
    . '<p style="margin:0 0 20px;font-size:12px;color:#6366f1;text-align:center;word-break:break-all;">' . $link . '</p>'
    . '<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:14px 18px;font-size:13px;color:#9a3412;">&#x26A0;&#xFE0F; This link expires in <strong>24 hours</strong>. If you did not create an account, you can safely ignore this email.</div>';

  return sendMail($toEmail, $toName, 'Verify your EduFlow account', $html);
}

function mailPasswordOTP(string $toEmail, string $toName, string $otp): array
{
  $html = '<h2 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#1e293b;">Reset your password &#x1F510;</h2>'
    . '<p style="margin:0 0 20px;font-size:15px;color:#475569;line-height:1.7;">Hi <strong>' . htmlspecialchars($toName) . '</strong>, we received a request to reset your EduFlow password. Use the code below — it\'s valid for <strong>15 minutes</strong>.</p>'
    . '<div style="text-align:center;margin:28px 0;"><div style="display:inline-block;background:#f8fafc;border:2px dashed #6366f1;border-radius:14px;padding:20px 40px;">'
    . '<div style="font-size:11px;font-weight:700;color:#94a3b8;letter-spacing:2px;text-transform:uppercase;margin-bottom:8px;">Your Reset Code</div>'
    . '<div style="font-size:40px;font-weight:900;color:#4f46e5;letter-spacing:10px;font-family:\'Courier New\',monospace;">' . htmlspecialchars($otp) . '</div>'
    . '</div></div>'
    . '<p style="margin:0 0 20px;font-size:14px;color:#475569;text-align:center;line-height:1.7;">Enter this code on the password reset page.<br>Do <strong>not</strong> share this code with anyone.</p>'
    . '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:14px 18px;font-size:13px;color:#991b1b;">&#x1F6A8; If you did not request a password reset, your account may be at risk. <a href="mailto:' . MAIL_FROM . '" style="color:#991b1b;font-weight:700;">Contact support immediately.</a></div>';

  return sendMail($toEmail, $toName, 'Your EduFlow password reset code', $html);
}

function mailApplicationReceived(string $toEmail, string $toName, string $studentId): array
{
  $applyUrl = APP_URL . '/auth/login.php';
  $html = '<h2 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#1e293b;">Email Verified — Next Step &#x1F4CB;</h2>'
    . '<p style="margin:0 0 20px;font-size:15px;color:#475569;line-height:1.7;">Hi <strong>' . htmlspecialchars($toName) . '</strong>, your email address has been successfully verified! Your EduFlow account is registered but not yet active.</p>'
    . '<div style="background:#f8fafc;border:2px solid #e2e8f0;border-radius:12px;padding:18px 24px;margin:20px 0;">'
    . '<div style="font-size:11px;font-weight:800;color:#94a3b8;letter-spacing:2px;text-transform:uppercase;margin-bottom:10px;">Your Student ID</div>'
    . '<div style="font-size:32px;font-weight:900;color:#4f46e5;font-family:\'Courier New\',monospace;letter-spacing:6px;">' . htmlspecialchars($studentId) . '</div>'
    . '</div>'
    . '<div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:18px 22px;margin:20px 0;">'
    . '<div style="font-size:14px;font-weight:700;color:#9a3412;margin-bottom:10px;">&#x26A0;&#xFE0F; What happens next?</div>'
    . '<ol style="margin:0;padding:0 0 0 18px;font-size:13px;color:#92400e;line-height:2;">'
    . '<li>Visit the EduFlow login page</li>'
    . '<li>Click <strong>"Apply for a Course"</strong></li>'
    . '<li>Select the course you want to join</li>'
    . '<li>Attempt the entry test when it becomes available</li>'
    . '<li>Admin will review your result and notify you by email</li>'
    . '</ol></div>'
    . '<div style="text-align:center;margin:28px 0;"><a href="' . $applyUrl . '" style="display:inline-block;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;text-decoration:none;font-weight:700;font-size:15px;padding:14px 36px;border-radius:10px;">Apply for a Course &rarr;</a></div>'
    . '<div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:14px 18px;font-size:13px;color:#0c4a6e;">&#x1F4A1; You cannot log in to EduFlow until your course application is approved by an admin.</div>';

  return sendMail($toEmail, $toName, 'EduFlow — Email Verified! Apply for a Course Next', $html);
}

function mailApplicationApproved(string $toEmail, string $toName, string $courseName): array
{
  // FIX 1: subject used single quotes — apostrophe in "You're" was breaking it
  $link = APP_URL . '/auth/login.php';
  $html = '<h2 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#1e293b;">You\'re Approved! &#x1F389;</h2>'
    . '<p style="margin:0 0 20px;font-size:15px;color:#475569;line-height:1.7;">Congratulations <strong>' . htmlspecialchars($toName) . '</strong>! Your application for <strong style="color:#4f46e5;">' . htmlspecialchars($courseName) . '</strong> has been reviewed and approved. Your EduFlow account is now fully active.</p>'
    . '<div style="background:#f0fdf4;border:2px solid #bbf7d0;border-radius:12px;padding:22px 24px;margin:24px 0;text-align:center;">'
    . '<div style="font-size:3rem;margin-bottom:10px;">&#x1F3C6;</div>'
    . '<div style="font-size:16px;font-weight:800;color:#166534;">Welcome to EduFlow!</div>'
    . '<div style="font-size:13px;color:#15803d;margin-top:6px;">You can now log in and access your course materials.</div>'
    . '</div>'
    . '<div style="text-align:center;margin:28px 0;"><a href="' . $link . '" style="display:inline-block;background:linear-gradient(135deg,#059669,#10b981);color:#fff;text-decoration:none;font-weight:700;font-size:15px;padding:14px 36px;border-radius:10px;">&#x1F680; Login to EduFlow</a></div>'
    . '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 18px;font-size:13px;color:#475569;">&#x1F4DA; You can log in using your <strong>Student ID</strong> or <strong>Email</strong> and your registered password.</div>';

  // FIX 1: was 'EduFlow — Application Approved! You're In 🎉' — apostrophe broke single-quoted string
  return sendMail($toEmail, $toName, 'EduFlow — Application Approved! You\'re In', $html);
}

function mailApplicationRejected(string $toEmail, string $toName, string $courseName, string $reason = ''): array
{
  // FIX 2: $reasonBlock was using double quotes inside a double-quoted string — PHP syntax error
  // Now built with concatenation so no quote conflicts
  $reapplyUrl = APP_URL . '/auth/login.php';

  if ($reason) {
    $reasonBlock = '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:16px 20px;margin:20px 0;">'
      . '<div style="font-size:12px;font-weight:700;color:#991b1b;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Reason from Admin</div>'
      . '<div style="font-size:14px;color:#7f1d1d;line-height:1.7;">' . htmlspecialchars($reason) . '</div>'
      . '</div>';
  } else {
    $reasonBlock = '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:16px 20px;margin:20px 0;font-size:13px;color:#991b1b;">'
      . 'No specific reason was provided. Please contact admin for more details.'
      . '</div>';
  }

  $html = '<h2 style="margin:0 0 8px;font-size:22px;font-weight:800;color:#1e293b;">Application Update &#x1F4CB;</h2>'
    . '<p style="margin:0 0 20px;font-size:15px;color:#475569;line-height:1.7;">Hi <strong>' . htmlspecialchars($toName) . '</strong>, after reviewing your entry test result for <strong style="color:#4f46e5;">' . htmlspecialchars($courseName) . '</strong>, we regret to inform you that your application has not been approved at this time.</p>'
    . $reasonBlock
    . '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:18px 22px;margin:20px 0;">'
    . '<div style="font-size:14px;font-weight:700;color:#1e293b;margin-bottom:8px;">What can you do?</div>'
    . '<ul style="margin:0;padding:0 0 0 18px;font-size:13px;color:#475569;line-height:2.2;">'
    . '<li>Prepare and re-apply when the next batch opens</li>'
    . '<li>Contact your admin for guidance on improvement</li>'
    . '<li>Your account remains registered — no need to re-register</li>'
    . '</ul></div>'
    . '<div style="text-align:center;margin:28px 0;"><a href="' . $reapplyUrl . '" style="display:inline-block;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 30px;border-radius:10px;">Re-apply for Next Batch</a></div>'
    . '<div style="font-size:12px;color:#94a3b8;text-align:center;">We encourage you to keep trying. Many successful students didn\'t make it on their first attempt.</div>';

  return sendMail($toEmail, $toName, 'EduFlow — Application Status Update', $html);
}
