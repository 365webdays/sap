import type { ReactNode } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import {
  LayoutDashboard,
  Users,
  CalendarClock,
  CalendarX,
  Grid3x3,
  Mail,
  History,
  LogOut,
  QrCode,
} from "lucide-react";
import Logo from "@/components/Logo";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { useAuth } from "@/context/AuthContext";

const NAV = [
  { to: "/admin", label: "Dashboard", Icon: LayoutDashboard, end: true },
  { to: "/admin/adorers", label: "Adorers", Icon: Users, end: false },
  { to: "/admin/attendance", label: "Attendance", Icon: CalendarClock, end: false },
  { to: "/admin/missed", label: "Missed", Icon: CalendarX, end: false },
  { to: "/admin/coverage", label: "Coverage", Icon: Grid3x3, end: false },
  { to: "/admin/email", label: "Email", Icon: Mail, end: false },
  { to: "/admin/email/history", label: "Email History", Icon: History, end: false },
] as const;

/**
 * Shell for the admin area.
 *
 * Desktop: fixed left sidebar. Mobile: a top bar with a horizontally
 * scrollable nav strip, since an admin on a phone is doing a quick lookup
 * rather than deep work.
 */
export default function AdminLayout({ children }: { children: ReactNode }) {
  const { admin, logout } = useAuth();
  const navigate = useNavigate();
  const { pathname } = useLocation();

  const handleLogout = () => {
    logout();
    navigate("/admin/login", { replace: true });
  };

  const isActive = (to: string, end?: boolean) =>
    end ? pathname === to : pathname === to || pathname.startsWith(to + "/");

  return (
    <div className="min-h-screen lg:flex">
      {/* Desktop sidebar */}
      <aside className="hidden w-60 shrink-0 border-r border-border bg-surface lg:flex lg:flex-col">
        <div className="flex items-center gap-2 border-b border-border px-4 py-4">
          <Logo size={36} />
          <div>
            <div className="text-sm font-medium text-accent">St. Anthony Adoration</div>
            <div className="text-xs text-muted">Admin Console</div>
          </div>
        </div>

        <nav className="flex-1 space-y-1 p-3">
          {NAV.map(({ to, label, Icon, end }) => (
            <Link
              key={to}
              to={to}
              aria-current={isActive(to, end) ? "page" : undefined}
              className={cn(
                "flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors",
                isActive(to, end)
                  ? "bg-accent/10 text-accent-dark"
                  : "text-muted hover:bg-bg hover:text-text"
              )}
            >
              <Icon className="h-4 w-4 shrink-0" aria-hidden="true" />
              {label}
            </Link>
          ))}
        </nav>

        <div className="border-t border-border p-3">
          {admin && <p className="mb-2 px-3 text-xs text-muted">Signed in as {admin.name}</p>}
          <Button variant="ghost" size="sm" onClick={handleLogout} className="w-full justify-start">
            <LogOut className="h-4 w-4" aria-hidden="true" />
            Sign out
          </Button>
        </div>
      </aside>

      {/* Mobile top bar */}
      <div className="flex-1">
        <header className="border-b border-border bg-surface lg:hidden">
          <div className="flex items-center justify-between px-4 py-3">
            <Link to="/admin" className="flex items-center gap-2">
              <Logo size={32} />
              <span className="text-sm font-medium text-accent">Admin</span>
            </Link>
            <Button variant="ghost" size="sm" onClick={handleLogout} aria-label="Sign out">
              <LogOut className="h-4 w-4" aria-hidden="true" />
            </Button>
          </div>
          <nav className="flex gap-1 overflow-x-auto px-2 pb-2">
            {NAV.map(({ to, label, Icon, end }) => (
              <Link
                key={to}
                to={to}
                aria-current={isActive(to, end) ? "page" : undefined}
                className={cn(
                  "flex shrink-0 items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors",
                  isActive(to, end) ? "bg-accent/10 text-accent-dark" : "text-muted hover:bg-bg"
                )}
              >
                <Icon className="h-3.5 w-3.5" aria-hidden="true" />
                {label}
              </Link>
            ))}
          </nav>
        </header>

        <main className="p-4 lg:p-8">
          {/* Desktop header with sign-out */}
          <div className="mb-6 hidden items-center justify-between lg:flex">
            {admin && <p className="text-sm text-muted">Welcome, {admin.name}.</p>}
            <Link
              to="/admin"
              className="ml-auto inline-flex items-center gap-1.5 text-sm text-muted hover:text-accent"
            >
              <QrCode className="h-4 w-4" aria-hidden="true" />
              QR code
            </Link>
          </div>
          {children}
        </main>
      </div>
    </div>
  );
}
