type Props = { defaultValue?: string; id?: string };
export default function SearchBox({
  defaultValue = "",
  id = "service-search",
}: Props) {
  return (
    <div className="field search-field">
      <label htmlFor={id}>Search services</label>
      <input
        id={id}
        type="search"
        name="q"
        placeholder="Try home care or health screening"
        maxLength={100}
        defaultValue={defaultValue}
      />
    </div>
  );
}
