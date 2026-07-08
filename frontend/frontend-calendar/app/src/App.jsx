import { Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import ProtectedRoute from './components/ProtectedRoute';
import Layout from './components/Layout';
import LoginPage from './pages/LoginPage';
import PaymentCalendarPage from './pages/PaymentCalendarPage';
import EntityListPage from './pages/EntityListPage';
import EntityFormPage from './pages/EntityFormPage';
import AuditLogPage from './pages/AuditLogPage';
import ReportsPage from './pages/ReportsPage';
import AdminLayout from './pages/admin/AdminLayout';

function HomeRedirect() {
  const { roles } = useAuth();
  const isOnlyInitiator = roles.length > 0 && roles.every((r) => r === 'initiator');
  return <Navigate to={isOnlyInitiator ? '/requests' : '/calendar'} replace />;
}

export default function App() {
  return (
    <AuthProvider>
      <Routes>
        <Route path="/login" element={<LoginPage />} />

        <Route
          element={
            <ProtectedRoute>
              <Layout />
            </ProtectedRoute>
          }
        >
          <Route path="/" element={<HomeRedirect />} />
          <Route path="/calendar" element={<PaymentCalendarPage />} />

          <Route path="/requests" element={<EntityListPage entityKey="requests" />} />
          <Route path="/requests/new" element={<EntityFormPage entityKey="requests" />} />
          <Route path="/requests/:id" element={<EntityFormPage entityKey="requests" />} />

          <Route path="/receipts" element={<EntityListPage entityKey="receipts" />} />
          <Route path="/receipts/new" element={<EntityFormPage entityKey="receipts" />} />
          <Route path="/receipts/:id" element={<EntityFormPage entityKey="receipts" />} />

          <Route path="/templates" element={<EntityListPage entityKey="templates" />} />
          <Route path="/templates/new" element={<EntityFormPage entityKey="templates" />} />
          <Route path="/templates/:id" element={<EntityFormPage entityKey="templates" />} />

          <Route path="/registries" element={<EntityListPage entityKey="registries" />} />
          <Route path="/registries/new" element={<EntityFormPage entityKey="registries" />} />
          <Route path="/registries/:id" element={<EntityFormPage entityKey="registries" />} />

          <Route path="/reports" element={<ReportsPage />} />

          <Route path="/admin" element={<AdminLayout />}>
            <Route index element={<Navigate to="/admin/users" replace />} />

            <Route path="users" element={<EntityListPage entityKey="users" />} />
            <Route path="users/new" element={<EntityFormPage entityKey="users" />} />
            <Route path="users/:id" element={<EntityFormPage entityKey="users" />} />

            <Route path="counterparties" element={<EntityListPage entityKey="counterparties" />} />
            <Route path="counterparties/new" element={<EntityFormPage entityKey="counterparties" />} />
            <Route path="counterparties/:id" element={<EntityFormPage entityKey="counterparties" />} />

            <Route path="accounts" element={<EntityListPage entityKey="accounts" />} />
            <Route path="accounts/new" element={<EntityFormPage entityKey="accounts" />} />
            <Route path="accounts/:id" element={<EntityFormPage entityKey="accounts" />} />

            <Route path="articles" element={<EntityListPage entityKey="articles" />} />
            <Route path="articles/new" element={<EntityFormPage entityKey="articles" />} />
            <Route path="articles/:id" element={<EntityFormPage entityKey="articles" />} />
          </Route>

          <Route path="/audit-log" element={<AuditLogPage />} />
        </Route>

        <Route path="*" element={<HomeRedirect />} />
      </Routes>
    </AuthProvider>
  );
}
