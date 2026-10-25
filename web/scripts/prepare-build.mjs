import { rm } from "node:fs/promises";

// Static prerendering can reuse fetch responses from a previous Next.js build.
// Every CMS rebuild must start with fresh content, including restored CI caches.
// Keep the compiler caches; only discard cached API responses.
const fetchCache = new URL("../.next/cache/fetch-cache/", import.meta.url);
await rm(fetchCache, { recursive: true, force: true });
console.log("Cleared cached API responses before static export.");
