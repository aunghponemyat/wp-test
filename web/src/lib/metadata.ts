import type { Metadata } from "next";
export const SITE_URL = (
  process.env.NEXT_PUBLIC_SITE_URL || "http://localhost:3000"
).replace(/\/$/, "");
export function pageMetadata(
  title: string,
  description: string,
  path: string,
): Metadata {
  const url = `${SITE_URL}${path}`;
  return {
    title: `${title} | CareConnect`,
    description,
    alternates: { canonical: url },
    openGraph: {
      title: `${title} | CareConnect`,
      description,
      url,
      siteName: "CareConnect",
      type: "website",
      locale: "en_SG",
    },
  };
}
export function displayDate(value: string): string {
  return new Intl.DateTimeFormat("en-SG", {
    day: "numeric",
    month: "long",
    year: "numeric",
    timeZone: "UTC",
  }).format(new Date(`${value}T00:00:00Z`));
}
