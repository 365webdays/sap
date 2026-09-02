<?php
/**
 * HTML email bodies.
 *
 * Kept as inline-styled tables because email clients (Outlook especially)
 * ignore stylesheets and modern layout. All interpolated values must be
 * escaped by the caller-facing methods here, never by the templates' callers.
 */

class EmailTemplate
{
    private const ACCENT = '#679b08';
    private const TEXT = '#2d2d2d';
    private const MUTED = '#6b6375';
    private const BORDER = '#e5e4e7';

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /** Shared shell: header, body slot, footer. */
    private static function layout(string $heading, string $innerHtml): string
    {
        $baseUrl = self::e(env('APP_BASE_URL', 'https://stanthonyadoration.com'));
        $heading = self::e($heading);
        $accent = self::ACCENT;
        $text = self::TEXT;
        $muted = self::MUTED;
        $border = self::BORDER;
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:24px 12px;background:#f7f7f5;font-family:Helvetica,Arial,sans-serif;color:{$text};">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid {$border};border-radius:8px;">
    <tr>
      <td style="padding:28px 32px 8px;text-align:center;border-bottom:1px solid {$border};">
        <div style="font-size:22px;font-weight:300;color:{$accent};">St. Anthony Adoration</div>
        <div style="font-size:13px;color:{$muted};padding-top:2px;">Chapel Registration &amp; Attendance</div>
      </td>
    </tr>
    <tr>
      <td style="padding:28px 32px;">
        <h1 style="margin:0 0 16px;font-size:19px;font-weight:500;color:{$text};">{$heading}</h1>
        {$innerHtml}
      </td>
    </tr>
    <tr>
      <td style="padding:16px 32px 24px;border-top:1px solid {$border};font-size:12px;color:{$muted};text-align:center;">
        <a href="{$baseUrl}" style="color:{$accent};text-decoration:none;">{$baseUrl}</a><br>
        &copy; {$year} St. Anthony of Padua Parish
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    /**
     * Welcome email sent after a successful registration.
     *
     * @return array{subject:string, html:string, text:string}
     */
    public static function welcome(string $fullName, string $dayOfWeek, string $timeSlot): array
    {
        $name = self::e($fullName);
        $day = self::e($dayOfWeek);
        $time = self::e(Schedule::label($timeSlot));
        $baseUrl = self::e(env('APP_BASE_URL', 'https://stanthonyadoration.com'));

        $accent = self::ACCENT;
        $text = self::TEXT;
        $muted = self::MUTED;
        $border = self::BORDER;

        $inner = <<<HTML
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:{$text};">
  Welcome, {$name}. Your registration for the Adoration Chapel is complete.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f7f7f5;border:1px solid {$border};border-radius:6px;margin:0 0 20px;">
  <tr>
    <td style="padding:16px 20px;">
      <div style="font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:{$muted};padding-bottom:6px;">Your adoration hour</div>
      <div style="font-size:17px;font-weight:500;color:{$accent};">{$day} &middot; {$time}</div>
    </td>
  </tr>
</table>

<p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:{$text};">
  You can sign in any time to check in for your hour, review your attendance
  history, and manage which notifications you receive.
</p>

<p style="margin:0 0 8px;">
  <a href="{$baseUrl}/login"
     style="display:inline-block;background:{$accent};color:#ffffff;text-decoration:none;font-size:15px;font-weight:500;padding:11px 22px;border-radius:6px;">
    Sign in
  </a>
</p>

<p style="margin:24px 0 0;font-size:14px;line-height:1.6;color:{$muted};">
  Thank you for giving your time to prayer before the Blessed Sacrament.
</p>
HTML;

        $textBody = "Welcome, {$fullName}.\n\n"
            . "Your registration for the Adoration Chapel is complete.\n\n"
            . "Your adoration hour: {$dayOfWeek} at " . Schedule::label($timeSlot) . "\n\n"
            . "Sign in any time to check in for your hour, review your attendance "
            . "history, and manage your notification preferences:\n"
            . env('APP_BASE_URL', 'https://stanthonyadoration.com') . "/login\n\n"
            . "Thank you for giving your time to prayer before the Blessed Sacrament.";

        return [
            'subject' => 'Welcome to St. Anthony Adoration',
            'html' => self::layout('Your registration is confirmed', $inner),
            'text' => $textBody,
        ];
    }
}
