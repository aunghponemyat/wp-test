import Link from "next/link";
import { pageMetadata } from "@/lib/metadata";
export const metadata = pageMetadata(
  "Page not found",
  "This page could not be found. Explore the CareConnect community care directory.",
  "/404/",
);
export default function NotFound() {
  return (
    <div className="container section empty-state">
      <p className="eyebrow">404 · A SMALL DETOUR</p>
      <h1>Let’s help you find your way.</h1>
      <p>
        This page may have moved, or the service may no longer be available.
      </p>
      <Link className="button" href="/services/">
        Explore services <span aria-hidden="true">→</span>
      </Link>
    </div>
  );
}
