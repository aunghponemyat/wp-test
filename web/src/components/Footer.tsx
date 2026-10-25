import Link from "next/link";
export default function Footer() {
  return (
    <footer className="site-footer">
      <div className="container footer-grid">
        <div>
          <Link href="/" className="footer-brand">
            CareConnect
          </Link>
          <p>Care closer to home.</p>
          <p className="small">
            A fictional community care directory.
            <br />
            All services and contact details are for demonstration.
          </p>
        </div>
        <nav aria-label="Footer navigation">
          {[
            ["/", "Home"],
            ["/services/", "Services"],
            ["/articles/", "Articles"],
            ["/contact/", "Contact"],
            ["/privacy/", "Privacy"],
          ].map(([href, label]) => (
            <Link href={href} key={href}>
              {label}
            </Link>
          ))}
        </nav>
      </div>
      <div className="container footer-bottom">
        <span>Built around people. Connected by care.</span>
        <span>English · Singapore demo</span>
      </div>
    </footer>
  );
}
