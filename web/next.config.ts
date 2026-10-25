import type { NextConfig } from "next";

if (!process.env.NEXT_PUBLIC_WP_API_URL) {
  throw new Error(
    "NEXT_PUBLIC_WP_API_URL is required. Copy .env.example to .env.local and set the WordPress API URL.",
  );
}
const api = new URL(process.env.NEXT_PUBLIC_WP_API_URL);
if (!["http:", "https:"].includes(api.protocol))
  throw new Error("NEXT_PUBLIC_WP_API_URL must be an HTTP(S) URL.");

const nextConfig: NextConfig = {
  output: "export",
  trailingSlash: true,
  images: { unoptimized: true },
  poweredByHeader: false,
};
export default nextConfig;
