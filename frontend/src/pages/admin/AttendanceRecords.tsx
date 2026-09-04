import { useCallback, useEffect, useState } from "react";
import { Download, Loader2 } from "lucide-react";
import AdminLayout from "@/components/AdminLayout";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { adminApi, type AdminAttendanceEntry, type Pagination } from "@/api/admin";
import { ApiRequestError, getStoredToken } from "@/api/client";
import { DAYS, TIME_SLOTS, slotLabel } from "@/lib/schedule";

export default function AttendanceRecords() {
  const [items, setItems] = useState<AdminAttendanceEntry[]>([]);
  const [pagination, setPagination] = useState<Pagination | null>(null);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [search, setSearch] = useState("");
  const [method, setMethod] = useState("");
  const [day, setDay] = useState("");
  const [slot, setSlot] = useState("");

  const [debouncedSearch, setDebouncedSearch] = useState("");
  useEffect(() => {
    const t = setTimeout(() => setDebouncedSearch(search), 300);
    return () => clearTimeout(t);
  }, [search]);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await adminApi.attendance({
        from: from || undefined,
        to: to || undefined,
        search: debouncedSearch || undefined,
        method: method || undefined,
        day: day || undefined,
        slot: slot || undefined,
        page,
        per_page: 25,
      });
      setItems(result.items);
      setPagination(result.pagination);
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "Could not load attendance records.");
    } finally {
      setLoading(false);
    }
  }, [from, to, debouncedSearch, method, day, slot, page]);

  useEffect(() => {
    void load();
  }, [load]);
  useEffect(() => setPage(1), [from, to, debouncedSearch, method, day, slot]);

  const handleExport = () => {
    const params: Record<string, string> = {};
    if (from) params.from = from;
    if (to) params.to = to;
    if (debouncedSearch) params.search = debouncedSearch;
    if (method) params.method = method;
    if (day) params.day = day;
    if (slot) params.slot = slot;

    const url = adminApi.exportUrl("attendance", params);
    const token = getStoredToken();
    // Fetch with the auth header, then trigger a download from the blob —
    // a plain <a href> would not carry the bearer token.
    fetch(`${import.meta.env.VITE_API_BASE_URL}${url}`, {
      headers: token ? { Authorization: `Bearer ${token}` } : {},
    })
      .then((r) => {
        if (!r.ok) throw new Error("Export failed");
        return r.blob();
      })
      .then((blob) => {
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = "attendance.csv";
        link.click();
        URL.revokeObjectURL(link.href);
      })
      .catch(() => setError("Could not download the export."));
  };

  return (
    <AdminLayout>
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle>Attendance Records</CardTitle>
            <Button variant="outline" size="sm" onClick={handleExport}>
              <Download className="h-4 w-4" aria-hidden="true" />
              Export CSV
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          {/* Filters */}
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div>
              <label className="mb-1 block text-xs text-muted">From</label>
              <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
            </div>
            <div>
              <label className="mb-1 block text-xs text-muted">To</label>
              <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
            </div>
            <div>
              <label className="mb-1 block text-xs text-muted">Search</label>
              <Input placeholder="Name or email" value={search} onChange={(e) => setSearch(e.target.value)} />
            </div>
            <div>
              <label className="mb-1 block text-xs text-muted">Method</label>
              <Select value={method} onChange={(e) => setMethod(e.target.value)}>
                <option value="">Any</option>
                <option value="manual">Manual</option>
                <option value="qr">QR</option>
              </Select>
            </div>
            <div>
              <label className="mb-1 block text-xs text-muted">Day</label>
              <Select value={day} onChange={(e) => setDay(e.target.value)}>
                <option value="">Any</option>
                {DAYS.map((d) => (
                  <option key={d} value={d}>{d}</option>
                ))}
              </Select>
            </div>
            <div>
              <label className="mb-1 block text-xs text-muted">Hour</label>
              <Select value={slot} onChange={(e) => setSlot(e.target.value)}>
                <option value="">Any</option>
                {TIME_SLOTS.map((s) => (
                  <option key={s} value={s}>{slotLabel(s)}</option>
                ))}
              </Select>
            </div>
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
              <p className="py-8 text-center text-sm text-muted">No check-ins match these filters.</p>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-left text-xs text-muted">
                    <th className="py-2 pr-4 font-medium">Date</th>
                    <th className="py-2 pr-4 font-medium">Time</th>
                    <th className="py-2 pr-4 font-medium">Adorer</th>
                    <th className="py-2 pr-4 font-medium">Method</th>
                    <th className="py-2 pr-4 font-medium">Scheduled Hour</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((entry) => (
                    <tr key={entry.id} className="border-b border-border/50 last:border-0">
                      <td className="py-3 pr-4">{entry.date_label}</td>
                      <td className="py-3 pr-4">{entry.time_label}</td>
                      <td className="py-3 pr-4">
                        <div className="font-medium">{entry.full_name}</div>
                        <div className="text-xs text-muted">{entry.email}</div>
                      </td>
                      <td className="py-3 pr-4">
                        <Badge variant="neutral">{entry.method === "qr" ? "QR" : "Manual"}</Badge>
                      </td>
                      <td className="py-3 pr-4 text-xs text-muted">
                        {entry.scheduled_hour ?? "Outside assigned hour"}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>

          {pagination && pagination.total_pages > 1 && (
            <div className="mt-4 flex items-center justify-between">
              <span className="text-sm text-muted">
                {pagination.total} records · Page {pagination.page} of {pagination.total_pages}
              </span>
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
