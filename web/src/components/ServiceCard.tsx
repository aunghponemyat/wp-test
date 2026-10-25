import Link from "next/link";
import type { ServiceCardData } from "@/lib/types";
export default function ServiceCard({ service }: { service: ServiceCardData }) {
  return (
    <article className="content-card service-card">
      {service.image && (
        <img
          className="card-image"
          src={service.image.url}
          alt={service.image.alt}
          width={480}
          height={260}
          loading="lazy"
          decoding="async"
        />
      )}
      <div className="card-body">
        <span className="badge">{service.type?.name || "Community care"}</span>
        <h3>
          <Link href={`/services/${service.slug}/`}>{service.name}</Link>
        </h3>
        <p>{service.short_description}</p>
        <Link
          href={`/services/${service.slug}/`}
          className="card-link"
          aria-label={`Explore ${service.name}`}
        >
          Explore service <span aria-hidden="true">↗</span>
        </Link>
      </div>
    </article>
  );
}
