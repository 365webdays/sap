import { useCallback, useEffect, useState } from "react";
import { Check, Download, Loader2, X } from "lucide-react";
import AdminLayout from "@/components/AdminLayout";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { adminApi, type MissedRecord } from "@/api/admin";
import { ApiRequestError, getStoredToken } from "@/api/client";

export default function MissedAttendancePage() {
  const [items, setItems] = useState<MissedRecord[]>([]);
  const [outstanding, setOutstanding] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [followedUp, setFollowedUp] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await adminApi.missed({
        from: from || undefined,
        to: to || undefined,
        followed_up: followedUp || undefined,
      });
      setItems(result.items);
      setOutstanding(result.outstanding);
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "Could not load missed attendance.");
    } finally {
      setLoading(false);
    }
  }, [from, to, followedUp]);

  useEffect(() => {
    void load();
  }, [load]);

  const toggleFollowUp = async (record: MissedRecord) => {
    const next = !record.followed_up;
    // Optimistic update so the toggle feels instant.
    setItems((prev) =>
      prev.map((r) =>
        r.user_id === record.user_id && r.schedule_id === record.schedule_id && r.missed_date === record.missed_date
          ? { ...r, followed_up: next }
          : r
      )
    );
    try {
      await adminApi.markFollowUp({
        user_id: record.user_id,
        schedule_id: record.schedule_id,
        missed_date: record.missed_date,
        followed_up: next,
      });
      setOutstanding((o) => o + (next ? -1 : 1));
    } catch {
      // Revert on failure.
      setItems((prev) =>
        prev.map((r) =>
          r.user_id === record.user_id && r.schedule_id === record.schedule_id && r.missed_date === record.missed_date
            ? { ...r, followed_up: record.followed_up }
            : r
        )
      );
      setError("Could not update follow-up status.");
    }
  };

  const handleExport = () => {
    const params: Record<string, string> = {};
    if (from) params.from = from;
    if (to) params.to = to;
    if (followedUp) params.followed_up = followedUp;

    const url = adminApi.exportUrl("missed", params);
    const token = getStoredToken();
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
        link.download = "missed-attendance.csv";
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
            <div>
              <CardTitle>Missed Attendance</CardTitle>
              <CardDescription>
                Adorers who did not check in during an assigned hour that has passed.
                {outstanding > 0 && (
                  <span className="ml-1 font-medium text-accent-dark">{outstanding} outstanding.</span>
                )}
              </CardDescription>
            </div>
            <Button variant="outline" size="sm" onClick={handleExport}>
              <Download className="h-4 w-4" aria-hidden="true" />
              Export CSV
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-3 gap-3">
            <div>
              <label className="mb-1 block text-xs text-muted">From</label>
              <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
            </div>
            <div>
              <label className="mb-1 block text-xs text-muted">To</label>
              <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
            </div>
            <div>
              <label className="mb-1 block text-xs text-muted">Followed Up</label>
              <Select value={followedUp} onChange={(e) => setFollowedUp(e.target.value)}>
                <option value="">All</option>
                <option value="no">Outstanding</option>
                <option value="yes">Followed up</option>
              </Select>
            </div>
          </div>

          {error && <Alert className="mt-4">{error}</Alert>}

          <div className="mt-4 overflow-x-auto">
            {loading ? (
              <div className="flex items-center gap-2 text-muted">
                <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                <span>Loading…</span>
              </div>
            ) : items.length === 0 ? (
              <p className="py-8 text-center text-sm text-muted">
                No missed hours in this range. Every assigned hour was covered.
              </p>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-left text-xs text-muted">
                    <th className="py-2 pr-4 font-medium">Missed Date</th>
                    <th className="py-2 pr-4 font-medium">Adorer</th>
                    <th className="py-2 pr-4 font-medium">Scheduled Hour</th>
                    <th className="py-2 pr-4 font-medium">Followed Up</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((r) => (
                    <tr
                      key={`${r.user_id}-${r.schedule_id}-${r.missed_date}`}
                      className="border-b border-border/50 last:border-0"
                    >
                      <td className="py-3 pr-4">{r.date_label}</td>
                      <td className="py-3 pr-4">
                        <div className="font-medium">{r.full_name}</div>
                        <div className="text-xs text-muted">{r.email}</div>
                      </td>
                      <td className="py-3 pr-4 text-xs">{r.day_of_week} at {r.time_label}</td>
                      <td className="py-3 pr-4">
                        <Button
                          variant={r.followed_up ? "default" : "outline"}
                          size="sm"
                          onClick={() => void toggleFollowUp(r)}
                        >
                          {r.followed_up ? (
                            <>
                              <Check className="h-3.5 w-3.5" aria-hidden="true" />
                              Done
                            </>
                          ) : (
                            <>
                              <X className="h-3.5 w-3.5" aria-hidden="true" />
                              Mark
                            </>
                          )}
                        </Button>
                        {r.followed_up_at && (
                          <div className="mt-1 text-xs text-muted">{r.followed_up_at}</div>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </CardContent>
      </Card>
    </AdminLayout>
  );
}
