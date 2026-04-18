import { Pencil, Plus, ShieldUser, SlidersHorizontal, UserRound } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api, buildFileUrl } from '../api/client';
import AppShell from '../components/AppShell';
import { useAuth } from '../context/AuthContext';
import { buildFormData } from '../utils/forms';
import { Modal, PageToolbar, SectionCard, StatusBadge, Toast } from '../components/UI';

const masterConfigs = {
  companies: {
    title: 'Company',
    endpoint: 'companies',
    empty: { id: '', name: '', code: '', email: '', phone: '', address: '', website: '', is_active: 1 },
    fields: ['name', 'code', 'email', 'phone', 'website', 'address'],
  },
  branches: {
    title: 'Branch',
    endpoint: 'branches',
    empty: { id: '', company_id: '', name: '', code: '', location: '', contact_email: '', is_active: 1 },
    fields: ['company_id', 'name', 'code', 'location', 'contact_email'],
  },
  departments: {
    title: 'Department',
    endpoint: 'departments',
    empty: { id: '', name: '', code: '', description: '', is_active: 1 },
    fields: ['name', 'code', 'description'],
  },
  designations: {
    title: 'Designation',
    endpoint: 'designations',
    empty: { id: '', name: '', code: '', description: '', is_active: 1 },
    fields: ['name', 'code', 'description'],
  },
  employeeDocumentMasters: {
    title: 'Employee Document Master',
    endpoint: 'employee-document-masters',
    empty: { id: '', name: '', code: '', has_expiry: 1, default_alert_days: 30, default_mail_enabled: 1, default_notification_enabled: 1, sort_order: 0 },
    fields: ['name', 'code', 'has_expiry', 'default_alert_days', 'default_mail_enabled', 'default_notification_enabled', 'sort_order'],
  },
  companyDocumentMasters: {
    title: 'Company Document Master',
    endpoint: 'company-document-masters',
    empty: { id: '', name: '', code: '', has_expiry: 1, default_alert_days: 30, default_mail_enabled: 1, default_notification_enabled: 1, sort_order: 0 },
    fields: ['name', 'code', 'has_expiry', 'default_alert_days', 'default_mail_enabled', 'default_notification_enabled', 'sort_order'],
  },
};

const userEmpty = {
  id: '',
  full_name: '',
  email: '',
  username: '',
  phone: '',
  company_id: '',
  branch_id: '',
  is_active: 1,
  password: '',
  roles: ['viewer'],
  avatar: null,
};

const passwordEmpty = {
  current_password: '',
  new_password: '',
  confirm_password: '',
};

const tabs = [
  { id: 'general', label: 'General', icon: SlidersHorizontal },
  { id: 'users', label: 'User Management', icon: ShieldUser },
  { id: 'profile', label: 'My Profile', icon: UserRound },
];

