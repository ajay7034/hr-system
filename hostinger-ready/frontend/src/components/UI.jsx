import { useEffect } from 'react';

export function MetricCard({ title, value, tone = 'default', hint, icon, onClick }) {
  const Icon = icon;

  return (
    <button type="button" className={`metric-card metric-${tone}`} onClick={onClick}>
      {Icon ? (
        <div className="metric-watermark">
          <Icon size={56} />
        </div>
      ) : null}
      <div>
        <span>{title}</span>
        <strong>{value}</strong>
      </div>
      <div className="metric-footer">
        <small>{hint || 'More info'}</small>
        <span>More Info</span>
      </div>
    </button>
  );
}

export function SectionCard({ title, subtitle, action, children }) {
  return (
    <section className="section-card glass-panel">
      <div className="section-head">
        <div>
          <h2>{title}</h2>
          {subtitle ? <p>{subtitle}</p> : null}
        </div>
        {action}
      </div>
      {children}
    </section>
  );
}

export function PageToolbar({ title, subtitle, action }) {
  return (
    <div className="page-toolbar glass-panel">
      <div>
        <strong>{title}</strong>
        {subtitle ? <p>{subtitle}</p> : null}
      </div>
      {action}
    </div>
  );
}

export function StatusBadge({ value }) {
  const tone = {
    valid: 'success',
    expiring_soon: 'warning',
    expired: 'danger',
    in_hand: 'success',
    outside: 'info',
    success: 'success',
    critical: 'danger',
    warning: 'warning',
    info: 'info',
    active: 'success',
    inactive: 'muted',
    resigned: 'warning',
    terminated: 'danger',
  }[value] || 'muted';

  return <span className={`badge badge-${tone}`}>{String(value).replaceAll('_', ' ')}</span>;
}

export function Modal({ open, title, subtitle, onClose, children, footer }) {
  useEffect(() => {
    if (!open) {
      document.body.classList.remove('modal-open');
      return undefined;
    }

    document.body.classList.add('modal-open');

    return () => {
      document.body.classList.remove('modal-open');
    };
  }, [open]);

  if (!open) {
    return null;
  }

  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div className="modal-panel glass-panel" onClick={(event) => event.stopPropagation()}>
        <div className="section-head">
          <div>
            <h2>{title}</h2>
            {subtitle ? <p>{subtitle}</p> : null}
          </div>
          <button type="button" className="icon-button" onClick={onClose}>×</button>
        </div>
        <div>{children}</div>
        {footer ? <div className="modal-footer">{footer}</div> : null}
      </div>
    </div>
  );
}

export function Toast({ toast, onClose }) {
  if (!toast) {
    return null;
  }

  return (
    <div className={`toast toast-${toast.type || 'success'}`}>
      <div>
        <strong>{toast.title}</strong>
        {toast.message ? <p>{toast.message}</p> : null}
      </div>
      <button type="button" className="link-button" onClick={onClose}>Dismiss</button>
    </div>
  );
}
