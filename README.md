# User Self Delete for WordPress

GDPR-compliant WordPress plugin for user self-deletion with country-based data retention periods for legal and tax compliance.

## Features

### 🌍 Smart Data Retention
- **Country-Based Retention**: Select countries where you do business
- **Automatic Calculation**: Plugin calculates maximum required retention period
- **Soft Delete System**: Users archived immediately, permanently deleted after retention period
- **Archive Table**: Deleted users stored separately for optimal performance

### 🛡️ GDPR Compliance
- **Article 17 Right to Erasure**: Users can delete accounts with minimal barriers
- **Legal Compliance**: Balances GDPR with tax/accounting retention requirements
- **Audit Trail**: Complete logging of all deletion activities
- **Data Anonymization**: WooCommerce orders anonymized while preserving business records

### ⚡ Performance Optimized
- Deleted users moved to separate archive table
- WordPress doesn't load archived users in queries
- Automatic daily cleanup via cron
- WP-CLI commands for manual management

### 🔧 Technical Features
- **Modern Stack**: PHP 8.2+, WordPress 6.4+
- **WooCommerce HPOS**: Compatible with High-Performance Order Storage
- **REST API**: Modern endpoint for account deletion
- **Vanilla JavaScript**: No jQuery dependency
- **Simplified UX**: Single-step password confirmation, integrated account details placement
- **Security Hardened**: Admin deletion prevention, IP validation, XSS-safe, clear error messaging

## Requirements

- **PHP**: 8.2 or higher
- **WordPress**: 6.4 or higher
- **WooCommerce**: 7.0+ (optional)

## Installation

1. Upload plugin to `/wp-content/plugins/user-self-delete/`
2. Activate through WordPress admin
3. Go to **Settings > User Self Delete**
4. Select countries where you have customers
5. Configure retention and deletion preferences

## Configuration

### Data Retention Settings

**Countries Where You Sell**
- Select all countries where you have customers
- Plugin automatically applies maximum retention period required
- Examples: Germany (10 years), UK (6 years), Bulgaria (5 years)

**Custom Retention Override**
- Optionally set custom retention period
- Useful for specific regulations or business requirements

### General Settings

- **Enable Logging**: Track deletions for audit (recommended)
- **Admin Notifications**: Email notifications for deletions
- **Order Handling**: Anonymize (recommended) or delete WooCommerce orders
- **Post Handling**: Reassign to admin or delete user posts

## Usage

### For Users

**WooCommerce Sites:**
1. Go to **My Account > Account Details**
2. Scroll to the **"Delete Account"** section
3. Click **"Delete My Account"** button
4. Enter your password in the confirmation modal
5. Click **"Delete My Account"** to confirm
6. Account deleted immediately, data archived per retention period

**Standard WordPress:**
- Available in user profile page
- Same streamlined deletion process

**Simplified Interface:**
- Single password confirmation step
- Clear error messages (e.g., admins cannot self-delete)
- No redundant warning prompts
- Clean, integrated design

### For Administrators

**View Deletion Statistics:**
- Go to **Settings > User Self Delete**
- View total deletions, monthly stats, recent activity

**Manage Archived Users:**
```bash
# View expired archived users (dry run)
wp user-self-delete cleanup --dry-run

# Cleanup expired users
wp user-self-delete cleanup --yes

# View deletion statistics
wp user-self-delete stats

# View recent deletion log
wp user-self-delete log --limit=20

# Export deletion log
wp user-self-delete export deletions-2024.csv
```

## How It Works

### Soft Delete Process

1. **User Requests Deletion**
   - Enters password to confirm
   - All personal data archived

2. **Immediate Anonymization**
   - User removed from wp_users table
   - Data moved to archive table
   - WooCommerce orders anonymized
   - Login prevented

3. **Scheduled Permanent Deletion**
   - Based on country retention requirements
   - Automatic cleanup via daily cron
   - Manual cleanup via WP-CLI

### Archive Table

Deleted users stored in `wp_user_self_delete_archive`:
- Original user data preserved for audit
- Scheduled deletion date tracked
- Retention periods recorded
- IP address and timestamp logged

## Database Tables

- `wp_user_self_delete_log` - Deletion activity log
- `wp_user_self_delete_archive` - Soft-deleted users archive

## REST API

**Delete Account Endpoint:**
```
POST /wp-json/user-self-delete/v1/delete-account
Authorization: Bearer [nonce]
Body: { "password": "user_password" }
```

**Account Info Endpoint:**
```
GET /wp-json/user-self-delete/v1/account-info
Authorization: Bearer [nonce]
```

## WP-CLI Commands

```bash
# Statistics
wp user-self-delete stats
wp user-self-delete stats --format=json

# Deletion log
wp user-self-delete log
wp user-self-delete log --limit=50 --format=csv

# Cleanup expired users
wp user-self-delete cleanup --dry-run
wp user-self-delete cleanup --yes --limit=100

# Export log
wp user-self-delete export
wp user-self-delete export --start-date=2024-01-01

# View settings
wp user-self-delete settings
```

## Hooks & Filters

```php
// Before soft deletion
do_action('user_self_delete_before_soft_deletion', $user_id, $user);

// After soft deletion
do_action('user_self_delete_after_soft_deletion', $user_id, $user, $scheduled_date);

// Before permanent deletion
do_action('user_self_delete_before_deletion', $user_id, $user);

// After permanent deletion
do_action('user_self_delete_after_deletion', $user_id, $user);

// Plugin data cleanup
do_action('user_self_delete_cleanup_plugin_data', $user_id);
```

## Supported Plugins

- **WooCommerce**: Full HPOS compatibility
- **BuddyPress**: Activity and profile cleanup
- **bbPress**: Forum data handling
- **Ultimate Member**: Profile data removal

## Retention Periods by Country

The plugin includes retention periods for 40+ countries:

**EU Examples:**
- Germany, France, Italy: 10 years
- Austria, Belgium, Netherlands: 7 years
- Denmark, Bulgaria: 5 years

**Other Regions:**
- United States: 7 years
- United Kingdom: 6 years
- Canada: 6 years
- Australia: 5 years

View full list in admin settings.

### Adding New Countries

To add a new country to the plugin:

1. Open `includes/retention-periods.php`
2. Find the `get_countries()` method (around line 49)
3. Add your country entry following this format:

```php
'XX' => array(
    'name'   => 'Country Name',
    'years'  => 7,  // Retention period in years
    'region' => 'Region Name',
),
```

**Example:**
```php
'FR' => array(
    'name'   => 'France',
    'years'  => 10,
    'region' => 'EU',
),
```

**Available regions:** EU, EEA, UK, Europe, North America, South America, Asia, Oceania, Middle East, Africa

The retention period should reflect the longest legal requirement for keeping business/tax records in that country.

## Security Features

- Password verification required for all deletions
- Admin accounts cannot self-delete (with clear error message)
- IP address logging for audit trail
- XSS-safe DOM manipulation
- Proper nonce verification (REST API and AJAX)
- SQL injection prevention
- Descriptive error messages for better user experience

## Migration

Plugin automatically migrates existing soft-deleted users to archive table on activation. This is a one-time operation.

## Legal Disclaimer

This plugin helps meet GDPR and data retention requirements but does not guarantee full legal compliance. Consult legal counsel for your specific jurisdiction and business requirements.

## Support

For issues, feature requests, or contributions:
- GitHub: https://github.com/Open-WP-Club/User-Self-Delete
- Report bugs via GitHub Issues

## License

GPL v2 or later
