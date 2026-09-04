import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { Loader2, Search } from "lucide-react";
import AdminLayout from "@/components/AdminLayout";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { adminApi, type AdorerSummary, type Pagination } from "@/api/admin";
import { ApiRequestError } from "@/api/client";
import { DAYS, TIME_SLOTS, slotLabel } from "@/lib/schedule";

export default function AdorerRoster() {
  const [items, setItems] = useState<AdorerSummary[]>([]);
  const [pagination, setPagination] = useState<Pagination | null>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [day, setDay] = useState("");
  const [slot, setSlot] = useState("");

  // Debounce search so typing does not fire a request per keystroke.
  const [debouncedSearch, setDebouncedSearch] = useState("");
  useEffect(() => {
    const t = setTimeout(() => setDebouncedSearch(search), 300);
    return () => clearTimeout(t);
  }, [search]);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await adminApi.adorers({
        search: debouncedSearch || undefined,
        status: status || undefined,
        day: day || undefined,
        slot: slot || undefined,
        page,
        per_page: 20,
      });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "Could not load adorers.");
    } finally {
      setLoading(false);
    }
  }, [debouncedSearch, status, day, slot, page]);

  useEffect(() => {
    void load();
  }, [load]);

  // Reset to page 1 when filters change.
  useEffect(() => setPage(1), [debouncedSearch, status, day, slot]);

  return (
    <AdminLayout>
      <Card>
        <CardHeader>
          <CardTitle>Adorer Roster</CardTitle>
        </CardHeader>
        <CardContent>
          {/* Filters */}
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" aria-hidden="true" />
              <Input
                placeholder="Search name, email, mobile"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="pl-9"
              />
            </div>
            <Select value={status} onChange={(e) => setStatus(e.target.value)}>
              <option value="">All statuses</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </Select>
            <Select value={day} onChange={(e) => setDay(e.target.value)}>
              <option value="">Any day</option>
              {DAYS.map((d) => (
                <option key={d} value={d}>{d}</option>
              ))}
            </Select>
            <Select value={slot} onChange={(e) => setSlot(e.target.value)}>
              <option value="">Any hour</option>
              {TIME_SLOTS.map((s) => (
                <option key={s} value={s}>{slotLabel(s)}</option>
              ))}
            </Select>
          </div>

          {error && <Alert className="mt-4">{error}</Alert>}

          {/* Table */}
          <div className="mt-4 overflow-x-auto">
            {loading ? (
              <div className="flex items-center gap-2 text-muted">
                <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                <span>Loading…</span>
              </div>
            ) : items.length === 0 ? (
              <p className="py-8 text-center text-sm text-muted">No adorers match these filters.</p>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-left text-xs text-muted">
                    <th className="py-2 pr-4 font-medium">Name</th>
                    <th className="py-2 pr-4 font-medium">Assigned Hours</th>
                    <th className="py-2 pr-4 font-medium">Check-Ins</th>
                    <th className="py-2 pr-4 font-medium">Last</th>
                    <th className="py-2 pr-4 font-medium">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((adorer) => (
                    <tr key={adorer.id} className="border-b border-border/50 last:border-0">
                      <td className="py-3 pr-4">
                        <Link
                          to={`/admin/adorers/${adorer.id}`}
                          className="font-medium text-text hover:text-accent"
                        >
                          {adorer.full_name}
                        </Link>
                        <div className="text-xs text-muted">{adorer.email}</div>
                      </td>
                      <td className="py-3 pr-4">
                        {adorer.schedules.length === 0 ? (
                          <span className="text-muted">—</span>
                        ) : (
                          <span className="text-xs">{adorer.schedules.map((s) => s.label).join(", ")}</span>
                        )}
                      </td>
                      <td className="py-3 pr-4">{adorer.check_in_count}</td>
                      <td className="py-3 pr-4 text-xs text-muted">
                        {adorer.last_check_in ?? "Never"}
                      </td>
                      <td className="py-3 pr-4">
                        <Badge variant={adorer.is_active ? "default" : "neutral"}>
                          {adorer.is_active ? "Active" : "Inactive"}
                        </Badge>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          {/* Pagination */}
          {pagination && pagination.total_pages > 1 && (
            <div className="mt-4 flex items-center justify-between">
              <span className="text-sm text-muted">
                {pagination.total} adorer{pagination.total === 1 ? "" : "s"} · Page {pagination.page} of {pagination.total_pages}
              </span>
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={loading || page <= 1}
                >
                  Previous
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setPage((p) => Math.min(pagination.total_pages, p + 1))}
                  disabled={loading || page >= pagination.total_pages}
                >
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
