# Changelog

All notable changes to this project are documented here.

## [2.1.0] - 2026-07-15

### Added
- Archived Users table in Settings page — admins can now view all soft-deleted users with deletion date, scheduled permanent removal, and retention period
- ARIA attributes on deletion modal (`role="dialog"`, `aria-modal`, `aria-labelledby`, `aria-expanded` on trigger button)
- Focus trap on deletion modal — Tab/Shift+Tab cycles only within the modal while open; focus returns to trigger on close
- `Tested up to: 6.8` and `WC tested up to: 9.9` headers

### Removed
- Dead `anonymize_user_data()` method — superseded by the archive table + `anonymize_woocommerce_orders()` in v2.0.0
- No-op `prevent_deleted_user_login` filter — archived users are deleted from `wp_users`, so the filter never fires
- Dead `add_user_list_styles()` and `modify_deleted_user_actions()` admin methods — archived users do not appear in the WordPress Users list
- Unimplemented option defaults (`require_password`, `deletion_cooldown`, `cooldown_period`) from plugin activation

## [2.0.0] - 2025-01-01

### Added
- Country-based data retention periods for 40+ countries
- Soft delete system — users moved to archive table, permanently deleted after retention period
- Archive table (`wp_user_self_delete_archive`) with scheduled deletion dates
- Daily cron job for automatic cleanup of expired archived users
- REST API endpoints for account deletion (`/wp-json/user-self-delete/v1/delete-account`)
- WooCommerce HPOS compatibility declaration
- WP-CLI commands: `stats`, `log`, `cleanup`, `export`, `settings`, `clear-log`
- Vanilla ES6+ frontend — no jQuery dependency
- Migration from legacy soft-delete meta flags to archive table on activation

### Changed
- Minimum PHP raised to 8.2
- Minimum WordPress raised to 6.4
- Plugin renamed to "User Self Delete for WordPress"
