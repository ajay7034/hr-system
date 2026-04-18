import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { api, buildFileUrl } from '../api/client';
import AppShell from '../components/AppShell';
import { PageToolbar, SectionCard, StatusBadge } from '../components/UI';

export default function EmployeeProfilePage() {
  const { id } = useParams();
  const [profile, setProfile] = useState(null);

  useEffect(() => {
    api.employee(id).then(({ data }) => setProfile(data.data));
  }, [id]);

  const employee = profile?.employee;
  const passport = profile?.passport;

  return (
    <AppShell title={employee?.full_name || 'Employee Profile'} subtitle="Central employee profile with documents, passport custody, and movement history." breadcrumbs={['Home', 'Employees', employee?.employee_code || 'Profile']}>
      <PageToolbar title="Employee Profile View" subtitle="Single-screen HR view for identity, passport, documents, and custody history." />
      <div className="dashboard-grid">
        <SectionCard title="Basic Information" subtitle="Master record details and status.">
          <div className="detail-grid">
            <div><span>Employee Code</span><strong>{employee?.employee_code}</strong></div>
            <div><span>Email</span><strong>{employee?.email || '-'}</strong></div>
            <div><span>Department</span><strong>{employee?.department || '-'}</strong></div>
            <div><span>Designation</span><strong>{employee?.designation || '-'}</strong></div>
            <div><span>Company</span><strong>{employee?.company || '-'}</strong></div>
            <div><span>Status</span><StatusBadge value={employee?.status || 'inactive'} /></div>
            <div><span>Profile Attachment</span><strong>{employee?.profile_photo_path ? <a href={buildFileUrl(employee.profile_photo_path)} target="_blank" rel="noreferrer" className="file-link">Open file</a> : '-'}</strong></div>
          </div>
        </SectionCard>

        <SectionCard title="Passport Custody" subtitle="Latest live passport record.">
          <div className="detail-grid">
            <div><span>Passport Number</span><strong>{passport?.passport_number || employee?.passport_number || '-'}</strong></div>
            <div><span>Issue Date</span><strong>{passport?.issue_date || '-'}</strong></div>
            <div><span>Expiry Date</span><strong>{passport?.expiry_date || '-'}</strong></div>
            <div><span>Current Holder</span><StatusBadge value={passport?.current_status || 'outside'} /></div>
            <div><span>Collected Date</span><strong>{passport?.collected_date || '-'}</strong></div>
            <div><span>Given Back Date</span><strong>{passport?.withdrawn_date || '-'}</strong></div>
            <div><span>Passport Attachment</span><strong>{passport?.passport_file_path ? <a href={buildFileUrl(passport.passport_file_path)} target="_blank" rel="noreferrer" className="file-link">Open file</a> : '-'}</strong></div>
          </div>
        </SectionCard>

        <SectionCard title="Document Overview" subtitle="Employee-level document statuses and renewal health.">
          <div className="table-scroll">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Document</th>
                  <th>Number</th>
                  <th>Expiry</th>
                  <th>Status</th>
                  <th>Attachment</th>
                </tr>
              </thead>
              <tbody>
                {(profile?.documents || []).map((document) => (
                  <tr key={document.id}>
                    <td>{document.document_type}</td>
                    <td>{document.document_number || '-'}</td>
                    <td>{document.expiry_date || '-'}</td>
                    <td><StatusBadge value={document.status} /></td>
                    <td>{document.file_path ? <a href={buildFileUrl(document.file_path)} target="_blank" rel="noreferrer" className="file-link">Open</a> : '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </SectionCard>

        <SectionCard title="Passport Movement Timeline" subtitle="Traceable custody history with actor and date.">
          <div className="timeline">
            {(profile?.passportHistory || []).map((event) => (
              <article key={event.id} className="timeline-item">
                <span className="timeline-dot" />
                <div>
                  <strong>{event.movement_type.replace('_', ' ')}</strong>
                  <p>{event.reason || 'No reason provided'} • {event.movement_date}</p>
                  <small>{event.updated_by_name || 'System'} • {event.remarks || 'No remarks'}</small>
                </div>
              </article>
            ))}
          </div>
        </SectionCard>
      </div>
    </AppShell>
  );
}
