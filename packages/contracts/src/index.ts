export const BOOKING_STATUSES = [
  "PENDING_PARTNER_APPROVAL",
  "CONFIRMED",
  "REJECTED_BY_PARTNER",
  "EXPIRED",
  "CANCELED_BY_CUSTOMER",
  "CANCELED_BY_PARTNER",
  "IN_PROGRESS",
  "COMPLETED",
  "NO_SHOW",
  "RESCHEDULED",
] as const;

export type BookingStatus = (typeof BOOKING_STATUSES)[number];

export type UserRole = "CUSTOMER" | "PARTNER_OWNER" | "PARTNER_STAFF" | "ADMIN";

export interface ServiceSummary {
  id: string;
  establishmentId: string;
  name: string;
  durationMinutes: number;
  priceInCents: number;
}

