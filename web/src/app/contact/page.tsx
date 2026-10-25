import ContactForm from "@/components/ContactForm";
import Breadcrumb from "@/components/Breadcrumb";
import { pageMetadata } from "@/lib/metadata";
export const metadata = pageMetadata(
  "Contact",
  "Get in touch with the fictional CareConnect team. Share a question about this community care directory demo.",
  "/contact/",
);
export default function Contact() {
  return (
    <div className="container section page-section">
      <Breadcrumb items={[{ label: "Contact" }]} />
      <div className="contact-grid">
        <div className="page-heading">
          <p className="eyebrow">LET’S START A CONVERSATION</p>
          <h1>A helping hand starts with hello.</h1>
          <p>
            Have a question about the directory? Leave a message for our
            fictional community team.
          </p>
          <div className="contact-note">
            <span aria-hidden="true">♡</span>
            <h2>A little care with your details</h2>
            <p>
              This is a demonstration. Please use fictional information and
              avoid sharing personal or medical details.
            </p>
            <p>
              Messages are saved privately. Demo email delivery is best-effort.
            </p>
          </div>
        </div>
        <ContactForm />
      </div>
    </div>
  );
}
