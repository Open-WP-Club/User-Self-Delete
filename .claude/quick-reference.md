# Quick Reference Guide

## File Locations

### Core Files
- `user-self-delete.php` - Main plugin bootstrap (11:1-417)
- `includes/user-self-delete.php` - Core deletion logic
- `includes/data-eraser.php` - Data cleanup and third-party plugin integration
- `includes/admin-settings.php` - Admin UI and settings page
- `includes/retention-periods.php` - Country retention period definitions (49:1)
- `includes/wp-cli.php` - WP-CLI command definitions

### Frontend Files
- `assets/js/delete-account.js` - Deletion interface and modal
- `assets/css/style.css` - Frontend styles
- `assets/css/admin.css` - Admin page styles

## Key Constants

```php
USER_SELF_DELETE_VERSION        // '2.0.0'
USER_SELF_DELETE_PLUGIN_DIR     // Plugin directory path
USER_SELF_DELETE_PLUGIN_URL     // Plugin URL
USER_SELF_DELETE_MIN_PHP        // '8.2'
USER_SELF_DELETE_MIN_WP         // '6.4'
```

## Database Tables

### wp_user_self_delete_log
Audit trail of deletions
- Primary key: `id`
- Important fields: `user_id`, `user_email`, `deletion_date`, `ip_address`, `status`

### wp_user_self_delete_archive
Soft-deleted user storage
- Primary key: `id`
- Important fields: `original_user_id`, `original_email`, `deletion_date`, `scheduled_deletion_date`, `retention_years`, `user_data` (JSON)

## WordPress Options

```php
user_self_delete_countries          // array - Selected countries
user_self_delete_custom_retention   // int - Custom retention years override
user_self_delete_enable_logging     // bool - Enable deletion logging
user_self_delete_admin_notification // bool - Email admin on deletion
user_self_delete_anonymize_orders   // bool - Anonymize (1) or delete (0) orders
user_self_delete_delete_posts       // bool - Delete user posts
user_self_delete_require_password   // bool - Require password confirmation
user_self_delete_deletion_cooldown  // bool - Enable cooldown between deletions
user_self_delete_cooldown_period    // int - Cooldown hours
```

## REST API Endpoints

### Delete Account
```
POST /wp-json/user-self-delete/v1/delete-account
Headers: X-WP-Nonce: [nonce]
Body: { "password": "string" }
Response: { "success": boolean, "message": "string" }
```

### Account Info
```
GET /wp-json/user-self-delete/v1/account-info
Headers: X-WP-Nonce: [nonce]
Response: { "email": "string", "display_name": "string" }
```

## WP-CLI Commands

### View Statistics
```bash
wp user-self-delete stats
wp user-self-delete stats --format=json
```

### View Deletion Log
```bash
wp user-self-delete log
wp user-self-delete log --limit=50 --format=csv
```

### Cleanup Expired Users
```bash
# Preview what would be deleted
wp user-self-delete cleanup --dry-run

# Actually delete expired users
wp user-self-delete cleanup --yes

# Limit number processed
wp user-self-delete cleanup --yes --limit=100
```

### Export Log
```bash
wp user-self-delete export
wp user-self-delete export deletions.csv
wp user-self-delete export --start-date=2024-01-01 --end-date=2024-12-31
```

### View Settings
```bash
wp user-self-delete settings
```

## Action Hooks

### Soft Deletion Hooks
```php
// Before archiving user (user still exists)
do_action('user_self_delete_before_soft_deletion', int $user_id, WP_User $user);

// After archiving user (user removed from wp_users)
do_action('user_self_delete_after_soft_deletion', int $user_id, WP_User $user, string $scheduled_date);
```

### Permanent Deletion Hooks
```php
// Before permanent deletion
do_action('user_self_delete_before_deletion', int $user_id, WP_User $user);

// After permanent deletion
do_action('user_self_delete_after_deletion', int $user_id, WP_User $user);
```

### Custom Plugin Integration
```php
// For adding custom cleanup logic
do_action('user_self_delete_cleanup_plugin_data', int $user_id);
```

## Common Code Patterns

### Singleton Instance
```php
$instance = User_Self_Delete_Core::get_instance();
```

### Get Retention Period
```php
$countries = array('US', 'GB', 'DE');
$retention_years = User_Self_Delete_Retention_Periods::get_retention_for_countries($countries);
```

### Check if User is Archived
```php
global $wpdb;
$archive_table = $wpdb->prefix . 'user_self_delete_archive';
$archived = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$archive_table} WHERE original_user_id = %d",
    $user_id
));
```

