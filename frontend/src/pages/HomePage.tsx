import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";

export default function HomePage() {
  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      <header className="mb-8 text-center">
        <h1 className="text-3xl font-light text-accent">St. Anthony Adoration</h1>
        <p className="mt-1 text-muted">Chapel Registration &amp; Attendance</p>
      </header>

      <Card>
        <CardHeader>
          <CardTitle>Setup Complete</CardTitle>
          <CardDescription>Phase 1–2 scaffolding is in place.</CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted">
            The frontend is running. Authentication, adorer features, and the admin
            panel will be built in subsequent phases.
          </p>
        </CardContent>
      </Card>
    </div>
  );
}
