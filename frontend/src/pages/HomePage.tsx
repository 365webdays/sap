import { Link } from "react-router-dom";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { buttonVariants } from "@/components/ui/button";
import Logo from "@/components/Logo";

export default function HomePage() {
  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      <header className="mb-8 flex flex-col items-center text-center">
        <Logo size={80} className="mb-3" />
        <h1 className="text-3xl font-light text-accent">St. Anthony Adoration</h1>
        <p className="mt-1 text-muted">Chapel Registration &amp; Attendance</p>
      </header>

      <Card>
        <CardHeader>
          <CardTitle>Perpetual Adoration Chapel</CardTitle>
          <CardDescription>
            Commit to a weekly holy hour before the Blessed Sacrament.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <p className="text-sm text-muted">
            St. Anthony of Padua Parish invites you to spend one hour each week
            in prayer before our Lord in the Eucharist. Register below to claim
            your hour and join our community of adorers.
          </p>
          <div className="flex flex-col gap-2 sm:flex-row">
            <Link to="/register" className={buttonVariants({ variant: "default" })}>
              Register as an adorer
            </Link>
            <Link to="/login" className={buttonVariants({ variant: "outline" })}>
              Sign in
            </Link>
          </div>
        </CardContent>
      </Card>

      <p className="mt-6 text-center text-sm text-muted">
        Parish staff?{" "}
        <Link to="/admin/login" className="font-medium text-accent hover:underline">
          Administrator sign in
        </Link>
      </p>
    </div>
  );
}
