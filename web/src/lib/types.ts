export type Term = { slug: string; name: string };
export type ContentImage = { url: string; alt: string } | null;
export type ServiceCardData = {
  id: number;
  slug: string;
  name: string;
  type: Term;
  short_description: string;
  image: ContentImage;
};
export type ServiceDetail = ServiceCardData & {
  content_html: string;
  eligibility: string;
  address: string;
  phone: string;
  opening_hours: string;
  last_reviewed: string;
  lat: number | null;
  lng: number | null;
  related: ServiceCardData[];
};
export type ArticleCardData = {
  id: number;
  slug: string;
  title: string;
  summary: string;
  date: string;
  category: Term | null;
  image: ContentImage;
};
export type ArticleDetail = ArticleCardData & { content_html: string };
export type Page<T> = {
  items: T[];
  total: number;
  total_pages: number;
  page: number;
  per_page: number;
};
export type ServiceType = Term & { count: number };
export type HomeData = {
  hero: { title: string; subtitle: string };
  featured_services: ServiceCardData[];
  latest_articles: ArticleCardData[];
};
export type ContactInput = {
  name: string;
  email: string;
  message: string;
  website: string;
  ts: number;
};
export type ContactErrors = Partial<
  Record<"name" | "email" | "message", string>
>;
export type ContactResult = {
  ok?: boolean;
  code?: string;
  message?: string;
  errors?: ContactErrors;
};