export default function SettingsPage() {
  const { user, setUser } = useAuth();
  const [params, setParams] = useSearchParams();
  const [data, setData] = useState(null);
  const [usersData, setUsersData] = useState({ users: [], roles: [] });
  const [profile, setProfile] = useState({ full_name: '', email: '', username: '', phone: '', company_id: '', branch_id: '', avatar: null, avatar_path: '' });
  const [passwordForm, setPasswordForm] = useState(passwordEmpty);
  const [toast, setToast] = useState(null);
  const [modal, setModal] = useState({ section: '', open: false });
  const [userModalOpen, setUserModalOpen] = useState(false);
  const [form, setForm] = useState({});
  const [userForm, setUserForm] = useState(userEmpty);
  const [saving, setSaving] = useState(false);
  const [companyProfile, setCompanyProfile] = useState({ name: '', supportEmail: '', timezone: '' });

  const activeTab = params.get('tab') || 'general';
  const isAdmin = (user?.roles || []).includes('admin');

  async function loadSettings() {
    const requests = [api.profile()];

    if (isAdmin) {
      requests.unshift(api.settings(), api.users());
    } else {
      requests.unshift(api.lookups());
    }

    const responses = await Promise.all(requests);
    const profilePayload = responses[responses.length - 1].data.data;

    if (isAdmin) {
      const settingsPayload = responses[0].data.data;
      const usersPayload = responses[1].data.data;
      setData(settingsPayload);
      setUsersData(usersPayload);

      const companyProfileSetting = (settingsPayload.settings || []).find((item) => item.setting_key === 'company_profile');
      if (companyProfileSetting) {
        try {
          setCompanyProfile(JSON.parse(companyProfileSetting.setting_value));
        } catch {
          setCompanyProfile({ name: '', supportEmail: '', timezone: '' });
        }
      }
    } else {
      const lookupResponse = responses[0];
      setData((current) => ({
        ...current,
        companies: lookupResponse.data.data?.companies || [],
        branches: lookupResponse.data.data?.branches || [],
        roles: [],
      }));
    }

    setProfile({
      full_name: profilePayload.full_name || '',
      email: profilePayload.email || '',
      username: profilePayload.username || '',
      phone: profilePayload.phone || '',
      company_id: profilePayload.company_id || '',
      branch_id: profilePayload.branch_id || '',
      avatar: null,
      avatar_path: profilePayload.avatar_path || '',
    });
  }

  useEffect(() => {
    loadSettings();
  }, [isAdmin]);

  useEffect(() => {
    if (!isAdmin && activeTab !== 'profile') {
      setParams({ tab: 'profile' });
    }
  }, [activeTab, isAdmin, setParams]);

  const companies = useMemo(() => data?.companies || [], [data]);
  const branches = useMemo(() => data?.branches || [], [data]);
  const roles = useMemo(() => data?.roles || usersData.roles || [], [data, usersData.roles]);

  const filteredUserBranches = useMemo(() => {
    if (!userForm.company_id) {
      return branches;
    }

    return branches.filter((branch) => String(branch.company_id) === String(userForm.company_id));
  }, [branches, userForm.company_id]);

  const filteredProfileBranches = useMemo(() => {
    if (!profile.company_id) {
      return branches;
    }

    return branches.filter((branch) => String(branch.company_id) === String(profile.company_id));
  }, [branches, profile.company_id]);

  function openMaster(section, item = null) {
    const config = masterConfigs[section];
    setForm(item ? { ...config.empty, ...item } : { ...config.empty });
    setModal({ section, open: true });
  }

  function openUser(item = null) {
    setUserForm(item ? {
      ...userEmpty,
      ...item,
      roles: item.roles?.length ? item.roles : ['viewer'],
      password: '',
      avatar: null,
    } : { ...userEmpty });
    setUserModalOpen(true);
  }

  function updateField(key, value) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  async function saveMaster(event) {
    event.preventDefault();
    setSaving(true);

    try {
      const config = masterConfigs[modal.section];
      await api.saveSettingMaster(config.endpoint, form);
      await loadSettings();
      setModal({ section: '', open: false });
      setToast({ type: 'success', title: `${config.title} saved`, message: 'The settings record has been updated.' });
    } catch (error) {
      setToast({ type: 'error', title: 'Unable to save settings', message: error.response?.data?.message || 'Save failed.' });
    } finally {
      setSaving(false);
    }
  }

  async function saveCompanyProfile(event) {
    event.preventDefault();
    setSaving(true);

    try {
      await api.saveSettingValue({
        category: 'company',
        setting_key: 'company_profile',
        setting_value: companyProfile,
      });
      await loadSettings();
      setToast({ type: 'success', title: 'Company profile saved', message: 'Company profile settings updated.' });
    } catch (error) {
      setToast({ type: 'error', title: 'Unable to save company profile', message: error.response?.data?.message || 'Save failed.' });
    } finally {
      setSaving(false);
    }
  }

  async function saveUser(event) {
    event.preventDefault();
    setSaving(true);

    try {
      const payload = buildFormData(userForm);
      if (userForm.id) {
        await api.updateUser(userForm.id, payload);
      } else {
        await api.saveUser(payload);
      }
      await loadSettings();
      setUserModalOpen(false);
      setToast({ type: 'success', title: userForm.id ? 'User updated' : 'User created', message: 'User account and role access saved.' });
    } catch (error) {
      setToast({ type: 'error', title: 'Unable to save user', message: error.response?.data?.message || 'Save failed.' });
    } finally {
      setSaving(false);
    }
  }

  async function saveProfile(event) {
    event.preventDefault();
    setSaving(true);

    try {
      const payload = buildFormData(profile);
      const { data: response } = await api.updateProfile(payload);
      setUser(response.data);
      setProfile((current) => ({ ...current, avatar: null, avatar_path: response.data.avatar_path || current.avatar_path }));
      setToast({ type: 'success', title: 'Profile updated', message: 'Your profile details are now updated.' });
    } catch (error) {
      setToast({ type: 'error', title: 'Unable to update profile', message: error.response?.data?.message || 'Save failed.' });
    } finally {
      setSaving(false);
    }
  }

  async function savePassword(event) {
    event.preventDefault();
    setSaving(true);

    try {
      await api.updatePassword(passwordForm);
      setPasswordForm(passwordEmpty);
      setToast({ type: 'success', title: 'Password updated', message: 'Your account password has been changed.' });
    } catch (error) {
      setToast({ type: 'error', title: 'Unable to update password', message: error.response?.data?.message || 'Save failed.' });
    } finally {
      setSaving(false);
    }
  }

  function renderMasterForm() {
    const config = masterConfigs[modal.section];
    if (!config) {
      return null;
    }

    return (
      <form id="settings-master-form" className="form-grid" onSubmit={saveMaster}>
        {config.fields.map((field) => {
          if (field === 'company_id') {
            return (
              <label key={field}>
                <span>Company</span>
                <select value={form.company_id || ''} onChange={(event) => updateField('company_id', event.target.value)}>
                  <option value="">Select company</option>
                  {companies.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                </select>
              </label>
            );
          }

          if (['has_expiry', 'default_mail_enabled', 'default_notification_enabled'].includes(field)) {
            return (
              <label key={field}>
                <span>{field.replaceAll('_', ' ')}</span>
                <select value={String(form[field] ?? 1)} onChange={(event) => updateField(field, Number(event.target.value))}>
                  <option value="1">Yes</option>
                  <option value="0">No</option>
                </select>
              </label>
            );
          }

          const type = field.includes('days') || field === 'sort_order' ? 'number' : 'text';
          return (
            <label key={field} className={field === 'address' || field === 'description' ? 'field-span-2' : ''}>
              <span>{field.replaceAll('_', ' ')}</span>
              {field === 'address' || field === 'description' ? (
                <textarea value={form[field] || ''} onChange={(event) => updateField(field, event.target.value)} />
              ) : (
                <input type={type} value={form[field] || ''} onChange={(event) => updateField(field, event.target.value)} />
              )}
            </label>
          );
        })}
      </form>
    );
  }

  function renderGeneralTab() {
    return (
      <div className="dashboard-grid">
        <SectionCard
          title="Company Profile"
          subtitle="Editable company profile settings used across the portal."
          action={<button type="submit" form="company-profile-form" className="secondary-button small-button">Save Profile</button>}
        >
          <form id="company-profile-form" className="form-grid" onSubmit={saveCompanyProfile}>
            <label><span>Company Name</span><input value={companyProfile.name || ''} onChange={(event) => setCompanyProfile((current) => ({ ...current, name: event.target.value }))} /></label>
            <label><span>Support Email</span><input value={companyProfile.supportEmail || ''} onChange={(event) => setCompanyProfile((current) => ({ ...current, supportEmail: event.target.value }))} /></label>
            <label><span>Timezone</span><input value={companyProfile.timezone || ''} onChange={(event) => setCompanyProfile((current) => ({ ...current, timezone: event.target.value }))} /></label>
          </form>
        </SectionCard>

        <SectionCard
          title="Employee Document Masters"
          subtitle="Editable employee-side document definitions."
          action={<button type="button" className="primary-button small-button" onClick={() => openMaster('employeeDocumentMasters')}><Plus size={14} />Add</button>}
        >
          <div className="stack-list">
            {(data?.employeeDocumentMasters || []).map((item) => (
              <article key={item.id} className="stack-item">
                <div>
                  <strong>{item.name}</strong>
                  <p>Alert {item.default_alert_days} days • Mail {item.default_mail_enabled ? 'on' : 'off'}</p>
                </div>
                <div className="table-actions">
                  <StatusBadge value={item.has_expiry ? 'valid' : 'inactive'} />
                  <button type="button" className="secondary-button small-button" onClick={() => openMaster('employeeDocumentMasters', item)}><Pencil size={14} /></button>
                </div>
              </article>
            ))}
          </div>
        </SectionCard>

        <SectionCard
          title="Company Document Masters"
          subtitle="Editable company-side compliance definitions."
          action={<button type="button" className="primary-button small-button" onClick={() => openMaster('companyDocumentMasters')}><Plus size={14} />Add</button>}
        >
          <div className="stack-list">
            {(data?.companyDocumentMasters || []).map((item) => (
              <article key={item.id} className="stack-item">
                <div>
                  <strong>{item.name}</strong>
                  <p>Alert {item.default_alert_days} days • Notifications {item.default_notification_enabled ? 'on' : 'off'}</p>
                </div>
                <div className="table-actions">
                  <StatusBadge value={item.has_expiry ? 'valid' : 'inactive'} />
                  <button type="button" className="secondary-button small-button" onClick={() => openMaster('companyDocumentMasters', item)}><Pencil size={14} /></button>
                </div>
              </article>
            ))}
          </div>
        </SectionCard>

        <SectionCard title="Master Data" subtitle="Companies, branches, departments, and designations are editable here.">
          <div className="settings-master-grid">
            {[
              ['companies', 'Companies'],
              ['branches', 'Branches'],
              ['departments', 'Departments'],
              ['designations', 'Designations'],
            ].map(([key, label]) => (
              <div key={key} className="settings-master-block">
                <div className="settings-master-head">
                  <strong>{label}</strong>
                  <button type="button" className="primary-button small-button" onClick={() => openMaster(key)}><Plus size={14} /></button>
                </div>
                {(data?.[key] || []).map((item) => (
                  <div key={item.id} className="settings-master-row">
                    <span>{item.name}</span>
                    <button type="button" className="secondary-button small-button" onClick={() => openMaster(key, item)}><Pencil size={14} /></button>
                  </div>
                ))}
              </div>
            ))}
          </div>
        </SectionCard>
      </div>
    );
  }

  function renderUsersTab() {
    return (
      <SectionCard
        title="User Management"
        subtitle="Create internal users, assign roles, and control account status."
        action={<button type="button" className="primary-button small-button" onClick={() => openUser()}><Plus size={14} />New User</button>}
      >
        <div className="table-scroll">
          <table className="data-table">
            <thead>
              <tr>
                <th>User</th>
                <th>Username</th>
                <th>Company</th>
                <th>Branch</th>
                <th>Roles</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              {(usersData.users || []).map((item) => (
                <tr key={item.id}>
                  <td>
                    <div className="user-cell">
                      <div className="avatar avatar-small">
                        {item.avatar_path ? <img src={buildFileUrl(item.avatar_path)} alt={item.full_name} className="avatar-image" /> : item.full_name.slice(0, 2)}
                      </div>
                      <div>
                        <strong>{item.full_name}</strong>
                        <p>{item.email}</p>
                      </div>
                    </div>
                  </td>
                  <td>{item.username}</td>
                  <td>{item.company || '-'}</td>
                  <td>{item.branch || '-'}</td>
                  <td>{(item.roles || []).map((role) => <span key={role} className="inline-tag">{role}</span>)}</td>
                  <td><StatusBadge value={Number(item.is_active) ? 'active' : 'inactive'} /></td>
                  <td>{item.last_login_at || '-'}</td>
                  <td><button type="button" className="secondary-button small-button" onClick={() => openUser(item)}><Pencil size={14} /></button></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </SectionCard>
    );
  }

  function renderProfileTab() {
    return (
      <div className="dashboard-grid">
        <SectionCard
          title="My Profile"
          subtitle="Update your account details, avatar, and company mapping."
          action={<button type="submit" form="profile-form" className="primary-button small-button">Save Profile</button>}
        >
          <form id="profile-form" className="form-grid" onSubmit={saveProfile}>
            <div className="field field-span-2 profile-hero">
              <div className="avatar avatar-large">
                {profile.avatar_path ? <img src={buildFileUrl(profile.avatar_path)} alt={profile.full_name} className="avatar-image" /> : (user?.full_name?.slice(0, 2) || 'HR')}
              </div>
              <div className="profile-copy">
                <strong>{profile.full_name || 'Portal User'}</strong>
                <p>{user?.roles?.join(', ') || 'Internal access'}</p>
              </div>
            </div>
            <label><span>Full Name</span><input value={profile.full_name} onChange={(event) => setProfile((current) => ({ ...current, full_name: event.target.value }))} /></label>
            <label><span>Email</span><input value={profile.email} onChange={(event) => setProfile((current) => ({ ...current, email: event.target.value }))} /></label>
            <label><span>Username</span><input value={profile.username} onChange={(event) => setProfile((current) => ({ ...current, username: event.target.value }))} /></label>
            <label><span>Phone</span><input value={profile.phone} onChange={(event) => setProfile((current) => ({ ...current, phone: event.target.value }))} /></label>
            <label><span>Company</span>
              <select value={profile.company_id} onChange={(event) => setProfile((current) => ({ ...current, company_id: event.target.value, branch_id: '' }))}>
                <option value="">Select company</option>
                {companies.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select>
            </label>
            <label><span>Branch</span>
              <select value={profile.branch_id} onChange={(event) => setProfile((current) => ({ ...current, branch_id: event.target.value }))}>
                <option value="">Select branch</option>
                {filteredProfileBranches.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
              </select>
            </label>
            <label className="field-span-2"><span>Profile Picture</span><input type="file" onChange={(event) => setProfile((current) => ({ ...current, avatar: event.target.files?.[0] || null }))} /></label>
          </form>
        </SectionCard>

        <SectionCard
          title="Password Update"
          subtitle="Change your login password using your current password."
          action={<button type="submit" form="password-form" className="primary-button small-button">Update Password</button>}
        >
          <form id="password-form" className="form-grid" onSubmit={savePassword}>
            <label><span>Current Password</span><input type="password" value={passwordForm.current_password} onChange={(event) => setPasswordForm((current) => ({ ...current, current_password: event.target.value }))} /></label>
            <label><span>New Password</span><input type="password" value={passwordForm.new_password} onChange={(event) => setPasswordForm((current) => ({ ...current, new_password: event.target.value }))} /></label>
            <label><span>Confirm Password</span><input type="password" value={passwordForm.confirm_password} onChange={(event) => setPasswordForm((current) => ({ ...current, confirm_password: event.target.value }))} /></label>
          </form>
        </SectionCard>
      </div>
    );
  }

  return (
    <AppShell title="Settings" subtitle="Manage masters, notifications, users, and your account profile." breadcrumbs={['Home', 'Administration', 'Settings']}>
      <PageToolbar title="Settings Management" subtitle="Administration, access control, and profile management in one place." />

      <div className="settings-tabs">
        {tabs.filter((tab) => isAdmin || tab.id === 'profile').map(({ id, label, icon: Icon }) => (
          <button
            key={id}
            type="button"
            className={`settings-tab ${activeTab === id ? 'active' : ''}`}
            onClick={() => setParams({ tab: id })}
          >
            <Icon size={16} />
            <span>{label}</span>
          </button>
        ))}
      </div>

      {activeTab === 'users' && isAdmin ? renderUsersTab() : activeTab === 'profile' ? renderProfileTab() : renderGeneralTab()}

      <Modal
        open={modal.open}
        title={modal.section ? `Edit ${masterConfigs[modal.section]?.title}` : 'Edit Settings'}
        subtitle="Update the selected settings master record."
        onClose={() => setModal({ section: '', open: false })}
        footer={(
          <>
            <button type="button" className="secondary-button" onClick={() => setModal({ section: '', open: false })}>Cancel</button>
            <button type="submit" form="settings-master-form" className="primary-button" disabled={saving}>{saving ? 'Saving...' : 'Save'}</button>
          </>
        )}
      >
        {renderMasterForm()}
      </Modal>

      <Modal
        open={userModalOpen}
        title={userForm.id ? 'Edit User' : 'Create User'}
        subtitle="Create internal login accounts and assign permission roles."
        onClose={() => setUserModalOpen(false)}
        footer={(
          <>
            <button type="button" className="secondary-button" onClick={() => setUserModalOpen(false)}>Cancel</button>
            <button type="submit" form="user-form" className="primary-button" disabled={saving}>{saving ? 'Saving...' : 'Save User'}</button>
          </>
        )}
      >
        <form id="user-form" className="form-grid" onSubmit={saveUser}>
          <label><span>Full Name</span><input value={userForm.full_name} onChange={(event) => setUserForm((current) => ({ ...current, full_name: event.target.value }))} required /></label>
          <label><span>Email</span><input value={userForm.email} onChange={(event) => setUserForm((current) => ({ ...current, email: event.target.value }))} required /></label>
          <label><span>Username</span><input value={userForm.username} onChange={(event) => setUserForm((current) => ({ ...current, username: event.target.value }))} required /></label>
          <label><span>Phone</span><input value={userForm.phone} onChange={(event) => setUserForm((current) => ({ ...current, phone: event.target.value }))} /></label>
          <label><span>Company</span>
            <select value={userForm.company_id} onChange={(event) => setUserForm((current) => ({ ...current, company_id: event.target.value, branch_id: '' }))}>
              <option value="">Select company</option>
              {companies.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          </label>
          <label><span>Branch</span>
            <select value={userForm.branch_id} onChange={(event) => setUserForm((current) => ({ ...current, branch_id: event.target.value }))}>
              <option value="">Select branch</option>
              {filteredUserBranches.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
            </select>
          </label>
          <label><span>{userForm.id ? 'Reset Password' : 'Password'}</span><input type="password" value={userForm.password} onChange={(event) => setUserForm((current) => ({ ...current, password: event.target.value }))} required={!userForm.id} /></label>
          <label><span>Status</span>
            <select value={String(userForm.is_active)} onChange={(event) => setUserForm((current) => ({ ...current, is_active: Number(event.target.value) }))}>
              <option value="1">Active</option>
              <option value="0">Inactive</option>
            </select>
          </label>
          <label className="field-span-2"><span>Profile Picture</span><input type="file" onChange={(event) => setUserForm((current) => ({ ...current, avatar: event.target.files?.[0] || null }))} /></label>
          <div className="field field-span-2">
            <span>Roles</span>
            <div className="inline-checks">
              {roles.map((role) => (
                <label key={role.slug}>
                  <input
                    type="checkbox"
                    checked={(userForm.roles || []).includes(role.slug)}
                    onChange={(event) => {
                      setUserForm((current) => ({
                        ...current,
                        roles: event.target.checked
                          ? [...new Set([...(current.roles || []), role.slug])]
                          : (current.roles || []).filter((item) => item !== role.slug),
                      }));
                    }}
                  />
                  {role.name}
                </label>
              ))}
            </div>
          </div>
        </form>
      </Modal>

      <Toast toast={toast} onClose={() => setToast(null)} />
    </AppShell>
  );
}
