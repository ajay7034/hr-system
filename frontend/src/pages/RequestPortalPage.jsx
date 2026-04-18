import { useEffect, useMemo, useState } from 'react';
import { api } from '../api/client';

const requestCards = [
  { id: 'leave', title: 'Apply For Leave', description: 'Travel dates and destinations' },
  { id: 'loan', title: 'Apply For Loan', description: 'Loan amount and request purpose' },
  { id: 'salary_certificate', title: 'Apply For Salary Certificate', description: 'Certificate purpose and language' },
];

const initialForms = {
  leave: { from_date: '', to_date: '', from_destination: '', to_destination: '', line_staff_employee_id: '', line_staff_name: '' },
  loan: { amount: '', purpose: '' },
  salary_certificate: { purpose: '', language: 'English' },
};

export default function RequestPortalPage() {
  const [step, setStep] = useState('employee');
  const [query, setQuery] = useState('');
  const [results, setResults] = useState([]);
  const [loadingResults, setLoadingResults] = useState(false);
  const [selectedEmployee, setSelectedEmployee] = useState(null);
  const [requestType, setRequestType] = useState('');
  const [forms, setForms] = useState(initialForms);
  const [submitting, setSubmitting] = useState(false);
  const [successState, setSuccessState] = useState({ ok: true, message: '' });
  const [lineStaffQuery, setLineStaffQuery] = useState('');
  const [lineStaffResults, setLineStaffResults] = useState([]);
  const [loadingLineStaffResults, setLoadingLineStaffResults] = useState(false);

  useEffect(() => {
    const term = query.trim();

    if (term.length < 1 || selectedEmployee?.full_name === term) {
      setResults([]);
      return;
    }

    let active = true;
    const timeout = window.setTimeout(async () => {
      setLoadingResults(true);
      try {
        const { data } = await api.portalEmployees({ q: term });
        if (active) {
          setResults(data.data || []);
        }
      } catch {
        if (active) {
          setResults([]);
        }
      } finally {
        if (active) {
          setLoadingResults(false);
        }
      }
    }, 180);

    return () => {
      active = false;
      window.clearTimeout(timeout);
    };
  }, [query, selectedEmployee]);

  const activeForm = useMemo(() => forms[requestType] || {}, [forms, requestType]);

  useEffect(() => {
    const term = lineStaffQuery.trim();

    if (requestType !== 'leave' || term.length < 1 || activeForm.line_staff_name === term) {
      setLineStaffResults([]);
      return;
    }

    let active = true;
    const timeout = window.setTimeout(async () => {
      setLoadingLineStaffResults(true);
      try {
        const { data } = await api.portalEmployees({ q: term });
        if (active) {
          setLineStaffResults((data.data || []).filter((employee) => String(employee.id) !== String(selectedEmployee?.id)));
        }
      } catch {
        if (active) {
          setLineStaffResults([]);
        }
      } finally {
        if (active) {
          setLoadingLineStaffResults(false);
        }
      }
    }, 180);

    return () => {
      active = false;
      window.clearTimeout(timeout);
    };
  }, [lineStaffQuery, requestType, activeForm.line_staff_name, selectedEmployee?.id]);

  function updateFormField(key, value) {
    setForms((current) => ({
      ...current,
      [requestType]: {
        ...current[requestType],
        [key]: value,
      },
    }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    if (!selectedEmployee || !requestType) {
      return;
    }

    setSubmitting(true);
    try {
      await api.submitEmployeeRequest({
        employee_id: selectedEmployee.id,
        request_type: requestType,
        ...activeForm,
      });

      setSuccessState({
        ok: true,
        message: `${requestCards.find((item) => item.id === requestType)?.title || 'Request'} submitted successfully.`,
      });
      setForms(initialForms);
      setRequestType('');
      setStep('success');
    } catch {
      setSuccessState({
        ok: false,
        message: 'Unable to submit request. Please try again.',
      });
      setStep('success');
    } finally {
      setSubmitting(false);
    }
  }

  function selectLineStaff(employee) {
    updateFormField('line_staff_employee_id', employee.id);
    updateFormField('line_staff_name', employee.full_name);
    setLineStaffQuery(employee.full_name);
    setLineStaffResults([]);
  }

  return (
    <div className="request-portal-shell">
      <div className="request-portal-card">
        {step === 'employee' ? (
          <>
            <div className="request-portal-head">
              <small>Employee Request Portal</small>
              <h1>Select Your Name</h1>
              <p>Start typing your name or employee code, then continue.</p>
            </div>

            <div className="request-portal-search">
              <input
                value={query}
                onChange={(event) => {
                  setSelectedEmployee(null);
                  setQuery(event.target.value);
                }}
                placeholder="Search your name"
              />
              <button
                type="button"
                className="primary-button"
                disabled={!selectedEmployee}
                onClick={() => setStep('types')}
              >
                Go Next
              </button>
            </div>

            <div className="request-portal-results">
              {results.map((employee) => (
                <button
                  key={employee.id}
                  type="button"
                  className={`request-portal-result ${String(selectedEmployee?.id) === String(employee.id) ? 'is-selected' : ''}`}
                  onClick={() => {
                    setSelectedEmployee(employee);
                    setQuery(employee.full_name);
                    setResults([]);
                  }}
                >
                  <strong>{employee.full_name}</strong>
                  <span>{employee.employee_code}</span>
                </button>
              ))}
              {loadingResults ? <div className="request-portal-empty">Searching employees...</div> : null}
            </div>
          </>
        ) : null}

        {step === 'types' ? (
          <>
            <div className="request-portal-head">
              <small>{selectedEmployee?.full_name}</small>
              <h1>Choose Request Type</h1>
              <p>Minimal, mobile-friendly request submission.</p>
            </div>

            <div className="request-type-grid">
              {requestCards.map((card) => (
                <button
                  key={card.id}
                  type="button"
                  className="request-type-card"
                  onClick={() => {
                    setRequestType(card.id);
                    setLineStaffQuery('');
                    setLineStaffResults([]);
                    setStep('form');
                  }}
                >
                  <strong>{card.title}</strong>
                  <span>{card.description}</span>
                </button>
              ))}
            </div>

            <button type="button" className="link-button request-portal-back" onClick={() => setStep('employee')}>
              Change Employee
            </button>
          </>
        ) : null}

        {step === 'form' ? (
          <>
            <div className="request-portal-head">
              <small>{selectedEmployee?.full_name}</small>
              <h1>{requestCards.find((item) => item.id === requestType)?.title}</h1>
              <p>Complete the required details and submit.</p>
            </div>

            <form className="request-portal-form" onSubmit={handleSubmit}>
              {requestType === 'leave' ? (
                <>
                  <label><span>From Date</span><input type="date" value={activeForm.from_date || ''} onChange={(event) => updateFormField('from_date', event.target.value)} required /></label>
                  <label><span>To Date</span><input type="date" value={activeForm.to_date || ''} onChange={(event) => updateFormField('to_date', event.target.value)} required /></label>
                  <label><span>From Destination</span><input value={activeForm.from_destination || ''} onChange={(event) => updateFormField('from_destination', event.target.value)} required /></label>
                  <label><span>To Destination</span><input value={activeForm.to_destination || ''} onChange={(event) => updateFormField('to_destination', event.target.value)} required /></label>
                  <label className="field-span-2 request-portal-picker">
                    <span>Line Staff</span>
                    <input
                      value={lineStaffQuery}
                      onChange={(event) => {
                        setLineStaffQuery(event.target.value);
                        updateFormField('line_staff_employee_id', '');
                        updateFormField('line_staff_name', '');
                      }}
                      placeholder="Search line staff name"
                      required
                    />
                    <div className="request-portal-results request-portal-results-inline">
                      {lineStaffResults.map((employee) => (
                        <button
                          key={employee.id}
                          type="button"
                          className={`request-portal-result ${String(activeForm.line_staff_employee_id) === String(employee.id) ? 'is-selected' : ''}`}
                          onClick={() => selectLineStaff(employee)}
                        >
                          <strong>{employee.full_name}</strong>
                          <span>{employee.employee_code}</span>
                        </button>
                      ))}
                      {loadingLineStaffResults ? <div className="request-portal-empty">Searching line staff...</div> : null}
                    </div>
                  </label>
                </>
              ) : null}

              {requestType === 'loan' ? (
                <>
                  <label><span>Amount</span><input value={activeForm.amount || ''} onChange={(event) => updateFormField('amount', event.target.value)} placeholder="Enter loan amount" required /></label>
                  <label><span>Purpose</span><textarea value={activeForm.purpose || ''} onChange={(event) => updateFormField('purpose', event.target.value)} required /></label>
                </>
              ) : null}

              {requestType === 'salary_certificate' ? (
                <>
                  <label><span>Purpose</span><textarea value={activeForm.purpose || ''} onChange={(event) => updateFormField('purpose', event.target.value)} required /></label>
                  <label>
                    <span>Language</span>
                    <select value={activeForm.language || 'English'} onChange={(event) => updateFormField('language', event.target.value)}>
                      <option value="English">English</option>
                      <option value="Arabic">Arabic</option>
                    </select>
                  </label>
                </>
              ) : null}

              <div className="request-portal-actions">
                <button type="button" className="secondary-button" onClick={() => setStep('types')}>Back</button>
                <button type="submit" className="primary-button" disabled={submitting}>
                  {submitting ? 'Submitting...' : 'Submit Request'}
                </button>
              </div>
            </form>
          </>
        ) : null}

        {step === 'success' ? (
          <div className="request-success">
            <div className={`request-success-mark ${successState.ok ? '' : 'is-error'}`}>{successState.ok ? '✓' : '!'}</div>
            <h1>{successState.ok ? 'Request Submitted' : 'Submission Failed'}</h1>
            <p>{successState.message}</p>
            <button
              type="button"
              className="primary-button"
              onClick={() => {
                setSelectedEmployee(null);
                setQuery('');
                setResults([]);
                setStep('employee');
              }}
            >
              Submit Another Request
            </button>
          </div>
        ) : null}
      </div>
    </div>
  );
}
