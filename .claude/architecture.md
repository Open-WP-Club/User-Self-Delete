# Architecture Overview

## System Design

### Soft Delete Architecture

The plugin implements a two-phase deletion system:

```
User Request → Soft Delete (Archive) → Retention Period → Hard Delete
     ↓              ↓                        ↓                ↓
  Password      Move to Archive        Daily Cron       Permanent
  Confirm       Anonymize Orders       Checks Dates     Removal
```

### Data Flow

```
User Interface (Frontend)
        ↓
REST API Endpoint (/wp-json/user-self-delete/v1/delete-account)
        ↓
User_Self_Delete_Core::process_soft_deletion()
        ↓
1. Validate user and password
2. Calculate retention period
3. Archive user data
4. Remove from wp_users
5. Log deletion
6. Send notifications
        ↓
Archive Table (retention period storage)
        ↓
Daily Cron Job (user_self_delete_cleanup)
        ↓
User_Self_Delete_Data_Eraser::cleanup_expired_users()
        ↓
Permanent deletion
```

## Class Hierarchy

### Core Classes

#### User_Self_Delete_Plugin (user-self-delete.php)
Main bootstrap class that:
- Checks system requirements (PHP 8.2+, WP 6.4+)
- Handles activation/deactivation
- Creates database tables
- Migrates legacy soft-deleted users
- Initializes all components

**Key Methods:**
- `get_instance()` - Singleton instance
- `activate()` - Plugin activation (creates tables, sets defaults)
- `create_log_table()` - Creates log and archive tables
- `migrate_soft_deleted_users()` - One-time migration from old system

#### User_Self_Delete_Core (includes/user-self-delete.php)
Core deletion functionality:
- Enqueues frontend scripts
- Registers REST API endpoints
- Handles account deletion requests
- Manages soft deletion process
- Schedules cleanup cron jobs

**Key Methods:**
- `register_rest_routes()` - Registers `/delete-account` and `/account-info` endpoints
- `handle_delete_account()` - REST API deletion handler
- `process_soft_deletion()` - Archives user and schedules deletion
- `run_automatic_cleanup()` - Cron job for expired user cleanup

**REST Endpoints:**
```
POST /wp-json/user-self-delete/v1/delete-account
  - Body: { "password": "string" }
  - Returns: { "success": boolean, "message": "string" }

GET /wp-json/user-self-delete/v1/account-info
  - Returns: { "email": "string", "display_name": "string" }
```

#### User_Self_Delete_Retention_Periods (includes/retention-periods.php)
Manages country-based retention periods:
- Stores retention data for 40+ countries
- Calculates maximum retention period
- Groups countries by region

**Key Methods:**
- `get_countries()` - Returns array of all countries with retention periods
- `get_retention_for_countries()` - Calculates max retention from selected countries
- `get_countries_by_region()` - Groups countries for admin UI

**Data Structure:**
```php
array(
    'US' => array(
        'name'   => 'United States',
        'years'  => 7,
        'region' => 'North America',
    ),
    // ...
)
```

#### User_Self_Delete_Data_Eraser (includes/data-eraser.php)
Handles data cleanup:
- Processes permanent user deletion
- Cleans up WooCommerce orders (anonymize or delete)
- Removes data from supported plugins (BuddyPress, bbPress, Ultimate Member)
- Runs scheduled cleanup of expired archived users

**Key Methods:**
- `cleanup_expired_users()` - Removes users past retention period
- `anonymize_woocommerce_orders()` - Replaces PII with anonymous data
- `cleanup_plugin_data()` - Removes user data from third-party plugins

#### User_Self_Delete_Admin (includes/admin-settings.php)
Admin interface:
- Settings page UI
- Statistics dashboard
- Country selection interface
- Plugin settings management

**Settings:**
```php
user_self_delete_countries          // Selected countries (array)
user_self_delete_custom_retention   // Custom retention override (int)
user_self_delete_enable_logging     // Enable audit logging (bool)
user_self_delete_admin_notification // Email admin on deletion (bool)
user_self_delete_anonymize_orders   // Anonymize vs delete orders (bool)
user_self_delete_delete_posts       // Delete user posts (bool)
```

## Database Schema

### wp_user_self_delete_log
Audit trail table for all deletion activities.

```sql
CREATE TABLE wp_user_self_delete_log (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id bigint(20) unsigned NOT NULL,
    user_email varchar(100) NOT NULL DEFAULT '',
    deletion_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address varchar(45) NOT NULL DEFAULT '',
    user_agent text,
    status varchar(20) NOT NULL DEFAULT 'completed',
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY deletion_date (deletion_date),
    KEY status (status)
)
```

