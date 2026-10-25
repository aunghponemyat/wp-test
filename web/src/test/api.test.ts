import { afterEach, expect, it, vi } from "vitest";
import { apiFetch, ApiError } from "@/lib/api";
afterEach(() => vi.unstubAllGlobals());
it("does not retry a missing or unpublished detail", async () => {
  const fetcher = vi.fn(async () => new Response("{}", { status: 404 }));
  vi.stubGlobal("fetch", fetcher);
  await expect(apiFetch("services/draft")).rejects.toBeInstanceOf(ApiError);
  expect(fetcher).toHaveBeenCalledTimes(1);
});
it("includes the failing endpoint when all attempts fail", async () => {
  vi.stubGlobal(
    "fetch",
    vi.fn(async () => {
      throw new Error("offline");
    }),
  );
  await expect(apiFetch("home", { retries: 0 })).rejects.toThrow(
    "https://cms.example.test/wp-json/careconnect/v1/home",
  );
});
