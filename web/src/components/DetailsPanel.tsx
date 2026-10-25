import type { ServiceDetail } from "@/lib/types";
import { displayDate } from "@/lib/metadata";
export default function DetailsPanel({ service }: { service: ServiceDetail }) {
  return (
    <aside className="details-panel" aria-labelledby="details-title">
      <h2 id="details-title">Plan your visit</h2>
      <dl>
        <dt>Address</dt>
        <dd>{service.address}</dd>
        <dt>Phone</dt>
        <dd>
          <a href={`tel:${service.phone.replace(/\s/g, "")}`}>
            {service.phone}
          </a>
        </dd>
        {service.opening_hours && (
          <>
            <dt>Opening hours</dt>
            <dd className="preserve-lines">{service.opening_hours}</dd>
          </>
        )}
        <dt>Last reviewed</dt>
        <dd>
          <time dateTime={service.last_reviewed}>
            {displayDate(service.last_reviewed)}
          </time>
        </dd>
      </dl>
      <p className="small">
        This is a fictional service. Please use this directory for demonstration
        only.
      </p>
    </aside>
  );
}
