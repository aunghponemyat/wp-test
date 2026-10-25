import { chromium, expect } from "@playwright/test";
import { readFile, writeFile } from "node:fs/promises";
const artifacts = new URL("../../docs/review/", import.meta.url);
const report = JSON.parse(
  await readFile(new URL("browser-report.json", artifacts), "utf8"),
);
const browser = await chromium.launch({
  executablePath: "/usr/bin/chromium",
  args: ["--no-sandbox", "--disable-dev-shm-usage"],
});
const page = await browser.newPage({ viewport: { width: 360, height: 800 } });
await page.route("**/www.openstreetmap.org/**", (route) =>
  route.fulfill({
    contentType: "text/html",
    body: '<html lang="en"><title>Map offline during local test</title></html>',
  }),
);
report.keyboard = [];
try {
  for (const { path } of report.layouts.filter((item) => item.width === 360)) {
    await page.goto(`http://localhost:3000${path}`);
    if (path === "/services/")
      await expect(page.locator(".service-card")).toHaveCount(9);
    const stops = [];
    let looped = false;
    for (let index = 0; index < 100; index++) {
      await page.keyboard.press("Tab");
      const focused = await page.evaluate(() => {
        const el = document.activeElement;
        const css = getComputedStyle(el);
        return {
          tag: el.tagName,
          id: el.id,
          text: el.textContent.trim().slice(0, 80),
          href: el.getAttribute("href"),
          outline: css.outlineStyle,
          width: parseFloat(css.outlineWidth),
          expanded: el.getAttribute("aria-expanded"),
        };
      });
      if (focused.tag === "BODY") continue;
      const key = JSON.stringify([
        focused.tag,
        focused.id,
        focused.href,
        focused.text,
      ]);
      if (stops.length && key === stops[0]) {
        looped = true;
        break;
      }
      if (focused.tag !== "IFRAME") {
        expect(
          focused.outline,
          `Missing focus ring on ${path}: ${key}`,
        ).not.toBe("none");
        expect(focused.width).toBeGreaterThanOrEqual(3);
      }
      stops.push(key);
      if (
        focused.tag === "BUTTON" &&
        focused.expanded === "false" &&
        focused.text.includes("Menu")
      ) {
        await page.keyboard.press("Enter");
        await expect(
          page.getByRole("button", { name: "Close menu" }),
        ).toHaveAttribute("aria-expanded", "true");
      }
    }
    expect(looped, `Keyboard cycle did not complete: ${path}`).toBe(true);
    report.keyboard.push({
      path,
      focusStops: stops.length,
      visibleFocus: true,
      completedCycle: true,
    });
  }
  for (const width of [360, 1280]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto("http://localhost:3000/");
    await page.locator("main").focus();
    await page.evaluate(async () => {
      for (let y = 0; y < document.body.scrollHeight; y += 600) {
        window.scrollTo(0, y);
        await new Promise((r) => setTimeout(r, 50));
      }
      window.scrollTo(0, 0);
    });
    await page.screenshot({
      path: new URL(`home-${width}.png`, artifacts).pathname,
      fullPage: true,
    });
  }
} finally {
  await writeFile(
    new URL("browser-report.json", artifacts),
    JSON.stringify(report, null, 2) + "\n",
  );
  await browser.close();
}
console.log(
  `Keyboard cycles and visible focus passed on ${report.keyboard.length} pages.`,
);
