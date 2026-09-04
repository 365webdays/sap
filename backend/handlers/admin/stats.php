<?php
/**
 * GET /api/admin/stats?granularity=day&periods=30&peak_days=90
 *
 * Summary cards, attendance trend series, and the peak-periods heatmap for the
 * admin dashboard.
 */

return function (): void {
    Auth::require(Token::ROLE_ADMIN);

    $now = new DateTimeImmutable('now');

    $granularity = (string) ($_GET['granularity'] ?? 'day');
    if (!in_array($granularity, ['day', 'week', 'month'], true)) {
        $granularity = 'day';
    }

    // Sensible span per granularity when the client does not ask for one.
    $defaultPeriods = ['day' => 30, 'week' => 12, 'month' => 12][$granularity];
    $periods = (int) ($_GET['periods'] ?? $defaultPeriods);
    $peakDays = (int) ($_GET['peak_days'] ?? 90);

    Response::success([
        'overview' => AdminStats::overview($now),
        'trend' => [
            'granularity' => $granularity,
            'series' => AdminStats::trend($now, $granularity, $periods),
        ],
        'peak' => AdminStats::peakPeriods($now, max(1, min(365, $peakDays))),
        'generated_at' => $now->format('c'),
    ]);
};
