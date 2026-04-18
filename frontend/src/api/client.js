import axios from 'axios';

const API_PATH = '/hr/backend/public/api';

function resolveApiBaseUrl() {
  const configuredBaseUrl = import.meta.env.VITE_API_BASE_URL?.trim();
  if (configuredBaseUrl) {
    return configuredBaseUrl.replace(/\/$/, '');
  }

  if (typeof window === 'undefined') {
    return `http://localhost${API_PATH}`;
  }

  const isFrontendDevServer = ['4173', '5173'].includes(window.location.port);
  const baseOrigin = isFrontendDevServer
    ? `${window.location.protocol}//${window.location.hostname}`
    : window.location.origin;

  return `${baseOrigin}${API_PATH}`;
}

const apiBaseUrl = resolveApiBaseUrl();

const client = axios.create({
  baseURL: apiBaseUrl,
  withCredentials: true,
});

export function buildFileUrl(path) {
  if (!path) {
    return '#';
  }

  return `${apiBaseUrl}/files/view?path=${encodeURIComponent(path)}`;
}

export const api = {
  login: (payload) => client.post('/auth/login', payload),
  me: () => client.get('/auth/me'),
  logout: () => client.post('/auth/logout'),
  profile: () => client.get('/profile'),
  updateProfile: (payload) => client.post('/profile', payload, { headers: { 'Content-Type': 'multipart/form-data' } }),
  updatePassword: (payload) => client.post('/profile/password', payload),
  dashboard: () => client.get('/dashboard/summary'),
  lookups: () => client.get('/lookups'),
  globalSearch: (params) => client.get('/search/global', { params }),
  portalEmployees: (params) => client.get('/request-portal/employees', { params }),
  submitEmployeeRequest: (payload) => client.post('/request-portal/requests', payload),
  employees: (params) => client.get('/employees', { params }),
  employee: (id) => client.get(`/employees/${id}`),
  employeeSummary: () => client.get('/employees/status-summary'),
  saveEmployee: (payload) => client.post('/employees', payload, { headers: { 'Content-Type': 'multipart/form-data' } }),
  updateEmployee: (id, payload) => client.post(`/employees/${id}`, payload, { headers: { 'Content-Type': 'multipart/form-data' } }),
  deleteEmployee: (id) => client.post(`/employees/${id}/delete`),
  passports: (params) => client.get('/passports', { params }),
  passportHistory: (employeeId) => client.get(`/passports/history/${employeeId}`),
  savePassport: (payload) => client.post('/passports', payload, { headers: { 'Content-Type': 'multipart/form-data' } }),
  deletePassport: (id) => client.post(`/passports/${id}/delete`),
  employeeDocuments: (params) => client.get('/employee-documents', { params }),
  saveEmployeeDocument: (payload) => client.post('/employee-documents', payload, { headers: { 'Content-Type': 'multipart/form-data' } }),
  updateEmployeeDocument: (id, payload) => client.post(`/employee-documents/${id}`, payload, { headers: { 'Content-Type': 'multipart/form-data' } }),
  deleteEmployeeDocument: (id) => client.post(`/employee-documents/${id}/delete`),
  companyDocuments: (params) => client.get('/company-documents', { params }),
  saveCompanyDocument: (payload) => client.post('/company-documents', payload, { headers: { 'Content-Type': 'multipart/form-data' } }),
  updateCompanyDocument: (id, payload) => client.post(`/company-documents/${id}`, payload, { headers: { 'Content-Type': 'multipart/form-data' } }),
  deleteCompanyDocument: (id) => client.post(`/company-documents/${id}/delete`),
  settings: () => client.get('/settings'),
  saveSettingMaster: (type, payload) => client.post(`/settings/master/${type}`, payload),
  saveSettingValue: (payload) => client.post('/settings', payload),
  notifications: () => client.get('/notifications'),
  readNotification: (id) => client.post(`/notifications/${id}/read`),
  readAllNotifications: () => client.post('/notifications/read-all'),
  users: () => client.get('/users'),
  saveUser: (payload) => client.post('/users', payload, { headers: { 'Content-Type': 'multipart/form-data' } }),
  updateUser: (id, payload) => client.post(`/users/${id}`, payload, { headers: { 'Content-Type': 'multipart/form-data' } }),
  passportReport: (params) => client.get('/reports/passports', { params }),
  passportMovementReport: (params) => client.get('/reports/passport-movements', { params }),
  expiryReport: (params) => client.get('/reports/expiry', { params }),
  employeeSummaryReport: (params) => client.get('/reports/employee-summary', { params }),
  requests: (params) => client.get('/requests', { params }),
  approveRequest: (id) => client.post(`/requests/${id}/approve`),
  activityLogs: (params) => client.get('/admin/activity-logs', { params }),
  downloadDatabaseBackup: () => client.get('/admin/backups/database', { responseType: 'blob' }),
};

export default client;
