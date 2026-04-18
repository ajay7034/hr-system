import { useEffect, useMemo, useState } from 'react';
import { Download, FileClock, FileSpreadsheet, FolderKanban, IdCard, RotateCcw, Search } from 'lucide-react';
import { Link } from 'react-router-dom';
import { api } from '../api/client';
import AppShell from '../components/AppShell';
import EmployeeAutocomplete from '../components/EmployeeAutocomplete';
import { MetricCard, PageToolbar, SectionCard, StatusBadge } from '../components/UI';

const REPORTS = [
  { id: 'passport_in_hand', title: 'Passports In Hand', hint: 'Locker custody list', icon: IdCard, tone: 'success' },
  { id: 'passport_outside', title: 'Passports Outside', hint: 'With employee list', icon: IdCard, tone: 'info' },
  { id: 'passport_movements', title: 'Passport Movement History', hint: 'Collection and return log', icon: RotateCcw, tone: 'accent' },
  { id: 'employee_expiring', title: 'Employee Expiring Soon', hint: 'Upcoming employee documents', icon: FileClock, tone: 'warning' },
  { id: 'employee_expired', title: 'Employee Expired', hint: 'Expired employee documents', icon: FileClock, tone: 'danger' },
  { id: 'company_expiring', title: 'Company Expiring Soon', hint: 'Upcoming company documents', icon: FolderKanban, tone: 'warning' },
  { id: 'company_expired', title: 'Company Expired', hint: 'Expired company documents', icon: FolderKanban, tone: 'danger' },
  { id: 'employee_summary', title: 'Employee Summary', hint: 'Employee-wise document summary', icon: FileSpreadsheet, tone: 'default' },
];

const INITIAL_FILTERS = {
  search: '',
  employee_id: '',
  company_id: '',
  department_id: '',
  employee_status: '',
  employee_document_master_id: '',
  company_document_master_id: '',
  date_from: '',
  date_to: '',
};

function downloadCsv(filename, rows) {
  if (!rows.length) {
    return;
  }

  const headers = Object.keys(rows[0]);
  const escapeValue = (value) => {
    const stringValue = value == null ? '' : String(value);
    if (stringValue.includes('"') || stringValue.includes(',') || stringValue.includes('\n')) {
      return `"${stringValue.replaceAll('"', '""')}"`;
    }

    return stringValue;
  };

  const csv = [headers.join(','), ...rows.map((row) => headers.map((header) => escapeValue(row[header])).join(','))].join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = filename;
  anchor.click();
  URL.revokeObjectURL(url);
}

function EmptyState({ title }) {
  return <div className="report-empty-state">{title}</div>;
}

