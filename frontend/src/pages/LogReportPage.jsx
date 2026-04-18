import { useEffect, useMemo, useState } from 'react';
import { RotateCcw, Search } from 'lucide-react';
import { api } from '../api/client';
import AppShell from '../components/AppShell';
import { MetricCard, PageToolbar, SectionCard } from '../components/UI';

const INITIAL_FILTERS = {
  search: '',
  user_id: '',
  entity_type: '',
  action: '',
  date_from: '',
  date_to: '',
};

export default function LogReportPage() {
  const [filters, setFilters] = useState(INITIAL_FILTERS);
  const [rows, setRows] = useState([]);
  const [lookups, setLookups] = useState({ users: [], entityTypes: [], actions: [] });
  const [loading, setLoading] = useState(true);

  async function loadRows(overrideFilters = filters) {
    setLoading(true);
    try {
      const { data } = await api.activityLogs(overrideFilters);
      setRows(data.data.rows || []);
      setLookups(data.data.lookups || { users: [], entityTypes: [], actions: [] });
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadRows();
  }, []);

  const metrics = useMemo(() => {
    const uniqueUsers = new Set(rows.map((row) => row.user_id).filter(Boolean)).size;
    const uniqueEntities = new Set(rows.map((row) => row.entity_type).filter(Boolean)).size;

    return {
      total: rows.length,
      users: uniqueUsers,
      entities: uniqueEntities,
    };
  }, [rows]);

  function updateField(event) {
    const { name, value } = event.target;
    setFilters((current) => ({ ...current, [name]: value }));
  }

  function resetFilters() {
    setFilters(INITIAL_FILTERS);
    loadRows(INITIAL_FILTERS);
  }

  return (
    <AppShell
      title="Log Report"
      subtitle="Full activity log across admin, employee, document, passport, and import actions."
      breadcrumbs={['Home', 'Administration', 'Log Report']}
    >
      <PageToolbar
        title="Activity Audit Trail"
        subtitle="Review who did what, when it happened, and which record was affected."
        action={(
          <div className="table-actions">
            <button type="button" className="secondary-button" onClick={resetFilters}>
              <RotateCcw size={16} />
              Reset
            </button>
            <button type="button" className="primary-button" onClick={() => loadRows()}>
              <Search size={16} />
              Apply
            </button>
          </div>
        )}
      />

      <section className="report-metric-grid">
        <MetricCard title="Total Logs" value={metrics.total} hint="Matching activity rows" />
        <MetricCard title="Active Users" value={metrics.users} tone="info" hint="Users in current result" />
        <MetricCard title="Entity Types" value={metrics.entities} tone="accent" hint="Modules represented" />
      </section>

      <div className="dashboard-grid report-layout-grid">
        <SectionCard title="Filters" subtitle="Filter the full activity stream by user, module, action, date, or free text.">
          <div className="form-grid">
            <label className="field-span-2">
              <span>Search</span>
              <input name="search" value={filters.search} onChange={updateField} placeholder="Description, entity type, action, IP, or username" />
            </label>

            <label>
              <span>User</span>
              <select name="user_id" value={filters.user_id} onChange={updateField}>
                <option value="">All Users</option>
                {lookups.users.map((item) => (
                  <option key={item.id} value={item.id}>{item.full_name || item.username}</option>
                ))}
              </select>
            </label>

            <label>
              <span>Entity Type</span>
              <select name="entity_type" value={filters.entity_type} onChange={updateField}>
                <option value="">All Entities</option>
                {lookups.entityTypes.map((item) => (
                  <option key={item} value={item}>{item}</option>
                ))}
              </select>
            </label>

            <label>
              <span>Action</span>
              <select name="action" value={filters.action} onChange={updateField}>
                <option value="">All Actions</option>
                {lookups.actions.map((item) => (
                  <option key={item} value={item}>{item}</option>
                ))}
              </select>
            </label>

            <label>
              <span>From Date</span>
              <input type="date" name="date_from" value={filters.date_from} onChange={updateField} />
            </label>

            <label>
              <span>To Date</span>
              <input type="date" name="date_to" value={filters.date_to} onChange={updateField} />
            </label>
          </div>
        </SectionCard>

        <SectionCard title="Activity Log" subtitle={loading ? 'Loading activity rows...' : `${rows.length} rows found.`}>
          {rows.length ? (
            <div className="table-scroll">
              <table className="data-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Entity</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((row) => (
                    <tr key={row.id}>
                      <td>{row.created_at}</td>
                      <td>
                        <div className="report-primary-cell">
                          <strong>{row.user_name || 'System'}</strong>
                          <span>{row.username || '-'}</span>
                        </div>
                      </td>
                      <td>{row.entity_type}{row.entity_id ? ` #${row.entity_id}` : ''}</td>
                      <td>{row.action}</td>
                      <td>{row.description || '-'}</td>
                      <td>{row.ip_address || '-'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="report-empty-state">{loading ? 'Loading activity rows...' : 'No activity rows found for the selected filters.'}</div>
          )}
        </SectionCard>
      </div>
    </AppShell>
  );
}
