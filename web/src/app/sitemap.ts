import type { MetadataRoute } from "next";
import { getAllArticles, getAllServices } from "@/lib/api";
import { SITE_URL } from "@/lib/metadata";
export const dynamic = "force-static";
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const [services, articles] = await Promise.all([
    getAllServices(),
    getAllArticles(),
  ]);
  return [
    "/",
    "/services/",
    "/articles/",
    "/contact/",
    "/privacy/",
    ...services.map((item) => `/services/${item.slug}/`),
    ...articles.map((item) => `/articles/${item.slug}/`),
  ].map((path) => ({ url: `${SITE_URL}${path}` }));
}
