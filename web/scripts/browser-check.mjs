// Run against the local static preview and disposable WordPress demo.
import { chromium, expect } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";
import { mkdir, readFile, writeFile } from "node:fs/promises";
const site = "http://localhost:3000";
const cms = "http://localhost:8080";
const api = `${cms}/wp-json/careconnect/v1`;
const artifacts = new URL("../../docs/review/", import.meta.url);
await mkdir(artifacts, { recursive: true });
const browser = await chromium.launch({
  executablePath: "/usr/bin/chromium",
  args: ["--no-sandbox", "--disable-dev-shm-usage"],
});
const context = await browser.newContext({
  viewport: { width: 360, height: 800 },
});
const page = await context.newPage();
const failures = [];
page.on("pageerror", (error) => failures.push(error.message));
await page.route("**/www.openstreetmap.org/**", (route) =>
  route.fulfill({
    body: '<html lang="en"><title>Map offline during local testing</title></html>',
    contentType: "text/html",
  }),
);
let createdId;
const report = {
  layouts: [],
  accessibility: [],
  workflows: [],
  pageErrors: failures,
};
try {
  const services = await (await fetch(`${api}/services`)).json();
  const articles = await (await fetch(`${api}/articles`)).json();
  expect(services.total).toBe(9);
  expect(articles.total).toBe(4);
  const routes = [
    "/",
    "/services/",
    "/articles/",
    "/contact/",
    "/privacy/",
    ...services.items.map((item) => `/services/${item.slug}/`),
    ...articles.items.map((item) => `/articles/${item.slug}/`),
  ];
  if (process.argv.includes("--layout-only")) {
    const previous = JSON.parse(
      await readFile(new URL("browser-report.json", artifacts), "utf8"),
    );
    report.workflows = previous.workflows;
  }
  if (process.argv.includes("--workflows-only")) {
    const previous = JSON.parse(
      await readFile(new URL("browser-report.json", artifacts), "utf8"),
    );
    report.layouts = previous.layouts;
    report.accessibility = previous.accessibility;
  }
  for (const width of process.argv.includes("--workflows-only")
    ? []
    : [360, 1280]) {
    await page.setViewportSize({ width, height: 900 });
    for (const path of routes) {
      const response = await page.goto(site + path);
      expect(response.status()).toBe(200);
      if (path === "/services/")
        await expect(
          page.getByRole("heading", { name: "9 services to explore" }),
        ).toBeVisible();
      expect(await page.locator("h1").count()).toBe(1);
      const overflow = await page.evaluate(
        () => document.documentElement.scrollWidth > window.innerWidth,
      );
      expect(overflow, `${width}px overflow on ${path}`).toBe(false);
      const result = await new AxeBuilder({ page })
        .withTags(["wcag2a", "wcag2aa", "wcag21aa"])
        .analyze();
      report.accessibility.push({
        width,
        path,
        violations: result.violations.map((v) => ({
          id: v.id,
          impact: v.impact,
          nodes: v.nodes.map((n) => n.target),
        })),
      });
      expect(
        result.violations,
        `Accessibility violations on ${path} at ${width}px`,
      ).toEqual([]);
      report.layouts.push({ width, path, horizontalOverflow: overflow });
      if (path === "/") {
        await page.locator("main").focus();
        await page.evaluate(async () => {
          for (let y = 0; y < document.body.scrollHeight; y += 600) {
            window.scrollTo(0, y);
            await new Promise((resolve) => setTimeout(resolve, 50));
          }
          window.scrollTo(0, 0);
        });
        await page.screenshot({
          path: new URL(`home-${width}.png`, artifacts).pathname,
          fullPage: true,
        });
      }
    }
  }
  if (process.argv.includes("--layout-only")) {
    console.log(
      `Layout and accessibility checks passed: ${report.layouts.length}`,
    );
  } else {
    await page.setViewportSize({ width: 360, height: 800 });
    await page.goto(site);
    await page.keyboard.press("Tab");
    await expect(
      page.getByRole("link", { name: "Skip to content" }),
    ).toBeFocused();
    await page.keyboard.press("Enter");
    await expect(page.locator("main")).toBeFocused();
    await page.getByRole("button", { name: "Menu", exact: true }).click();
    await expect(
      page.getByRole("button", { name: "Close menu" }),
    ).toHaveAttribute("aria-expanded", "true");
    await page
      .getByRole("navigation", { name: "Main navigation" })
      .getByRole("link", { name: "Services" })
      .click();
    await expect(
      page.getByRole("heading", { name: "9 services to explore" }),
    ).toBeVisible();
    await page.getByLabel("Search services").fill("Sunrise");
    await page.getByRole("button", { name: "Search", exact: true }).click();
    await expect(
      page.getByRole("heading", { name: "1 service to explore" }),
    ).toBeVisible();
    await page
      .getByLabel("Service type", { exact: true })
      .selectOption("community-clinic");
    await page.getByRole("button", { name: "Search", exact: true }).click();
    await expect(
      page.getByRole("heading", { name: "No services match your search." }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Clear filters" }).click();
    await expect(
      page.getByRole("heading", { name: "9 services to explore" }),
    ).toBeVisible();
    report.workflows.push(
      "Keyboard skip link, mobile navigation, search, type filter and empty state",
    );
    // Actual HTTP preflight and disallowed-origin checks.
    const preflight = await fetch(`${api}/contact`, {
      method: "OPTIONS",
      headers: {
        Origin: site,
        "Access-Control-Request-Method": "POST",
        "Access-Control-Request-Headers": "Content-Type",
      },
    });
    expect(preflight.headers.get("access-control-allow-origin")).toBe(site);
    expect(
      preflight.headers.get("access-control-allow-credentials"),
    ).toBeNull();
    expect(
      (
        await fetch(`${api}/services`, {
          headers: { Origin: "https://untrusted.example.test" },
        })
      ).status,
    ).toBe(403);
    report.workflows.push(
      "Exact-origin CORS preflight and forbidden-origin rejection",
    );
    // Verify the shortcode against the real endpoint.
    await page.goto(`${cms}/wp-services-demo/`);
    await expect(page.locator(".ccs-results article")).toHaveCount(9);
    await page.getByLabel("Search services").fill("Sunrise");
    await page.getByRole("button", { name: "Search", exact: true }).click();
    await expect(page.locator(".ccs-results article")).toHaveCount(1);
    await page
      .getByLabel("Service type", { exact: true })
      .selectOption("community-clinic");
    await page.getByRole("button", { name: "Search", exact: true }).click();
    await expect(page.locator(".ccs-results")).toHaveText(
      "No services match your search.",
    );
    report.workflows.push("WordPress shortcode search, type and empty state");
    // A real contact POST validates the cross-origin form and private-storage path.
    await page.goto(`${site}/contact/`);
    await page.getByLabel("Your name").fill("Example Visitor");
    await page.getByLabel("Email address").fill("visitor@example.test");
    await page
      .getByLabel("How can we help?")
      .fill("A fictional browser smoke-test message for this demonstration.");
    await page.waitForTimeout(3100);
    await page.getByRole("button", { name: "Send message" }).click();
    await expect(page.getByRole("status")).toContainText(
      "Your message has been saved",
    );
    report.workflows.push("Contact JSON POST with success announcement");
    // The Editor submits invalid content, then valid content for review; a Publisher publishes it.
    const editorContext = await browser.newContext();
    const editor = await editorContext.newPage();
    await editor.goto(`${cms}/wp-login.php`);
    await editor.locator("#user_login").fill("demo_editor");
    await editor.locator("#user_pass").fill("local-demo-only");
    await editor.locator("#wp-submit").click();
    await editor.goto(`${cms}/wp-admin/post-new.php?post_type=cc_service`);
    await expect(editor.locator("#publish")).toHaveValue("Submit for Review");
    await editor.locator("#title").fill("Example Browser Review Service");
    await editor.locator("#content-html").click();
    await editor
      .locator("#content")
      .fill("A fictional service used to verify the publishing workflow.");
    const initialDraftId = await editor.locator("#post_ID").inputValue();
    await editor.locator("#save-post").click();
    await editor.waitForTimeout(1000);
    await editor.goto(
      `${cms}/wp-admin/post.php?post=${initialDraftId}&action=edit`,
    );
    await editor.locator("#publish").click();
    await expect(editor.locator(".notice-error")).toContainText(
      "Content kept as a draft",
    );
    createdId = await editor.locator("#post_ID").inputValue();
    await editor.locator("#ccs_term").selectOption({ label: "Home Care" });
    await editor
      .locator("#ccs_short_description")
      .fill("A fictional workflow verification service.");
    await editor
      .locator("#ccs_address")
      .fill("10 Example Lane, Singapore 000010");
    await editor.locator("#ccs_phone").fill("65550110");
    await editor.locator("#ccs_last_reviewed").fill("2026-10-01");
    await editor.locator("#publish").click();
    expect(
      (await fetch(`${api}/services/example-browser-review-service`)).status,
    ).toBe(404);
    await editorContext.close();
    const publisherContext = await browser.newContext();
    const publisher = await publisherContext.newPage();
    await publisher.goto(`${cms}/wp-login.php`);
    await publisher.locator("#user_login").fill("demo_publisher");
    await publisher.locator("#user_pass").fill("local-demo-only");
    await publisher.locator("#wp-submit").click();
    await publisher.goto(
      `${cms}/wp-admin/post.php?post=${createdId}&action=edit`,
    );
    const takeover = publisher.getByRole("link", { name: /take over/i });
    if (await takeover.isVisible()) await takeover.click();
    await expect(publisher.locator("#publish")).toHaveValue("Publish");
    await publisher.locator("#publish").click();
    await expect(publisher.locator("#message")).toContainText("published");
    expect(
      (await fetch(`${api}/services/example-browser-review-service`)).status,
    ).toBe(200);
    // Ten published records make the actual default page size exercise both UIs.
    await page.goto(`${site}/services/`);
    await expect(
      page.getByRole("heading", { name: "10 services to explore" }),
    ).toBeVisible();
    await page.getByRole("button", { name: "Next", exact: true }).click();
    await expect(
      page.getByRole("navigation", { name: "Services pagination" }),
    ).toContainText("Page 2 of 2");
    await expect(page.locator(".service-card")).toHaveCount(1);
    await page.goto(`${cms}/wp-services-demo/`);
    await expect(page.locator(".ccs-results article")).toHaveCount(9);
    await page.getByRole("button", { name: "Next", exact: true }).click();
    await expect(page.locator(".ccs-results article")).toHaveCount(1);
    await publisher.goto(`${cms}/wp-admin/edit.php?post_type=cc_service`);
    await expect(publisher.locator(".ccs-overdue")).toHaveCount(2);
    await publisher.locator("#ccs_review").selectOption("overdue");
    await publisher.locator("#post-query-submit").click();
    await expect(publisher.locator("#the-list tr")).toHaveCount(2);
    await publisher.goto(
      `${cms}/wp-admin/post.php?post=${createdId}&action=edit`,
    );
    await publisher.locator("#delete-action a").click();
    expect(
      (await fetch(`${api}/services/example-browser-review-service`)).status,
    ).toBe(404);
    await publisherContext.close();
    report.workflows.push(
      "Editor draft validation → Pending Review → Publisher publish → unpublish; live paging in both directories",
    );
    await page.goto(`${site}/services/`);
    await page.route("**/wp-json/careconnect/v1/services?*", async (route) => {
      await new Promise((resolve) => setTimeout(resolve, 5000));
      await route.continue();
    });
    await page.reload();
    await expect(page.getByRole("status")).toContainText(
      "Waking up the demo server",
      { timeout: 4800 },
    );
    await expect(
      page.getByRole("heading", { name: "9 services to explore" }),
    ).toBeVisible({ timeout: 12000 });
    report.workflows.push("Cold-start hint after four seconds and recovery");
  }
  expect(failures).toEqual([]);
} finally {
  report.createdServiceId = createdId;
  await writeFile(
    new URL("browser-report.json", artifacts),
    JSON.stringify(report, null, 2) + "\n",
  );
  await browser.close();
}
console.log(
  JSON.stringify(
    {
      pagesChecked: report.layouts.length,
      accessibilityScans: report.accessibility.length,
      workflows: report.workflows,
      pageErrors: failures,
      createdServiceId: createdId,
    },
    null,
    2,
  ),
);
