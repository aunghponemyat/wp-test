import Breadcrumb from "@/components/Breadcrumb";
import { pageMetadata } from "@/lib/metadata";
export const metadata = pageMetadata(
  "Privacy",
  "How the fictional CareConnect demonstration handles contact messages and protects your details.",
  "/privacy/",
);
export default function Privacy() {
  return (
    <div className="container section page-section">
      <Breadcrumb items={[{ label: "Privacy" }]} />
      <article className="article-detail prose">
        <h1>Your details, treated with care.</h1>
        <p>
          CareConnect is a fictional demonstration. Please use fictional names
          and addresses, and avoid submitting personal or medical information.
        </p>
        <h2>Contact messages</h2>
        <p>
          When you send a message, its name, email and content are saved
          privately in the demo CMS. Only administrators can read these
          messages. A copy may be emailed to the demo contact address.
        </p>
        <h2>Spam prevention</h2>
        <p>
          The server temporarily stores a salted hash of the connection IP
          address for up to one hour to limit repeated messages. The application
          does not store the raw address for this purpose. Hosting providers may
          keep their own security and access logs.
        </p>
        <h2>No tracking</h2>
        <p>
          This site includes no analytics, advertising or tracking scripts.
          Optional maps load from OpenStreetMap when you view a service with a
          location; that provider receives the network information needed to
          serve the map.
        </p>
        <h2>Demo retention</h2>
        <p>
          Messages remain in the demonstration database until the administrator
          deletes them. A production service would publish a retention schedule
          and a verified contact for privacy requests.
        </p>
      </article>
    </div>
  );
}
