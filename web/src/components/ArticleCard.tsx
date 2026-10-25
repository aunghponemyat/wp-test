import Link from "next/link";
import type { ArticleCardData } from "@/lib/types";
import { displayDate } from "@/lib/metadata";
export default function ArticleCard({ article }: { article: ArticleCardData }) {
  return (
    <article className="content-card article-card">
      {article.image && (
        <img
          className="card-image"
          src={article.image.url}
          alt={article.image.alt}
          width={480}
          height={260}
          loading="lazy"
          decoding="async"
        />
      )}
      <div className="card-body">
        <p className="article-meta">
          {article.category?.name || "Community"}
          <span aria-hidden="true"> · </span>
          <time dateTime={article.date}>{displayDate(article.date)}</time>
        </p>
        <h3>
          <Link href={`/articles/${article.slug}/`}>{article.title}</Link>
        </h3>
        <p>{article.summary}</p>
        <Link
          className="card-link"
          href={`/articles/${article.slug}/`}
          aria-label={`Read ${article.title}`}
        >
          Read article <span aria-hidden="true">↗</span>
        </Link>
      </div>
    </article>
  );
}
