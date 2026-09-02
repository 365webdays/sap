import { useEffect, useState } from "react";
import { Link, useNavigate } from "react-router-dom";
import AuthLayout from "@/components/AuthLayout";
import FieldError from "@/components/FieldError";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { useAuth } from "@/context/AuthContext";
import { authApi, type ScheduleOptions } from "@/api/auth";
import { ApiRequestError } from "@/api/client";
import { DAYS, TIME_SLOTS, slotLabel } from "@/lib/schedule";

interface FormState {
  full_name: string;
  email: string;
  mobile_number: string;
  password: string;
  day_of_week: string;
  time_slot: string;
  privacy_consent: boolean;
}

const EMPTY: FormState = {
  full_name: "",
  email: "",
  mobile_number: "",
  password: "",
  day_of_week: "",
  time_slot: "",
  privacy_consent: false,
};

export default function RegisterPage() {
  const { registerAdorer, role } = useAuth();
  const navigate = useNavigate();

  const [form, setForm] = useState<FormState>(EMPTY);
  const [options, setOptions] = useState<ScheduleOptions | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  // Fetch the canonical day/slot options from the server so the form and the
  // validator agree on the same vocabulary. Fall back to the local mirror if
  // the API is unreachable (e.g. offline dev).
  useEffect(() => {
    let cancelled = false;
    authApi
      .scheduleOptions()
      .then((opts) => {
        if (!cancelled) setOptions(opts);
      })
      .catch(() => {
        /* local mirror is the fallback below */
      });
    return () => {
      cancelled = true;
    };
  }, []);

  // If an adorer is already signed in, they don't belong here.
  useEffect(() => {
    if (role === "adorer") navigate("/dashboard", { replace: true });
  }, [role, navigate]);

  const days = options?.days ?? DAYS;
  const slots = options?.time_slots ?? TIME_SLOTS.map((v) => ({ value: v, label: slotLabel(v) }));

  const update = <K extends keyof FormState>(key: K, value: FormState[K]) => {
    setForm((f) => ({ ...f, [key]: value }));
    setFieldErrors((prev) => {
      if (!prev[key]) return prev;
      const next = { ...prev };
      delete next[key];
      return next;
    });
    setFormError(null);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (submitting) return;
    setSubmitting(true);
    setFieldErrors({});
    setFormError(null);
    try {
      await registerAdorer(form);
      navigate("/dashboard", { replace: true });
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
    <AuthLayout
      title="Register as an Adorer"
      subtitle="Commit to a weekly holy hour before the Blessed Sacrament."
    >
      <Card>
        <form onSubmit={handleSubmit} noValidate className="space-y-4">
          {formError && <Alert>{formError}</Alert>}

          <div className="space-y-1.5">
            <Label htmlFor="full_name">Full name</Label>
            <Input
              id="full_name"
              autoComplete="name"
              value={form.full_name}
              onChange={(e) => update("full_name", e.target.value)}
              aria-invalid={!!fieldErrors.full_name}
              aria-describedby={fieldErrors.full_name ? "full_name-error" : undefined}
            />
            <FieldError id="full_name-error" message={fieldErrors.full_name} />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="email">Email address</Label>
            <Input
              id="email"
              type="email"
              autoComplete="email"
              value={form.email}
              onChange={(e) => update("email", e.target.value)}
              aria-invalid={!!fieldErrors.email}
              aria-describedby={fieldErrors.email ? "email-error" : undefined}
            />
            <FieldError id="email-error" message={fieldErrors.email} />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="mobile_number">Mobile number (optional)</Label>
            <Input
              id="mobile_number"
              type="tel"
              autoComplete="tel"
              value={form.mobile_number}
              onChange={(e) => update("mobile_number", e.target.value)}
              aria-invalid={!!fieldErrors.mobile_number}
              aria-describedby={fieldErrors.mobile_number ? "mobile_number-error" : undefined}
            />
            <FieldError id="mobile_number-error" message={fieldErrors.mobile_number} />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="password">Password</Label>
            <Input
              id="password"
              type="password"
              autoComplete="new-password"
              value={form.password}
              onChange={(e) => update("password", e.target.value)}
              aria-invalid={!!fieldErrors.password}
              aria-describedby={fieldErrors.password ? "password-error" : undefined}
            />
            <FieldError id="password-error" message={fieldErrors.password} />
            <p className="text-xs text-muted">At least 8 characters, at most 72.</p>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="day_of_week">Adoration day</Label>
              <Select
                id="day_of_week"
                value={form.day_of_week}
                onChange={(e) => update("day_of_week", e.target.value)}
                aria-invalid={!!fieldErrors.day_of_week}
              >
                <option value="" disabled>
                  Select day
                </option>
                {days.map((d) => (
                  <option key={d} value={d}>
                    {d}
                  </option>
                ))}
              </Select>
              <FieldError message={fieldErrors.day_of_week} />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="time_slot">Adoration hour</Label>
              <Select
                id="time_slot"
                value={form.time_slot}
                onChange={(e) => update("time_slot", e.target.value)}
                aria-invalid={!!fieldErrors.time_slot}
              >
                <option value="" disabled>
                  Select hour
                </option>
                {slots.map((s) => (
                  <option key={s.value} value={s.value}>
                    {s.label}
                  </option>
                ))}
              </Select>
              <FieldError message={fieldErrors.time_slot} />
            </div>
          </div>

          <div>
            <Checkbox
              checked={form.privacy_consent}
              onChange={(e) => update("privacy_consent", e.target.checked)}
              label={
                <>
                  I consent to the parish storing my contact details for the
                  purpose of coordinating adoration, per the privacy policy.
                </>
              }
            />
            <FieldError message={fieldErrors.privacy_consent} />
          </div>

          <Button type="submit" className="w-full" disabled={submitting}>
            {submitting ? "Registering…" : "Register"}
          </Button>

          <p className="text-center text-sm text-muted">
            Already registered?{" "}
            <Link to="/login" className="font-medium text-accent hover:underline">
              Sign in
            </Link>
          </p>
        </form>
      </Card>
    </AuthLayout>
  );
}
