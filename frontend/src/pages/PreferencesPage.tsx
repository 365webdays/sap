import { useEffect, useState } from "react";
import { Loader2 } from "lucide-react";
import AdorerLayout from "@/components/AdorerLayout";
import InstallPrompt from "@/components/InstallPrompt";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Switch } from "@/components/ui/switch";
import { adorerApi, type NotificationPreferences } from "@/api/adorer";
import { ApiRequestError } from "@/api/client";

const TOGGLES: {
  key: keyof NotificationPreferences;
  label: string;
  description: string;
}[] = [
  {
    key: "hour_reminders",
    label: "Hour reminders",
    description: "An email shortly before your assigned adoration hour begins.",
  },
  {
    key: "announcements",
    label: "Chapel announcements",
    description: "Occasional news and updates from the parish about the chapel.",
  },
  {
    key: "attendance_notifications",
    label: "Attendance notifications",
    description: "A gentle note if you miss your scheduled hour.",
  },
];

export default function PreferencesPage() {
  const [prefs, setPrefs] = useState<NotificationPreferences | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const { preferences } = await adorerApi.preferences();
        if (!cancelled) setPrefs(preferences);
      } catch (err) {
        if (!cancelled) {
          setError(
            err instanceof ApiRequestError ? err.message : "Could not load your preferences."
          );
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const toggle = (key: keyof NotificationPreferences) => {
    setPrefs((p) => (p === null ? p : { ...p, [key]: !p[key] }));
    setNotice(null);
    setError(null);
  };

  const handleSave = async () => {
    if (prefs === null || saving) return;
    setSaving(true);
    setError(null);
    setNotice(null);
    try {
      const result = await adorerApi.savePreferences(prefs);
      setPrefs(result.preferences);
      setNotice(result.message);
    } catch (err) {
      setError(
        err instanceof ApiRequestError ? err.message : "Could not save your preferences."
      );
    } finally {
      setSaving(false);
    }
  };

  return (
    <AdorerLayout>
      <Card>
        <CardHeader>
          <CardTitle>Notification Preferences</CardTitle>
          <CardDescription>
            Choose which emails you receive. You can change these at any time.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {error && <Alert className="mb-4">{error}</Alert>}
          {notice && (
            <Alert variant="info" className="mb-4">
              {notice}
            </Alert>
          )}

          {loading ? (
            <div className="flex items-center gap-2 text-muted">
              <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
              <span aria-live="polite">Loading…</span>
            </div>
          ) : prefs === null ? (
            <p className="text-sm text-muted">Preferences are unavailable right now.</p>
          ) : (
            <>
              <div className="divide-y divide-border">
                {TOGGLES.map(({ key, label, description }) => (
                  <Switch
                    key={key}
                    label={label}
                    description={description}
                    checked={prefs[key]}
                    onChange={() => toggle(key)}
                    disabled={saving}
                  />
                ))}
              </div>

              <Button className="mt-6 w-full sm:w-auto" onClick={handleSave} disabled={saving}>
                {saving ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                    Saving…
                  </>
                ) : (
                  "Save preferences"
                )}
              </Button>
            </>
          )}
        </CardContent>
      </Card>

      <Card className="mt-4">
        <CardHeader>
          <CardTitle>Install App</CardTitle>
          <CardDescription>
            Add St. Anthony Adoration to your home screen for quick access.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <InstallPrompt force />
        </CardContent>
      </Card>
    </AdorerLayout>
  );
}
