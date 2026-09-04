import { QrCode, CalendarCheck, CalendarX } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import type { AttendanceEntry } from "@/api/adorer";

/**
 * Check-in history rows. Shared by the dashboard summary and the full history
 * page so both render attendance identically.
 */
export default function AttendanceList({
  entries,
  emptyMessage = "No check-ins to show.",
}: {
  entries: AttendanceEntry[];
  emptyMessage?: string;
}) {
  if (entries.length === 0) {
    return (
      <div className="flex flex-col items-center py-6 text-center">
        <CalendarX className="mb-2 h-8 w-8 text-accent/40" aria-hidden="true" />
        <p className="text-sm text-muted">{emptyMessage}</p>
      </div>
    );
  }

  return (
    <ul className="divide-y divide-border">
      {entries.map((entry) => {
        const Icon = entry.method === "qr" ? QrCode : CalendarCheck;
        return (
          <li key={entry.id} className="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
            <Icon className="mt-0.5 h-4 w-4 shrink-0 text-muted" aria-hidden="true" />
            <div className="min-w-0 flex-1">
              <div className="text-sm text-text">
                {entry.date_label} at {entry.time_label}
              </div>
              <div className="mt-0.5 text-xs text-muted">
                {entry.scheduled_hour ?? "Outside your assigned hour"}
              </div>
            </div>
            <Badge variant="neutral" className="capitalize">
              {entry.method === "qr" ? "QR" : "Manual"}
            </Badge>
          </li>
        );
      })}
    </ul>
  );
}
