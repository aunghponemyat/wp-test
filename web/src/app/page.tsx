import Link from "next/link";
import Hero from "@/components/Hero";
import ServiceCard from "@/components/ServiceCard";
import ArticleCard from "@/components/ArticleCard";
import { getHome } from "@/lib/api";
import { pageMetadata } from "@/lib/metadata";
export const metadata = pageMetadata(
  "Community care, closer to home",
  "Find fictional community care services, practical guides and welcoming spaces for older residents and caregivers.",
  "/",
);
export default async function Home() {
  const home = await getHome();
  return (
    <>
      <Hero title={home.hero.title} subtitle={home.hero.subtitle} />
      <section className="section container">
        <div className="section-heading">
          <div>
            <p className="eyebrow">HERE TO HELP</p>
            <h2>Find your kind of support</h2>
            <p>Small steps towards a little more ease, every day.</p>
          </div>
          <Link className="text-link" href="/services/">
            View all services <span aria-hidden="true">→</span>
          </Link>
        </div>
        <div className="card-grid">
          {home.featured_services.map((service) => (
            <ServiceCard key={service.id} service={service} />
          ))}
        </div>
      </section>
      <section className="community-band">
        <div className="container band-inner">
          <span className="band-symbol" aria-hidden="true">
            ♡
          </span>
          <div>
            <h2>You don’t have to figure it out alone.</h2>
            <p>
              Start a conversation. We’re here to help you find your next step.
            </p>
          </div>
          <Link className="button" href="/contact/">
            Get in touch <span aria-hidden="true">→</span>
          </Link>
        </div>
      </section>
      <section className="section container">
        <div className="section-heading">
          <div>
            <p className="eyebrow">A LITTLE GUIDANCE</p>
            <h2>Good to know</h2>
            <p>Practical reads for caring, wellbeing and everyday life.</p>
          </div>
          <Link className="text-link" href="/articles/">
            All articles <span aria-hidden="true">→</span>
          </Link>
        </div>
        <div className="card-grid">
          {home.latest_articles.map((article) => (
            <ArticleCard key={article.id} article={article} />
          ))}
        </div>
      </section>
    </>
  );
}
