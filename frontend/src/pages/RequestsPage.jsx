import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api } from '../api/client';
import AppShell from '../components/AppShell';
import { PageToolbar, SectionCard, StatusBadge, Toast } from '../components/UI';

function parseDetails(value) {
  if (!value) {
    return {};
  }

  try {
    return JSON.parse(value);
  } catch {
    return {};
  }
}

function formatLabel(key) {
  return key.replaceAll('_', ' ');
}

export default function RequestsPage() {
  const [params, setParams] = useSearchParams();
  const [rows, setRows] = useState([]);
  const [summary, setSummary] = useState({ pending: 0, approved: 0 });
  const [toast, setToast] = useState(null);
  const [loading, setLoading] = useState(false);
  const status = params.get('status') || 'pending';

  async function loadRequests() {
    setLoading(true);
    try {
      const { data } = await api.requests({ status });
      setRows(data.data || []);
      setSummary(data.summary || { pending: 0, approved: 0 });
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadRequests();
  }, [status]);

  const title = useMemo(() => (status === 'approved' ? 'Approvals' : 'Requests'), [status]);

  async function approveRequest(id) {
    try {
      await api.approveRequest(id);
      await loadRequests();
      setToast({
        type: 'success',
        title: 'Request approved',
        message: 'The item has moved into the approvals section.',
      });
    } catch (error) {
      setToast({
        type: 'error',
        title: 'Unable to approve request',
        message: error.response?.data?.message || 'Approval failed.',
      });
    }
  }

  return (
    <AppShell
      title={title}
      subtitle="Employee-submitted service requests for HR review and approvals."
      breadcrumbs={['Home', 'Requests', title]}
    >
      <PageToolbar
        title="Request Center"
        subtitle="Review pending requests and browse approved items."
        action={(
          <div className="request-status-toggle">
            <button
              type="button"
              className={status === 'pending' ? 'is-active' : ''}
              onClick={() => setParams({ status: 'pending' })}
            >
              Requests ({summary.pending || 0})
            </button>
            <button
              type="button"
              className={status === 'approved' ? 'is-active' : ''}
              onClick={() => setParams({ status: 'approved' })}
            >
              Approvals ({summary.approved || 0})
            </button>
          </div>
        )}
      />

      <SectionCard
        title={status === 'approved' ? 'Approved Requests' : 'Pending Requests'}
        subtitle={status === 'approved' ? 'Approved items moved here from the requests queue.' : 'Approve incoming employee requests from the portal.'}
      >
        <div className="request-list">
          {rows.map((row) => {
            const details = parseDetails(row.details_json);

            return (
              <article key={row.id} className="request-card">
                <div className="request-card-head">
                  <div>
                    <strong>{row.full_name}</strong>
                    <p>{row.employee_code} • {row.request_title}</p>
                  </div>
                  <StatusBadge value={row.status} />
                </div>

                <p className="request-summary">{row.summary || 'No summary available.'}</p>

                <div className="request-detail-grid">
                  {Object.entries(details).map(([key, value]) => (
                    <div key={key} className="request-detail-item">
                      <span>{formatLabel(key)}</span>
                      <strong>{String(value)}</strong>
                    </div>
                  ))}
                </div>

                <div className="request-card-meta">
                  <span>Submitted: {row.created_at}</span>
                  {row.approved_at ? <span>Approved: {row.approved_at}</span> : null}
                  {row.approved_by_name ? <span>By: {row.approved_by_name}</span> : null}
                </div>

                {status === 'pending' ? (
                  <div className="request-card-actions">
                    <button type="button" className="primary-button small-button" onClick={() => approveRequest(row.id)}>
                      Approve
                    </button>
                  </div>
                ) : null}
              </article>
            );
          })}

          {!rows.length ? (
            <div className="request-empty-state">
              {loading ? 'Loading requests...' : (status === 'approved' ? 'No approved requests yet.' : 'No pending requests right now.')}
            </div>
          ) : null}
        </div>
      </SectionCard>

      <Toast toast={toast} onClose={() => setToast(null)} />
    </AppShell>
  );
}
