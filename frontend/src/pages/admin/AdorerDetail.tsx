import { useCallback, useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { ArrowLeft, Loader2, Plus, Trash2 } from "lucide-react";
import AdminLayout from "@/components/AdminLayout";
import AttendanceList from "@/components/AttendanceList";
import { Alert } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { adminApi, type AdorerDetail } from "@/api/admin";
import { ApiRequestError } from "@/api/client";
import { DAYS, TIME_SLOTS, slotLabel } from "@/lib/schedule";

interface ScheduleInput {
  day_of_week: string;
  time_slot: string;
}

export default function AdorerDetailPage() {
  const { id } = useParams<{ id: string }>();
  const adorerId = Number(id);

  const [data, setData] = useState<AdorerDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Editable fields
  const [fullName, setFullName] = useState("");
  const [email, setEmail] = useState("");
  const [mobile, setMobile] = useState("");
  const [isActive, setIsActive] = useState(true);
  const [schedules, setSchedules] = useState<ScheduleInput[]>([]);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await adminApi.adorer(adorerId);
      setData(result);
      setFullName(result.adorer.full_name);
      setEmail(result.adorer.email);
      setMobile(result.adorer.mobile_number);
      setIsActive(result.adorer.is_active);
      setSchedules(result.schedules.map((s) => ({ day_of_week: s.day_of_week, time_slot: s.time_slot })));
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "Could not load this adorer.");
    } finally {
      setLoading(false);
    }
  }, [adorerId]);

  useEffect(() => {
    if (Number.isFinite(adorerId)) void load();
  }, [load, adorerId]);

  const addSchedule = () =>
    setSchedules((prev) => [...prev, { day_of_week: "Sunday", time_slot: "08:00:00" }]);

  const removeSchedule = (index: number) =>
    setSchedules((prev) => prev.filter((_, i) => i !== index));

  const updateSchedule = (index: number, field: keyof ScheduleInput, value: string) =>
    setSchedules((prev) =>
      prev.map((s, i) => (i === index ? { ...s, [field]: value } : s))
    );

  const handleSave = async () => {
    if (saving) return;
    setSaving(true);
    setSaveError(null);
    setNotice(null);
    try {
      await adminApi.updateAdorer({
        id: adorerId,
        full_name: fullName,
        email,
        mobile_number: mobile,
        is_active: isActive,
        schedules,
      });
      setNotice("Adorer updated.");
      await load();
    } catch (err) {
      if (err instanceof ApiRequestError && err.status === 409) {
        setSaveError("That email address is already in use.");
      } else {
        setSaveError(err instanceof ApiRequestError ? err.message : "Could not save changes.");
      }
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <AdminLayout>
        <div className="flex items-center gap-2 text-muted">
          <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
          <span>Loading…</span>
        </div>
      </AdminLayout>
    );
  }

  if (error || !data) {
    return (
      <AdminLayout>
        <Alert>{error ?? "Adorer not found."}</Alert>
        <Link to="/admin/adorers" className="mt-4 inline-block text-sm text-accent hover:underline">
          &larr; Back to roster
        </Link>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout>
      <Link
        to="/admin/adorers"
        className="mb-4 inline-flex items-center gap-1.5 text-sm text-muted hover:text-accent"
      >
        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
        Back to roster
      </Link>

      <div className="grid gap-4 lg:grid-cols-2">
        {/* Edit form */}
        <Card>
          <CardHeader>
            <CardTitle>Edit Adorer</CardTitle>
            <CardDescription>{data.adorer.email}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            {saveError && <Alert>{saveError}</Alert>}
            {notice && <Alert variant="info">{notice}</Alert>}

            <div className="space-y-1.5">
              <Label htmlFor="full_name">Full Name</Label>
              <Input id="full_name" value={fullName} onChange={(e) => setFullName(e.target.value)} />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="email">Email</Label>
              <Input id="email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="mobile">Mobile Number</Label>
              <Input id="mobile" value={mobile} onChange={(e) => setMobile(e.target.value)} />
            </div>

            <Switch
              label="Active"
              description="Inactive adorers cannot log in and are not expected to attend."
              checked={isActive}
              onChange={(e) => setIsActive(e.target.checked)}
            />

            {/* Schedules */}
            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <Label>Assigned Hours</Label>
                <Button variant="outline" size="sm" onClick={addSchedule}>
                  <Plus className="h-3.5 w-3.5" aria-hidden="true" />
                  Add
                </Button>
              </div>
              {schedules.map((s, i) => (
                <div key={i} className="flex gap-2">
                  <Select value={s.day_of_week} onChange={(e) => updateSchedule(i, "day_of_week", e.target.value)}>
                    {DAYS.map((d) => (
                      <option key={d} value={d}>{d}</option>
                    ))}
                  </Select>
                  <Select value={s.time_slot} onChange={(e) => updateSchedule(i, "time_slot", e.target.value)}>
                    {TIME_SLOTS.map((t) => (
                      <option key={t} value={t}>{slotLabel(t)}</option>
                    ))}
                  </Select>
                  <Button variant="ghost" size="sm" onClick={() => removeSchedule(i)} aria-label="Remove hour">
                    <Trash2 className="h-4 w-4" aria-hidden="true" />
                  </Button>
                </div>
              ))}
              {schedules.length === 0 && (
                <p className="text-sm text-muted">No hours assigned.</p>
              )}
            </div>

            <Button onClick={handleSave} disabled={saving}>
              {saving ? (
                <>
                  <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                  Saving…
                </>
              ) : (
                "Save changes"
              )}
            </Button>
          </CardContent>
        </Card>

        {/* Profile + history */}
        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Profile</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-muted">Registered</span>
                <span>{data.adorer.created_at}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted">Privacy consent</span>
                <Badge variant={data.adorer.privacy_consent ? "default" : "neutral"}>
                  {data.adorer.privacy_consent ? "Yes" : "No"}
                </Badge>
              </div>
              <div className="flex justify-between">
                <span className="text-muted">Total check-ins</span>
                <span>{data.total_check_ins}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted">Last check-in</span>
                <span>
                  {data.last_check_in
                    ? `${data.last_check_in.date_label} at ${data.last_check_in.time_label}`
                    : "Never"}
                </span>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Recent Attendance</CardTitle>
            </CardHeader>
            <CardContent>
              <AttendanceList
                entries={data.recent_history}
                emptyMessage="No check-ins recorded."
              />
            </CardContent>
          </Card>
        </div>
      </div>
    </AdminLayout>
  );
}
