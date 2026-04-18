import { Pencil, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { api, buildFileUrl } from '../api/client';
import AppShell from '../components/AppShell';
import { Modal, PageToolbar, SectionCard, StatusBadge, Toast } from '../components/UI';
import { buildFormData } from '../utils/forms';

const emptyForm = {
  company_id: '',
  branch_id: '',
  document_master_id: '',
  document_name: '',
  document_number: '',
  issue_date: '',
  expiry_date: '',
  remarks: '',
  alert_days: 30,
  mail_enabled: true,
  notification_enabled: true,
  document_file: null,
};

export default function CompanyDocumentsPage() {
  const [rows, setRows] = useState([]);
  const [lookups, setLookups] = useState({ companies: [], branches: [], companyDocumentMasters: [] });
  const [open, setOpen] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [form, setForm] = useState(emptyForm);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState(null);

  async function loadRows() {
    const { data } = await api.companyDocuments();
    setRows(data.data);
  }

  useEffect(() => {
    loadRows();
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
      company_id: row.company_id || '',
      branch_id: row.branch_id || '',
      document_master_id: row.document_master_id || '',
      document_name: row.document_name || '',
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
        await api.updateCompanyDocument(editingId, payload);
      } else {
        await api.saveCompanyDocument(payload);
      }
      await loadRows();
      setOpen(false);
      setToast({
        type: 'success',
        title: editingId ? 'Company document updated' : 'Company document added',
        message: 'The company compliance record has been saved.',
      });
    } catch (error) {
      setToast({
        type: 'error',
        title: 'Unable to save company document',
        message: error.response?.data?.message || 'Review the document values and try again.',
      });
    } finally {
      setSaving(false);
    }
  }

  return (
    <AppShell
      title="Company Documents"
      subtitle="Corporate compliance register for licenses, VAT, insurance, tenancy, and related files."
      breadcrumbs={['Home', 'Company', 'Company Documents']}
      actions={(
        <button type="button" className="primary-button small-button" onClick={openCreate}>
          <Plus size={16} />
          <span>Add Company Document</span>
        </button>
      )}
    >
      <PageToolbar title="Company Compliance Register" subtitle="Track corporate renewals, branch records, and notification settings." />
      <SectionCard title="Company Register" subtitle="Tracked with alert windows, notification toggles, and expiry states.">
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                <th>Document</th>
                <th>Type</th>
                <th>Company</th>
                <th>Branch</th>
                <th>Expiry</th>
                <th>Status</th>
                <th>Attachment</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>{row.document_name}</td>
                  <td>{row.document_type}</td>
                  <td>{row.company_name || '-'}</td>
                  <td>{row.branch_name || '-'}</td>
                  <td>{row.expiry_date || '-'}</td>
                  <td><StatusBadge value={row.status} /></td>
                  <td>{row.file_path ? <a href={buildFileUrl(row.file_path)} target="_blank" rel="noreferrer" className="file-link">View</a> : '-'}</td>
                  <td><button type="button" className="secondary-button small-button" onClick={() => openEdit(row)}><Pencil size={14} /></button></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </SectionCard>

      <Modal
        open={open}
        title={editingId ? 'Edit Company Document' : 'Add Company Document'}
        subtitle="Maintain branch and company compliance documents with alert and mail settings."
        onClose={() => setOpen(false)}
        footer={(
          <>
            <button type="button" className="secondary-button" onClick={() => setOpen(false)}>Cancel</button>
            <button type="submit" form="company-document-form" className="primary-button" disabled={saving}>
              {saving ? 'Saving...' : 'Save Document'}
            </button>
          </>
        )}
      >
        <form id="company-document-form" className="form-grid" onSubmit={handleSubmit}>
          <label><span>Company</span>
            <select value={form.company_id} onChange={(event) => updateField('company_id', event.target.value)} required>
              <option value="">Select company</option>
              {lookups.companies.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          </label>
          <label><span>Branch</span>
            <select value={form.branch_id} onChange={(event) => updateField('branch_id', event.target.value)}>
              <option value="">Select branch</option>
              {lookups.branches.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          </label>
          <label><span>Document Type</span>
            <select value={form.document_master_id} onChange={(event) => updateField('document_master_id', event.target.value)} required>
              <option value="">Select document type</option>
              {lookups.companyDocumentMasters.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          </label>
          <label><span>Document Name</span><input value={form.document_name} onChange={(event) => updateField('document_name', event.target.value)} required /></label>
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
    </AppShell>
  );
}
