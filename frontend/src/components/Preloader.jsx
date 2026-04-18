export function Preloader({ label = 'Loading portal...', fullscreen = true }) {
  return (
    <div className={fullscreen ? 'preloader-screen' : 'preloader-inline'}>
      <div className="preloader-orbit" aria-hidden="true">
        <span className="preloader-ring preloader-ring-primary" />
        <span className="preloader-ring preloader-ring-accent" />
        <span className="preloader-core" />
      </div>
      <p>{label}</p>
    </div>
  );
}

export function RouteLoader({ active }) {
  if (!active) {
    return null;
  }

  return (
    <div className="route-loader-overlay">
      <div className="route-loader-shell">
        <div className="preloader-orbit" aria-hidden="true">
          <span className="preloader-ring preloader-ring-primary" />
          <span className="preloader-ring preloader-ring-accent" />
          <span className="preloader-core" />
        </div>
      </div>
    </div>
  );
}
