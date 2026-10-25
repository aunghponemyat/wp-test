/* global ccsServices */
"use strict";
for (const directory of document.querySelectorAll(".ccs-directory")) {
  const form = directory.querySelector("form");
  const results = directory.querySelector(".ccs-results");
  const paging = directory.querySelector(".ccs-pagination");
  const select = form.querySelector("select");
  let controller;
  let page = 1;
  let query = { search: "", type: "" };
  function element(tag, text) {
    const node = document.createElement(tag);
    node.textContent = text;
    return node;
  }
  async function request(path, signal) {
    for (let attempt = 0; attempt < 3; attempt++) {
      try {
        const response = await fetch(ccsServices.api + path, {
          signal: AbortSignal.any([signal, AbortSignal.timeout(30000)]),
        });
        if (!response.ok) {
          const error = new Error(ccsServices.error);
          error.retryable = response.status >= 500 || response.status === 429;
          throw error;
        }
        return await response.json();
      } catch (error) {
        if (signal.aborted || error.retryable === false || attempt === 2)
          throw error;
        await new Promise((resolve) => setTimeout(resolve, 1000));
      }
    }
  }
  async function load() {
    controller?.abort();
    const current = new AbortController();
    controller = current;
    results.textContent = ccsServices.loading;
    results.setAttribute("aria-busy", "true");
    paging.replaceChildren();
    const timer = setTimeout(() => {
      results.textContent = ccsServices.waking;
    }, 4000);
    try {
      const data = await request(
        `services?${new URLSearchParams({ ...query, page, per_page: 9 })}`,
        current.signal,
      );
      if (current.signal.aborted) return;
      results.replaceChildren();
      if (!data.items.length) results.append(element("p", ccsServices.empty));
      for (const item of data.items) {
        const card = document.createElement("article");
        const title = element("h2", item.name);
        if (ccsServices.frontend) {
          const link = element("a", item.name);
          link.href = `${ccsServices.frontend.replace(/\/$/, "")}/services/${encodeURIComponent(item.slug)}/`;
          title.replaceChildren(link);
        }
        card.append(
          title,
          element("p", item.type?.name || ""),
          element("p", item.short_description),
        );
        results.append(card);
      }
      if (data.total_pages > 1) {
        for (const [label, target, disabled] of [
          [ccsServices.previous, page - 1, page === 1],
          [ccsServices.next, page + 1, page >= data.total_pages],
        ]) {
          const button = element("button", label);
          button.type = "button";
          button.disabled = disabled;
          button.addEventListener("click", () => {
            page = target;
            load();
          });
          paging.append(button);
        }
        paging.append(
          element("p", `${ccsServices.page} ${page} / ${data.total_pages}`),
        );
      }
    } catch {
      if (!current.signal.aborted) {
        results.textContent = ccsServices.error;
        const retry = element("button", ccsServices.next);
        retry.textContent = form.querySelector("button").textContent;
        retry.addEventListener("click", load);
        results.append(retry);
      }
    } finally {
      clearTimeout(timer);
      if (!current.signal.aborted) results.removeAttribute("aria-busy");
    }
  }
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    query = { search: form.elements.search.value.trim(), type: select.value };
    page = 1;
    load();
  });
  const typeController = new AbortController();
  request("service-types", typeController.signal)
    .then((types) => {
      for (const type of types) {
        const option = element("option", type.name);
        option.value = type.slug;
        select.append(option);
      }
    })
    .catch(() => {
      select.disabled = true;
    });
  load();
}
