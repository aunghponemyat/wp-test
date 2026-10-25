import ServiceCard from "@/components/ServiceCard";
import Breadcrumb from "@/components/Breadcrumb";
import DetailsPanel from "@/components/DetailsPanel";
import { getAllServices, getService } from "@/lib/api";
import { pageMetadata } from "@/lib/metadata";
export const dynamicParams = false;
export async function generateStaticParams() {
  return (await getAllServices()).map(({ slug }) => ({ slug }));
}
type Props = { params: Promise<{ slug: string }> };
export async function generateMetadata({ params }: Props) {
  const service = await getService((await params).slug);
  return pageMetadata(
    service.name,
    service.short_description,
    `/services/${service.slug}/`,
  );
}
export default async function ServicePage({ params }: Props) {
  const service = await getService((await params).slug);
  const hasMap = service.lat !== null && service.lng !== null;
  const lat = service.lat ?? 0,
    lng = service.lng ?? 0;
  const mapQuery = new URLSearchParams({
    bbox: `${lng - 0.008},${lat - 0.006},${lng + 0.008},${lat + 0.006}`,
    layer: "mapnik",
    marker: `${lat},${lng}`,
  });
  return (
    <div className="container section page-section">
      <Breadcrumb
        items={[
          { label: "Services", href: "/services/" },
          { label: service.name },
        ]}
      />
      <div className="detail-heading">
        <span className="badge">{service.type?.name}</span>
        <h1>{service.name}</h1>
        <p className="lead">{service.short_description}</p>
      </div>
      <div className="detail-grid">
        <div>
          {service.image && (
            <img
              className="detail-image"
              src={service.image.url}
              alt={service.image.alt}
              width={800}
              height={440}
              decoding="async"
              fetchPriority="high"
            />
          )}
          <section className="prose">
            <h2>About this service</h2>
            <div dangerouslySetInnerHTML={{ __html: service.content_html }} />
          </section>
          {service.eligibility && (
            <section className="prose">
              <h2>Who it’s for</h2>
              <p>{service.eligibility}</p>
            </section>
          )}
          {hasMap && (
            <section className="map-section">
              <h2>Find this service</h2>
              <iframe
                src={`https://www.openstreetmap.org/export/embed.html?${mapQuery}`}
                title={`Map showing ${service.name}`}
                loading="lazy"
                width="640"
                height="300"
              />
              <a
                href={`https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}#map=16/${lat}/${lng}`}
                target="_blank"
                rel="noopener noreferrer"
              >
                View larger map
              </a>
              <p className="small">
                Illustrative location for this fictional service.
              </p>
            </section>
          )}
        </div>
        <DetailsPanel service={service} />
      </div>
      {service.related.length > 0 && (
        <section className="section related-section">
          <div className="section-heading">
            <div>
              <p className="eyebrow">MORE WAYS TO FIND SUPPORT</p>
              <h2>Related services</h2>
            </div>
          </div>
          <div className="card-grid">
            {service.related.map((item) => (
              <ServiceCard key={item.id} service={item} />
            ))}
          </div>
        </section>
      )}
    </div>
  );
}
