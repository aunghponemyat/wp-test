import ArticleCard from "@/components/ArticleCard";
import Breadcrumb from "@/components/Breadcrumb";
import { getAllArticles } from "@/lib/api";
import { pageMetadata } from "@/lib/metadata";
export const metadata = pageMetadata(
  "Articles",
  "Practical fictional guides on community care, wellbeing and getting started with support.",
  "/articles/",
);
export default async function Articles() {
  const articles = await getAllArticles();
  return (
    <div className="container section page-section">
      <Breadcrumb items={[{ label: "Articles" }]} />
      <div className="page-heading">
        <p className="eyebrow">INFORMATION THAT HELPS</p>
        <h1>A little guidance goes a long way.</h1>
        <p>Thoughtful reads for caring, wellbeing and everyday life.</p>
      </div>
      <section aria-labelledby="articles-heading">
        <h2 id="articles-heading" className="sr-only">
          Latest articles
        </h2>
        <div className="card-grid">
          {articles.map((article) => (
            <ArticleCard key={article.id} article={article} />
          ))}
        </div>
        {!articles.length && (
          <p>New articles are on their way. Please check back soon.</p>
        )}
      </section>
    </div>
  );
}
