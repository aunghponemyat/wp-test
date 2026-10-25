import type {
  ArticleCardData,
  ArticleDetail,
  HomeData,
  Page,
  ServiceCardData,
  ServiceDetail,
} from "./types";

export const API_URL =
  process.env.NEXT_PUBLIC_WP_API_URL?.replace(/\/$/, "") ?? "";
export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
  }
}
const pause = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

/** Shared bounded transport. A build gets six retries to wake a sleeping Render instance. */
export async function apiFetch<T>(
  path: string,
  options: { signal?: AbortSignal; retries?: number } = {},
): Promise<T> {
  if (!API_URL)
    throw new Error(
      "NEXT_PUBLIC_WP_API_URL is required to fetch CareConnect content.",
    );
  const url = `${API_URL}/${path.replace(/^\//, "")}`;
  const browser = typeof window !== "undefined";
  const retries = options.retries ?? (browser ? 2 : 6);
  let lastError: unknown;
  for (let attempt = 0; attempt <= retries; attempt++) {
    try {
      options.signal?.throwIfAborted();
      const signal = options.signal
        ? AbortSignal.any([options.signal, AbortSignal.timeout(30000)])
        : AbortSignal.timeout(30000);
      const response = await fetch(url, { signal });
      if (!response.ok)
        throw new ApiError(
          `Request failed (${response.status}): ${url}`,
          response.status,
        );
      return (await response.json()) as T;
    } catch (error) {
      lastError = error;
      if (
        options.signal?.aborted ||
        (error instanceof ApiError &&
          error.status < 500 &&
          error.status !== 429)
      )
        throw error;
      if (attempt < retries) await pause(browser ? 1000 : 10000);
    }
  }
  throw new Error(
    `Unable to fetch ${url} after ${retries + 1} attempts. Check that WordPress is reachable.`,
    { cause: lastError },
  );
}
export const getHome = () => apiFetch<HomeData>("home");
export const getService = (slug: string) =>
  apiFetch<ServiceDetail>(`services/${encodeURIComponent(slug)}`);
export const getArticle = (slug: string) =>
  apiFetch<ArticleDetail>(`articles/${encodeURIComponent(slug)}`);
async function allPages<T>(route: string): Promise<T[]> {
  const first = await apiFetch<Page<T>>(`${route}?per_page=50`);
  const items = [...first.items];
  for (let page = 2; page <= first.total_pages; page++) {
    items.push(
      ...(await apiFetch<Page<T>>(`${route}?per_page=50&page=${page}`)).items,
    );
  }
  return items;
}
export const getAllServices = () => allPages<ServiceCardData>("services");
export const getAllArticles = () => allPages<ArticleCardData>("articles");
