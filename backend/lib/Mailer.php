<?php
/**
 * Outbound email via authenticated SMTP (PHPMailer).
 *
 * Sending is best-effort: a mail failure must never break the request that
 * triggered it (e.g. a registration should still succeed if the welcome email
 * bounces). Failures are logged and reported via the return value instead.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

class Mailer
{
    /** True when SMTP is configured well enough to attempt a send. */
    public static function isConfigured(): bool
    {
        return (env('SMTP_HOST', '') ?: '') !== ''
            && (env('SMTP_USER', '') ?: '') !== ''
            && (env('SMTP_PASS', '') ?: '') !== '';
    }

    /**
     * Send an HTML email. Returns true on success.
     *
     * @param string $htmlBody Full HTML body
     * @param string $textBody Plain-text alternative; derived from HTML if omitted
     */
    public static function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody = ''
    ): bool {
        if (!self::isConfigured()) {
            error_log('Mailer: SMTP is not configured; skipping send to ' . $toEmail);
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = env('SMTP_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = env('SMTP_USER');
            $mail->Password = env('SMTP_PASS');
            $mail->Port = (int) env('SMTP_PORT', '587');
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Timeout = 15;

            // 465 is implicit TLS; 587 (and anything else) negotiates STARTTLS.
            $mail->SMTPSecure = $mail->Port === 465
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;

            if ((env('SMTP_DEBUG', '') ?: '') !== '') {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = 'error_log';
            }

            $mail->setFrom(
                env('SMTP_FROM_EMAIL', 'noreply@stanthonyadoration.com'),
                env('SMTP_FROM_NAME', 'St. Anthony Adoration')
            );
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody !== ''
                ? $textBody
                : trim(html_entity_decode(strip_tags($htmlBody), ENT_QUOTES, 'UTF-8'));

            $mail->send();
            return true;
        } catch (MailException | Throwable $e) {
            error_log('Mailer: send to ' . $toEmail . ' failed: ' . $e->getMessage());
            return false;
        }
    }
}
