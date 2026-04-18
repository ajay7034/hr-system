import {
  ChevronDown,
  Bell,
  BriefcaseBusiness,
  Building2,
  Download,
  FileClock,
  FileSpreadsheet,
  FileText,
  IdCard,
  LayoutDashboard,
  LogOut,
  Menu,
  MoonStar,
  Search,
  Settings,
  SunMedium,
  UserCog,
  Users,
  X,
} from 'lucide-react';
import { NavLink, useLocation, useNavigate } from 'react-router-dom';
import { useEffect, useMemo, useRef, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import { api, buildFileUrl } from '../api/client';

const navGroups = [
  {
    label: 'Dashboard',
    items: [{ to: '/', label: 'Dashboard', icon: LayoutDashboard, keywords: ['home', 'overview', 'summary'] }],
  },
  {
    label: 'Employees',
    items: [
      { to: '/employees', label: 'Employee Master', icon: Users, keywords: ['employee', 'staff', 'master', 'workers'] },
      { to: '/passports', label: 'Passport Custody', icon: IdCard, keywords: ['passport', 'custody', 'movement', 'locker'] },
      { to: '/employee-documents', label: 'Employee Documents', icon: FileClock, keywords: ['employee', 'documents', 'expiry', 'reminder'] },
    ],
  },
  {
    label: 'Company',
    items: [
      { to: '/company-documents', label: 'Company Documents', icon: Building2, keywords: ['company', 'documents', 'compliance', 'expiry'] },
      { to: '/reports', label: 'Reports', icon: FileSpreadsheet, keywords: ['report', 'analytics', 'summary', 'export'] },
    ],
  },
  {
    label: 'Forms',
    items: [
      { to: '/forms/passport-withdrawal', label: 'Passport Withdrawal Form', icon: FileText, keywords: ['forms', 'form', 'passport', 'withdrawal', 'pdf', 'document template', 'generate'] },
      { to: '/forms/rejoining-report', label: 'Rejoining Report', icon: FileText, keywords: ['forms', 'form', 'rejoining', 'rejoin', 'passport', 'return', 'pdf', 'document template', 'generate'] },
    ],
  },
  {
    label: 'Administration',
    items: [
      { to: '/settings', label: 'Settings', icon: Settings, keywords: ['settings', 'preferences', 'configuration', 'config'] },
      { to: '/requests?status=pending', label: 'Request Center', icon: FileText, keywords: ['requests', 'approvals', 'leave', 'loan', 'salary certificate', 'portal'] },
      { to: '/settings?tab=users', label: 'User Management', icon: UserCog, keywords: ['users', 'user', 'roles', 'permissions', 'access'] },
      { to: '/admin/log-report', label: 'Log Report', icon: FileClock, keywords: ['logs', 'activity', 'audit', 'history'] },
      { to: '/admin/backup', label: 'Backup', icon: FileSpreadsheet, keywords: ['backup', 'database', 'sql', 'download'] },
    ],
  },
];

export default function AppShell({ title, subtitle, actions, notifications = [], breadcrumbs = [], children }) {
  const location = useLocation();
  const navigate = useNavigate();
  const { user, logout } = useAuth();
  const [theme, setTheme] = useState(localStorage.getItem('hr-theme') || 'dark');
  const [expandedGroups, setExpandedGroups] = useState(['Dashboard', 'Employees', 'Company', 'Forms', 'Administration']);
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const [navSearch, setNavSearch] = useState('');
  const [globalSearch, setGlobalSearch] = useState('');
  const [globalSearchResults, setGlobalSearchResults] = useState([]);
  const [globalSearchOpen, setGlobalSearchOpen] = useState(false);
  const [globalSearchLoading, setGlobalSearchLoading] = useState(false);
  const [notificationItems, setNotificationItems] = useState([]);
  const [notificationOpen, setNotificationOpen] = useState(false);
  const [installPromptEvent, setInstallPromptEvent] = useState(null);
  const [isStandalone, setIsStandalone] = useState(() => {
    if (typeof window === 'undefined') {
      return false;
    }

    return window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true;
  });
  const notificationRef = useRef(null);
  const globalSearchRef = useRef(null);

  useEffect(() => {
    document.documentElement.dataset.theme = theme;
    localStorage.setItem('hr-theme', theme);
  }, [theme]);

  useEffect(() => {
    if (!user) {
      return;
    }

    loadNotifications();
  }, [user]);

  useEffect(() => {
    if (notificationOpen) {
      loadNotifications();
    }
  }, [notificationOpen]);

  useEffect(() => {
    setMobileNavOpen(false);
    setGlobalSearch('');
    setGlobalSearchResults([]);
    setGlobalSearchOpen(false);
    setNotificationOpen(false);
  }, [location.pathname, location.search]);

  useEffect(() => {
    document.body.classList.toggle('mobile-nav-open', mobileNavOpen);

    return () => document.body.classList.remove('mobile-nav-open');
  }, [mobileNavOpen]);

  useEffect(() => {
    if (typeof window === 'undefined') {
      return undefined;
    }

    const mediaQuery = window.matchMedia?.('(display-mode: standalone)');
    const syncStandalone = () => {
      setIsStandalone(mediaQuery?.matches || window.navigator.standalone === true);
    };
    const handleBeforeInstallPrompt = (event) => {
      event.preventDefault();
      setInstallPromptEvent(event);
    };
    const handleInstalled = () => {
      setInstallPromptEvent(null);
      setIsStandalone(true);
    };

    syncStandalone();
    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    window.addEventListener('appinstalled', handleInstalled);
    mediaQuery?.addEventListener?.('change', syncStandalone);

    return () => {
      window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
      window.removeEventListener('appinstalled', handleInstalled);
      mediaQuery?.removeEventListener?.('change', syncStandalone);
    };
  }, []);

  useEffect(() => {
    const query = globalSearch.trim();

    if (query.length < 2) {
      setGlobalSearchLoading(false);
      setGlobalSearchResults([]);
      return;
    }

    let active = true;
    const timeout = window.setTimeout(async () => {
      setGlobalSearchLoading(true);

      try {
        const { data } = await api.globalSearch({ q: query });
        if (active) {
          setGlobalSearchResults(data.data || []);
          setGlobalSearchOpen(true);
        }
      } catch {
        if (active) {
          setGlobalSearchResults([]);
        }
      } finally {
        if (active) {
          setGlobalSearchLoading(false);
        }
      }
    }, 220);

    return () => {
      active = false;
      window.clearTimeout(timeout);
    };
  }, [globalSearch]);

  useEffect(() => {
    function handleOutsideClick(event) {
      if (!notificationRef.current?.contains(event.target)) {
        setNotificationOpen(false);
      }

      if (!globalSearchRef.current?.contains(event.target)) {
        setGlobalSearchOpen(false);
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
  const showGlobalSearchMenu = globalSearchOpen && globalSearch.trim().length >= 2;

  const searchQuery = navSearch.trim().toLowerCase();
  const filteredNavItems = useMemo(() => {
    if (!searchQuery) {
      return [];
    }

    return navGroups.flatMap((group) => (
      group.items
        .filter((item) => {
          const haystack = [group.label, item.label, ...(item.keywords || [])]
            .join(' ')
            .toLowerCase();

          return haystack.includes(searchQuery);
        })
        .map((item) => ({ ...item, groupLabel: group.label }))
    ));
  }, [searchQuery]);

  async function loadNotifications() {
    try {
      const { data } = await api.notifications();
      setNotificationItems(data.data || []);
    } catch {
      setNotificationItems((current) => current);
    }
  }

  async function markNotificationRead(item) {
    if (!item || Number(item.is_read)) {
      return;
    }

    await api.readNotification(item.id);
    await loadNotifications();
  }

  async function markAllRead() {
    await api.readAllNotifications();
    await loadNotifications();
  }

  async function handleInstallApp() {
    if (!installPromptEvent) {
      return;
    }

    await installPromptEvent.prompt();
    await installPromptEvent.userChoice.catch(() => null);
    setInstallPromptEvent(null);
  }

  function handleNavSelection() {
    setNavSearch('');
    setMobileNavOpen(false);
  }

  function selectGlobalResult(item) {
    if (!item?.route) {
      return;
    }

    navigate(item.route);
    setGlobalSearch('');
    setGlobalSearchResults([]);
    setGlobalSearchOpen(false);
  }

  return (
    <div className="shell">
      <button
        type="button"
        className={`mobile-sidebar-overlay ${mobileNavOpen ? 'is-visible' : ''}`}
        aria-label="Close navigation"
        onClick={() => setMobileNavOpen(false)}
      />

      <aside className={`sidebar glass-panel ${mobileNavOpen ? 'is-mobile-open' : ''}`}>
        <div className="sidebar-mobile-head">
          <strong>Navigation</strong>
          <button type="button" className="icon-button sidebar-mobile-close" aria-label="Close menu" onClick={() => setMobileNavOpen(false)}>
            <X size={18} />
          </button>
        </div>

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
          <input
            value={navSearch}
            onChange={(event) => setNavSearch(event.target.value)}
            placeholder="Search settings, reports, users..."
          />
        </div>

        {searchQuery ? (
          <>
            <div className="sidebar-caption">Search Results</div>
            <nav className="sidebar-nav sidebar-search-results">
              {filteredNavItems.length ? filteredNavItems.map(({ to, label, icon: Icon, groupLabel }) => (
                <NavLink
                  key={`search-${groupLabel}-${label}`}
                  to={to}
                  end={to === '/'}
                  className="nav-link nav-search-link"
                  onClick={handleNavSelection}
                >
                  <Icon size={16} />
                  <span>{label}</span>
                  <small>{groupLabel}</small>
                </NavLink>
              )) : (
                <div className="sidebar-search-empty">No pages matched your search.</div>
              )}
            </nav>
          </>
        ) : (
          <>
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
                          <NavLink key={`${group.label}-${label}`} to={to} end={to === '/'} className="nav-link" onClick={handleNavSelection}>
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
          </>
        )}

        <button
          type="button"
          className="sidebar-logout"
          onClick={async () => {
            await logout();
            setMobileNavOpen(false);
            navigate('/login');
          }}
        >
          <LogOut size={18} />
          <span>Logout</span>
        </button>
      </aside>

      <main className="content page-transition">
        <header className="topbar glass-panel">
          <div className="mobile-topbar-row">
            <button
              type="button"
              className="icon-button mobile-nav-toggle"
              aria-label="Open menu"
              onClick={() => setMobileNavOpen(true)}
            >
              <Menu size={18} />
            </button>
            <div className="mobile-topbar-brand">
              <strong>Media HR</strong>
              <span>{user?.username || 'Portal user'}</span>
            </div>
            {installPromptEvent && !isStandalone ? (
              <button type="button" className="secondary-button mobile-install-button" onClick={handleInstallApp}>
                <Download size={16} />
                <span>Install</span>
              </button>
            ) : (
              <div className="mobile-topbar-spacer" />
            )}
          </div>

          <div className="topbar-main">
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
            <form
              className="global-search"
              ref={globalSearchRef}
              onSubmit={(event) => {
                event.preventDefault();
                if (globalSearchResults[0]) {
                  selectGlobalResult(globalSearchResults[0]);
                }
              }}
            >
              <Search size={18} className="global-search-icon" />
              <input
                value={globalSearch}
                onChange={(event) => setGlobalSearch(event.target.value)}
                onFocus={() => {
                  if (globalSearch.trim().length >= 2) {
                    setGlobalSearchOpen(true);
                  }
                }}
                placeholder="Search employees, document numbers, passports..."
              />
              {showGlobalSearchMenu ? (
                <div className="global-search-menu glass-panel">
                  {globalSearchLoading ? (
                    <div className="global-search-empty">Searching records...</div>
                  ) : globalSearchResults.length ? (
                    globalSearchResults.map((item, index) => (
                      <button
                        type="button"
                        key={`${item.type}-${item.route}-${index}`}
                        className="global-search-item"
                        onClick={() => selectGlobalResult(item)}
                      >
                        <span className="global-search-type">{item.type_label}</span>
                        <div className="global-search-copy">
                          <strong>{item.title}</strong>
                          <small>{item.subtitle}</small>
                        </div>
                      </button>
                    ))
                  ) : (
                    <div className="global-search-empty">No matching employees or documents found.</div>
                  )}
                </div>
              ) : null}
            </form>
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
