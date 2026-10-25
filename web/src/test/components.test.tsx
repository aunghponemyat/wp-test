import React from "react";
import { render, screen, waitFor, fireEvent } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, describe, expect, it, vi } from "vitest";
import Header from "@/components/Header";
import Hero from "@/components/Hero";
import ServiceCard from "@/components/ServiceCard";
import ServicesExplorer from "@/components/ServicesExplorer";
import ContactForm from "@/components/ContactForm";

const { push } = vi.hoisted(() => ({ push: vi.fn() }));
vi.mock("next/navigation", () => ({
  usePathname: () => "/services/",
  useRouter: () => ({ push }),
  useSearchParams: () => new URLSearchParams("q=Sunrise"),
}));
afterEach(() => {
  vi.unstubAllGlobals();
  vi.clearAllMocks();
});
const service = {
  id: 1,
  slug: "sunrise-home-visit-programme",
  name: "Sunrise Home Visit Programme",
  type: { slug: "home-care", name: "Home Care" },
  short_description: "Friendly support at home.",
  image: { url: "/images/seed/home-care.svg", alt: "A welcoming home" },
};

describe("Accessible navigation and content", () => {
  it("announces the mobile menu state and closes after navigation", async () => {
    const user = userEvent.setup();
    render(<Header />);
    const menu = screen.getByRole("button", { name: /menu/i });
    expect(menu).toHaveAttribute("aria-expanded", "false");
    await user.click(menu);
    expect(menu).toHaveAttribute("aria-expanded", "true");
    await user.click(screen.getByRole("link", { name: "Articles" }));
    expect(menu).toHaveAttribute("aria-expanded", "false");
  });
  it("uses a labelled GET search and a single primary heading", () => {
    render(<Hero title="Find support" subtitle="Care closer to home." />);
    expect(screen.getAllByRole("heading", { level: 1 })).toHaveLength(1);
    expect(screen.getByRole("search")).toHaveAttribute("action", "/services/");
    expect(screen.getByLabelText("Search services")).toHaveAttribute(
      "name",
      "q",
    );
  });
  it("renders image alt text and the published service detail link", () => {
    render(<ServiceCard service={service} />);
    expect(screen.getByRole("img")).toHaveAttribute("alt", "A welcoming home");
    expect(screen.getByRole("link", { name: service.name })).toHaveAttribute(
      "href",
      expect.stringMatching(new RegExp(`^/services/${service.slug}/?$`)),
    );
  });
});
describe("Service search", () => {
  it("renders an empty result and submits search through URL state", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(
        async (url: string) =>
          new Response(
            JSON.stringify(
              url.includes("service-types")
                ? []
                : { items: [], total: 0, total_pages: 0, page: 1, per_page: 9 },
            ),
          ),
      ),
    );
    render(<ServicesExplorer />);
    expect(
      await screen.findByRole("heading", {
        name: "No services match your search.",
      }),
    ).toBeVisible();
    fireEvent.change(screen.getByLabelText("Search services"), {
      target: { value: "clinic" },
    });
    fireEvent.submit(screen.getByRole("search"));
    expect(push).toHaveBeenCalledWith("/services/?q=clinic", { scroll: false });
  });
  it("announces a slow server after four seconds", async () => {
    vi.useFakeTimers();
    vi.stubGlobal(
      "fetch",
      vi.fn(() => new Promise(() => {})),
    );
    const view = render(<ServicesExplorer />);
    const { act } = await import("@testing-library/react");
    await act(async () => {
      await vi.advanceTimersByTimeAsync(4001);
    });
    expect(screen.getByRole("status")).toHaveTextContent(
      "Waking up the demo server",
    );
    view.unmount();
    vi.useRealTimers();
  });
});
describe("Contact submission", () => {
  async function fill() {
    const user = userEvent.setup();
    await user.type(screen.getByLabelText("Your name"), "Example Visitor");
    await user.type(
      screen.getByLabelText("Email address"),
      "visitor@example.test",
    );
    await user.type(
      screen.getByLabelText("How can we help?"),
      "A fictional message about community care.",
    );
    return user;
  }
  it("ties server validation errors to their fields", async () => {
    vi.stubGlobal(
      "fetch",
      vi.fn(
        async () =>
          new Response(
            JSON.stringify({
              message: "Please check the highlighted fields.",
              errors: { email: "Enter a valid email address." },
            }),
            { status: 422 },
          ),
      ),
    );
    render(<ContactForm />);
    const user = await fill();
    await user.click(screen.getByRole("button", { name: /send message/i }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Please check");
    expect(screen.getByLabelText("Email address")).toHaveAttribute(
      "aria-invalid",
      "true",
    );
    expect(screen.getByLabelText("Email address")).toHaveAttribute(
      "aria-describedby",
      "email-error",
    );
  });
  it("sends JSON once and announces a successful private save", async () => {
    const fetcher = vi.fn<typeof fetch>(
      async () => new Response(JSON.stringify({ ok: true }), { status: 201 }),
    );
    vi.stubGlobal("fetch", fetcher);
    render(<ContactForm />);
    const user = await fill();
    await user.click(screen.getByRole("button", { name: /send message/i }));
    expect(await screen.findByRole("status")).toHaveTextContent(
      "Your message has been saved",
    );
    expect(fetcher).toHaveBeenCalledTimes(1);
    const body = JSON.parse(String(fetcher.mock.calls[0][1]?.body));
    expect(body.website).toBe("");
    expect(body.ts).toBeGreaterThan(0);
    await waitFor(() =>
      expect(screen.getByLabelText("Your name")).toHaveValue(""),
    );
  });
});