### Log Deletion
```php
global $wpdb;
$wpdb->insert(
    $wpdb->prefix . 'user_self_delete_log',
    array(
        'user_id'       => $user_id,
        'user_email'    => $user_email,
        'deletion_date' => current_time('mysql'),
        'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'status'        => 'completed',
    )
);
```

## Troubleshooting

### Deletion not working
1. Check JavaScript console for errors
2. Verify REST API is accessible: `/wp-json/user-self-delete/v1/account-info`
3. Check nonce is valid (refresh page)
4. Verify user is not an admin
5. Check error logs: `tail -f wp-content/debug.log`

### Cron not running
```bash
# Check if scheduled
wp cron event list --search=user_self_delete

# Manually trigger
wp cron event run user_self_delete_cleanup
```

### Archive users not being deleted
```bash
# Check if any are past retention date
wp db query "SELECT COUNT(*) FROM wp_user_self_delete_archive WHERE scheduled_deletion_date < NOW()"

# Run cleanup with verbose output
wp user-self-delete cleanup --yes
```

### Admin cannot self-delete
This is by design. Administrators cannot delete their own accounts through the self-service interface. This prevents accidental loss of admin access.

### "Password incorrect" even with correct password
1. Check for password managers interfering
2. Verify user is logged in as the correct account
3. Check for special characters in password
4. Try resetting password and testing again

## Security Notes

### XSS Prevention
- Always use `esc_html()`, `esc_attr()`, `esc_url()` for output
- JavaScript uses `textContent`, not `innerHTML`
- Never output unsanitized user input

### SQL Injection Prevention
- All queries use `$wpdb->prepare()`
- Never concatenate user input into queries
- Use typed placeholders: `%d` (int), `%s` (string), `%f` (float)

### CSRF Prevention
- All forms use nonce verification
- REST API requires `X-WP-Nonce` header
- AJAX requests verify nonces

### Password Verification
- Required for all deletion requests
- Uses WordPress's `wp_check_password()` function
- Failed attempts logged with IP address

## Performance Tips

### Large Archive Tables
If archive table grows very large (100k+ rows):
1. Run cleanup regularly: Daily cron should handle this
2. Use WP-CLI with `--limit` flag for batch processing
3. Consider archiving old log entries to separate table
4. Add additional indexes if specific queries are slow

### Slow Deletion Process
If individual deletions are slow:
1. Check which plugins are installed (some have expensive cleanup)
2. Consider disabling less critical cleanup hooks temporarily
3. Profile queries with Query Monitor plugin
4. Check for slow external API calls in custom hooks

## Common Modifications

### Change Default Retention Period
```php
// In functions.php or custom plugin
add_filter('user_self_delete_default_retention', function($years) {
    return 5; // 5 years instead of calculated maximum
});
```

### Disable Deletion for Specific Roles
```php
add_action('user_self_delete_before_soft_deletion', function($user_id, $user) {
    if (in_array('vip_member', $user->roles)) {
        wp_die('VIP members cannot self-delete. Please contact support.');
    }
}, 10, 2);
```

### Add Custom Validation
```php
add_action('user_self_delete_before_soft_deletion', function($user_id) {
    $pending_orders = wc_get_orders(array(
        'customer_id' => $user_id,
        'status'      => array('pending', 'processing'),
        'limit'       => 1,
    ));

    if (!empty($pending_orders)) {
        wp_send_json_error(array(
            'message' => 'Cannot delete account with pending orders.'
        ), 403);
    }
}, 10, 1);
```

### Send Custom Notification
```php
add_action('user_self_delete_after_soft_deletion', function($user_id, $user, $scheduled_date) {
    // Send to internal system
    wp_remote_post('https://internal.api/user-deleted', array(
        'body' => array(
            'user_id' => $user_id,
            'email'   => $user->user_email,
            'date'    => $scheduled_date,
        ),
    ));
}, 10, 3);
```

## Testing Checklist

- [ ] Create test user
- [ ] Test deletion with correct password
- [ ] Test deletion with wrong password
- [ ] Verify user appears in archive table
- [ ] Verify user removed from wp_users
- [ ] Check deletion appears in log
- [ ] Test admin cannot self-delete
- [ ] Verify WooCommerce orders anonymized (if WC active)
- [ ] Run cleanup dry-run
- [ ] Test actual cleanup with forced past date
- [ ] Verify permanent deletion removes from archive
- [ ] Test WP-CLI commands
- [ ] Check email notifications sent (if enabled)
