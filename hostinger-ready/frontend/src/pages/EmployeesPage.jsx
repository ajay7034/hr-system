import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api, buildFileUrl } from '../api/client';
import AppShell from '../components/AppShell';
import { Modal, PageToolbar, SectionCard, StatusBadge, Toast } from '../components/UI';
import { buildFormData } from '../utils/forms';

const emptyForm = {
  employee_id: '',
  employee_code: '',
  company_id: '',
  branch_id: '',
  department_id: '',
  designation_id: '',
  full_name: '',
  first_name: '',
  last_name: '',
  email: '',
  mobile: '',
  joining_date: '',
  visa_status: '',
  emirates_id: '',
  passport_number: '',
  nationality: '',
  status: 'active',
  notes: '',
  profile_photo: null,
};

export default function EmployeesPage() {
  const navigate = useNavigate();
  const [rows, setRows] = useState([]);
  const [lookups, setLookups] = useState({ companies: [], branches: [], departments: [], designations: [] });
  const [search, setSearch] = useState('');
  const [open, setOpen] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState(null);
  const [deleteRow, setDeleteRow] = useState(null);
  const [importOpen, setImportOpen] = useState(false);
  const [importFile, setImportFile] = useState(null);
  const [importSummary, setImportSummary] = useState(null);

  async function loadEmployees() {
    const { data } = await api.employees({ search });
    setRows(data.data);
  }

  useEffect(() => {
    loadEmployees();
  }, [search]);

  useEffect(() => {
    api.lookups().then(({ data }) => setLookups(data.data));
  }, []);

  const branchOptions = useMemo(() => {
    if (!form.company_id) {
      return lookups.branches || [];
    }

    return (lookups.branches || []).filter((branch) => String(branch.company_id) === String(form.company_id));
  }, [form.company_id, lookups.branches]);

  function updateField(key, value) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function openCreate() {
    setEditingId(null);
    setForm(emptyForm);
    setOpen(true);
  }

  async function openEdit(row, event) {
    event.stopPropagation();
    const { data } = await api.employee(row.id);
    const employee = data.data.employee;

    setEditingId(row.id);
    setForm({
      employee_id: employee.employee_id || '',
      employee_code: employee.employee_code || '',
      company_id: employee.company_id || '',
      branch_id: employee.branch_id || '',
      department_id: employee.department_id || '',
      designation_id: employee.designation_id || '',
      full_name: employee.full_name || '',
      first_name: employee.first_name || '',
      last_name: employee.last_name || '',
      email: employee.email || '',
      mobile: employee.mobile || '',
      joining_date: employee.joining_date || '',
      visa_status: employee.visa_status || '',
      emirates_id: employee.emirates_id || '',
      passport_number: employee.passport_number || '',
      nationality: employee.nationality || '',
      status: employee.status || 'active',
      notes: employee.notes || '',
      profile_photo: null,
    });
    setOpen(true);
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setSaving(true);

    try {
      const payload = buildFormData(form);
      if (editingId) {
        await api.updateEmployee(editingId, payload);
      } else {
        await api.saveEmployee(payload);
      }

      await loadEmployees();
      setOpen(false);
      setToast({
        type: 'success',
        title: editingId ? 'Employee updated' : 'Employee created',
        message: 'The employee master record has been saved.',
      });
    } catch (error) {
      setToast({
        type: 'error',
        title: 'Unable to save employee',
        message: error.response?.data?.message || 'Check the form values and try again.',
      });
    } finally {
      setSaving(false);
    }
  }

  async function confirmDelete() {
    if (!deleteRow) {
      return;
    }

    try {
      await api.deleteEmployee(deleteRow.id);
      await loadEmployees();
      setDeleteRow(null);
      setToast({
        type: 'success',
        title: 'Employee deleted',
        message: 'The employee record was removed from the active master list.',
      });
    } catch (error) {
      setToast({
        type: 'error',
        title: 'Unable to delete employee',
        message: error.response?.data?.message || 'Delete failed.',
      });
    }
  }

  async function handleImport(event) {
    event.preventDefault();
    if (!importFile) {
      return;
    }

    setSaving(true);
    try {
      const payload = new FormData();
      payload.append('import_file', importFile);
      const { data } = await api.importEmployees(payload);
      await loadEmployees();
      setImportSummary(data.data);
      setToast({
        type: 'success',
        title: 'Employee import completed',
        message: `${data.data.success_count} rows imported successfully.`,
      });
    } catch (error) {
      setToast({
        type: 'error',
        title: 'Employee import failed',
        message: error.response?.data?.message || 'Check the CSV file and try again.',
      });
    } finally {
      setSaving(false);
    }
  }

  return (
    <AppShell
      title="Employee Master"
      subtitle="Search, review, and maintain employee profiles with drill-down access."
      breadcrumbs={['Home', 'Employees', 'Employee Master']}
      actions={(
        <div className="table-actions">
          <button type="button" className="secondary-button small-button" onClick={() => setImportOpen(true)}>
            Upload Excel
          </button>
          <button type="button" className="primary-button small-button" onClick={openCreate}>
            <Plus size={16} />
            <span>Add Employee</span>
          </button>
        </div>
      )}
    >
      <PageToolbar title="Manage Employee Records" subtitle="Create, edit, and review employee master data." />
      <SectionCard
        title="Employee Directory"
        subtitle="Responsive employee register with passport and profile linkage."
        action={(
          <div className="toolbar-search">
            <Search size={16} />
            <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search employee, code, passport, email" />
          </div>
        )}
      >
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Department</th>
                <th>Designation</th>
                <th>Branch</th>
                <th>Status</th>
                <th>Passport</th>
                <th>Attachment</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id} onClick={() => navigate(`/employees/${row.id}`)}>
                  <td>{row.employee_code}</td>
                  <td>{row.full_name}</td>
                  <td>{row.department || '-'}</td>
                  <td>{row.designation || '-'}</td>
                  <td>{row.branch || '-'}</td>
                  <td><StatusBadge value={row.status} /></td>
                  <td><StatusBadge value={row.passport_status || 'outside'} /></td>
                  <td>
                    {row.profile_photo_path ? <a href={buildFileUrl(row.profile_photo_path)} target="_blank" rel="noreferrer" className="file-link">View</a> : '-'}
                  </td>
                  <td>
                    <div className="table-actions">
                      <button type="button" className="secondary-button small-button" onClick={(event) => openEdit(row, event)}>
                        <Pencil size={14} />
                      </button>
                      <button
                        type="button"
                        className="secondary-button small-button delete-button"
                        onClick={(event) => {
                          event.stopPropagation();
                          setDeleteRow(row);
                        }}
                      >
                        <Trash2 size={14} />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </SectionCard>

      <Modal
        open={open}
        title={editingId ? 'Edit Employee' : 'Add Employee'}
        subtitle="Maintain employee master details, contact data, joining information, and passport identifiers."
        onClose={() => setOpen(false)}
        footer={(
          <>
            <button type="button" className="secondary-button" onClick={() => setOpen(false)}>Cancel</button>
            <button type="submit" form="employee-form" className="primary-button" disabled={saving}>
              {saving ? 'Saving...' : 'Save Employee'}
            </button>
          </>
        )}
      >
        <form id="employee-form" className="form-grid" onSubmit={handleSubmit}>
          <label><span>Employee ID</span><input value={form.employee_id} onChange={(event) => updateField('employee_id', event.target.value)} /></label>
          <label><span>Employee Code</span><input value={form.employee_code} onChange={(event) => updateField('employee_code', event.target.value)} required /></label>
          <label className="field-span-2"><span>Full Name</span><input value={form.full_name} onChange={(event) => updateField('full_name', event.target.value)} required /></label>
          <label><span>First Name</span><input value={form.first_name} onChange={(event) => updateField('first_name', event.target.value)} /></label>
          <label><span>Last Name</span><input value={form.last_name} onChange={(event) => updateField('last_name', event.target.value)} /></label>
          <label><span>Company</span>
            <select value={form.company_id} onChange={(event) => updateField('company_id', event.target.value)}>
              <option value="">Select company</option>
              {lookups.companies.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          </label>
          <label><span>Branch</span>
            <select value={form.branch_id} onChange={(event) => updateField('branch_id', event.target.value)}>
              <option value="">Select branch</option>
              {branchOptions.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          </label>
          <label><span>Department</span>
            <select value={form.department_id} onChange={(event) => updateField('department_id', event.target.value)}>
              <option value="">Select department</option>
              {lookups.departments.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          </label>
          <label><span>Designation</span>
            <select value={form.designation_id} onChange={(event) => updateField('designation_id', event.target.value)}>
              <option value="">Select designation</option>
              {lookups.designations.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          </label>
          <label><span>Email</span><input type="email" value={form.email} onChange={(event) => updateField('email', event.target.value)} /></label>
          <label><span>Mobile</span><input value={form.mobile} onChange={(event) => updateField('mobile', event.target.value)} /></label>
          <label><span>Joining Date</span><input type="date" value={form.joining_date} onChange={(event) => updateField('joining_date', event.target.value)} /></label>
          <label><span>Visa Status</span><input value={form.visa_status} onChange={(event) => updateField('visa_status', event.target.value)} /></label>
          <label><span>Emirates ID</span><input value={form.emirates_id} onChange={(event) => updateField('emirates_id', event.target.value)} /></label>
          <label><span>Passport Number</span><input value={form.passport_number} onChange={(event) => updateField('passport_number', event.target.value)} /></label>
          <label><span>Nationality</span><input value={form.nationality} onChange={(event) => updateField('nationality', event.target.value)} /></label>
          <label><span>Status</span>
            <select value={form.status} onChange={(event) => updateField('status', event.target.value)}>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="resigned">Resigned</option>
              <option value="terminated">Terminated</option>
            </select>
          </label>
          <label><span>Profile Attachment</span><input type="file" onChange={(event) => updateField('profile_photo', event.target.files?.[0] || null)} /></label>
          <label className="field-span-2"><span>Notes</span><textarea value={form.notes} onChange={(event) => updateField('notes', event.target.value)} /></label>
        </form>
      </Modal>

      <Toast toast={toast} onClose={() => setToast(null)} />

      <Modal
        open={Boolean(deleteRow)}
        title="Delete Employee"
        subtitle="Do you want to delete this employee from the master list?"
        onClose={() => setDeleteRow(null)}
        footer={(
          <>
            <button type="button" className="secondary-button" onClick={() => setDeleteRow(null)}>No</button>
            <button type="button" className="primary-button" onClick={confirmDelete}>Yes</button>
          </>
        )}
      >
        {deleteRow ? (
          <div className="confirm-copy">
            <strong>{deleteRow.full_name}</strong>
            <p>{deleteRow.employee_code}</p>
          </div>
        ) : null}
      </Modal>

      <Modal
        open={importOpen}
        title="Employee Master Upload"
        subtitle="Download the template, fill it in Excel, save it as CSV, then upload it here."
        onClose={() => setImportOpen(false)}
        footer={(
          <>
            <a href="/templates/employee_master_template.csv" className="secondary-button" download>Download Template</a>
            <button type="button" className="secondary-button" onClick={() => setImportOpen(false)}>Close</button>
            <button type="submit" form="employee-import-form" className="primary-button" disabled={saving}>{saving ? 'Uploading...' : 'Upload'}</button>
          </>
        )}
      >
        <form id="employee-import-form" className="form-grid" onSubmit={handleImport}>
          <label className="field-span-2"><span>Excel-Compatible CSV File</span><input type="file" accept=".csv" onChange={(event) => setImportFile(event.target.files?.[0] || null)} required /></label>
          {importSummary ? (
            <div className="field-span-2 import-summary">
              <strong>Last Import Summary</strong>
              <p>Success: {importSummary.success_count} | Failed: {importSummary.failed_count}</p>
            </div>
          ) : null}
        </form>
      </Modal>
    </AppShell>
  );
}
