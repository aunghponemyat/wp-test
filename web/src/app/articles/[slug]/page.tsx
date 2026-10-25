import Breadcrumb from "@/components/Breadcrumb";
import { getAllArticles, getArticle } from "@/lib/api";
import { displayDate, pageMetadata } from "@/lib/metadata";
export const dynamicParams = false;
export async function generateStaticParams() {
  return (await getAllArticles()).map(({ slug }) => ({ slug }));
}
type Props = { params: Promise<{ slug: string }> };
export async function generateMetadata({ params }: Props) {
  const article = await getArticle((await params).slug);
  return pageMetadata(
    article.title,
    article.summary,
    `/articles/${article.slug}/`,
  );
}
export default async function Article({ params }: Props) {
  const article = await getArticle((await params).slug);
  return (
    <div className="container section page-section">
      <Breadcrumb
        items={[
          { label: "Articles", href: "/articles/" },
          { label: article.title },
        ]}
      />
      <article className="article-detail">
        <header>
          <p className="eyebrow">
            {article.category?.name || "Community care"}
          </p>
          <h1>{article.title}</h1>
          <p className="lead">{article.summary}</p>
          <p className="article-date">
            Published{" "}
            <time dateTime={article.date}>{displayDate(article.date)}</time>
          </p>
        </header>
        {article.image && (
          <img
            className="detail-image"
            src={article.image.url}
            alt={article.image.alt}
            width={800}
            height={440}
            decoding="async"
            fetchPriority="high"
          />
        )}
        <div
          className="prose"
          dangerouslySetInnerHTML={{ __html: article.content_html }}
        />
        <aside className="notice">
          This article is fictional and offers general information. Speak with a
          qualified professional about your individual needs.
        </aside>
      </article>
    </div>
  );
}
