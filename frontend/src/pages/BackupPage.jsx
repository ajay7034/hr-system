import { useState } from 'react';
import { Download, ShieldAlert } from 'lucide-react';
import { api } from '../api/client';
import AppShell from '../components/AppShell';
import { PageToolbar, SectionCard, Toast } from '../components/UI';

function resolveDownloadFilename(headers) {
  const disposition = headers['content-disposition'] || '';
  const match = disposition.match(/filename="?([^"]+)"?/i);
  return match?.[1] || 'database-backup.sql';
}

export default function BackupPage() {
  const [downloading, setDownloading] = useState(false);
  const [toast, setToast] = useState(null);

  async function handleDownload() {
    setDownloading(true);
    try {
      const response = await api.downloadDatabaseBackup();
      const blob = new Blob([response.data], { type: response.headers['content-type'] || 'application/sql' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = resolveDownloadFilename(response.headers);
      link.click();
      URL.revokeObjectURL(url);

      setToast({
        type: 'success',
        title: 'Backup downloaded',
        message: 'The SQL backup file was generated and downloaded successfully.',
      });
    } catch (error) {
      setToast({
        type: 'error',
        title: 'Backup failed',
        message: error.response?.data?.message || 'Unable to generate the SQL backup file.',
      });
    } finally {
      setDownloading(false);
    }
  }

  return (
    <AppShell
      title="Backup"
      subtitle="Download a SQL backup of the current HR database for safekeeping."
      breadcrumbs={['Home', 'Administration', 'Backup']}
    >
      <PageToolbar
        title="Database Backup"
        subtitle="Generate a downloadable SQL dump of the active database."
        action={(
          <button type="button" className="primary-button" onClick={handleDownload} disabled={downloading}>
            <Download size={16} />
            {downloading ? 'Generating...' : 'Download SQL Backup'}
          </button>
        )}
      />

      <SectionCard title="Backup Control" subtitle="This creates a SQL file that can be used to restore the application database later.">
        <div className="stack-list">
          <article className="stack-item">
            <div>
              <strong>What is included</strong>
              <p>Tables, schema, and data from the currently configured HR database.</p>
            </div>
          </article>
          <article className="stack-item">
            <div>
              <strong>Recommended use</strong>
              <p>Take a backup before large imports, cleanup tasks, or structural changes.</p>
            </div>
          </article>
          <article className="stack-item">
            <div>
              <strong>Admin only</strong>
              <p><ShieldAlert size={14} style={{ verticalAlign: 'text-bottom', marginRight: 6 }} />This action is intended for administrators.</p>
            </div>
          </article>
        </div>
      </SectionCard>

      <Toast toast={toast} onClose={() => setToast(null)} />
    </AppShell>
  );
}
