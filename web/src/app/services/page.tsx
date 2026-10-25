import { Suspense } from "react";
import ServicesExplorer from "@/components/ServicesExplorer";
import { pageMetadata } from "@/lib/metadata";
import Breadcrumb from "@/components/Breadcrumb";
export const metadata = pageMetadata(
  "Services",
  "Browse fictional home care, community clinics and senior activities. Search for support that suits your needs.",
  "/services/",
);
export default function ServicesPage() {
  return (
    <div className="container section page-section">
      <Breadcrumb items={[{ label: "Services" }]} />
      <div className="page-heading">
        <p className="eyebrow">SUPPORT FOR EVERYDAY LIFE</p>
        <h1>Find the right support</h1>
        <p>Explore care and community services, at your own pace.</p>
      </div>
      <Suspense fallback={<p role="status">Loading service search…</p>}>
        <ServicesExplorer />
      </Suspense>
    </div>
  );
}
