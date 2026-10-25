"use client";
import { useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { apiFetch } from "@/lib/api";
import type { Page, ServiceCardData, ServiceType } from "@/lib/types";
import ServiceCard from "./ServiceCard";
import SearchBox from "./SearchBox";
import TypeFilter from "./TypeFilter";
export default function ServicesExplorer() {
  const params = useSearchParams();
  const router = useRouter();
  const q = params.get("q")?.slice(0, 100) || "";
  const type = params.get("type") || "";
  const page = Math.max(1, Number.parseInt(params.get("page") || "1", 10) || 1);
  const [types, setTypes] = useState<ServiceType[]>([]);
  const [data, setData] = useState<Page<ServiceCardData> | null>(null);
  const [status, setStatus] = useState("Loading services…");
  const [error, setError] = useState(false);
  const [retry, setRetry] = useState(0);
  useEffect(() => {
    const controller = new AbortController();
    const timer = setTimeout(
      () =>
        setStatus("Waking up the demo server… This can take a little while."),
      4000,
    );
    const query = new URLSearchParams({
      search: q,
      type,
      page: String(page),
      per_page: "9",
    });
    Promise.all([
      apiFetch<Page<ServiceCardData>>(`services?${query}`, {
        signal: controller.signal,
      }),
      apiFetch<ServiceType[]>("service-types", { signal: controller.signal }),
    ])
      .then(([result, serviceTypes]) => {
        if (!controller.signal.aborted) {
          setData(result);
          setTypes(serviceTypes);
          setError(false);
          setStatus("");
        }
      })
      .catch(() => {
        if (!controller.signal.aborted) {
          setData(null);
          setError(true);
          setStatus("We couldn’t load the services. Please try again.");
        }
      })
      .finally(() => clearTimeout(timer));
    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [q, type, page, retry]);
  function navigate(nextPage: number, nextQ = q, nextType = type) {
    const query = new URLSearchParams();
    if (nextQ) query.set("q", nextQ);
    if (nextType) query.set("type", nextType);
    if (nextPage > 1) query.set("page", String(nextPage));
    setData(null);
    setError(false);
    setStatus("Loading services…");
    router.push(`/services/${query.size ? `?${query}` : ""}`, {
      scroll: false,
    });
    if (nextPage === page && nextQ === q && nextType === type)
      setRetry((value) => value + 1);
  }
  return (
    <>
      <form
        role="search"
        className="filter-panel"
        onSubmit={(event) => {
          event.preventDefault();
          const values = new FormData(event.currentTarget);
          navigate(
            1,
            String(values.get("q") || "").trim(),
            String(values.get("type") || ""),
          );
        }}
      >
        <SearchBox key={q} defaultValue={q} />
        <TypeFilter
          key={`${type}-${types.length}`}
          defaultValue={type}
          types={types}
        />
        <button className="button" type="submit">
          Search <span aria-hidden="true">→</span>
        </button>
      </form>
      <p className="demo-hint">
        Demo server: the first search may take a moment while it wakes up.
      </p>
      <div aria-live="polite" aria-busy={!!status && !error}>
        {status && (
          <div
            className={error ? "notice error-notice" : "notice"}
            role={error ? "alert" : "status"}
          >
            {status}
            {error && (
              <button
                className="button"
                onClick={() => {
                  setError(false);
                  setStatus("Loading services…");
                  setRetry((value) => value + 1);
                }}
              >
                Try again
              </button>
            )}
          </div>
        )}
        {data && (
          <>
            <div className="results-heading">
              <h2>
                {data.total
                  ? `${data.total} service${data.total === 1 ? "" : "s"} to explore`
                  : "Search results"}
              </h2>
              <span>{q && `Results for “${q}”`}</span>
            </div>
            {data.items.length ? (
              <div className="card-grid">
                {data.items.map((service) => (
                  <ServiceCard key={service.id} service={service} />
                ))}
              </div>
            ) : (
              <div className="empty-state">
                <span aria-hidden="true">⌕</span>
                <h3>No services match your search.</h3>
                <p>Try a different keyword or select all service types.</p>
                <button className="button" onClick={() => navigate(1, "", "")}>
                  Clear filters
                </button>
              </div>
            )}
            {data.total_pages > 1 && (
              <nav className="pagination" aria-label="Services pagination">
                <button
                  className="button button-outline"
                  disabled={page <= 1}
                  onClick={() => navigate(page - 1)}
                >
                  Previous
                </button>
                <span>
                  Page {page} of {data.total_pages}
                </span>
                <button
                  className="button button-outline"
                  disabled={page >= data.total_pages}
                  onClick={() => navigate(page + 1)}
                >
                  Next
                </button>
              </nav>
            )}
          </>
        )}
      </div>
    </>
  );
}
