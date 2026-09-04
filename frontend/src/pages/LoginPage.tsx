import { useEffect, useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import AuthLayout from "@/components/AuthLayout";
import FieldError from "@/components/FieldError";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useAuth } from "@/context/AuthContext";
import { ApiRequestError } from "@/api/client";
import InstallPrompt from "@/components/InstallPrompt";

interface LocationState {
  from?: { pathname: string };
}

export default function LoginPage() {
  const { loginAdorer, role } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const from = (location.state as LocationState | null)?.from?.pathname ?? "/dashboard";

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (role === "adorer") navigate("/dashboard", { replace: true });
  }, [role, navigate]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (submitting) return;
    setSubmitting(true);
    setFieldErrors({});
    setFormError(null);
    try {
      await loginAdorer({ email, password });
      navigate(from, { replace: true });
    } catch (err) {
      if (err instanceof ApiRequestError) {
        if (err.fields && Object.keys(err.fields).length > 0) {
          setFieldErrors(err.fields);
        }
        setFormError(err.message);
      } else {
        setFormError("Something went wrong. Please try again.");
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <AuthLayout title="Sign In" subtitle="Access your adoration schedule and attendance.">
      <Card>
        <form onSubmit={handleSubmit} noValidate className="space-y-4">
          {formError && <Alert>{formError}</Alert>}

          <div className="space-y-1.5">
            <Label htmlFor="email">Email address</Label>
            <Input
              id="email"
              type="email"
              autoComplete="email"
              value={email}
              onChange={(e) => {
                setEmail(e.target.value);
                setFieldErrors((p) => ({ ...p, email: "" }));
                setFormError(null);
              }}
              aria-invalid={!!fieldErrors.email}
            />
            <FieldError message={fieldErrors.email} />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="password">Password</Label>
            <Input
              id="password"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => {
                setPassword(e.target.value);
                setFieldErrors((p) => ({ ...p, password: "" }));
                setFormError(null);
              }}
              aria-invalid={!!fieldErrors.password}
            />
            <FieldError message={fieldErrors.password} />
          </div>

          <Button type="submit" className="w-full" disabled={submitting}>
            {submitting ? "Signing in…" : "Sign in"}
          </Button>

          <p className="text-center text-sm text-muted">
            New here?{" "}
            <Link to="/register" className="font-medium text-accent hover:underline">
              Register as an adorer
            </Link>
          </p>
        </form>
      </Card>

      <div className="mt-4">
        <InstallPrompt />
      </div>
    </AuthLayout>
  );
}
