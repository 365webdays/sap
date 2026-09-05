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
        <div className="relative mb-4">
          <div className="absolute inset-0 -z-10 rounded-full bg-accent/10 blur-2xl" aria-hidden="true" />
          <Logo size={128} />
        </div>
        <h1 className="font-serif text-2xl font-medium text-accent">St. Anthony Adoration</h1>
        <p className="mt-1 text-base text-muted">Chapel Registration &amp; Attendance</p>
      </header>
      <div className="animate-fade-in">
        <h2 className="mb-1 text-xl font-medium text-text">{title}</h2>
        {subtitle && <p className="mb-4 text-base text-muted">{subtitle}</p>}
        <div className="mt-2">{children}</div>
      </div>
    </div>
  );
}
