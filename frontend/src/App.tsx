import { BrowserRouter, Navigate, Routes, Route } from "react-router-dom";
import { AuthProvider, useAuth } from "@/context/AuthContext";
import ProtectedRoute from "@/components/ProtectedRoute";
import HomePage from "@/pages/HomePage";
import RegisterPage from "@/pages/RegisterPage";
import LoginPage from "@/pages/LoginPage";
import AdminLoginPage from "@/pages/AdminLoginPage";
import AdorerDashboard from "@/pages/AdorerDashboard";
import AttendancePage from "@/pages/AttendancePage";
import PreferencesPage from "@/pages/PreferencesPage";
import CheckinPage from "@/pages/CheckinPage";
import AdminDashboard from "@/pages/AdminDashboard";
import AdorerRoster from "@/pages/admin/AdorerRoster";
import AdorerDetail from "@/pages/admin/AdorerDetail";
import AttendanceRecords from "@/pages/admin/AttendanceRecords";
import MissedAttendancePage from "@/pages/admin/MissedAttendance";
import CoveragePage from "@/pages/admin/Coverage";
import EmailCompose from "@/pages/admin/EmailCompose";
import EmailHistory from "@/pages/admin/EmailHistory";

/**
 * Redirect an already-signed-in adorer away from the public landing page to
 * their dashboard. Admins go to /admin. Kept as a tiny wrapper so the public
 * page still renders for anonymous visitors.
 */
function HomeOrDashboard() {
  const { bootstrapped, role } = useAuth();
  if (!bootstrapped) {
    return (
      <div className="flex min-h-screen items-center justify-center text-muted">
        Loading…
      </div>
    );
  }
  if (role === "adorer") return <Navigate to="/dashboard" replace />;
  if (role === "admin") return <Navigate to="/admin" replace />;
  return <HomePage />;
}

function AppRoutes() {
  return (
    <Routes>
      <Route path="/" element={<HomeOrDashboard />} />

      {/* Adorer auth */}
      <Route path="/register" element={<RegisterPage />} />
      <Route path="/login" element={<LoginPage />} />

      {/* Admin auth */}
      <Route path="/admin/login" element={<AdminLoginPage />} />

      {/* Protected */}
      <Route
        path="/dashboard"
        element={
          <ProtectedRoute role="adorer">
            <AdorerDashboard />
          </ProtectedRoute>
        }
      />
      <Route
        path="/attendance"
        element={
          <ProtectedRoute role="adorer">
            <AttendancePage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/preferences"
        element={
          <ProtectedRoute role="adorer">
            <PreferencesPage />
          </ProtectedRoute>
        }
      />
      {/* QR landing. Anonymous scans are sent to /login and returned here. */}
      <Route
        path="/checkin"
        element={
          <ProtectedRoute role="adorer">
            <CheckinPage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/admin"
        element={
          <ProtectedRoute role="admin">
            <AdminDashboard />
          </ProtectedRoute>
        }
      />
      <Route
        path="/admin/adorers"
        element={
          <ProtectedRoute role="admin">
            <AdorerRoster />
          </ProtectedRoute>
        }
      />
      <Route
        path="/admin/adorers/:id"
        element={
          <ProtectedRoute role="admin">
            <AdorerDetail />
          </ProtectedRoute>
        }
      />
      <Route
        path="/admin/attendance"
        element={
          <ProtectedRoute role="admin">
            <AttendanceRecords />
          </ProtectedRoute>
        }
      />
      <Route
        path="/admin/missed"
        element={
          <ProtectedRoute role="admin">
            <MissedAttendancePage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/admin/coverage"
        element={
          <ProtectedRoute role="admin">
            <CoveragePage />
          </ProtectedRoute>
        }
      />
      <Route
        path="/admin/email"
        element={
          <ProtectedRoute role="admin">
            <EmailCompose />
          </ProtectedRoute>
        }
      />
      <Route
        path="/admin/email/history"
        element={
          <ProtectedRoute role="admin">
            <EmailHistory />
          </ProtectedRoute>
        }
      />

      {/* Fallback */}
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}

function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <AppRoutes />
      </AuthProvider>
    </BrowserRouter>
  );
}

export default App;
