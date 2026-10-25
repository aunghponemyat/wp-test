"use client";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
export const navigation = [
  ["/", "Home"],
  ["/services/", "Services"],
  ["/articles/", "Articles"],
  ["/contact/", "Contact"],
] as const;
export default function Header() {
  const [open, setOpen] = useState(false);
  const pathname = usePathname();
  return (
    <header className="site-header">
      <div className="container header-inner">
        <Link href="/" className="brand" aria-label="CareConnect home">
          <span className="brand-mark" aria-hidden="true">
            ♡
          </span>
          Care<span>Connect</span>
        </Link>
        <button
          className="menu-button"
          aria-controls="main-navigation"
          aria-expanded={open}
          onClick={() => setOpen(!open)}
        >
          {open ? "Close menu" : "Menu"}
          <span aria-hidden="true">{open ? "×" : "☰"}</span>
        </button>
        <nav id="main-navigation" aria-label="Main navigation" data-open={open}>
          {navigation.map(([href, label]) => (
            <Link
              key={href}
              href={href}
              onClick={() => setOpen(false)}
              aria-current={
                pathname === href || (href !== "/" && pathname.startsWith(href))
                  ? "page"
                  : undefined
              }
            >
              {label}
            </Link>
          ))}
        </nav>
      </div>
    </header>
  );
}
