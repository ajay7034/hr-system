import { Download, FileText } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api } from '../api/client';
import AppShell from '../components/AppShell';
import { PageToolbar, SectionCard, Toast } from '../components/UI';
import { printRejoiningReport } from '../utils/printRejoiningReport';

const yesNoOptions = ['YES', 'NO'];
const initialForm = {
  employee_id: '',
  employee_name: '',
  designation: '',
  nationality: '',
  contact_no: '',
  passport_number: '',
  form_date: new Date().toISOString().slice(0, 10),
  vacation_start_date: '',
  vacation_end_date: '',
  rejoin_date: new Date().toISOString().slice(0, 10),
  passport_received_at_head_office: 'YES',
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

export default function RejoiningFormPage() {
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
        designation: '',
        nationality: '',
        contact_no: '',
        passport_number: '',
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
        designation: employee.designation || '',
        nationality: employee.nationality || '',
        contact_no: employee.mobile || '',
        passport_number: overrides.passport_number || passport.passport_number || employee.passport_number || '',
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
    const formDate = params.get('form_date') || '';
    const rejoinDate = params.get('rejoin_date') || '';
    const passportNumber = params.get('passport_number') || '';
    const vacationStartDate = params.get('vacation_start_date') || '';
    const vacationEndDate = params.get('vacation_end_date') || '';
    const passportReceived = params.get('passport_received_at_head_office') || '';

    if (!employeeId && !formDate && !rejoinDate && !passportNumber && !vacationStartDate && !vacationEndDate && !passportReceived) {
      return;
    }

    const overrides = {
      ...(formDate ? { form_date: formDate } : {}),
      ...(rejoinDate ? { rejoin_date: rejoinDate } : {}),
      ...(passportNumber ? { passport_number: passportNumber } : {}),
      ...(vacationStartDate ? { vacation_start_date: vacationStartDate } : {}),
      ...(vacationEndDate ? { vacation_end_date: vacationEndDate } : {}),
      ...(passportReceived ? { passport_received_at_head_office: passportReceived } : {}),
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
    const opened = printRejoiningReport({
      ...form,
      logo_url: companyLogoUrl,
      form_date_label: formatDisplayDate(form.form_date),
      rejoin_date_label: formatDisplayDate(form.rejoin_date),
      vacation_start_date_label: formatDisplayDate(form.vacation_start_date),
      vacation_end_date_label: formatDisplayDate(form.vacation_end_date),
    });

    setToast({
      type: opened ? 'success' : 'error',
      title: opened ? 'Form ready' : 'Popup blocked',
      message: opened
        ? 'The rejoining report is open in the print dialog. Save it as PDF from there.'
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
      subtitle="Rejoining report entry and printable template for passport collection returns."
      breadcrumbs={['Home', 'Forms', 'Rejoining Report']}
      actions={(
        <button type="button" className="primary-button small-button" onClick={handleGeneratePdf}>
          <Download size={16} />
          <span>Print / Save PDF</span>
        </button>
      )}
    >
      <PageToolbar
        title="Rejoining Report"
        subtitle="Select the employee and complete the rejoining details before printing."
        action={(
          <div className="forms-toolbar-tag">
            <FileText size={16} />
            <span>Rejoining Report</span>
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

            <label>
              <span>Form Date</span>
              <input type="date" value={form.form_date} onChange={(event) => updateField('form_date', event.target.value)} />
            </label>
            <label>
              <span>Date Of Rejoin</span>
              <input type="date" value={form.rejoin_date} onChange={(event) => updateField('rejoin_date', event.target.value)} />
            </label>

            <label>
              <span>Vacation Start Date</span>
              <input type="date" value={form.vacation_start_date} onChange={(event) => updateField('vacation_start_date', event.target.value)} />
            </label>
            <label>
              <span>Vacation End Date</span>
              <input type="date" value={form.vacation_end_date} onChange={(event) => updateField('vacation_end_date', event.target.value)} />
            </label>

            <label>
              <span>Passport Received At Head Office</span>
              <select
                value={form.passport_received_at_head_office}
                onChange={(event) => updateField('passport_received_at_head_office', event.target.value)}
              >
                {yesNoOptions.map((item) => <option key={item} value={item}>{item}</option>)}
              </select>
            </label>
            <label><span>Passport No.</span><input value={form.passport_number} onChange={(event) => updateField('passport_number', event.target.value)} readOnly /></label>
            <label><span>Designation</span><input value={form.designation} onChange={(event) => updateField('designation', event.target.value)} readOnly /></label>
            <label><span>Nationality</span><input value={form.nationality} onChange={(event) => updateField('nationality', event.target.value)} readOnly /></label>
            <label><span>Contact No.</span><input value={form.contact_no} onChange={(event) => updateField('contact_no', event.target.value)} readOnly /></label>
            <label className="field-span-2"><span>Employee</span><input value={form.employee_name} onChange={(event) => updateField('employee_name', event.target.value)} readOnly /></label>

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
          subtitle="Printable template based on the rejoining report layout you shared."
        >
          <article className="passport-withdrawal-sheet">
            <header className="passport-withdrawal-head">
              <div className="passport-withdrawal-logo">
                <img src={companyLogoUrl} alt="Media company logo" />
              </div>
              <div className="passport-withdrawal-title">
                <h3>REJOINING REPORT</h3>
                <h4>MEDIA GROUP OF COMPANIES</h4>
              </div>
              <div className="passport-withdrawal-meta">
                <span>Form Date</span>
                <strong>{formatDisplayDate(form.form_date) || '-'}</strong>
              </div>
            </header>

            <div className="passport-withdrawal-summary passport-withdrawal-summary-two">
              <div className="passport-withdrawal-summary-card">
                <span>Employee</span>
                <strong>{form.employee_name || '-'}</strong>
              </div>
              <div className="passport-withdrawal-summary-card">
                <span>Date Of Rejoin</span>
                <strong>{formatDisplayDate(form.rejoin_date) || '-'}</strong>
              </div>
            </div>

            <div className="passport-withdrawal-table">
              <div className="passport-withdrawal-table-head">Rejoining Details</div>
              <div className="passport-withdrawal-row"><span>Passport Received At Head Office</span><b>{form.passport_received_at_head_office || '-'}</b></div>
              <div className="passport-withdrawal-row"><span>Name Of The Employee</span><b>{form.employee_name || '-'}</b></div>
              <div className="passport-withdrawal-row"><span>Designation</span><b>{form.designation || '-'}</b></div>
              <div className="passport-withdrawal-row"><span>Nationality</span><b>{form.nationality || '-'}</b></div>
              <div className="passport-withdrawal-row"><span>Contact No.</span><b>{form.contact_no || '-'}</b></div>
              <div className="passport-withdrawal-row"><span>Passport No.</span><b>{form.passport_number || '-'}</b></div>
              <div className="passport-withdrawal-row"><span>Vacation Start Date</span><b>{formatDisplayDate(form.vacation_start_date) || '-'}</b></div>
              <div className="passport-withdrawal-row"><span>Vacation End Date</span><b>{formatDisplayDate(form.vacation_end_date) || '-'}</b></div>
              <div className="passport-withdrawal-row"><span>Date Of Rejoin</span><b>{formatDisplayDate(form.rejoin_date) || '-'}</b></div>
            </div>

            <div className="passport-withdrawal-signatures">
              <div className="passport-withdrawal-signature-card">
                <span>Employee Signature</span>
                <strong aria-hidden="true"></strong>
              </div>
              <div className="passport-withdrawal-signature-card">
                <span>Company Manager Signature</span>
                <strong aria-hidden="true"></strong>
              </div>
            </div>

            <footer className="passport-withdrawal-approvals passport-withdrawal-approvals-two">
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
