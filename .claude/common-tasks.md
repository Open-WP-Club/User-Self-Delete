# Common Development Tasks

## Adding a New Country

When adding a new country with its retention period:

### 1. Locate the retention periods file
```
includes/retention-periods.php
```

### 2. Find the `get_countries()` method
Around line 49 in the file.

### 3. Add country entry
```php
'XX' => array(
    'name'   => 'Country Name',
    'years'  => 7,  // Retention period in years
    'region' => 'Region Name',
),
```

### 4. Available regions
- `'EU'` - European Union countries
- `'EEA'` - European Economic Area
- `'UK'` - United Kingdom
- `'Europe'` - Other European countries
- `'North America'`
- `'South America'`
- `'Asia'`
- `'Oceania'`
- `'Middle East'`
- `'Africa'`

### 5. Country code format
Use ISO 3166-1 alpha-2 codes (two-letter codes):
- US (United States)
- FR (France)
- DE (Germany)
- JP (Japan)
- etc.

### 6. Research retention period
The retention period should reflect the longest legal requirement for keeping business/tax records in that country. Common periods:
- 5 years (minimum for most countries)
- 6-7 years (common for tax purposes)
- 10 years (stricter jurisdictions)

## Modifying the Deletion Process

### Adding custom cleanup logic

Hook into the cleanup action to remove data from custom tables or plugins:

```php
add_action('user_self_delete_cleanup_plugin_data', 'my_custom_cleanup', 10, 1);

function my_custom_cleanup(int $user_id): void {
    global $wpdb;

    // Example: Remove from custom table
    $wpdb->delete(
        $wpdb->prefix . 'my_custom_table',
        array('user_id' => $user_id),
        array('%d')
    );

    // Example: Remove custom post meta
    delete_metadata('post', null, '_my_custom_user_id', $user_id, true);
}
```

### Adding pre-deletion validation

Prevent deletion under certain conditions:

```php
add_action('user_self_delete_before_soft_deletion', 'my_deletion_check', 10, 2);

function my_deletion_check(int $user_id, WP_User $user): void {
    // Example: Prevent deletion if user has pending orders
    if (has_pending_orders($user_id)) {
        wp_die(
            'Cannot delete account with pending orders.',
            'Deletion Prevented',
            array('response' => 403)
        );
    }
}
```

### Logging custom information

```php
add_action('user_self_delete_after_soft_deletion', 'my_deletion_log', 10, 3);

function my_deletion_log(int $user_id, WP_User $user, string $scheduled_date): void {
    error_log(sprintf(
        'User %d (%s) scheduled for deletion on %s',
        $user_id,
        $user->user_email,
        $scheduled_date
    ));
}
```

## Testing Deletion Flow

### Manual Testing Steps

1. **Create test user**
   ```bash
   wp user create testuser test@example.com --role=subscriber --user_pass=testpass123
   ```

2. **Set short retention period** (for testing)
   - Go to Settings > User Self Delete
   - Select countries with short retention (e.g., Bulgaria - 5 years)
   - Or use custom retention override: 1 year

3. **Test deletion as user**
   - Log in as test user
   - Go to My Account > Account Details (WooCommerce)
   - Click "Delete My Account"
   - Enter password
   - Confirm deletion

4. **Verify soft deletion**
   ```bash
   # Check archive table
   wp db query "SELECT * FROM wp_user_self_delete_archive WHERE original_email = 'test@example.com'"

   # Verify user removed from wp_users
   wp user list --field=user_email | grep test@example.com
   ```

5. **Test cleanup (dry run)**
   ```bash
   wp user-self-delete cleanup --dry-run
   ```

6. **Force cleanup for testing**
   ```bash
   # Manually update scheduled_deletion_date to past date
   wp db query "UPDATE wp_user_self_delete_archive SET scheduled_deletion_date = '2020-01-01' WHERE original_email = 'test@example.com'"

   # Run cleanup
   wp user-self-delete cleanup --yes
   ```

## Debugging

