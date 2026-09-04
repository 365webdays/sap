<?php
/**
 * GET /api/admin/adorers?search=&status=&day=&slot=&page=1&per_page=20
 *
 * Paginated, searchable adorer roster.
 */

return function (): void {
    Auth::require(Token::ROLE_ADMIN);

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = AdminQuery::clampPerPage((int) ($_GET['per_page'] ?? 20));

    $filters = [
        'search' => (string) ($_GET['search'] ?? ''),
        'status' => (string) ($_GET['status'] ?? ''),
        'day' => (string) ($_GET['day'] ?? ''),
        'slot' => (string) ($_GET['slot'] ?? ''),
    ];

    $result = AdminQuery::adorers($filters, $perPage, ($page - 1) * $perPage);
    $totalPages = $result['total'] === 0 ? 1 : (int) ceil($result['total'] / $perPage);

    Response::success([
        'items' => $result['items'],
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $result['total'],
            'total_pages' => $totalPages,
        ],
        'filters' => $filters,
    ]);
};