### wp_user_self_delete_archive
Archive table for soft-deleted users.

```sql
CREATE TABLE wp_user_self_delete_archive (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    original_user_id bigint(20) unsigned NOT NULL,
    original_email varchar(100) NOT NULL DEFAULT '',
    user_login varchar(60) NOT NULL DEFAULT '',
    user_nicename varchar(50) NOT NULL DEFAULT '',
    display_name varchar(250) NOT NULL DEFAULT '',
    deletion_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    scheduled_deletion_date datetime DEFAULT NULL,
    retention_years int(11) NOT NULL DEFAULT 0,
    retention_countries text,
    ip_address varchar(45) NOT NULL DEFAULT '',
    user_agent text,
    user_data longtext,              -- JSON snapshot of user metadata
    PRIMARY KEY (id),
    KEY original_user_id (original_user_id),
    KEY deletion_date (deletion_date),
    KEY scheduled_deletion_date (scheduled_deletion_date),
    KEY original_email (original_email)
)
```

## Frontend Integration

### WooCommerce Integration
- Hooks into `woocommerce_after_edit_account_form`
- Adds deletion section to My Account > Account Details page
- Integrated design matching WooCommerce styles

### Standard WordPress
- Hooks into `show_user_profile` and `edit_user_profile`
- Appears in user profile pages
- Same deletion interface

### JavaScript Interface (assets/js/delete-account.js)
- Modal confirmation dialog
- Password verification
- Real-time validation
- Error handling and display
- Success redirect

## Scheduled Tasks

### Daily Cleanup Cron
```php
Hook: user_self_delete_cleanup
Schedule: daily
Function: User_Self_Delete_Core::run_automatic_cleanup()
```

Checks for archived users past retention period and permanently deletes them.

## WP-CLI Commands (includes/wp-cli.php)

### Available Commands
```bash
wp user-self-delete stats [--format=<format>]
  # View deletion statistics

wp user-self-delete log [--limit=<number>] [--format=<format>]
  # View deletion log entries

wp user-self-delete cleanup [--dry-run] [--yes] [--limit=<number>]
  # Cleanup expired archived users

wp user-self-delete export [<filename>] [--start-date=<date>] [--end-date=<date>]
  # Export deletion log to CSV

wp user-self-delete settings
  # View current plugin settings
```

## Action Hooks (Developer API)

### Available Hooks
```php
// Before soft deletion (user still in wp_users)
do_action('user_self_delete_before_soft_deletion', $user_id, $user);

// After soft deletion (user archived)
do_action('user_self_delete_after_soft_deletion', $user_id, $user, $scheduled_date);

// Before permanent deletion
do_action('user_self_delete_before_deletion', $user_id, $user);

// After permanent deletion
do_action('user_self_delete_after_deletion', $user_id, $user);

// Plugin data cleanup (for third-party integrations)
do_action('user_self_delete_cleanup_plugin_data', $user_id);
```

### Hook Usage Example
```php
// Custom plugin integration
add_action('user_self_delete_cleanup_plugin_data', function($user_id) {
    global $wpdb;
    $wpdb->delete(
        $wpdb->prefix . 'my_custom_table',
        array('user_id' => $user_id),
        array('%d')
    );
});
```

## Performance Optimizations

### Separate Archive Table
- Deleted users don't slow down WordPress user queries
- Can grow indefinitely without affecting active users
- Indexed for fast lookups by date and ID

### Batch Processing
- Cleanup cron can process in batches (default: 100 users)
- Prevents timeout on large deletions
- Configurable via `--limit` flag in WP-CLI

### Query Optimization
- All tables have appropriate indexes
- Queries use prepared statements
- Count queries avoid loading full datasets

## Security Measures

### Input Validation
- Password verification required
- Nonce verification on all requests
- User capability checks (cannot delete admin accounts)
- IP address validation and logging

### Output Escaping
- All user-facing output escaped (`esc_html`, `esc_attr`, `esc_url`)
- XSS prevention in JavaScript (uses `textContent`, not `innerHTML`)
- SQL injection prevention (prepared statements)

### Rate Limiting
- Optional cooldown period between deletion attempts
- Configurable in settings

### Admin Protection
- Administrators cannot self-delete (requires another admin)
- Clear error message explaining restriction
