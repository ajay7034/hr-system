import axios from 'axios';

const client = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost/hr/backend/public/api',
  withCredentials: true,
});

export function buildFileUrl(path) {
  if (!path) {
    return '#';
  }

  const apiBase = (import.meta.env.VITE_API_BASE_URL || 'http://localhost/hr/backend/public/api').replace(/\/$/, '');
  return `${apiBase}/files/view?path=${encodeURIComponent(path)}`;
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
  employees: (params) => client.get('/employees', { params }),
  employee: (id) => client.get(`/employees/${id}`),
  employeeSummary: () => client.get('/employees/status-summary'),
  saveEmployee: (payload) => client.post('/employees', payload, { headers: { 'Content-Type': 'multipart/form-data' } }),
  updateEmployee: (id, payload) => client.post(`/employees/${id}`, payload, { headers: { 'Content-Type': 'multipart/form-data' } }),
  deleteEmployee: (id) => client.post(`/employees/${id}/delete`),
  passports: (params) => client.get('/passports', { params }),
  passportHistory: (employeeId) => client.get(`/passports/history/${employeeId}`),
  savePassport: (payload) => client.post('/passports', payload, { headers: { 'Content-Type': 'multipart/form-data' } }),
  employeeDocuments: () => client.get('/employee-documents'),
  saveEmployeeDocument: (payload) => client.post('/employee-documents', payload, { headers: { 'Content-Type': 'multipart/form-data' } }),
  updateEmployeeDocument: (id, payload) => client.post(`/employee-documents/${id}`, payload, { headers: { 'Content-Type': 'multipart/form-data' } }),
  companyDocuments: () => client.get('/company-documents'),
  saveCompanyDocument: (payload) => client.post('/company-documents', payload, { headers: { 'Content-Type': 'multipart/form-data' } }),
  updateCompanyDocument: (id, payload) => client.post(`/company-documents/${id}`, payload, { headers: { 'Content-Type': 'multipart/form-data' } }),
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
};

export default client;
