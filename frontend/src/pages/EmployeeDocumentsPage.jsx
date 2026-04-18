import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api, buildFileUrl } from '../api/client';
import AppShell from '../components/AppShell';
import EmployeeAutocomplete from '../components/EmployeeAutocomplete';
import { Modal, PageToolbar, SectionCard, StatusBadge, Toast } from '../components/UI';
import { buildFormData } from '../utils/forms';

const emptyForm = {
  employee_id: '',
  document_master_id: '',
  document_number: '',
  issue_date: '',
  expiry_date: '',
  remarks: '',
  alert_days: 30,
  mail_enabled: true,
  notification_enabled: true,
  document_file: null,
};

export default function EmployeeDocumentsPage() {
  const [params, setParams] = useSearchParams();
  const [rows, setRows] = useState([]);
  const [lookups, setLookups] = useState({ employees: [], employeeDocumentMasters: [] });
  const [open, setOpen] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState(null);
  const [deleteRow, setDeleteRow] = useState(null);
  const search = params.get('search') || '';

  async function loadRows() {
    const { data } = await api.employeeDocuments({ search });
    setRows(data.data);
  }

  useEffect(() => {
    loadRows();
  }, [search]);

  useEffect(() => {
    api.lookups().then(({ data }) => setLookups(data.data));
  }, []);

  function openCreate() {
    setEditingId(null);
    setForm(emptyForm);
    setOpen(true);
  }

  function openEdit(row) {
    setEditingId(row.id);
    setForm({
      employee_id: row.employee_id || '',
      document_master_id: row.document_master_id || '',
      document_number: row.document_number || '',
      issue_date: row.issue_date || '',
      expiry_date: row.expiry_date || '',
      remarks: row.remarks || '',
      alert_days: row.alert_days || 30,
      mail_enabled: Boolean(Number(row.mail_enabled)),
      notification_enabled: Boolean(Number(row.notification_enabled)),
      document_file: null,
    });
    setOpen(true);
  }

  function updateField(key, value) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setSaving(true);

    try {
      const payload = buildFormData(form);
      if (editingId) {
        await api.updateEmployeeDocument(editingId, payload);
      } else {
        await api.saveEmployeeDocument(payload);
      }
      await loadRows();
      setOpen(false);
      setToast({
        type: 'success',
        title: editingId ? 'Employee document updated' : 'Employee document added',
        message: 'Expiry tracking and notification settings were saved.',
      });
    } catch (error) {
      setToast({
        type: 'error',
        title: 'Unable to save employee document',
        message: error.response?.data?.message || 'Review the selected employee and document type.',
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
      await api.deleteEmployeeDocument(deleteRow.id);
      await loadRows();
      setDeleteRow(null);
      setToast({
        type: 'success',
        title: 'Employee document deleted',
        message: 'The document was removed from the active register.',
      });
    } catch (error) {
      setToast({
        type: 'error',
        title: 'Unable to delete employee document',
        message: error.response?.data?.message || 'Delete failed.',
      });
    }
  }

  return (
    <AppShell
      title="Employee Documents"
      subtitle="Track employee-level document validity, alerts, and renewal status."
      breadcrumbs={['Home', 'Employees', 'Employee Documents']}
      actions={(
        <button type="button" className="primary-button small-button" onClick={openCreate}>
          <Plus size={16} />
          <span>Add Document</span>
        </button>
      )}
    >
      <PageToolbar title="Document Expiry Control" subtitle="Maintain employee-linked documents and reminder settings." />
      <SectionCard
        title="Document Register"
        subtitle="Passport, visa, EID, labour, insurance, contracts, and custom master-based documents."
        action={(
          <div className="toolbar-search">
            <Search size={16} />
            <input
              value={search}
              onChange={(event) => {
                const value = event.target.value.trimStart();
                const next = new URLSearchParams(params);
                if (value) {
                  next.set('search', value);
                } else {
                  next.delete('search');
                }
                setParams(next);
              }}
              placeholder="Search employee, code, document number"
            />
          </div>
        )}
      >
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Document</th>
                <th>Number</th>
                <th>Expiry</th>
                <th>Alert Days</th>
                <th>Status</th>
                <th>Attachment</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>{row.full_name}</td>
                  <td>{row.document_type}</td>
                  <td>{row.document_number || '-'}</td>
                  <td>{row.expiry_date || '-'}</td>
                  <td>{row.alert_days}</td>
                  <td><StatusBadge value={row.status} /></td>
                  <td>{row.file_path ? <a href={buildFileUrl(row.file_path)} target="_blank" rel="noreferrer" className="file-link">View</a> : '-'}</td>
                  <td>
                    <div className="table-actions">
                      <button type="button" className="secondary-button small-button" onClick={() => openEdit(row)}><Pencil size={14} /></button>
                      <button
                        type="button"
                        className="secondary-button small-button delete-button"
                        onClick={() => setDeleteRow(row)}
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
        title={editingId ? 'Edit Employee Document' : 'Add Employee Document'}
        subtitle="Save issue and expiry dates, notification rules, and document tracking details."
        onClose={() => setOpen(false)}
        footer={(
          <>
            <button type="button" className="secondary-button" onClick={() => setOpen(false)}>Cancel</button>
            <button type="submit" form="employee-document-form" className="primary-button" disabled={saving}>
              {saving ? 'Saving...' : 'Save Document'}
            </button>
          </>
        )}
      >
        <form id="employee-document-form" className="form-grid" onSubmit={handleSubmit}>
          <EmployeeAutocomplete
            employees={lookups.employees}
            value={form.employee_id}
            onChange={(value) => updateField('employee_id', value)}
            required
          />
          <label><span>Document Type</span>
            <select value={form.document_master_id} onChange={(event) => updateField('document_master_id', event.target.value)} required>
              <option value="">Select document type</option>
              {lookups.employeeDocumentMasters.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          </label>
          <label><span>Document Number</span><input value={form.document_number} onChange={(event) => updateField('document_number', event.target.value)} /></label>
          <label><span>Alert Days</span><input type="number" value={form.alert_days} onChange={(event) => updateField('alert_days', event.target.value)} min="0" /></label>
          <label><span>Issue Date</span><input type="date" value={form.issue_date} onChange={(event) => updateField('issue_date', event.target.value)} /></label>
          <label><span>Expiry Date</span><input type="date" value={form.expiry_date} onChange={(event) => updateField('expiry_date', event.target.value)} /></label>
          <label className="field-span-2"><span>Document Attachment</span><input type="file" onChange={(event) => updateField('document_file', event.target.files?.[0] || null)} /></label>
          <div className="field field-span-2">
            <span>Notification Options</span>
            <div className="inline-checks">
              <label><input type="checkbox" checked={form.mail_enabled} onChange={(event) => updateField('mail_enabled', event.target.checked)} />Mail Reminder</label>
              <label><input type="checkbox" checked={form.notification_enabled} onChange={(event) => updateField('notification_enabled', event.target.checked)} />Dashboard Notification</label>
            </div>
          </div>
          <label className="field-span-2"><span>Remarks</span><textarea value={form.remarks} onChange={(event) => updateField('remarks', event.target.value)} /></label>
        </form>
      </Modal>

      <Toast toast={toast} onClose={() => setToast(null)} />

      <Modal
        open={Boolean(deleteRow)}
        title="Delete Employee Document"
        subtitle="Do you want to delete this employee document?"
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
            <p>{deleteRow.document_type}</p>
          </div>
        ) : null}
      </Modal>
    </AppShell>
  );
}
