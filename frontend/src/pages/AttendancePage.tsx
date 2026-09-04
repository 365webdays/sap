import { useCallback, useEffect, useState } from "react";
import { Loader2 } from "lucide-react";
import AdorerLayout from "@/components/AdorerLayout";
import AttendanceList from "@/components/AttendanceList";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { adorerApi, type AttendanceEntry, type Pagination } from "@/api/adorer";
import { ApiRequestError } from "@/api/client";

const PER_PAGE = 20;

export default function AttendancePage() {
  const [entries, setEntries] = useState<AttendanceEntry[]>([]);
  const [pagination, setPagination] = useState<Pagination | null>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async (targetPage: number) => {
    setLoading(true);
    setError(null);
    try {
      const result = await adorerApi.attendance(targetPage, PER_PAGE);
      setEntries(result.items);
      setPagination(result.pagination);
    } catch (err) {
      setError(
        err instanceof ApiRequestError ? err.message : "Could not load your attendance history."
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load(page);
  }, [load, page]);

  return (
    <AdorerLayout>
      <Card>
        <CardHeader>
          <CardTitle>Attendance History</CardTitle>
          <CardDescription>
            {pagination
              ? `${pagination.total} check-in${pagination.total === 1 ? "" : "s"} recorded.`
              : "Your complete check-in record."}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {error && <Alert className="mb-4">{error}</Alert>}

          {loading ? (
            <div className="flex items-center gap-2 text-muted">
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
              <span aria-live="polite">Loading…</span>
            </div>
          ) : (
            <AttendanceList
              entries={entries}
              emptyMessage="You have no recorded check-ins yet."
            />
          )}

          {pagination && pagination.total_pages > 1 && (
            <div className="mt-6 flex items-center justify-between gap-3">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={loading || page <= 1}
              >
                Previous
              </Button>
              <span className="text-sm text-muted">
                Page {pagination.page} of {pagination.total_pages}
              </span>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setPage((p) => Math.min(pagination.total_pages, p + 1))}
                disabled={loading || page >= pagination.total_pages}
              >
                Next
              </Button>
            </div>
          )}
        </CardContent>
      </Card>
    </AdorerLayout>
  );
}
