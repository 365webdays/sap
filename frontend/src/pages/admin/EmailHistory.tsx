import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Loader2, Mail } from "lucide-react";
import AdminLayout from "@/components/AdminLayout";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { adminApi, type EmailHistoryEntry, type Pagination } from "@/api/admin";
import { ApiRequestError } from "@/api/client";

const GROUP_LABELS: Record<string, string> = {
  all: "All",
  active: "Active",
  inactive: "Inactive",
  missed: "Missed",
};

export default function EmailHistory() {
  const [items, setItems] = useState<EmailHistoryEntry[]>([]);
  const [pagination, setPagination] = useState<Pagination | null>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await adminApi.emailHistory(page);
      setItems(result.items);
      setPagination(result.pagination);
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "Could not load email history.");
    } finally {
      setLoading(false);
    }
  }, [page]);

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <AdminLayout>
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle className="flex items-center gap-2">
              <Mail className="h-5 w-5" aria-hidden="true" />
              Email History
            </CardTitle>
            <Link to="/admin/email" className="text-sm text-accent hover:underline">
              Compose new &rarr;
            </Link>
          </div>
        </CardHeader>
        <CardContent>
          {error && <Alert className="mb-4">{error}</Alert>}

          {loading ? (
            <div className="flex items-center gap-2 text-muted">
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
              <span>Loading…</span>
            </div>
          ) : items.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted">No announcements have been sent yet.</p>
          ) : (
            <div className="space-y-3">
              {items.map((entry) => (
                <div
                  key={entry.id}
                  className="rounded-md border border-border p-4"
                >
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0 flex-1">
                      <h3 className="font-medium text-text">{entry.subject}</h3>
                      <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted">
                        <Badge variant="neutral">
                          {GROUP_LABELS[entry.recipient_group] ?? entry.recipient_group}
                        </Badge>
                        <span>{entry.sent_at}</span>
                        {entry.admin_name && <span>by {entry.admin_name}</span>}
                      </div>
                    </div>
                    <div className="text-right text-xs">
                      <div className="text-text">
                        {entry.sent_count} / {entry.recipient_count} sent
                      </div>
                      {entry.failed_count > 0 && (
                        <div className="text-red-600">{entry.failed_count} failed</div>
                      )}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}

          {pagination && pagination.total_pages > 1 && (
            <div className="mt-4 flex items-center justify-between">
              <span className="text-sm text-muted">Page {pagination.page} of {pagination.total_pages}</span>
              <div className="flex gap-2">
                <Button variant="outline" size="sm" onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={loading || page <= 1}>
                  Previous
                </Button>
                <Button variant="outline" size="sm" onClick={() => setPage((p) => Math.min(pagination.total_pages, p + 1))} disabled={loading || page >= pagination.total_pages}>
                  Next
                </Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </AdminLayout>
  );
}
