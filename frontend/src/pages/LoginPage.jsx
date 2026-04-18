import { LockKeyhole, Mail } from 'lucide-react';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export default function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ identifier: 'admin', password: 'password' });
  const [remember, setRemember] = useState(true);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function handleSubmit(event) {
    event.preventDefault();
    setLoading(true);
    setError('');

    try {
      await login(form);
      if (remember) {
        localStorage.setItem('hr-remember-user', form.identifier);
      }
      navigate('/');
    } catch (err) {
      setError(err.response?.data?.message || 'Unable to login.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="login-page page-transition">
      <div className="login-art">
        <div className="login-copy">
          <span className="eyebrow">Internal HR Control Center</span>
          <h1>HR Document & Passport Management Portal</h1>
          <p>
            Centralize employee records, passport custody, document expiry alerts, company compliance,
            and audit history in one responsive internal porta developed by media group of companies.
          </p>
        </div>
      </div>

      <form className="login-card glass-panel" onSubmit={handleSubmit}>
        <div>
          <span className="eyebrow">Secure Sign In</span>
          <h2>Welcome back</h2>
          <p>Use your internal credentials to continue.</p>
        </div>

        <label>
          <span>Email or username</span>
          <div className="input-with-icon">
            <Mail size={16} />
            <input
              value={form.identifier}
              onChange={(event) => setForm((current) => ({ ...current, identifier: event.target.value }))}
              placeholder="admin@northstar.local"
            />
          </div>
        </label>

        <label>
          <span>Password</span>
          <div className="input-with-icon">
            <LockKeyhole size={16} />
            <input
              type="password"
              value={form.password}
              onChange={(event) => setForm((current) => ({ ...current, password: event.target.value }))}
              placeholder="Enter password"
            />
          </div>
        </label>

        <div className="form-inline">
          <label className="checkbox">
            <input type="checkbox" checked={remember} onChange={() => setRemember((value) => !value)} />
            <span>Remember me</span>
          </label>
          <button type="button" className="link-button">Forgot password</button>
        </div>

        {error ? <div className="form-error">{error}</div> : null}

        <button type="submit" className="primary-button" disabled={loading}>
          {loading ? 'Signing in...' : 'Login'}
        </button>
      </form>
    </div>
  );
}
