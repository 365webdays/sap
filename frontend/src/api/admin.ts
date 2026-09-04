import { request } from "@/api/client";
import type { Schedule } from "@/api/auth";
import type { AttendanceEntry, NotificationPreferences } from "@/api/adorer";

// --- Stats ---

export interface AdminOverview {
  total_adorers: number;
  active_adorers: number;
  inactive_adorers: number;
  assigned_hours: number;
  attendance_today: number;
  attendance_this_week: number;
  attendance_this_month: number;
  attendance_total: number;
}

export interface TrendPoint {
  bucket: string;
  label: string;
  count: number;
}

export interface PeakCell {
  day: string;
  hour: number;
  count: number;
}

export interface AdminStats {
  overview: AdminOverview;
  trend: { granularity: "day" | "week" | "month"; series: TrendPoint[] };
  peak: { cells: PeakCell[]; max: number; days_back: number };
  generated_at: string;
}

// --- Roster ---

export interface AdorerSummary {
  id: number;
  full_name: string;
  email: string;
  mobile_number: string;
  is_active: boolean;
  created_at: string;
  check_in_count: number;
  last_check_in: string | null;
  schedules: (Schedule & { id: number; label: string })[];
}

export interface Pagination {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

export interface AdorerRosterResponse {
  items: AdorerSummary[];
  pagination: Pagination;
  filters: Record<string, string>;
}

// --- Adorer detail ---

export interface AdorerDetail {
  adorer: {
    id: number;
    full_name: string;
    email: string;
    mobile_number: string;
    privacy_consent: boolean;
    email_verified_at: string | null;
    is_active: boolean;
    created_at: string;
  };
  schedules: (Schedule & { id: number; label: string })[];
  preferences: NotificationPreferences;
  summary: { total: number; this_month: number; this_year: number };
  last_check_in: AttendanceEntry | null;
  recent_history: AttendanceEntry[];
  total_check_ins: number;
}

// --- Attendance records ---

export interface AdminAttendanceEntry {
  id: number;
  user_id: number;
  full_name: string;
  email: string;
  check_in_at: string;
  date_label: string;
  time_label: string;
  method: string;
  scheduled_hour: string | null;
}

// --- Missed attendance ---

export interface MissedRecord {
  user_id: number;
  schedule_id: number;
  full_name: string;
  email: string;
  missed_date: string;
  date_label: string;
  day_of_week: string;
  time_slot: string;
  time_label: string;
  followed_up: boolean;
  followed_up_at: string | null;
  follow_up_note: string | null;
}

// --- Coverage ---

export interface CoverageSlot {
  day_of_week: string;
  time_slot: string;
  time_label: string;
  adorers: {
    schedule_id: number;
    user_id: number;
    full_name: string;
    email: string;
    is_active: boolean;
  }[];
  count: number;
  active_count: number;
}

// --- Email ---

export type RecipientGroup = "all" | "active" | "inactive" | "missed";

export interface EmailHistoryEntry {
  id: number;
  subject: string;
  recipient_group: string;
  recipient_count: number;
  sent_count: number;
  failed_count: number;
  sent_at: string;
  admin_name: string | null;
}

// --- API ---

export const adminApi = {
  stats: (params?: { granularity?: string; periods?: number; peak_days?: number }) =>
    request<AdminStats>({ method: "GET", url: "/admin/stats", params }),

  adorers: (params?: {
    search?: string;
    status?: string;
    day?: string;
    slot?: string;
    page?: number;
    per_page?: number;
  }) => request<AdorerRosterResponse>({ method: "GET", url: "/admin/adorers", params }),

  adorer: (id: number) =>
    request<AdorerDetail>({ method: "GET", url: "/admin/adorer", params: { id } }),

  updateAdorer: (data: {
    id: number;
    full_name?: string;
    email?: string;
    mobile_number?: string;
    is_active?: boolean;
    schedules?: { day_of_week: string; time_slot: string }[];
  }) => request<{ id: number; schedules: unknown; message: string }>({
    method: "PUT",
    url: "/admin/adorer",
    data,
  }),

  attendance: (params?: {
    from?: string;
    to?: string;
    search?: string;
    method?: string;
    day?: string;
    slot?: string;
    page?: number;
    per_page?: number;
  }) =>
    request<{ items: AdminAttendanceEntry[]; pagination: Pagination; filters: Record<string, string> }>({
      method: "GET",
      url: "/admin/attendance",
      params,
    }),

  missed: (params?: { from?: string; to?: string; user_id?: number; followed_up?: string }) =>
    request<{ items: MissedRecord[]; range: { from: string; to: string }; total: number; outstanding: number }>({
      method: "GET",
      url: "/admin/missed",
      params,
    }),

  markFollowUp: (data: {
    user_id: number;
    schedule_id: number;
    missed_date: string;
    note?: string;
    followed_up?: boolean;
  }) => request<{ followed_up: boolean; message: string }>({
    method: "POST",
    url: "/admin/missed/followup",
    data,
  }),

  coverage: (onlyGaps = false) =>
    request<{
      slots: CoverageSlot[];
      total_slots: number;
      covered_slots: number;
      gap_count: number;
      days: string[];
      time_slots: { value: string; label: string }[];
    }>({ method: "GET", url: "/admin/coverage", params: { only_gaps: onlyGaps ? 1 : undefined } }),

  emailPreview: (group: RecipientGroup) =>
    request<{ group: string; recipient_count: number; recipients: { id: number; full_name: string }[] }>({
      method: "POST",
      url: "/admin/email/preview",
      data: { group },
    }),

  emailSend: (data: { group: RecipientGroup; subject: string; body: string }) =>
    request<{
      sent: number;
      failed: number;
      recipient_count: number;
      failures: string[];
      message: string;
    }>({ method: "POST", url: "/admin/email/send", data }),

  emailHistory: (page = 1, perPage = 20) =>
    request<{ items: EmailHistoryEntry[]; pagination: Pagination }>({
      method: "GET",
      url: "/admin/email/history",
      params: { page, per_page: perPage },
    }),

  exportUrl: (type: "attendance" | "adorers" | "missed", params?: Record<string, string>) => {
    const search = new URLSearchParams({ type, ...params }).toString();
    return `/admin/export?${search}`;
  },
};
