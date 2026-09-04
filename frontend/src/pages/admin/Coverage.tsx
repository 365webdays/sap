import { useEffect, useState } from "react";
import { Loader2 } from "lucide-react";
import AdminLayout from "@/components/AdminLayout";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { adminApi, type CoverageSlot } from "@/api/admin";
import { ApiRequestError } from "@/api/client";
import { DAYS } from "@/lib/schedule";

export default function CoveragePage() {
  const [slots, setSlots] = useState<CoverageSlot[]>([]);
  const [gapCount, setGapCount] = useState(0);
  const [totalSlots, setTotalSlots] = useState(0);
  const [coveredSlots, setCoveredSlots] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [onlyGaps, setOnlyGaps] = useState(false);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      setLoading(true);
      setError(null);
      try {
        const result = await adminApi.coverage(onlyGaps);
        if (cancelled) return;
        setSlots(result.slots);
        setGapCount(result.gap_count);
        setTotalSlots(result.total_slots);
        setCoveredSlots(result.covered_slots);
      } catch (err) {
        if (!cancelled) {
          setError(err instanceof ApiRequestError ? err.message : "Could not load coverage.");
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, [onlyGaps]);

  // Group slots by day for display.
  const byDay = DAYS.map((day) => ({
    day,
    slots: slots.filter((s) => s.day_of_week === day),
  }));

  return (
    <AdminLayout>
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>Adoration Hours Coverage</CardTitle>
              <CardDescription>
                {coveredSlots} of {totalSlots} hours covered ·{" "}
                <span className="text-accent-dark">{gapCount} gap{gapCount === 1 ? "" : "s"}</span>
              </CardDescription>
            </div>
            <label className="flex cursor-pointer items-center gap-2 text-sm text-muted">
              <input
                type="checkbox"
                checked={onlyGaps}
                onChange={(e) => setOnlyGaps(e.target.checked)}
                className="h-4 w-4 rounded border-border"
              />
              Gaps only
            </label>
          </div>
        </CardHeader>
        <CardContent>
          {error && <Alert className="mb-4">{error}</Alert>}

          {loading ? (
            <div className="flex items-center gap-2 text-muted">
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
              <span>Loading…</span>
            </div>
          ) : (
            <div className="space-y-6">
              {byDay.map(({ day, slots: daySlots }) => (
                <div key={day}>
                  <h3 className="mb-2 text-sm font-medium text-text">{day}</h3>
                  {daySlots.length === 0 ? (
                    <p className="text-xs text-muted">No {onlyGaps ? "gaps" : "hours"} to show.</p>
                  ) : (
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                      {daySlots.map((slot) => (
                        <div
                          key={`${slot.day_of_week}-${slot.time_slot}`}
                          className={`rounded-md border p-3 ${
                            slot.active_count === 0
                              ? "border-red-200 bg-red-50"
                              : "border-border bg-surface"
                          }`}
                        >
                          <div className="flex items-center justify-between">
                            <span className="text-sm font-medium">{slot.time_label}</span>
                            {slot.active_count === 0 ? (
                              <Badge variant="neutral" className="bg-red-100 text-red-700">Gap</Badge>
                            ) : (
                              <Badge>{slot.active_count} adorer{slot.active_count === 1 ? "" : "s"}</Badge>
                            )}
                          </div>
                          {slot.adorers.length > 0 && (
                            <ul className="mt-2 space-y-0.5 text-xs text-muted">
                              {slot.adorers.map((a) => (
                                <li key={a.user_id} className={!a.is_active ? "opacity-50" : ""}>
                                  {a.full_name}{!a.is_active && " (inactive)"}
                                </li>
                              ))}
                            </ul>
                          )}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </AdminLayout>
  );
}
