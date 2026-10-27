CREATE TABLE IF NOT EXISTS users (
 id TEXT PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, phone TEXT UNIQUE,
 password_hash TEXT, google_id TEXT UNIQUE, role TEXT NOT NULL DEFAULT 'CUSTOMER',
 active INTEGER NOT NULL DEFAULT 1, session_version INTEGER NOT NULL DEFAULT 1,
 terms_version TEXT NOT NULL, consent_at TEXT NOT NULL, consent_origin TEXT NOT NULL, created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS categories (
 id TEXT PRIMARY KEY, name TEXT NOT NULL, parent_id TEXT REFERENCES categories(id),
 sort_order INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS businesses (
 id TEXT PRIMARY KEY, owner_id TEXT NOT NULL REFERENCES users(id), name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE,
 description TEXT NOT NULL, address TEXT NOT NULL, city TEXT NOT NULL, phone TEXT NOT NULL,
 timezone TEXT NOT NULL DEFAULT 'America/Sao_Paulo', latitude TEXT, longitude TEXT, image TEXT,
 status TEXT NOT NULL DEFAULT 'PENDING', policy TEXT NOT NULL,
 opens TEXT NOT NULL DEFAULT '09:00', closes TEXT NOT NULL DEFAULT '18:00',
 weekdays TEXT NOT NULL DEFAULT '1,2,3,4,5,6', pending_minutes INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS memberships (
 id TEXT PRIMARY KEY, business_id TEXT NOT NULL REFERENCES businesses(id), user_id TEXT NOT NULL REFERENCES users(id),
 role TEXT NOT NULL, UNIQUE(business_id, user_id)
);
CREATE TABLE IF NOT EXISTS professionals (
 id TEXT PRIMARY KEY, business_id TEXT NOT NULL REFERENCES businesses(id), name TEXT NOT NULL,
 active INTEGER NOT NULL DEFAULT 1, opens TEXT NOT NULL DEFAULT '09:00', closes TEXT NOT NULL DEFAULT '18:00',
 weekdays TEXT NOT NULL DEFAULT '1,2,3,4,5,6'
);
CREATE TABLE IF NOT EXISTS services (
 id TEXT PRIMARY KEY, business_id TEXT NOT NULL REFERENCES businesses(id), category_id TEXT NOT NULL REFERENCES categories(id),
 name TEXT NOT NULL, description TEXT NOT NULL, duration_minutes INTEGER NOT NULL CHECK(duration_minutes > 0),
 price_cents INTEGER NOT NULL CHECK(price_cents >= 0), active INTEGER NOT NULL DEFAULT 1, image TEXT
);
CREATE TABLE IF NOT EXISTS service_professionals (
 service_id TEXT NOT NULL REFERENCES services(id), professional_id TEXT NOT NULL REFERENCES professionals(id),
 PRIMARY KEY(service_id,professional_id)
);
CREATE TABLE IF NOT EXISTS schedule_blocks (
 id TEXT PRIMARY KEY, professional_id TEXT NOT NULL REFERENCES professionals(id), starts_at TEXT NOT NULL,
 ends_at TEXT NOT NULL, reason TEXT NOT NULL, CHECK(ends_at > starts_at)
);
CREATE TABLE IF NOT EXISTS promotions (
 id TEXT PRIMARY KEY, service_id TEXT NOT NULL UNIQUE REFERENCES services(id), percent INTEGER NOT NULL CHECK(percent BETWEEN 1 AND 90),
 starts_at TEXT NOT NULL, ends_at TEXT NOT NULL, CHECK(ends_at > starts_at)
);
CREATE TABLE IF NOT EXISTS bookings (
 id TEXT PRIMARY KEY, customer_id TEXT NOT NULL REFERENCES users(id), business_id TEXT NOT NULL REFERENCES businesses(id),
 service_id TEXT NOT NULL REFERENCES services(id), professional_id TEXT NOT NULL REFERENCES professionals(id),
 starts_at TEXT NOT NULL, ends_at TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'PENDING_PARTNER_APPROVAL',
 price_cents INTEGER NOT NULL, duration_minutes INTEGER NOT NULL, service_name TEXT NOT NULL,
 professional_name TEXT NOT NULL, policy TEXT NOT NULL, timezone TEXT NOT NULL,
 expires_at TEXT, request_key TEXT NOT NULL, created_at TEXT NOT NULL,
 UNIQUE(customer_id, request_key), CHECK(ends_at > starts_at)
);
CREATE INDEX IF NOT EXISTS booking_schedule ON bookings(professional_id,starts_at,ends_at,status);
CREATE TABLE IF NOT EXISTS booking_history (
 id TEXT PRIMARY KEY, booking_id TEXT NOT NULL REFERENCES bookings(id), actor_id TEXT NOT NULL REFERENCES users(id),
 status TEXT NOT NULL, reason TEXT NOT NULL, created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS conversations (
 id TEXT PRIMARY KEY, booking_id TEXT NOT NULL UNIQUE REFERENCES bookings(id), blocked INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS messages (
 id TEXT PRIMARY KEY, conversation_id TEXT NOT NULL REFERENCES conversations(id), sender_id TEXT REFERENCES users(id),
 body TEXT NOT NULL, created_at TEXT NOT NULL, is_system INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS conversation_reads (
 conversation_id TEXT NOT NULL REFERENCES conversations(id), user_id TEXT NOT NULL REFERENCES users(id),
 read_at TEXT NOT NULL, PRIMARY KEY(conversation_id,user_id)
);
CREATE TABLE IF NOT EXISTS favorites (
 user_id TEXT NOT NULL REFERENCES users(id), business_id TEXT NOT NULL REFERENCES businesses(id), PRIMARY KEY(user_id,business_id)
);
CREATE TABLE IF NOT EXISTS reviews (
 id TEXT PRIMARY KEY, booking_id TEXT NOT NULL UNIQUE REFERENCES bookings(id), customer_id TEXT NOT NULL REFERENCES users(id),
 business_id TEXT NOT NULL REFERENCES businesses(id), rating INTEGER NOT NULL CHECK(rating BETWEEN 1 AND 5),
 body TEXT NOT NULL, visible INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS notifications (
 id TEXT PRIMARY KEY, user_id TEXT NOT NULL REFERENCES users(id), body TEXT NOT NULL, link TEXT NOT NULL,
 created_at TEXT NOT NULL, read_at TEXT
);
CREATE TABLE IF NOT EXISTS reports (
 id TEXT PRIMARY KEY, user_id TEXT NOT NULL REFERENCES users(id), target_type TEXT NOT NULL,
 target_id TEXT NOT NULL, reason TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'OPEN', created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS audit_events (
 id TEXT PRIMARY KEY, actor_id TEXT NOT NULL REFERENCES users(id), action TEXT NOT NULL,
 target_id TEXT NOT NULL, created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS password_resets (
 id TEXT PRIMARY KEY, user_id TEXT NOT NULL REFERENCES users(id), token_hash TEXT NOT NULL UNIQUE,
 expires_at TEXT NOT NULL, used_at TEXT
);
CREATE TABLE IF NOT EXISTS rate_limits (
 key_hash TEXT PRIMARY KEY, attempts INTEGER NOT NULL, window_start INTEGER NOT NULL
);
