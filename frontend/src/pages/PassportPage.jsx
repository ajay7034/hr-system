import { Pencil, Plus, Search, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { api, buildFileUrl } from '../api/client';
import AppShell from '../components/AppShell';
import EmployeeAutocomplete from '../components/EmployeeAutocomplete';
import { Modal, PageToolbar, SectionCard, StatusBadge, Toast } from '../components/UI';
import { buildFormData } from '../utils/forms';

const today = new Date().toISOString().slice(0, 10);

const emptyForm = {
  employee_id: '',
  passport_number: '',
  issue_date: '',
  expiry_date: '',
  movement_type: 'collected',
  movement_date: today,
  reason: '',
  remarks: '',
  passport_file: null,
};

function extractPassportDefaults(payload = {}) {
  const employee = payload.employee || {};
  const passportRecord = payload.passport || {};
  const documents = payload.documents || [];
  const passportDocument = documents.find((document) => (
    String(document.document_type || '')
      .trim()
      .toLowerCase()
      .includes('passport')
  ));

  return {
    passport_number: passportDocument?.document_number || passportRecord.passport_number || employee.passport_number || '',
    issue_date: passportDocument?.issue_date || passportRecord.issue_date || '',
    expiry_date: passportDocument?.expiry_date || passportRecord.expiry_date || '',
  };
}

export default function PassportPage() {
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const [rows, setRows] = useState([]);
  const [employees, setEmployees] = useState([]);
  const [history, setHistory] = useState([]);
  const [selectedRow, setSelectedRow] = useState(null);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [saving, setSaving] = useState(false);
  const [toast, setToast] = useState(null);
  const [deleteRow, setDeleteRow] = useState(null);
  const [importOpen, setImportOpen] = useState(false);
  const [importFile, setImportFile] = useState(null);
  const [importSummary, setImportSummary] = useState(null);
  const status = params.get('status') || '';
  const search = params.get('search') || '';

  async function loadRows() {
    const { data } = await api.passports({ status, search });
    setRows(data.data);
  }

  useEffect(() => {
    loadRows();
  }, [search, status]);

  useEffect(() => {
    api.lookups().then(({ data }) => setEmployees(data.data.employees || []));
  }, []);

  const title = useMemo(() => {
    if (status === 'in_hand') return 'Passports In Hand';
    if (status === 'outside') return 'Passports Outside';
    return 'Passport Custody';
  }, [status]);

  async function openManage(row = null) {
    setSelectedRow(row);
    setForm({
      employee_id: row?.employee_id || '',
      passport_number: row?.passport_number || '',
      issue_date: row?.issue_date || '',
      expiry_date: row?.expiry_date || '',
      movement_type: row?.current_status === 'in_hand' ? 'given_back' : 'collected',
      movement_date: today,
      reason: '',
      remarks: row?.remarks || '',
      passport_file: null,
    });
    if (row?.employee_id) {
      const { data } = await api.passportHistory(row.employee_id);
      setHistory(data.data);
    } else {
      setHistory([]);
    }
    setOpen(true);
  }

  function updateField(key, value) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  async function handleEmployeeChange(employeeId) {
    if (!employeeId) {
      setForm((current) => ({
        ...current,
        employee_id: '',
        passport_number: '',
        issue_date: '',
        expiry_date: '',
      }));
      setHistory([]);
      return;
    }

    setForm((current) => ({ ...current, employee_id: employeeId }));

    try {
      const [{ data: employeeData }, { data: historyData }] = await Promise.all([
        api.employee(employeeId),
        api.passportHistory(employeeId),
      ]);
      const defaults = extractPassportDefaults(employeeData.data || {});

      setForm((current) => {
        if (String(current.employee_id) !== String(employeeId)) {
          return current;
        }

        return {
          ...current,
          passport_number: defaults.passport_number,
          issue_date: defaults.issue_date,
          expiry_date: defaults.expiry_date,
        };
      });
      setHistory(historyData.data || []);
    } catch {
      setToast({
        type: 'error',
        title: 'Unable to load passport details',
        message: 'Passport defaults could not be loaded from the employee passport document.',
      });
    }
  }

  const selectedEmployee = useMemo(
    () => employees.find((employee) => String(employee.id) === String(form.employee_id)),
    [employees, form.employee_id]
  );

  async function saveMovement({ redirectToForm = false } = {}) {
    setSaving(true);

    try {
      await api.savePassport(buildFormData(form));
      await loadRows();
      if (form.employee_id) {
        const { data } = await api.passportHistory(form.employee_id);
        setHistory(data.data);
      }
      setOpen(false);

      if (redirectToForm && form.movement_type === 'given_back') {
        const next = new URLSearchParams({
          employee_id: String(form.employee_id || ''),
          withdrawal_date: form.movement_date || '',
          form_date: form.movement_date || '',
          reason: form.reason || '',
          passport_number: form.passport_number || '',
        });

        navigate(`/forms/passport-withdrawal?${next.toString()}`);
        return;
      }

      if (redirectToForm && form.movement_type === 'collected') {
        const next = new URLSearchParams({
          employee_id: String(form.employee_id || ''),
          form_date: form.movement_date || '',
          rejoin_date: form.movement_date || '',
          passport_number: form.passport_number || '',
          passport_received_at_head_office: 'YES',
        });

        navigate(`/forms/rejoining-report?${next.toString()}`);
        return;
      }

      setToast({
        type: 'success',
        title: 'Passport custody updated',
        message: 'Movement date, reason, and live holder status were saved.',
      });
    } catch (error) {
      setToast({
        type: 'error',
        title: 'Unable to update passport custody',
        message: error.response?.data?.message || 'Please review the selected employee and dates.',
      });
    } finally {
      setSaving(false);
    }
  }

  async function handleSubmit(event) {
    event.preventDefault();
    await saveMovement();
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
      const { data } = await api.importPassports(payload);
      await loadRows();
      setImportSummary(data.data);
      setToast({
        type: 'success',
        title: 'Passport import completed',
        message: `${data.data.success_count} rows imported successfully.`,
      });
    } catch (error) {
      setToast({
        type: 'error',
        title: 'Passport import failed',
        message: error.response?.data?.message || 'Check the CSV file and try again.',
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
      await api.deletePassport(deleteRow.id);
      await loadRows();
      setDeleteRow(null);
      setToast({
        type: 'success',
        title: 'Passport custody deleted',
        message: 'The passport custody record and its movement history were deleted.',
      });
    } catch (error) {
      setToast({
        type: 'error',
        title: 'Unable to delete passport custody',
        message: error.response?.data?.message || 'Delete failed.',
      });
    }
  }

  return (
    <AppShell
      title={title}
      subtitle="Live custody records with collected and withdrawn dates, reasons, and latest status."
      breadcrumbs={['Home', 'Employees', 'Passport Custody']}
      actions={(
        <div className="table-actions">
          <button type="button" className="secondary-button small-button" onClick={() => setImportOpen(true)}>
            Upload Excel
          </button>
          <button type="button" className="primary-button small-button" onClick={() => openManage()}>
            <Plus size={16} />
            <span>New Movement</span>
          </button>
        </div>
      )}
    >
      <PageToolbar title="Passport Movement Register" subtitle="Collected and given-back actions with traceable dates and reasons." />
      <SectionCard
        title="Custody Register"
        subtitle="Dashboard drill-down target for passport movement and holder visibility."
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
              placeholder="Search employee, code, passport number"
            />
          </div>
        )}
      >
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                <th>Employee</th>
                <th>Code</th>
                <th>Department</th>
                <th>Passport</th>
                <th>Status</th>
                <th>Collected</th>
                <th>Given Back</th>
                <th>Attachment</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.id}>
                  <td>{row.full_name}</td>
                  <td>{row.employee_code}</td>
                  <td>{row.department || '-'}</td>
                  <td>{row.passport_number}</td>
                  <td><StatusBadge value={row.current_status} /></td>
                  <td>{row.collected_date || '-'}</td>
                  <td>{row.withdrawn_date || '-'}</td>
                  <td>{row.passport_file_path ? <a href={buildFileUrl(row.passport_file_path)} target="_blank" rel="noreferrer" className="file-link">View</a> : '-'}</td>
                  <td>
                    <div className="table-actions">
                      <button type="button" className="secondary-button small-button" onClick={() => openManage(row)}>
                        <Pencil size={14} />
                      </button>
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
        title="Passport Movement"
        subtitle="Track collection and give-back actions with editable dates, reasons, and remarks."
        onClose={() => setOpen(false)}
        footer={(
          <>
            <button type="button" className="secondary-button" onClick={() => setOpen(false)}>Cancel</button>
            {['given_back', 'collected'].includes(form.movement_type) ? (
              <button
                type="button"
                className="secondary-button"
                onClick={() => saveMovement({ redirectToForm: true })}
                disabled={saving}
              >
                {saving ? 'Saving...' : 'Save and Print'}
              </button>
            ) : null}
            <button type="submit" form="passport-form" className="primary-button" disabled={saving}>
              {saving ? 'Saving...' : 'Save Movement'}
            </button>
          </>
        )}
      >
        <form id="passport-form" className="form-grid" onSubmit={handleSubmit}>
          <EmployeeAutocomplete
            employees={employees}
            value={form.employee_id}
            onChange={handleEmployeeChange}
            required
          />
          <label><span>Movement Type</span>
            <select value={form.movement_type} onChange={(event) => updateField('movement_type', event.target.value)}>
              <option value="collected">Collected</option>
              <option value="given_back">Given Back</option>
            </select>
          </label>
          <label><span>Passport Number</span><input value={form.passport_number} onChange={(event) => updateField('passport_number', event.target.value)} required /></label>
          <label><span>Movement Date</span><input type="date" value={form.movement_date} onChange={(event) => updateField('movement_date', event.target.value)} required /></label>
          <label><span>Issue Date</span><input type="date" value={form.issue_date} onChange={(event) => updateField('issue_date', event.target.value)} /></label>
          <label><span>Expiry Date</span><input type="date" value={form.expiry_date} onChange={(event) => updateField('expiry_date', event.target.value)} /></label>
          <label className="field-span-2"><span>Passport Attachment</span><input type="file" onChange={(event) => updateField('passport_file', event.target.files?.[0] || null)} /></label>
          <label className="field-span-2"><span>{form.movement_type === 'given_back' ? 'Reason For Giving Back' : 'Reason For Collecting Back'}</span><input value={form.reason} onChange={(event) => updateField('reason', event.target.value)} required /></label>
          <label className="field-span-2"><span>Remarks</span><textarea value={form.remarks} onChange={(event) => updateField('remarks', event.target.value)} /></label>
          <div className="field">
            <span>Selected Employee</span>
            <div className="pill">{selectedEmployee ? `${selectedEmployee.full_name} • ${selectedEmployee.email || selectedEmployee.passport_number || 'No extra data'}` : 'No employee selected'}</div>
          </div>
          <div className="field">
            <span>Current Live Status After Save</span>
            <div className="pill">
              <StatusBadge value={form.movement_type === 'given_back' ? 'outside' : 'in_hand'} />
            </div>
          </div>
        </form>

        {history.length ? (
          <div className="timeline" style={{ marginTop: 18 }}>
            {history.map((item) => (
              <article key={item.id} className="timeline-item">
                <span className="timeline-dot" />
                <div>
                  <strong>{item.movement_type.replaceAll('_', ' ')}</strong>
                  <p>{item.movement_date} • {item.reason || 'No reason provided'}</p>
                  <small>{item.updated_by_name || 'System'} • {item.remarks || 'No remarks'}</small>
                  {item.attachment_path ? <a href={buildFileUrl(item.attachment_path)} target="_blank" rel="noreferrer" className="file-link">Open attachment</a> : null}
                </div>
              </article>
            ))}
          </div>
        ) : null}
      </Modal>

      <Toast toast={toast} onClose={() => setToast(null)} />

      <Modal
        open={Boolean(deleteRow)}
        title="Delete Passport Custody"
        subtitle="Do you want to delete this passport custody record?"
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
            <p>{deleteRow.passport_number}</p>
          </div>
        ) : null}
      </Modal>

      <Modal
        open={importOpen}
        title="Passport Update Upload"
        subtitle="Download the template, fill it in Excel, save it as CSV, then upload it here."
        onClose={() => setImportOpen(false)}
        footer={(
          <>
            <a href="/templates/passport_update_template.csv" className="secondary-button" download>Download Template</a>
            <button type="button" className="secondary-button" onClick={() => setImportOpen(false)}>Close</button>
            <button type="submit" form="passport-import-form" className="primary-button" disabled={saving}>{saving ? 'Uploading...' : 'Upload'}</button>
          </>
        )}
      >
        <form id="passport-import-form" className="form-grid" onSubmit={handleImport}>
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
