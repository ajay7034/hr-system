import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Bell, BriefcaseBusiness, Building2, Clock3, FileClock, FileSpreadsheet, FileText, IdCard, ShieldAlert, Users } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis, Cell } from 'recharts';
import { api } from '../api/client';
import AppShell from '../components/AppShell';
import { MetricCard, PageToolbar, SectionCard, StatusBadge } from '../components/UI';

const colors = ['#58d5ff', '#0fd28f', '#ffb957', '#ff6f91', '#9f8cff', '#7be495'];

export default function DashboardPage() {
  const navigate = useNavigate();
  const [data, setData] = useState(null);

  useEffect(() => {
    let active = true;

    const loadDashboard = async () => {
      try {
        const { data: dashboardResponse } = await api.dashboard();
        const mergedData = {
          ...dashboardResponse.data,
          counts: {
            ...(dashboardResponse.data?.counts || {}),
          },
        };

        try {
          const { data: requestSummaryResponse } = await api.requests({ status: '' });
          mergedData.counts.pendingRequests = requestSummaryResponse.summary?.pending ?? mergedData.counts.pendingRequests ?? 0;
          mergedData.counts.approvedRequests = requestSummaryResponse.summary?.approved ?? mergedData.counts.approvedRequests ?? 0;
        } catch {
          mergedData.counts.pendingRequests = mergedData.counts.pendingRequests ?? 0;
          mergedData.counts.approvedRequests = mergedData.counts.approvedRequests ?? 0;
        }

        if (active) {
          setData(mergedData);
        }
      } catch {
        if (active) {
          setData((current) => current);
        }
      }
    };

    loadDashboard();

    const interval = window.setInterval(loadDashboard, 5000);
    const handleFocus = () => loadDashboard();

    window.addEventListener('focus', handleFocus);

    return () => {
      active = false;
      window.clearInterval(interval);
      window.removeEventListener('focus', handleFocus);
    };
  }, []);

  const counts = data?.counts || {};

  return (
    <AppShell
      title="Dashboard"
      subtitle="Live oversight for employee records, passport custody, and expiring compliance documents."
      notifications={data?.notifications || []}
      breadcrumbs={['Home', 'Dashboard']}
    >
      <PageToolbar
        title="Control Panel"
        subtitle="Quick links and live tiles modeled for HR admin daily operations."
        action={<div className="toolbar-meta">Internal Panel</div>}
      />
      <section className="metric-grid">
        <MetricCard title="Total Employees" value={counts.totalEmployees || 0} icon={Users} hint="Active records" />
        <MetricCard title="Passports In Hand" value={counts.passportsInHand || 0} tone="success" icon={IdCard} hint="Locker custody list" onClick={() => navigate('/passports?status=in_hand')} />
        <MetricCard title="Passports Outside" value={counts.passportsOutside || 0} tone="info" icon={BriefcaseBusiness} hint="Currently with employees" onClick={() => navigate('/passports?status=outside')} />
        <MetricCard title="Employee Expiring" value={counts.employeeDocsExpiring || 0} tone="warning" icon={Clock3} hint="Documents nearing expiry" onClick={() => navigate('/employee-documents')} />
        <MetricCard title="Employee Expired" value={counts.employeeDocsExpired || 0} tone="danger" icon={ShieldAlert} hint="Immediate HR action needed" onClick={() => navigate('/employee-documents')} />
        <MetricCard title="Company Expiring" value={counts.companyDocsExpiring || 0} tone="warning" icon={Building2} hint="Upcoming company renewals" onClick={() => navigate('/company-documents')} />
        <MetricCard title="Company Expired" value={counts.companyDocsExpired || 0} tone="danger" icon={FileClock} hint="Overdue company documents" onClick={() => navigate('/company-documents')} />
        <MetricCard title="Requests" value={counts.pendingRequests || 0} tone="warning" icon={FileText} hint="Pending employee requests" onClick={() => navigate('/requests?status=pending')} />
        <MetricCard title="Approvals" value={counts.approvedRequests || 0} tone="success" icon={FileSpreadsheet} hint="Approved employee requests" onClick={() => navigate('/requests?status=approved')} />
        <MetricCard title="Mail Queue" value={counts.mailQueueCount || 0} tone="accent" icon={Bell} hint="Pending reminder dispatches" onClick={() => navigate('/settings')} />
      </section>

      <section className="dashboard-grid">
        <SectionCard title="Passport Custody Overview" subtitle="Current live custody status across the workforce.">
          <div className="chart-wrap">
            <ResponsiveContainer width="100%" height={260}>
              <PieChart>
                <Pie data={data?.passportStatusChart || []} dataKey="value" nameKey="label" innerRadius={62} outerRadius={92}>
                  {(data?.passportStatusChart || []).map((entry, index) => <Cell key={entry.label} fill={colors[index % colors.length]} />)}
                </Pie>
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
          </div>
        </SectionCard>

        <SectionCard title="Expiry Status Summary" subtitle="Employee and company documents grouped by live status.">
          <div className="chart-wrap">
            <ResponsiveContainer width="100%" height={260}>
              <BarChart data={data?.documentStatusChart || []}>
                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                <XAxis dataKey="label" />
                <YAxis allowDecimals={false} />
                <Tooltip />
                <Bar dataKey="value" radius={[12, 12, 0, 0]} fill="#73d2ff" />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </SectionCard>

        <SectionCard title="Employees by Department" subtitle="Headcount visibility for operational planning.">
          <div className="chart-wrap">
            <ResponsiveContainer width="100%" height={260}>
              <BarChart data={data?.employeesByDepartmentChart || []} layout="vertical" margin={{ left: 20 }}>
                <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                <XAxis type="number" allowDecimals={false} />
                <YAxis type="category" dataKey="label" width={96} />
                <Tooltip />
                <Bar dataKey="value" radius={[0, 12, 12, 0]} fill="#0fd28f" />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </SectionCard>

        <SectionCard title="Notifications" subtitle="Unread warnings and internal reminders.">
          <div className="stack-list">
            {(data?.notifications || []).map((item) => (
              <article key={item.id} className="stack-item">
                <div>
                  <strong>{item.title}</strong>
                  <p>{item.message}</p>
                </div>
                <StatusBadge value={item.severity} />
              </article>
            ))}
          </div>
        </SectionCard>

        <SectionCard title="Recent Passport Movements" subtitle="Latest custody updates with drill-down access.">
          <div className="stack-list">
            {(data?.recentMovements || []).map((item) => (
              <button key={item.id} type="button" className="stack-item stack-button" onClick={() => navigate('/employees')}>
                <div>
                  <strong>{item.full_name}</strong>
                  <p>{item.movement_type.replace('_', ' ')} on {item.movement_date}</p>
                </div>
                <StatusBadge value={item.movement_type === 'collected' ? 'in_hand' : 'outside'} />
              </button>
            ))}
          </div>
        </SectionCard>

        <SectionCard title="Upcoming Expiries" subtitle="Sorted list of employee and company records nearing action.">
          <div className="table-scroll">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Scope</th>
                  <th>Subject</th>
                  <th>Document</th>
                  <th>Expiry Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {(data?.upcomingExpiries || []).map((row, index) => (
                  <tr key={`${row.scope}-${index}`}>
                    <td>{row.scope}</td>
                    <td>{row.subject}</td>
                    <td>{row.document_type}</td>
                    <td>{row.expiry_date}</td>
                    <td><StatusBadge value={row.status} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </SectionCard>
      </section>
    </AppShell>
  );
}
