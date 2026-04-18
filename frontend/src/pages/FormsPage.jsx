import { Download, FileText } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import AppShell from '../components/AppShell';
import { PageToolbar, SectionCard, Toast } from '../components/UI';
import { api } from '../api/client';
import { printPassportWithdrawalForm } from '../utils/printPassportWithdrawalForm';

const reasonOptions = ['TERMINATED', 'RESIGNED', 'VACATION', 'TRANSFER', 'CANCELLATION', 'PERSONAL'];
const yesNoOptions = ['NIL', 'YES', 'NO', 'PENDING', 'COMPLETED'];
const initialForm = {
  employee_id: '',
  employee_name: '',
  passport_number: '',
  designation: '',
  branch: '',
  form_date: new Date().toISOString().slice(0, 10),
  withdrawal_date: new Date().toISOString().slice(0, 10),
  reason: 'TERMINATED',
  sim_card_status: 'NIL',
  no_due_status: 'NIL',
};

const companyLogoUrl = `${import.meta.env.BASE_URL}logo-media-black.png`;

function formatDisplayDate(value) {
  if (!value) {
    return '';
  }

  const [year, month, day] = value.split('-');
  if (!year || !month || !day) {
    return value;
  }

  return `${day}/${month}/${year}`;
}

export default function FormsPage() {
  const [params] = useSearchParams();
  const [employees, setEmployees] = useState([]);
  const [form, setForm] = useState(initialForm);
  const [loadingEmployee, setLoadingEmployee] = useState(false);
  const [toast, setToast] = useState(null);

  useEffect(() => {
    api.lookups().then(({ data }) => {
      setEmployees(data.data?.employees || []);
    });
  }, []);

  async function handleEmployeeChange(employeeId, overrides = {}) {
    setForm((current) => ({ ...current, employee_id: employeeId }));

    if (!employeeId) {
      setForm((current) => ({
        ...current,
        employee_id: '',
        employee_name: '',
        passport_number: '',
        designation: '',
        branch: '',
        ...overrides,
      }));
      return;
    }

    setLoadingEmployee(true);

    try {
      const { data } = await api.employee(employeeId);
      const employee = data.data?.employee || {};
      const passport = data.data?.passport || {};

      setForm((current) => ({
        ...current,
        employee_id: employeeId,
        employee_name: employee.full_name || '',
        passport_number: overrides.passport_number || passport.passport_number || employee.passport_number || '',
        designation: employee.designation || '',
        branch: employee.branch || employee.company || '',
        ...overrides,
      }));
    } catch {
      setToast({
        type: 'error',
        title: 'Unable to load employee',
        message: 'Employee details could not be loaded for this form.',
      });
    } finally {
      setLoadingEmployee(false);
    }
  }

  useEffect(() => {
    const employeeId = params.get('employee_id') || '';
    const withdrawalDate = params.get('withdrawal_date') || '';
    const formDate = params.get('form_date') || '';
    const reason = params.get('reason') || '';
    const passportNumber = params.get('passport_number') || '';

    if (!employeeId && !withdrawalDate && !formDate && !reason && !passportNumber) {
      return;
    }

    const overrides = {
      ...(withdrawalDate ? { withdrawal_date: withdrawalDate } : {}),
      ...(formDate ? { form_date: formDate } : {}),
      ...(reason ? { reason } : {}),
      ...(passportNumber ? { passport_number: passportNumber } : {}),
    };

    if (employeeId) {
      handleEmployeeChange(employeeId, overrides);
      return;
    }

    setForm((current) => ({
      ...current,
      ...overrides,
    }));
  }, [params]);

  function updateField(key, value) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  function handleGeneratePdf() {
    const opened = printPassportWithdrawalForm({
      ...form,
      logo_url: companyLogoUrl,
      form_date_label: formatDisplayDate(form.form_date),
      withdrawal_date_label: formatDisplayDate(form.withdrawal_date),
    });

    setToast({
      type: opened ? 'success' : 'error',
      title: opened ? 'Form ready' : 'Popup blocked',
      message: opened
        ? 'The passport withdrawal form is open in the print dialog. Save it as PDF from there.'
        : 'Allow popups for this site to print or save the form as PDF.',
    });
  }

  const selectedEmployeeLabel = useMemo(
    () => employees.find((item) => String(item.id) === String(form.employee_id))?.full_name || '',
    [employees, form.employee_id]
  );

  return (
    <AppShell
      title="Forms"
      subtitle="Passport withdrawal form entry and printable template for company use."
      breadcrumbs={['Home', 'Forms', 'Passport Withdrawal Form']}
      actions={(
        <button type="button" className="primary-button small-button" onClick={handleGeneratePdf}>
          <Download size={16} />
          <span>Print / Save PDF</span>
        </button>
      )}
    >
      <PageToolbar
        title="Passport Withdrawal Form"
        subtitle="Select the employee and complete the dropdown-driven fields before printing."
        action={(
          <div className="forms-toolbar-tag">
            <FileText size={16} />
            <span>Passport Withdrawal</span>
          </div>
        )}
      />

      <div className="dashboard-grid forms-layout-grid">
        <SectionCard
          title="Form Entry"
          subtitle="Employee details are filled from the selected employee record and passport data."
        >
          <form className="form-grid" onSubmit={(event) => event.preventDefault()}>
            <label className="field-span-2">
              <span>Employee Name</span>
              <select value={form.employee_id} onChange={(event) => handleEmployeeChange(event.target.value)} required>
                <option value="">Select employee</option>
                {employees.map((item) => (
                  <option key={item.id} value={item.id}>
                    {item.full_name} {item.employee_code ? `• ${item.employee_code}` : ''}
                  </option>
                ))}
              </select>
            </label>

            <label><span>Form Date</span><input type="date" value={form.form_date} onChange={(event) => updateField('form_date', event.target.value)} /></label>
            <label><span>Passport Withdrawal Date</span><input type="date" value={form.withdrawal_date} onChange={(event) => updateField('withdrawal_date', event.target.value)} /></label>

            <label><span>Passport No.</span><input value={form.passport_number} onChange={(event) => updateField('passport_number', event.target.value)} readOnly /></label>
            <label><span>Designation</span><input value={form.designation} onChange={(event) => updateField('designation', event.target.value)} readOnly /></label>
            <label><span>Branch</span><input value={form.branch} onChange={(event) => updateField('branch', event.target.value)} readOnly /></label>
            <label><span>Employee</span><input value={form.employee_name} onChange={(event) => updateField('employee_name', event.target.value)} readOnly /></label>

            <label>
              <span>Reason</span>
              <select value={form.reason} onChange={(event) => updateField('reason', event.target.value)}>
                {reasonOptions.map((item) => <option key={item} value={item}>{item}</option>)}
              </select>
            </label>

            <label>
              <span>SIM Card</span>
              <select value={form.sim_card_status} onChange={(event) => updateField('sim_card_status', event.target.value)}>
                {yesNoOptions.map((item) => <option key={item} value={item}>{item}</option>)}
              </select>
            </label>

            <label className="field-span-2">
              <span>No Due Confirmation / Work Handing Over</span>
              <select value={form.no_due_status} onChange={(event) => updateField('no_due_status', event.target.value)}>
                {yesNoOptions.map((item) => <option key={item} value={item}>{item}</option>)}
              </select>
            </label>

            <div className="field field-span-2">
              <span>Selected Employee</span>
              <div className="pill">
                {loadingEmployee ? 'Loading employee details...' : (selectedEmployeeLabel || 'No employee selected')}
              </div>
            </div>
          </form>
        </SectionCard>

        <SectionCard
          title="Form Preview"
          subtitle="Printable template based on the passport withdrawal layout you shared."
        >
          <article className="passport-withdrawal-sheet">
            <header className="passport-withdrawal-head">
              <div className="passport-withdrawal-logo">
                <img src={companyLogoUrl} alt="Media company logo" />
              </div>
              <div className="passport-withdrawal-title">
                <h3>PASSPORT WITHDRAWAL FORM</h3>
                <h4>MEDIA GROUP OF COMPANIES</h4>
              </div>
              <div className="passport-withdrawal-meta">
                <span>Form Date</span>
                <strong>{formatDisplayDate(form.form_date) || '-'}</strong>
              </div>
            </header>

            <div className="passport-withdrawal-summary">
              <div className="passport-withdrawal-summary-card">
                <span>Employee</span>
                <strong>{form.employee_name || '-'}</strong>
              </div>
              <div className="passport-withdrawal-summary-card">
                <span>Passport No.</span>
                <strong>{form.passport_number || '-'}</strong>
              </div>
              <div className="passport-withdrawal-summary-card">
                <span>Withdrawal Date</span>
                <strong>{formatDisplayDate(form.withdrawal_date) || '-'}</strong>
              </div>
            </div>

            <div className="passport-withdrawal-table">
              <div className="passport-withdrawal-table-head">Withdrawal Details</div>
              <div className="passport-withdrawal-row"><span>Name Of Person</span><b>{form.employee_name || '-'}</b></div>
              <div className="passport-withdrawal-row"><span>Passport No.</span><b>{form.passport_number || '-'}</b></div>
              <div className="passport-withdrawal-row"><span>Designation</span><b>{form.designation || '-'}</b></div>
              <div className="passport-withdrawal-row"><span>Branch</span><b>{form.branch || '-'}</b></div>
              <div className="passport-withdrawal-row"><span>Passport Withdrawal Date</span><b>{formatDisplayDate(form.withdrawal_date) || '-'}</b></div>
              <div className="passport-withdrawal-row"><span>Reason</span><b>{form.reason || '-'}</b></div>
              <div className="passport-withdrawal-row"><span>SIM Card (to be collected, if any)</span><b>{form.sim_card_status || '-'}</b></div>
              <div className="passport-withdrawal-row"><span>No Due Confirmation / Work Handing Over</span><b>{form.no_due_status || '-'}</b></div>
            </div>

            <div className="passport-withdrawal-signatures">
              <div className="passport-withdrawal-signature-card">
                <span>Employee Signature</span>
                <strong aria-hidden="true"></strong>
              </div>
              <div className="passport-withdrawal-signature-card">
                <span>PRO Signature</span>
                <strong aria-hidden="true"></strong>
              </div>
            </div>

            <footer className="passport-withdrawal-approvals">
              <div><strong>Approved By<br />Accounts Manager</strong></div>
              <div><strong>Approved By<br />General Manager</strong></div>
              <div><strong>Approved By<br />Managing Director</strong></div>
            </footer>
          </article>
        </SectionCard>
      </div>

      <Toast toast={toast} onClose={() => setToast(null)} />
    </AppShell>
  );
}
