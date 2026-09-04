import { useState } from "react";
import { Link } from "react-router-dom";
import { Eye, Loader2, Mail, Send } from "lucide-react";
import AdminLayout from "@/components/AdminLayout";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { adminApi, type RecipientGroup } from "@/api/admin";
import { ApiRequestError } from "@/api/client";

const GROUP_LABELS: Record<RecipientGroup, string> = {
  all: "All Adorers",
  active: "Active Adorers",
  inactive: "Inactive Adorers",
  missed: "Adorers Who Missed (last 7 days)",
};

export default function EmailCompose() {
  const [group, setGroup] = useState<RecipientGroup>("active");
  const [subject, setSubject] = useState("");
  const [body, setBody] = useState("");
  const [previewCount, setPreviewCount] = useState<number | null>(null);
  const [previewing, setPreviewing] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const handlePreview = async () => {
    setPreviewing(true);
    setError(null);
    setNotice(null);
    try {
      const result = await adminApi.emailPreview(group);
      setPreviewCount(result.recipient_count);
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "Could not preview recipients.");
      setPreviewCount(null);
    } finally {
      setPreviewing(false);
    }
  };

  const handleSend = async () => {
    if (sending) return;
    if (!subject.trim() || !body.trim()) {
      setError("Subject and message are both required.");
      return;
    }
    setSending(true);
    setError(null);
    setNotice(null);
    try {
      const result = await adminApi.emailSend({ group, subject, body });
      setNotice(result.message);
      setSubject("");
      setBody("");
      setPreviewCount(null);
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "Could not send the email.");
    } finally {
      setSending(false);
    }
  };

  return (
    <AdminLayout>
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Mail className="h-5 w-5" aria-hidden="true" />
            Send Announcement
          </CardTitle>
          <CardDescription>
            Email all adorers in a group. Each recipient is emailed individually.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {error && <Alert>{error}</Alert>}
          {notice && <Alert variant="info">{notice}</Alert>}

          <div className="space-y-1.5">
            <Label htmlFor="group">Recipients</Label>
            <div className="flex gap-2">
              <Select
                id="group"
                value={group}
                onChange={(e) => {
                  setGroup(e.target.value as RecipientGroup);
                  setPreviewCount(null);
                }}
                className="flex-1"
              >
                {(Object.keys(GROUP_LABELS) as RecipientGroup[]).map((g) => (
                  <option key={g} value={g}>{GROUP_LABELS[g]}</option>
                ))}
              </Select>
              <Button variant="outline" onClick={handlePreview} disabled={previewing}>
                {previewing ? (
                  <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                ) : (
                  <>
                    <Eye className="h-4 w-4" aria-hidden="true" />
                    Preview count
                  </>
                )}
              </Button>
            </div>
            {previewCount !== null && (
              <p className="text-sm text-muted">
                {previewCount === 0
                  ? "No adorers in this group."
                  : `${previewCount} adorer${previewCount === 1 ? "" : "s"} will receive this email.`}
              </p>
            )}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="subject">Subject</Label>
            <input
              id="subject"
              className="flex h-10 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
              value={subject}
              onChange={(e) => setSubject(e.target.value)}
              maxLength={255}
            />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="body">Message</Label>
            <Textarea
              id="body"
              rows={10}
              value={body}
              onChange={(e) => setBody(e.target.value)}
              placeholder="Write your announcement here. Basic HTML is supported."
            />
            <p className="text-xs text-muted">
              The message is wrapped in the parish email template before sending.
            </p>
          </div>

          <Button onClick={handleSend} disabled={sending || previewCount === 0}>
            {sending ? (
              <>
                <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                Sending…
              </>
            ) : (
              <>
                <Send className="h-4 w-4" aria-hidden="true" />
                Send to {previewCount !== null ? previewCount : "recipients"}
              </>
            )}
          </Button>

          <Link to="/admin/email/history" className="block text-sm text-accent hover:underline">
            View sent history &rarr;
          </Link>
        </CardContent>
      </Card>
    </AdminLayout>
  );
}
