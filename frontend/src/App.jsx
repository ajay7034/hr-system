import { useEffect, useState } from 'react';
import { Route, Routes, useLocation } from 'react-router-dom';
import ProtectedRoute from './components/ProtectedRoute';
import { RouteLoader } from './components/Preloader';
import CompanyDocumentsPage from './pages/CompanyDocumentsPage';
import DashboardPage from './pages/DashboardPage';
import BackupPage from './pages/BackupPage';
import EmployeeDocumentsPage from './pages/EmployeeDocumentsPage';
import EmployeeProfilePage from './pages/EmployeeProfilePage';
import EmployeesPage from './pages/EmployeesPage';
import FormsPage from './pages/FormsPage';
import LoginPage from './pages/LoginPage';
import LogReportPage from './pages/LogReportPage';
import PassportPage from './pages/PassportPage';
import RejoiningFormPage from './pages/RejoiningFormPage';
import RequestPortalPage from './pages/RequestPortalPage';
import RequestsPage from './pages/RequestsPage';
import ReportsPage from './pages/ReportsPage';
import SettingsPage from './pages/SettingsPage';

export default function App() {
  const location = useLocation();
  const [transitioning, setTransitioning] = useState(false);

  useEffect(() => {
    setTransitioning(true);
    const timeout = window.setTimeout(() => setTransitioning(false), 380);

    return () => window.clearTimeout(timeout);
  }, [location.pathname]);

  return (
    <>
      <RouteLoader active={transitioning} />
      <div key={location.pathname} className="route-stage">
        <Routes location={location}>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/request-portal" element={<RequestPortalPage />} />
          <Route path="/" element={<ProtectedRoute><DashboardPage /></ProtectedRoute>} />
          <Route path="/employees" element={<ProtectedRoute><EmployeesPage /></ProtectedRoute>} />
          <Route path="/employees/:id" element={<ProtectedRoute><EmployeeProfilePage /></ProtectedRoute>} />
          <Route path="/passports" element={<ProtectedRoute><PassportPage /></ProtectedRoute>} />
          <Route path="/employee-documents" element={<ProtectedRoute><EmployeeDocumentsPage /></ProtectedRoute>} />
          <Route path="/company-documents" element={<ProtectedRoute><CompanyDocumentsPage /></ProtectedRoute>} />
          <Route path="/forms/demo" element={<ProtectedRoute><FormsPage /></ProtectedRoute>} />
          <Route path="/forms/passport-withdrawal" element={<ProtectedRoute><FormsPage /></ProtectedRoute>} />
          <Route path="/forms/rejoining-report" element={<ProtectedRoute><RejoiningFormPage /></ProtectedRoute>} />
          <Route path="/requests" element={<ProtectedRoute><RequestsPage /></ProtectedRoute>} />
          <Route path="/reports" element={<ProtectedRoute><ReportsPage /></ProtectedRoute>} />
          <Route path="/settings" element={<ProtectedRoute><SettingsPage /></ProtectedRoute>} />
          <Route path="/admin/log-report" element={<ProtectedRoute><LogReportPage /></ProtectedRoute>} />
          <Route path="/admin/backup" element={<ProtectedRoute><BackupPage /></ProtectedRoute>} />
        </Routes>
      </div>
    </>
  );
}
