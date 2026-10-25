import SearchBox from "./SearchBox";
export default function Hero({
  title,
  subtitle,
}: {
  title: string;
  subtitle: string;
}) {
  return (
    <section className="hero">
      <div className="container hero-grid">
        <div>
          <p className="eyebrow">
            <span aria-hidden="true" />
            CARE, WITHIN REACH
          </p>
          <h1>{title}</h1>
          <p className="hero-subtitle">{subtitle}</p>
          <form
            action="/services/"
            method="get"
            className="hero-search"
            role="search"
          >
            <SearchBox id="home-search" />
            <button className="button" type="submit">
              Find support <span aria-hidden="true">→</span>
            </button>
          </form>
          <p className="hero-note">
            <span aria-hidden="true">♡</span> A place to start, for you and
            those you care for.
          </p>
        </div>
        <div className="hero-art">
          <img
            src="/images/seed/community.svg"
            alt="A welcoming neighbourhood with a home, clinic and community garden"
            width={520}
            height={420}
            decoding="async"
            fetchPriority="high"
          />
          <span className="art-caption">
            <span aria-hidden="true">✦</span> Stronger together
          </span>
        </div>
      </div>
    </section>
  );
}
