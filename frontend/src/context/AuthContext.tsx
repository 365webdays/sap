import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import {
  authApi,
  type Admin,
  type Adorer,
  type AdminSession,
  type AuthSession,
  type LoginPayload,
  type RegisterPayload,
  type Schedule,
} from "@/api/auth";
import {
  clearStoredSession,
  getStoredRole,
  getStoredToken,
  storeSession,
} from "@/api/client";

type Role = "adorer" | "admin";

interface AuthState {
  /** Resolved only after the boot check finishes; false before that. */
  bootstrapped: boolean;
  role: Role | null;
  adorer: Adorer | null;
  admin: Admin | null;
  schedule: Schedule | null;
}

interface AuthContextValue extends AuthState {
  registerAdorer: (payload: RegisterPayload) => Promise<void>;
  loginAdorer: (payload: LoginPayload) => Promise<void>;
  loginAdmin: (payload: LoginPayload) => Promise<void>;
  logout: () => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<AuthState>({
    bootstrapped: false,
    role: null,
    adorer: null,
    admin: null,
    schedule: null,
  });

  // Guard against StrictMode double-invoking the boot effect in dev.
  const bootStartedRef = useRef(false);

  const applyAdorerSession = useCallback((session: AuthSession) => {
    storeSession(session.token, "adorer");
    setState({
      bootstrapped: true,
      role: "adorer",
      adorer: session.user,
      admin: null,
      schedule: session.schedule,
    });
  }, []);

  const applyAdminSession = useCallback((session: AdminSession) => {
    storeSession(session.token, "admin");
    setState({
      bootstrapped: true,
      role: "admin",
      adorer: null,
      admin: session.admin,
      schedule: null,
    });
  }, []);

  const registerAdorer = useCallback(
    async (payload: RegisterPayload) => {
      const session = await authApi.register(payload);
      applyAdorerSession(session);
    },
    [applyAdorerSession]
  );

  const loginAdorer = useCallback(
    async (payload: LoginPayload) => {
      const session = await authApi.login(payload);
      applyAdorerSession(session);
    },
    [applyAdorerSession]
  );

  const loginAdmin = useCallback(
    async (payload: LoginPayload) => {
      const session = await authApi.adminLogin(payload);
      applyAdminSession(session);
    },
    [applyAdminSession]
  );

  const logout = useCallback(() => {
    clearStoredSession();
    setState({
      bootstrapped: true,
      role: null,
      adorer: null,
      admin: null,
      schedule: null,
    });
  }, []);

  // On boot, if a token is present, validate it against the server so a
  // deleted/deactivated account or a tampered token cannot leave the client
  // signed in. A 401 clears the session via the axios interceptor.
  useEffect(() => {
    if (bootStartedRef.current) return;
    bootStartedRef.current = true;

    const token = getStoredToken();
    const role = getStoredRole();

    if (!token || (role !== "adorer" && role !== "admin")) {
      setState((s) => ({ ...s, bootstrapped: true }));
      return;
    }

    let cancelled = false;
    (async () => {
      try {
        if (role === "adorer") {
          const { user, schedule } = await authApi.me();
          if (cancelled) return;
          setState({ bootstrapped: true, role: "adorer", adorer: user, admin: null, schedule });
        } else {
          const { admin } = await authApi.adminMe();
          if (cancelled) return;
          setState({ bootstrapped: true, role: "admin", adorer: null, admin, schedule: null });
        }
      } catch {
        if (cancelled) return;
        // 401 already cleared storage; just settle as logged-out.
        setState({ bootstrapped: true, role: null, adorer: null, admin: null, schedule: null });
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      ...state,
      registerAdorer,
      loginAdorer,
      loginAdmin,
      logout,
    }),
    [state, registerAdorer, loginAdorer, loginAdmin, logout]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (ctx === null) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return ctx;
}
