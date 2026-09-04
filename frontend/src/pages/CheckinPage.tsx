import { useEffect, useRef, useState } from "react";
import { Link } from "react-router-dom";
import { CheckCircle2, Loader2, QrCode } from "lucide-react";
import AdorerLayout from "@/components/AdorerLayout";
import { Alert } from "@/components/ui/alert";
import { buttonVariants } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { adorerApi, type CheckInResult } from "@/api/adorer";
import { ApiRequestError } from "@/api/client";

type State =
  | { status: "working" }
  | { status: "done"; result: CheckInResult }
  | { status: "duplicate"; message: string }
  | { status: "error"; message: string };

/**
 * Landing page for the chapel QR code.
 *
 * Reaching this route already means the adorer is authenticated (the guard
 * sends anonymous visitors to /login and back), so the check-in fires
 * immediately on mount — a scan should not require a second tap.
 */
export default function CheckinPage() {
  const [state, setState] = useState<State>({ status: "working" });

  // StrictMode mounts effects twice in development; without this the page
  // would POST twice and the second call would come back as a duplicate.
  const startedRef = useRef(false);

  useEffect(() => {
    if (startedRef.current) return;
    startedRef.current = true;

    (async () => {
      try {
        const result = await adorerApi.checkIn("qr");
        if (navigator.vibrate) navigator.vibrate(50);
        setState({ status: "done", result });
      } catch (err) {
        if (err instanceof ApiRequestError && err.status === 409) {
          setState({ status: "duplicate", message: err.message });
          return;
        }
        setState({
          status: "error",
          message: err instanceof ApiRequestError ? err.message : "Could not record your check-in.",
        });
      }
    })();
  }, []);

  return (
    <AdorerLayout>
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <QrCode className="h-5 w-5" aria-hidden="true" />
            Chapel Check-In
          </CardTitle>
          <CardDescription>Scanned at the chapel entrance.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {state.status === "working" && (
            <div className="flex items-center gap-2 text-muted">
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
              <span aria-live="polite">Recording your check-in…</span>
            </div>
          )}

          {state.status === "done" && (
            <>
              <div className="animate-scale-in flex items-start gap-3 rounded-md border border-accent/30 bg-accent/5 px-4 py-3">
                <CheckCircle2 className="animate-check-bounce mt-0.5 h-5 w-5 shrink-0 text-accent" aria-hidden="true" />
                <div>
                  <p className="font-medium text-text" aria-live="polite">
                    {state.result.message}
                  </p>
                  <p className="mt-1 text-sm text-muted">
                    {state.result.entry.date_label} at {state.result.entry.time_label}
                  </p>
                </div>
              </div>
              {!state.result.within_scheduled_hour && (
                <p className="text-sm text-muted">
                  This visit was outside your assigned hour, so it is recorded as an
                  additional visit.
                </p>
              )}
            </>
          )}

          {state.status === "duplicate" && <Alert variant="info">{state.message}</Alert>}
          {state.status === "error" && <Alert>{state.message}</Alert>}

          {state.status !== "working" && (
            <Link
              to="/dashboard"
              className={buttonVariants({ variant: "outline", className: "w-full sm:w-auto" })}
            >
              Go to dashboard
            </Link>
          )}
        </CardContent>
      </Card>
    </AdorerLayout>
  );
}
