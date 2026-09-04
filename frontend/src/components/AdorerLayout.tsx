import type { ReactNode } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { CalendarCheck, History, Settings, LogOut } from "lucide-react";
import Logo from "@/components/Logo";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { useAuth } from "@/context/AuthContext";

const NAV = [
  { to: "/dashboard", label: "Dashboard", Icon: CalendarCheck },
  { to: "/attendance", label: "History", Icon: History },
  { to: "/preferences", label: "Settings", Icon: Settings },
] as const;

/**
 * Shell for the signed-in adorer area.
 *
 * Mobile-first: navigation sits in a fixed bottom bar within thumb reach, and
 * moves inline into the header from `sm:` up. The extra bottom padding keeps
 * page content clear of the bar on small screens.
 */
export default function AdorerLayout({ children }: { children: ReactNode }) {
  const { adorer, logout } = useAuth();
  const navigate = useNavigate();
  const { pathname } = useLocation();

  const handleLogout = () => {
    logout();
    navigate("/login", { replace: true });
  };

  return (
    <div className="min-h-screen pb-20 sm:pb-0">
      <header className="border-b border-border bg-surface">
        <div className="mx-auto flex max-w-3xl items-center gap-3 px-4 py-3">
          <Link to="/dashboard" className="flex items-center gap-3">
            <Logo size={40} />
            <span className="text-lg font-light text-accent">St. Anthony Adoration</span>
          </Link>

          <nav className="ml-auto hidden items-center gap-1 sm:flex">
            {NAV.map(({ to, label, Icon }) => (
              <Link
                key={to}
                to={to}
                aria-current={pathname === to ? "page" : undefined}
                className={cn(
                  "inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors",
                  pathname === to
                    ? "bg-accent/10 text-accent-dark"
                    : "text-muted hover:bg-bg hover:text-text"
                )}
              >
                <Icon className="h-4 w-4" aria-hidden="true" />
                {label}
              </Link>
            ))}
          </nav>

          <Button
            variant="ghost"
            size="sm"
            onClick={handleLogout}
            className="ml-auto sm:ml-1"
            aria-label="Sign out"
          >
            <LogOut className="h-4 w-4" aria-hidden="true" />
            <span className="hidden sm:inline">Sign out</span>
          </Button>
        </div>
      </header>

      <main className="mx-auto max-w-3xl px-4 py-6">
        {adorer && (
          <p className="mb-4 text-sm text-muted">
            Welcome, <span className="text-text">{adorer.full_name}</span>.
          </p>
        )}
        {children}
      </main>

      {/* Bottom tab bar — mobile only */}
      <nav
        aria-label="Main"
        className="fixed inset-x-0 bottom-0 border-t border-border bg-surface sm:hidden"
      >
        <div className="mx-auto flex max-w-3xl">
          {NAV.map(({ to, label, Icon }) => (
            <Link
              key={to}
              to={to}
              aria-current={pathname === to ? "page" : undefined}
              className={cn(
                "flex min-h-[56px] flex-1 flex-col items-center justify-center gap-0.5 text-xs font-medium transition-colors",
                pathname === to ? "text-accent" : "text-muted"
              )}
            >
              <Icon className="h-5 w-5" aria-hidden="true" />
              {label}
            </Link>
          ))}
        </div>
      </nav>
    </div>
  );
}
