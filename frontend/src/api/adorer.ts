import { request } from "@/api/client";
import type { Adorer, Schedule } from "@/api/auth";

export type CheckInMethod = "manual" | "qr";

export interface AttendanceEntry {
  id: number;
  check_in_at: string;
  check_in_at_iso: string;
  date_label: string;
  time_label: string;
  method: CheckInMethod;
  /** Null when the visit fell outside the adorer's assigned hour. */
  scheduled_hour: string | null;
}

export interface CheckInState {
  can_check_in: boolean;
  window_minutes: number;
  last_within_window: { id: number; check_in_at: string; method: string } | null;
  within_scheduled_hour: boolean;
  current_scheduled_hour: Schedule | null;
}

export interface AttendanceSummary {
  total: number;
  this_month: number;
  this_year: number;
}

export interface AdorerDashboard {
  user: Adorer;
  schedules: (Schedule & { id: number })[];
  last_check_in: AttendanceEntry | null;
  recent_history: AttendanceEntry[];
  total_check_ins: number;
  summary: AttendanceSummary;
  check_in: CheckInState;
  server_time: string;
}

export interface CheckInResult {
  entry: AttendanceEntry;
  within_scheduled_hour: boolean;
  message: string;
}

export interface Pagination {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

export interface NotificationPreferences {
  hour_reminders: boolean;
  announcements: boolean;
  attendance_notifications: boolean;
}

export const adorerApi = {
  dashboard: () => request<AdorerDashboard>({ method: "GET", url: "/adorer/dashboard" }),

  checkIn: (method: CheckInMethod = "manual") =>
    request<CheckInResult>({ method: "POST", url: "/adorer/checkin", data: { method } }),

  attendance: (page = 1, perPage = 20) =>
    request<{ items: AttendanceEntry[]; pagination: Pagination }>({
      method: "GET",
      url: "/adorer/attendance",
      params: { page, per_page: perPage },
    }),

  preferences: () =>
    request<{ preferences: NotificationPreferences }>({
      method: "GET",
      url: "/adorer/preferences",
    }),

  savePreferences: (preferences: NotificationPreferences) =>
    request<{ preferences: NotificationPreferences; message: string }>({
      method: "PUT",
      url: "/adorer/preferences",
      data: preferences,
    }),
};
