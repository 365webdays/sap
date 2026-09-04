<?php
/**
 * GET /api/adorer/attendance?page=1&per_page=20
 *
 * Paginated check-in history for the signed-in adorer.
 */

return function (): void {
    $user = Auth::require(Token::ROLE_ADORER);
    $userId = (int) $user['id'];

    // Clamp rather than reject: a bad page number should not be an error page.
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = (int) ($_GET['per_page'] ?? 20);
    $perPage = max(1, min(100, $perPage));

    $history = Attendance::history($userId, $perPage, ($page - 1) * $perPage);
    $totalPages = $history['total'] === 0 ? 1 : (int) ceil($history['total'] / $perPage);

    Response::success([
        'items' => $history['items'],
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $history['total'],
            'total_pages' => $totalPages,
        ],
    ]);
};
