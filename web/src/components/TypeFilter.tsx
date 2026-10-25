import type { ServiceType } from "@/lib/types";
export default function TypeFilter({
  types,
  defaultValue = "",
}: {
  types: ServiceType[];
  defaultValue?: string;
}) {
  return (
    <div className="field">
      <label htmlFor="service-type">Service type</label>
      <select id="service-type" name="type" defaultValue={defaultValue}>
        <option value="">All service types</option>
        {types.map((type) => (
          <option key={type.slug} value={type.slug}>
            {type.name} ({type.count})
          </option>
        ))}
      </select>
    </div>
  );
}