export default function ReportsPage() {
  const [activeReport, setActiveReport] = useState('passport_in_hand');
  const [filters, setFilters] = useState(INITIAL_FILTERS);
  const [lookups, setLookups] = useState({
    employees: [],
    companies: [],
    departments: [],
    employeeDocumentMasters: [],
    companyDocumentMasters: [],
  });
  const [passportRows, setPassportRows] = useState([]);
  const [movementRows, setMovementRows] = useState([]);
  const [employeeExpiryRows, setEmployeeExpiryRows] = useState([]);
  const [companyExpiryRows, setCompanyExpiryRows] = useState([]);
  const [employeeSummaryRows, setEmployeeSummaryRows] = useState([]);
  const [loading, setLoading] = useState(true);

  const reportMeta = REPORTS.find((report) => report.id === activeReport) || REPORTS[0];

  const reportCounts = useMemo(() => ({
    passport_in_hand: passportRows.filter((row) => row.current_status === 'in_hand').length,
    passport_outside: passportRows.filter((row) => row.current_status === 'outside').length,
    passport_movements: movementRows.length,
    employee_expiring: employeeExpiryRows.filter((row) => row.status === 'expiring_soon').length,
    employee_expired: employeeExpiryRows.filter((row) => row.status === 'expired').length,
    company_expiring: companyExpiryRows.filter((row) => row.status === 'expiring_soon').length,
    company_expired: companyExpiryRows.filter((row) => row.status === 'expired').length,
    employee_summary: employeeSummaryRows.length,
  }), [companyExpiryRows, employeeExpiryRows, employeeSummaryRows, movementRows, passportRows]);

  useEffect(() => {
    api.lookups().then((response) => {
      setLookups(response.data.data);
    });
  }, []);

  useEffect(() => {
    loadReports();
  }, [activeReport]);

  const loadReports = async (overrideFilters = filters) => {
    setLoading(true);

    const common = {
      search: overrideFilters.search || '',
      employee_id: overrideFilters.employee_id || '',
      company_id: overrideFilters.company_id || '',
      department_id: overrideFilters.department_id || '',
      employee_status: overrideFilters.employee_status || '',
      date_from: overrideFilters.date_from || '',
      date_to: overrideFilters.date_to || '',
    };

    try {
      const requests = [
        api.passportReport(common),
        api.passportMovementReport({
          ...common,
          movement_type: activeReport === 'passport_movements' ? '' : '',
        }),
        api.expiryReport({
          ...common,
          scope: 'all',
          status: '',
          employee_document_master_id: overrideFilters.employee_document_master_id || '',
          company_document_master_id: overrideFilters.company_document_master_id || '',
        }),
        api.employeeSummaryReport(common),
      ];

      const [passportResponse, movementResponse, expiryResponse, summaryResponse] = await Promise.all(requests);
      setPassportRows(passportResponse.data.data || []);
      setMovementRows(movementResponse.data.data || []);
      setEmployeeExpiryRows(expiryResponse.data.data?.employee || []);
      setCompanyExpiryRows(expiryResponse.data.data?.company || []);
      setEmployeeSummaryRows(summaryResponse.data.data || []);
    } finally {
      setLoading(false);
    }
  };

  const visibleRows = useMemo(() => {
    switch (activeReport) {
      case 'passport_in_hand':
        return passportRows.filter((row) => row.current_status === 'in_hand');
      case 'passport_outside':
        return passportRows.filter((row) => row.current_status === 'outside');
      case 'passport_movements':
        return movementRows;
      case 'employee_expiring':
        return employeeExpiryRows.filter((row) => row.status === 'expiring_soon');
      case 'employee_expired':
        return employeeExpiryRows.filter((row) => row.status === 'expired');
      case 'company_expiring':
        return companyExpiryRows.filter((row) => row.status === 'expiring_soon');
      case 'company_expired':
        return companyExpiryRows.filter((row) => row.status === 'expired');
      case 'employee_summary':
        return employeeSummaryRows;
      default:
        return [];
    }
  }, [activeReport, companyExpiryRows, employeeExpiryRows, employeeSummaryRows, movementRows, passportRows]);

  const exportRows = useMemo(() => {
    switch (activeReport) {
      case 'passport_in_hand':
      case 'passport_outside':
        return visibleRows.map((row) => ({
          employee_id: row.employee_id,
          employee_code: row.employee_code,
          full_name: row.full_name,
          company: row.company,
          department: row.department,
          designation: row.designation,
          employee_status: row.employee_status,
          passport_number: row.passport_number,
          issue_date: row.issue_date,
          expiry_date: row.expiry_date,
          current_status: row.current_status,
          collected_date: row.collected_date,
          withdrawn_date: row.withdrawn_date,
          collected_reason: row.collected_reason,
          withdrawn_reason: row.withdrawn_reason,
          remarks: row.remarks,
        }));
      case 'passport_movements':
        return visibleRows.map((row) => ({
          movement_date: row.movement_date,
          movement_type: row.movement_type,
          employee_id: row.employee_id,
          employee_code: row.employee_code,
          full_name: row.full_name,
          company: row.company,
          department: row.department,
          passport_number: row.passport_number,
          from_status: row.from_status,
          to_status: row.to_status,
          reason: row.reason,
          remarks: row.remarks,
          updated_by: row.updated_by_name,
        }));
      case 'employee_expiring':
      case 'employee_expired':
        return visibleRows.map((row) => ({
          employee_id: row.employee_id,
          employee_code: row.employee_code,
          full_name: row.full_name,
          company: row.company,
          department: row.department,
          employee_status: row.employee_status,
          document_type: row.document_type,
          document_number: row.document_number,
          issue_date: row.issue_date,
          expiry_date: row.expiry_date,
          status: row.status,
          alert_days: row.alert_days,
        }));
      case 'company_expiring':
      case 'company_expired':
        return visibleRows.map((row) => ({
          document_name: row.document_name,
          document_type: row.document_type,
          company: row.company,
          document_number: row.document_number,
          issue_date: row.issue_date,
          expiry_date: row.expiry_date,
          status: row.status,
          alert_days: row.alert_days,
        }));
      case 'employee_summary':
        return visibleRows.map((row) => ({
          employee_id: row.employee_id,
          employee_code: row.employee_code,
          full_name: row.full_name,
          company: row.company,
          department: row.department,
          employee_status: row.employee_status,
          total_documents: row.total_documents,
          valid_documents: row.valid_documents,
          expiring_documents: row.expiring_documents,
          expired_documents: row.expired_documents,
          nearest_expiry_date: row.nearest_expiry_date,
        }));
      default:
        return [];
    }
  }, [activeReport, visibleRows]);

  const handleFilterChange = (event) => {
    const { name, value } = event.target;
    setFilters((current) => ({
      ...current,
      [name]: value,
    }));
  };

  const resetFilters = () => {
    setFilters(INITIAL_FILTERS);
    loadReports(INITIAL_FILTERS);
  };

  const passportColumns = (
    <table className="data-table">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Company</th>
          <th>Passport</th>
          <th>Status</th>
          <th>Collected</th>
          <th>Given Back</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        {visibleRows.map((row) => (
          <tr key={row.id}>
            <td>
              <div className="report-primary-cell">
                <strong>{row.full_name}</strong>
                <span>{row.employee_code}</span>
              </div>
            </td>
            <td>{row.company || '-'}</td>
            <td>{row.passport_number}</td>
            <td><StatusBadge value={row.current_status} /></td>
            <td>{row.collected_date || '-'}</td>
            <td>{row.withdrawn_date || '-'}</td>
            <td><Link className="link-button" to={`/employees/${row.employee_record_id}`}>Open</Link></td>
          </tr>
        ))}
      </tbody>
    </table>
  );

  const movementColumns = (
    <table className="data-table">
      <thead>
        <tr>
          <th>Movement Date</th>
          <th>Employee</th>
          <th>Passport</th>
          <th>Movement</th>
          <th>Route</th>
          <th>Reason</th>
          <th>Updated By</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        {visibleRows.map((row) => (
          <tr key={row.id}>
            <td>{row.movement_date}</td>
            <td>
              <div className="report-primary-cell">
                <strong>{row.full_name}</strong>
                <span>{row.employee_code}</span>
              </div>
            </td>
            <td>{row.passport_number || '-'}</td>
            <td><StatusBadge value={row.movement_type === 'given_back' ? 'outside' : 'in_hand'} /></td>
            <td>{row.from_status} to {row.to_status}</td>
            <td>{row.reason || '-'}</td>
            <td>{row.updated_by_name || '-'}</td>
            <td><Link className="link-button" to={`/employees/${row.employee_record_id}`}>Open</Link></td>
          </tr>
        ))}
      </tbody>
    </table>
  );

  const employeeExpiryColumns = (
    <table className="data-table">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Document</th>
          <th>Company</th>
          <th>Expiry</th>
          <th>Status</th>
          <th>Alert Days</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        {visibleRows.map((row) => (
          <tr key={row.id}>
            <td>
              <div className="report-primary-cell">
                <strong>{row.full_name}</strong>
                <span>{row.employee_code}</span>
              </div>
            </td>
            <td>{row.document_type}</td>
            <td>{row.company || '-'}</td>
            <td>{row.expiry_date || '-'}</td>
            <td><StatusBadge value={row.status} /></td>
            <td>{row.alert_days}</td>
            <td><Link className="link-button" to={`/employees/${row.employee_record_id}`}>Open</Link></td>
          </tr>
        ))}
      </tbody>
    </table>
  );

  const companyExpiryColumns = (
    <table className="data-table">
      <thead>
        <tr>
          <th>Document</th>
          <th>Type</th>
          <th>Company</th>
          <th>Expiry</th>
          <th>Status</th>
          <th>Alert Days</th>
        </tr>
      </thead>
      <tbody>
        {visibleRows.map((row) => (
          <tr key={row.id}>
            <td>{row.document_name}</td>
            <td>{row.document_type}</td>
            <td>{row.company || '-'}</td>
            <td>{row.expiry_date || '-'}</td>
            <td><StatusBadge value={row.status} /></td>
            <td>{row.alert_days}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );

  const employeeSummaryColumns = (
    <table className="data-table">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Company</th>
          <th>Total Docs</th>
          <th>Valid</th>
          <th>Expiring</th>
          <th>Expired</th>
          <th>Nearest Expiry</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        {visibleRows.map((row) => (
          <tr key={row.employee_record_id}>
            <td>
              <div className="report-primary-cell">
                <strong>{row.full_name}</strong>
                <span>{row.employee_code}</span>
              </div>
            </td>
            <td>{row.company || '-'}</td>
            <td>{row.total_documents}</td>
            <td>{row.valid_documents}</td>
            <td>{row.expiring_documents}</td>
            <td>{row.expired_documents}</td>
            <td>{row.nearest_expiry_date || '-'}</td>
            <td><Link className="link-button" to={`/employees/${row.employee_record_id}`}>Open</Link></td>
          </tr>
        ))}
      </tbody>
    </table>
  );

  const resultTable = () => {
    if (!visibleRows.length) {
      return <EmptyState title="No rows found for the selected report and filters." />;
    }

    if (['passport_in_hand', 'passport_outside'].includes(activeReport)) {
      return passportColumns;
    }

    if (activeReport === 'passport_movements') {
      return movementColumns;
    }

    if (['employee_expiring', 'employee_expired'].includes(activeReport)) {
      return employeeExpiryColumns;
    }

    if (['company_expiring', 'company_expired'].includes(activeReport)) {
      return companyExpiryColumns;
    }

    return employeeSummaryColumns;
  };

  const reportDescription = {
    passport_in_hand: 'Employees whose passports are currently in company custody or locker.',
    passport_outside: 'Employees whose passports are currently outside or with the employee.',
    passport_movements: 'Chronological custody handover log with movement reasons and updater trace.',
    employee_expiring: 'Employee-linked documents that need follow-up before expiry.',
    employee_expired: 'Employee-linked documents already expired and pending action.',
    company_expiring: 'Upcoming company compliance and legal document renewals.',
    company_expired: 'Expired company documents that require immediate renewal.',
    employee_summary: 'Employee-wise document count and nearest-expiry summary.',
  }[activeReport];

  return (
    <AppShell title="Reports" subtitle="Configured operational reports for custody, expiries, and employee compliance follow-up." breadcrumbs={['Home', 'Company', 'Reports']}>
      <PageToolbar
        title="Report Center"
        subtitle={reportDescription}
        action={(
          <div className="toolbar-actions">
            <button type="button" className="secondary-button" onClick={resetFilters}>Reset Filters</button>
            <button
              type="button"
              className="primary-button"
              onClick={() => downloadCsv(`${activeReport}.csv`, exportRows)}
              disabled={!exportRows.length}
            >
              <Download size={16} />
              Export CSV
            </button>
          </div>
        )}
      />

      <div className="report-metric-grid">
        {REPORTS.map((report) => (
          <MetricCard
            key={report.id}
            title={report.title}
            value={reportCounts[report.id] ?? 0}
            hint={report.hint}
            icon={report.icon}
            tone={activeReport === report.id ? report.tone : 'default'}
            onClick={() => setActiveReport(report.id)}
          />
        ))}
      </div>

      <div className="dashboard-grid report-layout-grid">
        <SectionCard
          title="Filters"
          subtitle="Narrow the operational report before exporting or drilling into employee records."
          action={(
            <button type="button" className="secondary-button small-button" onClick={() => loadReports()}>
              <Search size={14} />
              Apply
            </button>
          )}
        >
          <div className="form-grid">
            <label className="field-span-2">
              <span>Search</span>
              <input name="search" value={filters.search} onChange={handleFilterChange} placeholder="Employee, code, document number, passport number..." />
            </label>

            <EmployeeAutocomplete
              employees={lookups.employees}
              value={filters.employee_id}
              onChange={(value) => setFilters((current) => ({ ...current, employee_id: value }))}
              label="Employee"
              placeholder="Predict employee from master"
            />

            <label>
              <span>Company</span>
              <select name="company_id" value={filters.company_id} onChange={handleFilterChange}>
                <option value="">All Companies</option>
                {lookups.companies.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select>
            </label>

            <label>
              <span>Department</span>
              <select name="department_id" value={filters.department_id} onChange={handleFilterChange}>
                <option value="">All Departments</option>
                {lookups.departments.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select>
            </label>

            <label>
              <span>Employee Status</span>
              <select name="employee_status" value={filters.employee_status} onChange={handleFilterChange}>
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="resigned">Resigned</option>
                <option value="terminated">Terminated</option>
              </select>
            </label>

            <label>
              <span>From Date</span>
              <input type="date" name="date_from" value={filters.date_from} onChange={handleFilterChange} />
            </label>

            <label>
              <span>To Date</span>
              <input type="date" name="date_to" value={filters.date_to} onChange={handleFilterChange} />
            </label>

            <label>
              <span>Employee Document Type</span>
              <select
                name="employee_document_master_id"
                value={filters.employee_document_master_id}
                onChange={handleFilterChange}
                disabled={!['employee_expiring', 'employee_expired'].includes(activeReport)}
              >
                <option value="">All Employee Document Types</option>
                {lookups.employeeDocumentMasters.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select>
            </label>

            <label>
              <span>Company Document Type</span>
              <select
                name="company_document_master_id"
                value={filters.company_document_master_id}
                onChange={handleFilterChange}
                disabled={!['company_expiring', 'company_expired'].includes(activeReport)}
              >
                <option value="">All Company Document Types</option>
                {lookups.companyDocumentMasters.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select>
            </label>
          </div>
        </SectionCard>

        <SectionCard
          title={reportMeta.title}
          subtitle={`${visibleRows.length} rows loaded for the current report.`}
        >
          {loading ? <EmptyState title="Loading report data..." /> : <div className="table-scroll">{resultTable()}</div>}
        </SectionCard>
      </div>
    </AppShell>
  );
}
