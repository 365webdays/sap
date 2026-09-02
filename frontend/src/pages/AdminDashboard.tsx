import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import Logo from "@/components/Logo";
import { useAuth } from "@/context/AuthContext";

export default function AdminDashboard() {
  const { admin, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate("/admin/login", { replace: true });
  };

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      <header className="mb-8 flex items-start justify-between">
        <div className="flex items-center gap-3">
          <Logo size={48} />
          <div>
            <h1 className="text-2xl font-light text-accent">Administration</h1>
            <p className="mt-1 text-sm text-muted">
              Signed in as {admin?.name ?? "administrator"}.
            </p>
          </div>
        </div>
        <Button variant="outline" size="sm" onClick={handleLogout}>
          Sign out
        </Button>
      </header>

      <Card>
        <CardHeader>
          <CardTitle>Admin Console</CardTitle>
          <CardDescription>
            Manage adorers, schedules, attendance, and email campaigns.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted">
            The adorer roster, attendance reports, and email tools will be built
            here in subsequent phases.
          </p>
        </CardContent>
      </Card>
    </div>
  );
}
