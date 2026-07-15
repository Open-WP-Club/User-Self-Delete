# Roadmap

Future planned improvements. Priorities may shift based on user feedback.

## v2.2.0 — Archived Users Management

- Dedicated admin page under **Users > Archived Users**
- Search by email, filter by date range, sort by any column
- Manual "Permanently Delete Now" action per user (with confirmation)
- Bulk permanent-delete action

## v2.3.0 — Pre-Deletion Email Notification

- Email sent to the archived user's original address N days before scheduled permanent deletion
- Admin-configurable number of days in advance (e.g. 30 days)
- Opt-in per-user to skip the notification

## v3.0.0 — Native WooCommerce Blocks My Account

- Register a custom inner block via `Automattic\WooCommerce\Blocks\Integration\IntegrationInterface`
- React component for the delete account UI in the Blocks My Account page
- Falls back to classic hook for non-Blocks sites
