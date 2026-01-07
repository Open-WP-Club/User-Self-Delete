# User Self Delete for WordPress

## Project Overview

This is a GDPR-compliant WordPress plugin that enables users to self-delete their accounts while maintaining legal compliance with country-specific data retention requirements.

**Current Version:** 2.0.0
**Minimum Requirements:**
- PHP 8.2+
- WordPress 6.4+
- WooCommerce 7.0+ (optional)

## Core Concept

The plugin implements a **soft delete system** where:
1. Users request account deletion with password confirmation
2. Account is immediately archived (moved to separate table)
3. Data is retained based on country-specific legal requirements
4. Permanent deletion happens automatically after retention period expires

## Key Features

### Data Retention System
- **Country-Based Retention**: 40+ countries with specific retention periods
- **Automatic Calculation**: Plugin determines maximum retention period based on selected countries
- **Archive Table**: Deleted users stored in `wp_user_self_delete_archive`
- **Scheduled Cleanup**: Daily cron job removes expired archived users

### GDPR Compliance
- Article 17 Right to Erasure implementation
- Balances user rights with legal/tax retention requirements
- Complete audit trail via logging system
- WooCommerce order anonymization (preserves business records)

### Technical Highlights
- **Modern PHP**: Uses strict types, type hints, PHP 8.2+ features
- **WooCommerce HPOS**: Full compatibility with High-Performance Order Storage
- **REST API**: Modern endpoint for account deletion
- **Vanilla JavaScript**: No jQuery dependency
- **Security Hardened**: Nonce verification, password confirmation, XSS prevention
- **WP-CLI Commands**: Management and statistics via command line

## Database Schema

### Tables
- `wp_user_self_delete_log` - Audit trail of all deletion activities
- `wp_user_self_delete_archive` - Soft-deleted users with retention metadata

### Archive Table Key Fields
- `original_user_id` - Original WordPress user ID
- `original_email` - User's email before deletion
- `deletion_date` - When user requested deletion
- `scheduled_deletion_date` - When permanent deletion will occur
- `retention_years` - Calculated retention period
- `retention_countries` - Countries that determined retention period
- `user_data` - JSON snapshot of user metadata

## Plugin Architecture

### Singleton Pattern
All major classes use singleton pattern via `get_instance()` method.

### File Structure
```
user-self-delete.php          # Main plugin file, bootstrap
includes/
  ├── user-self-delete.php    # Core deletion functionality
  ├── data-eraser.php         # Handles data cleanup for various plugins
  ├── admin-settings.php      # Settings page in WordPress admin
  ├── retention-periods.php   # Country retention period definitions
  └── wp-cli.php              # WP-CLI commands
assets/
  ├── js/delete-account.js    # Frontend deletion interface
  └── css/                    # Styling
```

## Integration Points

### Supported Plugins
- **WooCommerce**: Order anonymization, HPOS compatibility
- **BuddyPress**: Activity and profile cleanup
- **bbPress**: Forum data handling
- **Ultimate Member**: Profile data removal

### Hooks Provided

**Action Hooks:**
- `user_self_delete_before_soft_deletion` - Before archiving user
- `user_self_delete_after_soft_deletion` - After archiving user
- `user_self_delete_before_deletion` - Before permanent deletion
- `user_self_delete_after_deletion` - After permanent deletion
- `user_self_delete_cleanup_plugin_data` - For custom plugin integration

## Development Philosophy

### Code Quality
- Strict PHP typing throughout
- WordPress Coding Standards compliance
- Security-first approach (XSS prevention, SQL injection prevention)
- Performance optimized (separate archive table, indexed queries)

### User Experience
- Minimal barriers to account deletion (GDPR requirement)
- Single-step password confirmation
- Clear error messages
- Integrated UI (appears in WooCommerce account details)

### Legal Compliance
- Transparent about retention periods
- Balances GDPR Article 17 with local retention laws
- Audit trail for compliance verification
- Anonymization over deletion where legally required

## Repository

- GitHub: https://github.com/Open-WP-Club/User-Self-Delete
- License: GPL v2 or later
- Maintained by: Open WP Club
