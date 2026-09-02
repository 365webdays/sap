import { request } from "@/api/client";

export type Role = "adorer" | "admin";

export interface Adorer {
  id: number;
  full_name: string;
  email: string;
  mobile_number: string | null;
  role: "adorer";
}

export interface Admin {
  id: number;
  name: string;
  email: string;
  role: "admin";
}

export interface Schedule {
  day_of_week: string;
  time_slot: string;
  label: string;
}

export interface AuthSession {
  token: string;
  user: Adorer;
  schedule: Schedule | null;
  welcome_email_sent?: boolean;
}

export interface AdminSession {
  token: string;
  admin: Admin;
}

export interface RegisterPayload {
  full_name: string;
  email: string;
  mobile_number?: string;
  password: string;
  day_of_week: string;
  time_slot: string;
  privacy_consent: boolean;
}

export interface LoginPayload {
  email: string;
  password: string;
}

export interface ScheduleOptions {
  days: string[];
  time_slots: { value: string; label: string }[];
}

export const authApi = {
  register: (payload: RegisterPayload) =>
    request<AuthSession>({ method: "POST", url: "/auth/register", data: payload }),

  login: (payload: LoginPayload) =>
    request<AuthSession>({ method: "POST", url: "/auth/login", data: payload }),

  me: () => request<{ user: Adorer; schedule: Schedule | null }>({ method: "GET", url: "/auth/me" }),

  adminLogin: (payload: LoginPayload) =>
    request<AdminSession>({ method: "POST", url: "/admin/login", data: payload }),

  adminMe: () => request<{ admin: Admin }>({ method: "GET", url: "/admin/me" }),

  scheduleOptions: () =>
    request<ScheduleOptions>({ method: "GET", url: "/schedule/options" }),
};
