<?php

namespace App\Services;

use App\Core\Logger;

/**
 * MailService — wrapper around PHPMailer with a graceful fallback.
 *
 * When SMTP is unavailable (free hosting, blocked ports) the message is
 * logged and the caller receives ok=false so it can surface a development
 * fallback instead of failing the whole request.
 */
class MailService
{
    /** Locate the bundled PHPMailer library. */
    private static function bootstrap()
    {
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return true;
        }

        $candidates = [
            ROOT_PATH . '/vendor/PHPMailer/src/PHPMailer.php',
            ROOT_PATH . '/app/Vendor/PHPMailer/src/PHPMailer.php',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                require_once dirname($path) . '/Exception.php';
                require_once $path;
                require_once dirname($path) . '/SMTP.php';
                return class_exists('PHPMailer\\PHPMailer\\PHPMailer');
            }
        }

        return false;
    }

    /**
     * Send an HTML email.
     * @return array{ok:bool,error?:string}
     */
    public static function send($to, $toName, $subject, $htmlBody, $altBody = '')
    {
        if (!self::bootstrap() || MAIL_USER === '') {
            Logger::warning('Mail skipped (no transport) to=' . $to . ' subject=' . $subject);
            return ['ok' => false, 'error' => 'Mail transport is not configured.'];
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USER;
            $mail->Password = MAIL_PASS;
            $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = MAIL_PORT;
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 12;

            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($to, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $altBody !== '' ? $altBody : strip_tags($htmlBody);

            $mail->send();
            return ['ok' => true];
        } catch (\Throwable $e) {
            Logger::error('Mail failed to=' . $to . ' error=' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Shared responsive shell so every message looks consistent. */
    public static function wrap($heading, $bodyHtml, $footer = '')
    {
        return '<!DOCTYPE html><html><body style="margin:0;padding:24px;background:#edf0f5;'
            . 'font-family:Inter,Segoe UI,Arial,sans-serif;color:#1d2939">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">'
            . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" '
            . 'style="max-width:560px;background:#fff;border:1px solid #dfe4ec;border-radius:8px">'
            . '<tr><td style="padding:22px 24px;border-bottom:1px solid #dfe4ec">'
            . '<span style="display:inline-block;width:30px;height:30px;background:#2258b8;color:#fff;'
            . 'border-radius:7px;text-align:center;line-height:30px;font-weight:700;font-size:13px">EF</span>'
            . '<span style="margin-left:10px;font-weight:600;font-size:15px">' . e(APP_NAME) . '</span>'
            . '</td></tr>'
            . '<tr><td style="padding:24px">'
            . '<h1 style="margin:0 0 12px;font-size:18px;color:#101828">' . e($heading) . '</h1>'
            . $bodyHtml
            . '</td></tr>'
            . '<tr><td style="padding:16px 24px;border-top:1px solid #dfe4ec;color:#667085;font-size:12px">'
            . ($footer !== '' ? $footer : 'This message was sent by ' . e(APP_NAME) . '.')
            . '</td></tr></table></td></tr></table></body></html>';
    }

    public static function button($url, $label)
    {
        return '<a href="' . e($url) . '" style="display:inline-block;margin:8px 0 16px;padding:11px 18px;'
            . 'background:#2258b8;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px">'
            . e($label) . '</a>';
    }

    /* ── Message templates ───────────────────────────────────── */

    public static function verificationEmail($name, $token, $studentId)
    {
        $link = url('/verify-email', ['token' => $token]);
        $body = '<p style="margin:0 0 12px;font-size:14px;line-height:1.6">Hello ' . e($name) . ',</p>'
            . '<p style="margin:0 0 12px;font-size:14px;line-height:1.6">Your ' . e(APP_NAME)
            . ' account has been created. Please confirm your email address to activate it.</p>'
            . '<p style="margin:0 0 8px;font-size:14px"><strong>Student ID:</strong> ' . e($studentId) . '</p>'
            . self::button($link, 'Verify my email address')
            . '<p style="margin:0;font-size:12px;color:#667085">If the button does not work, copy this link:<br>'
            . e($link) . '</p>';

        return self::wrap('Confirm your email address', $body,
            'This link expires in ' . TOKEN_EXPIRY_HOURS . ' hours.');
    }

    public static function passwordOtp($name, $otp)
    {
        $body = '<p style="margin:0 0 12px;font-size:14px;line-height:1.6">Hello ' . e($name) . ',</p>'
            . '<p style="margin:0 0 12px;font-size:14px;line-height:1.6">Use the code below to reset your password.</p>'
            . '<p style="margin:0 0 12px;font-size:28px;letter-spacing:6px;font-weight:700;color:#2258b8">'
            . e($otp) . '</p>'
            . '<p style="margin:0;font-size:12px;color:#667085">The code expires in ' . OTP_EXPIRY_MINUTES . ' minutes.</p>';

        return self::wrap('Your password reset code', $body);
    }

    public static function applicationApproved($name, $courseTitle, $batchName)
    {
        $body = '<p style="margin:0 0 12px;font-size:14px;line-height:1.6">Hello ' . e($name) . ',</p>'
            . '<p style="margin:0 0 12px;font-size:14px;line-height:1.6">Your application has been approved and you are now '
            . 'enrolled in <strong>' . e($courseTitle) . '</strong> (' . e($batchName) . ').</p>'
            . self::button(url('/login'), 'Sign in to the portal');

        return self::wrap('Your application was approved', $body);
    }

    public static function applicationRejected($name, $courseTitle, $reason)
    {
        $body = '<p style="margin:0 0 12px;font-size:14px;line-height:1.6">Hello ' . e($name) . ',</p>'
            . '<p style="margin:0 0 12px;font-size:14px;line-height:1.6">After reviewing your entry test, your application '
            . 'for <strong>' . e($courseTitle) . '</strong> was not approved.</p>'
            . ($reason !== ''
                ? '<p style="margin:0 0 12px;font-size:14px;line-height:1.6"><strong>Reason:</strong> ' . e($reason) . '</p>'
                : '');

        return self::wrap('Application update', $body);
    }
}

