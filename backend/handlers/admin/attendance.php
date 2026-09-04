<?php
/**
 * GET /api/admin/attendance?from=&to=&search=&method=&day=&slot=&page=&per_page=
 *
 * All check-ins across all adorers, filtered and paginated.
 */

return function (): void {
    Auth::require(Token::ROLE_ADMIN);

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = AdminQuery::clampPerPage((int) ($_GET['per_page'] ?? 25));

    $filters = [
        'from' => (string) ($_GET['from'] ?? ''),
        'to' => (string) ($_GET['to'] ?? ''),
        'search' => (string) ($_GET['search'] ?? ''),
        'method' => (string) ($_GET['method'] ?? ''),
        'day' => (string) ($_GET['day'] ?? ''),
        'slot' => (string) ($_GET['slot'] ?? ''),
    ];

    $result = AdminQuery::attendance($filters, $perPage, ($page - 1) * $perPage);
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
