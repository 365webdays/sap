import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { useAuth } from "@/context/AuthContext";

export default function AdorerDashboard() {
  const { adorer, schedule, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate("/login", { replace: true });
  };

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      <header className="mb-8 flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-light text-accent">St. Anthony Adoration</h1>
          <p className="mt-1 text-sm text-muted">
            Welcome, {adorer?.full_name ?? "adorer"}.
          </p>
        </div>
        <Button variant="outline" size="sm" onClick={handleLogout}>
          Sign out
        </Button>
      </header>

      <Card>
        <CardHeader>
          <CardTitle>Your Adoration Hour</CardTitle>
          <CardDescription>
            The weekly holy hour you are committed to.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {schedule ? (
            <p className="text-lg text-text">{schedule.label}</p>
          ) : (
            <p className="text-sm text-muted">
              No adoration hour is assigned to your account yet. Please contact
              the parish office.
            </p>
          )}
        </CardContent>
      </Card>

      <Card className="mt-4">
        <CardHeader>
          <CardTitle>Account</CardTitle>
        </CardHeader>
        <CardContent>
          <dl className="space-y-1 text-sm">
            <div className="flex justify-between">
              <dt className="text-muted">Email</dt>
              <dd className="text-text">{adorer?.email}</dd>
            </div>
            <div className="flex justify-between">
              <dt className="text-muted">Mobile</dt>
              <dd className="text-text">{adorer?.mobile_number ?? "—"}</dd>
            </div>
          </dl>
        </CardContent>
      </Card>

      <p className="mt-6 text-center text-sm text-muted">
        Check-in, attendance history, and email preferences arrive in the next
        phase.
      </p>
    </div>
  );
}
