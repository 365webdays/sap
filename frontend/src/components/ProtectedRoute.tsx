import { Navigate, useLocation } from "react-router-dom";
import { useAuth } from "@/context/AuthContext";

type Role = "adorer" | "admin";

interface ProtectedRouteProps {
  role: Role;
  children: React.ReactNode;
  /** Where to send a signed-in user of the wrong role. */
  fallbackPath?: string;
}

/**
 * Route guard. Waits for the auth boot check to finish (so a stored token is
 * validated against the server) before deciding to render or redirect —
 * otherwise a page refresh would bounce a valid session to the login screen.
 */
export default function ProtectedRoute({
  role,
  children,
  fallbackPath,
}: ProtectedRouteProps) {
  const { bootstrapped, role: currentRole } = useAuth();
  const location = useLocation();

  if (!bootstrapped) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <div className="text-muted" aria-live="polite">
          Loading…
        </div>
      </div>
    );
  }

  if (currentRole !== role) {
    // Send an already-signed-in user of the other role to their own area
    // instead of a login page they don't need.
    const target =
      fallbackPath ?? (currentRole === "admin" ? "/admin" : currentRole === "adorer" ? "/dashboard" : "/login");
    return <Navigate to={target} replace state={{ from: location }} />;
  }

  return <>{children}</>;
}
