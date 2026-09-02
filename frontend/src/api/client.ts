import axios, { type AxiosError, type AxiosResponse } from "axios";

/**
 * Backend contract:
 *   success: { success: true,  data: T }
 *   error:   { success: false, error: string, fields?: Record<string,string> }
 */
export interface ApiSuccess<T> {
  success: true;
  data: T;
}
export interface ApiError {
  success: false;
  error: string;
  /** Present on 422 validation failures: field name => message. */
  fields?: Record<string, string>;
}
export type ApiResponse<T> = ApiSuccess<T> | ApiError;

export interface FieldErrors {
  [field: string]: string;
}

export class ApiRequestError extends Error {
  public readonly status: number;
  public readonly fields: FieldErrors;

  constructor(message: string, status: number, fields: FieldErrors = {}) {
    super(message);
    this.name = "ApiRequestError";
    this.status = status;
    this.fields = fields;
  }
}

const TOKEN_KEY = "sap_token";
const ROLE_KEY = "sap_role";

export function getStoredToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}
export function getStoredRole(): string | null {
  return localStorage.getItem(ROLE_KEY);
}
export function storeSession(token: string, role: string): void {
  localStorage.setItem(TOKEN_KEY, token);
  localStorage.setItem(ROLE_KEY, role);
}
export function clearStoredSession(): void {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(ROLE_KEY);
}

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? "http://localhost:8000/api",
  headers: { "Content-Type": "application/json" },
});

// Attach JWT token from localStorage to every request if present
api.interceptors.request.use((config) => {
  const token = getStoredToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Centralized 401 handling: a token that the server rejects (expired, forged,
// or belonging to a deleted account) must not leave the client pretending to
// be signed in. We clear the stale session and let the response reject so the
// calling page can redirect to the appropriate login.
api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiError>) => {
    if (error.response?.status === 401) {
      clearStoredSession();
    }
    return Promise.reject(error);
  }
);

/**
 * Typed wrapper around axios that unwraps the backend envelope.
 * Throws ApiRequestError with .fields on validation failures.
 */
export async function request<T>(config: Parameters<typeof api.request>[0]): Promise<T> {
  try {
    const response: AxiosResponse<ApiSuccess<T>> = await api.request(config);
    return response.data.data;
  } catch (err) {
    const axiosErr = err as AxiosError<ApiError>;
    const body = axiosErr.response?.data;
    const status = axiosErr.response?.status ?? 0;
    const message = body?.error ?? axiosErr.message ?? "Request failed";
    const fields = body?.fields ?? {};
    throw new ApiRequestError(message, status, fields);
  }
}

export default api;