### Enable logging
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Check logs
```bash
# View WordPress debug log
tail -f /path/to/wp-content/debug.log

# View deletion statistics
wp user-self-delete stats

# View recent deletion log
wp user-self-delete log --limit=20
```

### Check scheduled events
```bash
wp cron event list --search=user_self_delete
```

### Manually trigger cleanup
```php
// In WordPress admin or via wp-shell
do_action('user_self_delete_cleanup');
```

## Database Maintenance

### View archived users
```bash
wp db query "SELECT id, original_email, deletion_date, scheduled_deletion_date FROM wp_user_self_delete_archive ORDER BY deletion_date DESC LIMIT 10"
```

### View deletion log
```bash
wp user-self-delete log --limit=50 --format=table
```

### Export deletion log
```bash
wp user-self-delete export deletions-$(date +%Y-%m).csv
```

### Check table sizes
```bash
wp db query "
SELECT
    table_name AS 'Table',
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.TABLES
WHERE table_schema = DATABASE()
AND table_name LIKE '%user_self_delete%'
ORDER BY (data_length + index_length) DESC
"
```

## Modifying Settings

### Programmatically update settings
```php
// Enable logging
update_option('user_self_delete_enable_logging', 1);

// Set custom retention (in years)
update_option('user_self_delete_custom_retention', 7);

// Set countries
update_option('user_self_delete_countries', array('US', 'GB', 'DE'));

// Enable admin notifications
update_option('user_self_delete_admin_notification', 1);

// Anonymize orders (don't delete)
update_option('user_self_delete_anonymize_orders', 1);
```

### View current settings
```bash
wp user-self-delete settings
```

## Extending the Admin Interface

### Add custom statistics

Hook into the admin settings page:

```php
add_action('user_self_delete_admin_after_stats', 'my_custom_stats');

function my_custom_stats(): void {
    // Calculate custom statistics
    $custom_count = get_my_custom_deletion_count();

    ?>
    <div class="postbox">
        <h2>Custom Statistics</h2>
        <div class="inside">
            <p>Custom deletions: <?php echo esc_html($custom_count); ?></p>
        </div>
    </div>
    <?php
}
```

## REST API Integration

### JavaScript example
```javascript
// Get account info
const infoResponse = await fetch('/wp-json/user-self-delete/v1/account-info', {
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
});
const info = await infoResponse.json();

// Delete account
const deleteResponse = await fetch('/wp-json/user-self-delete/v1/delete-account', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        password: userPassword
    })
});
const result = await deleteResponse.json();
```

### PHP example (from another plugin)
```php
$request = new WP_REST_Request('POST', '/user-self-delete/v1/delete-account');
$request->set_param('password', $user_password);
$response = rest_do_request($request);
```

## Plugin Compatibility

### Adding support for a new plugin

1. **Identify plugin's user data tables/meta**
2. **Add cleanup logic to `User_Self_Delete_Data_Eraser` class**
3. **Hook into `user_self_delete_cleanup_plugin_data`**

Example for a hypothetical plugin:

```php
// In includes/data-eraser.php, add to cleanup_plugin_data() method

// Example Plugin cleanup
if (defined('EXAMPLE_PLUGIN_VERSION')) {
    global $wpdb;

    // Remove from custom table
    $wpdb->delete(
        $wpdb->prefix . 'example_plugin_data',
        array('user_id' => $user_id),
        array('%d')
    );

    // Remove custom meta
    delete_user_meta($user_id, 'example_plugin_meta');
}
```

## Version Updates

### When bumping version:

1. **Update version in main file** (`user-self-delete.php`, line 6)
2. **Update constant** (`USER_SELF_DELETE_VERSION`, line 29)
3. **Update README.md** if needed
4. **Check for database schema changes**
   - If schema changed, increment `user_self_delete_db_version`
   - Add migration logic in activation hook

### Release checklist:
- [ ] Version numbers updated
- [ ] README.md updated
- [ ] CHANGELOG.md updated (if present)
- [ ] Database migrations tested
- [ ] Tested on minimum PHP/WP versions
- [ ] WooCommerce compatibility verified
- [ ] Git tag created
