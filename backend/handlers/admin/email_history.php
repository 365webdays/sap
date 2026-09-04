<?php
/**
 * GET /api/admin/email/history?page=1&per_page=20
 *
 * Paginated log of past bulk announcements.
 */

return function (): void {
    Auth::require(Token::ROLE_ADMIN);

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = AdminQuery::clampPerPage((int) ($_GET['per_page'] ?? 20));
    $offset = ($page - 1) * $perPage;

    $db = Database::getConnection();

    $total = (int) $db->query('SELECT COUNT(*) FROM email_logs')->fetchColumn();

    $stmt = $db->prepare(
        "SELECT el.id, el.subject, el.recipient_group, el.recipient_count,
                el.sent_count, el.failed_count, el.sent_at,
                a.name AS admin_name
         FROM email_logs el
         LEFT JOIN admins a ON a.id = el.sent_by_admin_id
         ORDER BY el.sent_at DESC, el.id DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $stmt->execute();

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[] = [
            'id' => (int) $row['id'],
            'subject' => $row['subject'],
            'recipient_group' => $row['recipient_group'],
            'recipient_count' => (int) $row['recipient_count'],
            'sent_count' => (int) $row['sent_count'],
            'failed_count' => (int) $row['failed_count'],
            'sent_at' => $row['sent_at'],
            'admin_name' => $row['admin_name'],
        ];
    }

    $totalPages = $total === 0 ? 1 : (int) ceil($total / $perPage);

    Response::success([
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
        ],
    ]);
};
