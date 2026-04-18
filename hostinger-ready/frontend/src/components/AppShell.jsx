import {
  ChevronDown,
  Bell,
  BriefcaseBusiness,
  Building2,
  FileClock,
  FileSpreadsheet,
  IdCard,
  LayoutDashboard,
  LogOut,
  MoonStar,
  Search,
  Settings,
  SunMedium,
  UserCog,
  Users,
} from 'lucide-react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import { api, buildFileUrl } from '../api/client';

const navGroups = [
  {
    label: 'Dashboard',
    items: [{ to: '/', label: 'Dashboard', icon: LayoutDashboard }],
  },
  {
    label: 'Employees',
    items: [
      { to: '/employees', label: 'Employee Master', icon: Users },
      { to: '/passports', label: 'Passport Custody', icon: IdCard },
      { to: '/employee-documents', label: 'Employee Documents', icon: FileClock },
    ],
  },
  {
    label: 'Company',
    items: [
      { to: '/company-documents', label: 'Company Documents', icon: Building2 },
      { to: '/reports', label: 'Reports', icon: FileSpreadsheet },
    ],
  },
  {
    label: 'Administration',
    items: [
      { to: '/settings', label: 'Settings', icon: Settings },
      { to: '/settings?tab=users', label: 'User Management', icon: UserCog },
    ],
  },
];

export default function AppShell({ title, subtitle, actions, notifications = [], breadcrumbs = [], children }) {
  const navigate = useNavigate();
  const { user, logout } = useAuth();
  const [theme, setTheme] = useState(localStorage.getItem('hr-theme') || 'dark');
  const [expandedGroups, setExpandedGroups] = useState(['Dashboard', 'Employees', 'Company', 'Administration']);
  const [notificationItems, setNotificationItems] = useState([]);
  const [notificationOpen, setNotificationOpen] = useState(false);
  const notificationRef = useRef(null);

  useEffect(() => {
    document.documentElement.dataset.theme = theme;
    localStorage.setItem('hr-theme', theme);
  }, [theme]);

  useEffect(() => {
    if (!user) {
      return;
    }

    api.notifications().then(({ data }) => {
      setNotificationItems(data.data || []);
    }).catch(() => {});
  }, [user]);

  useEffect(() => {
    function handleOutsideClick(event) {
      if (!notificationRef.current?.contains(event.target)) {
        setNotificationOpen(false);
      }
    }

    document.addEventListener('mousedown', handleOutsideClick);
    return () => document.removeEventListener('mousedown', handleOutsideClick);
  }, []);

  const breadcrumbItems = useMemo(() => {
    if (breadcrumbs.length) {
      return breadcrumbs;
    }

    return ['Home', title];
  }, [breadcrumbs, title]);

  const unreadCount = useMemo(
    () => notificationItems.filter((item) => !Number(item.is_read)).length,
    [notificationItems]
  );

  async function markNotificationRead(item) {
    if (!item || Number(item.is_read)) {
      return;
    }

    await api.readNotification(item.id);
    setNotificationItems((current) => current.map((notification) => (
      notification.id === item.id ? { ...notification, is_read: 1 } : notification
    )));
  }

  async function markAllRead() {
    await api.readAllNotifications();
    setNotificationItems((current) => current.map((notification) => ({ ...notification, is_read: 1 })));
  }

  return (
    <div className="shell">
      <aside className="sidebar glass-panel">
        <div className="brand-band">
          <div className="brand">
            <div className="brand-mark">
              <BriefcaseBusiness size={18} />
            </div>
            <div>
              <p>Media HR</p>
              <span>Employee M S</span>
            </div>
          </div>
        </div>

        <div className="sidebar-user">
          <div className="avatar">
            {user?.avatar_path ? <img src={buildFileUrl(user.avatar_path)} alt={user.full_name} className="avatar-image" /> : (user?.full_name?.slice(0, 2) || 'HR')}
          </div>
          <div>
            <strong>{user?.full_name || 'Portal User'}</strong>
            <span>{user?.roles?.join(', ') || 'Internal access'}</span>
            <small className="online-state">Online</small>
          </div>
        </div>

        <div className="sidebar-search">
          <Search size={14} />
          <input placeholder="Search..." />
        </div>

        <div className="sidebar-caption">Main Navigation</div>

        <nav className="sidebar-nav">
          {navGroups.map((group) => {
            const expanded = expandedGroups.includes(group.label);

            return (
              <div key={group.label} className="nav-group">
                <button
                  type="button"
                  className="nav-group-toggle"
                  onClick={() => {
                    setExpandedGroups((current) => (
                      current.includes(group.label)
                        ? current.filter((item) => item !== group.label)
                        : [...current, group.label]
                    ));
                  }}
                >
                  <span>{group.label}</span>
                  <ChevronDown size={14} className={expanded ? 'rotated' : ''} />
                </button>
                {expanded ? (
                  <div className="nav-submenu">
                    {group.items.map(({ to, label, icon: Icon }) => (
                      <NavLink key={`${group.label}-${label}`} to={to} end={to === '/'} className="nav-link">
                        <Icon size={16} />
                        <span>{label}</span>
                      </NavLink>
                    ))}
                  </div>
                ) : null}
              </div>
            );
          })}
        </nav>

        <button
          type="button"
          className="sidebar-logout"
          onClick={async () => {
            await logout();
            navigate('/login');
          }}
        >
          <LogOut size={18} />
          <span>Logout</span>
        </button>
      </aside>

      <main className="content page-transition">
        <header className="topbar glass-panel">
          <div className="topbar-copy">
            <div className="breadcrumbs">
              {breadcrumbItems.map((item, index) => (
                <span key={`${item}-${index}`} className="breadcrumb-item">
                  {index > 0 ? <small>/</small> : null}
                  {item}
                </span>
              ))}
            </div>
            <h1>{title}</h1>
            <p>{subtitle}</p>
          </div>
          <div className="topbar-actions">
            {actions}
            <button type="button" className="icon-button" onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}>
              {theme === 'dark' ? <SunMedium size={18} /> : <MoonStar size={18} />}
            </button>
            <div className="notification-wrap" ref={notificationRef}>
              <button type="button" className="notification-pill" onClick={() => setNotificationOpen((current) => !current)}>
                <Bell size={16} />
                <span>{unreadCount}</span>
              </button>
              {notificationOpen ? (
                <div className="notification-menu glass-panel">
                  <div className="notification-menu-head">
                    <strong>Notifications</strong>
                    <button type="button" className="link-button" onClick={markAllRead}>Mark all read</button>
                  </div>
                  <div className="notification-menu-list">
                    {notificationItems.length ? notificationItems.slice(0, 8).map((item) => (
                      <button
                        type="button"
                        key={item.id}
                        className={`notification-item ${Number(item.is_read) ? 'is-read' : 'is-unread'}`}
                        onClick={async () => {
                          await markNotificationRead(item);
                        }}
                      >
                        <div className={`notification-severity severity-${item.severity}`} />
                        <div>
                          <strong>{item.title}</strong>
                          <p>{item.message}</p>
                          <small>{item.created_at}</small>
                        </div>
                      </button>
                    )) : (
                      <div className="notification-empty">No notifications available.</div>
                    )}
                  </div>
                </div>
              ) : null}
            </div>
            <div className="topbar-user">{user?.username || 'admin'}</div>
          </div>
        </header>

        {children}
      </main>
    </div>
  );
}
