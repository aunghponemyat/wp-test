"use client";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { API_URL } from "@/lib/api";
import type { ContactErrors, ContactResult } from "@/lib/types";
import FormField from "./FormField";
export default function ContactForm() {
  const ts = useRef(0);
  const form = useRef<HTMLFormElement>(null);
  const [errors, setErrors] = useState<ContactErrors>({});
  const [sending, setSending] = useState(false);
  const [status, setStatus] = useState("");
  const [success, setSuccess] = useState(false);
  const [waking, setWaking] = useState(false);
  useEffect(() => {
    ts.current = Date.now();
    const input = form.current?.elements.namedItem(
      "ts",
    ) as HTMLInputElement | null;
    if (input) input.value = String(ts.current);
  }, []);
  async function submit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (sending) return;
    const data = new FormData(event.currentTarget);
    setSending(true);
    setErrors({});
    setStatus("");
    setSuccess(false);
    const timer = setTimeout(() => setWaking(true), 4000);
    try {
      // A POST is sent once: automatic retries could save duplicate messages.
      const response = await fetch(`${API_URL}/contact`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        signal: AbortSignal.timeout(90000),
        body: JSON.stringify({
          name: data.get("name"),
          email: data.get("email"),
          message: data.get("message"),
          website: data.get("website"),
          ts: ts.current,
        }),
      });
      const result = (await response.json()) as ContactResult;
      if (!response.ok || !result.ok) {
        setErrors(result.errors || {});
        setStatus(
          result.message || "We couldn’t send your message. Please try again.",
        );
        if (result.errors) {
          const first = Object.keys(result.errors)[0];
          setTimeout(() => document.getElementById(first)?.focus(), 0);
        }
      } else {
        setSuccess(true);
        setStatus(
          "Thank you. Your message has been saved, and the demo team has been notified where email is available.",
        );
        form.current?.reset();
        ts.current = Date.now();
      }
    } catch {
      setStatus(
        "We couldn’t confirm your message was sent. The demo server may still be waking up. Please wait a moment before trying again.",
      );
    } finally {
      clearTimeout(timer);
      setWaking(false);
      setSending(false);
    }
  }
  const attributes = (id: keyof ContactErrors) => ({
    "aria-invalid": !!errors[id],
    "aria-describedby": errors[id] ? `${id}-error` : undefined,
  });
  return (
    <form ref={form} className="contact-form" onSubmit={submit}>
      <h2>Send a message</h2>
      <p className="small">All fields are required.</p>
      <FormField id="name" label="Your name" error={errors.name}>
        <input
          id="name"
          name="name"
          required
          maxLength={100}
          autoComplete="name"
          {...attributes("name")}
        />
      </FormField>
      <FormField id="email" label="Email address" error={errors.email}>
        <input
          id="email"
          name="email"
          type="email"
          required
          autoComplete="email"
          {...attributes("email")}
        />
      </FormField>
      <FormField id="message" label="How can we help?" error={errors.message}>
        <textarea
          id="message"
          name="message"
          required
          minLength={10}
          maxLength={2000}
          rows={6}
          {...attributes("message")}
        />
      </FormField>
      <div className="honeypot" aria-hidden="true">
        <label htmlFor="website">Website</label>
        <input id="website" name="website" tabIndex={-1} autoComplete="off" />
      </div>
      <input type="hidden" name="ts" defaultValue="" />
      <p className="small">
        Your message is stored privately. Read our{" "}
        <Link href="/privacy/">privacy note</Link>.
      </p>
      <button className="button" type="submit" disabled={sending}>
        {sending ? "Sending…" : "Send message"}
        <span aria-hidden="true">→</span>
      </button>
      {waking && (
        <p role="status">
          Waking up the demo server… This can take a little while.
        </p>
      )}
      {status && (
        <div
          className={`notice ${success ? "success-notice" : "error-notice"}`}
          role={success ? "status" : "alert"}
        >
          {status}
        </div>
      )}
    </form>
  );
}
