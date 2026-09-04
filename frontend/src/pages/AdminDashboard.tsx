import { useCallback, useEffect, useState } from "react";
import { BarChart3, Grid3x3, Loader2, Users } from "lucide-react";
import AdminLayout from "@/components/AdminLayout";
import CheckinQrCode from "@/components/CheckinQrCode";
import PeakHeatmap from "@/components/PeakHeatmap";
import TrendChart from "@/components/TrendChart";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { adminApi, type AdminStats } from "@/api/admin";
import { ApiRequestError } from "@/api/client";

type Granularity = "day" | "week" | "month";

const SUMMARY_CARDS: { key: keyof AdminStats["overview"]; label: string }[] = [
  { key: "total_adorers", label: "Total Adorers" },
  { key: "active_adorers", label: "Active Adorers" },
  { key: "attendance_today", label: "Today's Attendance" },
  { key: "attendance_this_week", label: "This Week" },
  { key: "attendance_this_month", label: "This Month" },
  { key: "assigned_hours", label: "Assigned Hours" },
];

export default function AdminDashboard() {
  const [stats, setStats] = useState<AdminStats | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [granularity, setGranularity] = useState<Granularity>("day");

  const load = useCallback(async (g: Granularity) => {
    setLoading(true);
    setError(null);
    try {
      setStats(await adminApi.stats({ granularity: g, periods: g === "day" ? 30 : 12 }));
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "Could not load dashboard data.");
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load(granularity);
  }, [load, granularity]);

  return (
    <AdminLayout>
      {error && (
        <Alert className="mb-4">
          {error}
          <Button variant="outline" size="sm" className="ml-2" onClick={() => void load(granularity)}>
            Retry
          </Button>
        </Alert>
      )}

      {/* Summary cards */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        {SUMMARY_CARDS.map(({ key, label }) => (
          <Card key={key} className="p-4">
            <div className="text-2xl font-light text-accent">
              {stats?.overview[key] ?? "—"}
            </div>
            <div className="mt-0.5 text-xs text-muted">{label}</div>
          </Card>
        ))}
      </div>

      {/* Trend chart */}
      <Card className="mt-4">
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="flex items-center gap-2">
                <BarChart3 className="h-5 w-5" aria-hidden="true" />
                Attendance Trend
              </CardTitle>
              <CardDescription>Check-ins over time.</CardDescription>
            </div>
            <div className="flex gap-1">
              {(["day", "week", "month"] as const).map((g) => (
                <Button
                  key={g}
                  variant={granularity === g ? "default" : "outline"}
                  size="sm"
                  onClick={() => setGranularity(g)}
                >
                  {g === "day" ? "Daily" : g === "week" ? "Weekly" : "Monthly"}
                </Button>
              ))}
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="flex items-center gap-2 text-muted">
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
              <span>Loading…</span>
            </div>
          ) : stats ? (
            <TrendChart series={stats.trend.series} />
          ) : (
            <p className="text-sm text-muted">No data.</p>
          )}
        </CardContent>
      </Card>

      {/* Peak heatmap */}
      <Card className="mt-4">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Grid3x3 className="h-5 w-5" aria-hidden="true" />
            Peak Attendance Periods
          </CardTitle>
          <CardDescription>
            Check-in frequency by day and hour{stats ? ` (last ${stats.peak.days_back} days)` : ""}.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {stats ? <PeakHeatmap cells={stats.peak.cells} max={stats.peak.max} /> : null}
        </CardContent>
      </Card>

      {/* QR code */}
      <Card className="mt-4">
        <CardHeader>
          <CardTitle>Chapel Check-In QR Code</CardTitle>
          <CardDescription>
            Print and post at the chapel entrance. Adorers scan it to check in.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <CheckinQrCode />
        </CardContent>
      </Card>

      {/* Quick links */}
      <div className="mt-4 flex flex-wrap gap-2">
        <a
          href="/admin/adorers"
          className="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-sm font-medium text-text hover:bg-bg"
        >
          <Users className="h-4 w-4" aria-hidden="true" />
          View adorers
        </a>
        <Badge variant="neutral">
          {stats ? `${stats.overview.attendance_total} total check-ins` : ""}
        </Badge>
      </div>
    </AdminLayout>
  );
}
