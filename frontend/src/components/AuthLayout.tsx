import type { ReactNode } from "react";
import Logo from "@/components/Logo";

/**
 * Centered single-card layout used by the auth pages. Renders the parish
 * header above whatever card content is passed in.
 */
export default function AuthLayout({
  title,
  subtitle,
  children,
}: {
  title: string;
  subtitle?: string;
  children: ReactNode;
}) {
  return (
    <div className="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-8">
      <header className="mb-6 flex flex-col items-center text-center">
        <Logo size={72} className="mb-3" />
        <h1 className="text-2xl font-light text-accent">St. Anthony Adoration</h1>
        <p className="mt-1 text-sm text-muted">Chapel Registration &amp; Attendance</p>
      </header>
      <h2 className="mb-1 text-xl font-medium text-text">{title}</h2>
      {subtitle && <p className="mb-4 text-sm text-muted">{subtitle}</p>}
      <div className="mt-2">{children}</div>
    </div>
  );
}
