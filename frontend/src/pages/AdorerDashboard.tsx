import { useCallback, useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { CalendarCheck, Clock, Loader2 } from "lucide-react";
import AdorerLayout from "@/components/AdorerLayout";
import AttendanceList from "@/components/AttendanceList";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { adorerApi, type AdorerDashboard as DashboardData } from "@/api/adorer";
import { ApiRequestError } from "@/api/client";

export default function AdorerDashboard() {
  const [data, setData] = useState<DashboardData | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [checkingIn, setCheckingIn] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [checkInError, setCheckInError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      setData(await adorerApi.dashboard());
      setLoadError(null);
    } catch (err) {
      setLoadError(
        err instanceof ApiRequestError ? err.message : "Could not load your dashboard."
      );
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const handleCheckIn = async () => {
    if (checkingIn) return;
    setCheckingIn(true);
    setCheckInError(null);
    setNotice(null);
    try {
      const result = await adorerApi.checkIn("manual");
      setNotice(result.message);
      await load();
    } catch (err) {
      // A 409 means an earlier check-in is still inside the duplicate window;
      // it is expected, not a failure worth alarming the user about.
      setCheckInError(
        err instanceof ApiRequestError ? err.message : "Could not record your check-in."
      );
      if (err instanceof ApiRequestError && err.status === 409) await load();
    } finally {
      setCheckingIn(false);
    }
  };

  if (loadError) {
    return (
      <AdorerLayout>
        <Alert>{loadError}</Alert>
        <Button variant="outline" className="mt-4" onClick={() => void load()}>
          Try again
        </Button>
      </AdorerLayout>
    );
  }

  if (!data) {
    return (
      <AdorerLayout>
        <div className="flex items-center gap-2 text-muted">
          <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
          <span aria-live="polite">Loading your dashboard…</span>
        </div>
      </AdorerLayout>
    );
  }

  const { schedules, last_check_in, recent_history, summary, check_in } = data;

  return (
    <AdorerLayout>
      {notice && (
        <Alert variant="info" className="mb-4">
          {notice}
        </Alert>
      )}

      {/* Check-in */}
      <Card>
        <CardHeader>
          <CardTitle>Check In</CardTitle>
          <CardDescription>
            {check_in.within_scheduled_hour && check_in.current_scheduled_hour
              ? `You are in your ${check_in.current_scheduled_hour.label} hour right now.`
              : "Record your visit to the chapel."}
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-3">
          {checkInError && <Alert>{checkInError}</Alert>}

          <Button
            size="lg"
            className="w-full"
            onClick={handleCheckIn}
            disabled={checkingIn || !check_in.can_check_in}
          >
            {checkingIn ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                Checking in…
              </>
            ) : (
              <>
                <CalendarCheck className="h-4 w-4" aria-hidden="true" />
                {check_in.can_check_in ? "Check in now" : "Already checked in"}
              </>
            )}
          </Button>

          {!check_in.can_check_in && (
            <p className="text-sm text-muted">
              You can check in again in a little while — repeat check-ins within{" "}
              {check_in.window_minutes} minutes are not recorded.
            </p>
          )}
        </CardContent>
      </Card>

      {/* Assigned hours */}
      <Card className="mt-4">
        <CardHeader>
          <CardTitle>Your Adoration {schedules.length > 1 ? "Hours" : "Hour"}</CardTitle>
          <CardDescription>
            The weekly holy {schedules.length > 1 ? "hours" : "hour"} you are committed to.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {schedules.length === 0 ? (
            <p className="text-sm text-muted">
              No adoration hour is assigned to your account yet. Please contact the
              parish office.
            </p>
          ) : (
            <ul className="space-y-2">
              {schedules.map((s) => (
                <li key={s.id} className="flex items-center gap-2 text-text">
                  <Clock className="h-4 w-4 text-accent" aria-hidden="true" />
                  <span>{s.label}</span>
                  {check_in.current_scheduled_hour?.time_slot === s.time_slot &&
                    check_in.current_scheduled_hour?.day_of_week === s.day_of_week && (
                      <Badge>Now</Badge>
                    )}
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>

      {/* Totals */}
      <div className="mt-4 grid grid-cols-3 gap-3">
        {[
          { label: "This month", value: summary.this_month },
          { label: "This year", value: summary.this_year },
          { label: "All time", value: summary.total },
        ].map(({ label, value }) => (
          <Card key={label} className="p-4 text-center">
            <div className="text-2xl font-light text-accent">{value}</div>
            <div className="mt-0.5 text-xs text-muted">{label}</div>
          </Card>
        ))}
      </div>

      {/* Last check-in + recent history */}
      <Card className="mt-4">
        <CardHeader>
          <CardTitle>Recent Attendance</CardTitle>
          <CardDescription>
            {last_check_in
              ? `Last check-in: ${last_check_in.date_label} at ${last_check_in.time_label}.`
              : "You have not checked in yet."}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <AttendanceList
            entries={recent_history}
            emptyMessage="Your check-ins will appear here once you visit the chapel."
          />
          {data.total_check_ins > recent_history.length && (
            <Link
              to="/attendance"
              className="mt-4 inline-block text-sm font-medium text-accent hover:underline"
            >
              View full history ({data.total_check_ins})
            </Link>
          )}
        </CardContent>
      </Card>
    </AdorerLayout>
  );
}
