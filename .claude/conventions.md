# Coding Conventions

## PHP Standards

### Type Safety
- **Strict types enabled**: Every PHP file starts with `declare(strict_types=1);`
- **Type hints everywhere**: All parameters and return types must be type-hinted
- **Nullable types**: Use `?Type` for nullable parameters/returns (e.g., `?string`, `?int`)

```php
public function example_method(string $name, ?int $id = null): bool {
    // Implementation
}
```

### File Structure
Every PHP file follows this pattern:
```php
<?php
/**
 * File description.
 *
 * @package UserSelfDelete
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Class or function definitions
```

### Class Design
- **Singleton pattern** for main classes
- **Final classes** to prevent inheritance
- **Private constructors** for singletons
- **Static `get_instance()` method**

```php
final class Example_Class {
    private static ?Example_Class $instance = null;

    public static function get_instance(): Example_Class {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize
    }
}
```

### WordPress Coding Standards
- **Yoda conditions**: `if ( 'value' === $variable )`
- **Array syntax**: Use `array()` not `[]` for WordPress compatibility
- **Spacing**: Follow WordPress spacing rules (spaces inside parentheses, around operators)
- **Braces**: Always use braces, even for single-line statements
- **Function naming**: Use `snake_case` for functions and methods

### Security Practices
- **Input validation**: Always validate and sanitize user input
- **Output escaping**: Use `esc_html()`, `esc_attr()`, `esc_url()`, etc.
- **Nonce verification**: Required for all forms and AJAX requests
- **SQL preparation**: Use `$wpdb->prepare()` for all database queries
- **Capability checks**: Verify user permissions before privileged operations

```php
// Good: Properly escaped output
echo '<div class="' . esc_attr( $class ) . '">' . esc_html( $content ) . '</div>';

// Good: Prepared SQL query
$wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$table} WHERE user_id = %d AND status = %s",
    $user_id,
    $status
) );
```

### Database Operations
- **Table prefixes**: Always use `$wpdb->prefix`
- **Charset collation**: Use `$wpdb->get_charset_collate()`
- **dbDelta**: Use for table creation/updates
- **Prepared statements**: Required for all dynamic queries

### Documentation
- **DocBlocks**: Required for all classes, methods, and functions
- **Parameter types**: Document with `@param Type $name Description`
- **Return types**: Document with `@return Type Description`
- **Since tags**: Include `@since` version tag

```php
/**
 * Process user deletion request.
 *
 * Archives user data to separate table and schedules permanent deletion
 * based on configured retention period.
 *
 * @since 2.0.0
 * @param int    $user_id User ID to delete.
 * @param string $reason  Optional deletion reason.
 * @return bool True on success, false on failure.
 */
public function process_deletion(int $user_id, string $reason = ''): bool {
    // Implementation
}
```

## JavaScript Standards

### Modern JavaScript
- **Vanilla JS**: No jQuery dependency
- **ES6+ features**: Use arrow functions, const/let, template literals
- **Strict mode**: Always use `'use strict';`

### DOM Manipulation
- **XSS prevention**: Use `textContent` not `innerHTML` for user data
- **Element creation**: Create elements properly with `createElement()`
- **Event delegation**: Use event delegation where appropriate

```javascript
// Good: XSS-safe
errorElement.textContent = errorMessage;

// Bad: XSS vulnerable
errorElement.innerHTML = errorMessage;
```

### API Calls
- **Fetch API**: Use modern Fetch API for AJAX requests
- **Error handling**: Always include try/catch blocks
- **Nonce verification**: Include nonce in all API requests

```javascript
const response = await fetch(deleteAccountEndpoint, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({ password: password })
});
```

## CSS Standards

### Organization
- Logical grouping of related styles
- Comments for major sections
- Mobile-first approach for responsive design

### Naming
- BEM-like naming for components
- Descriptive class names
- WordPress-compatible prefixes

## File Organization

### Directory Structure
```
includes/       - PHP backend classes
assets/
  ├── js/       - JavaScript files
  └── css/      - Stylesheets
languages/      - Translation files (if present)
```

### File Naming
- **PHP files**: `kebab-case.php` (e.g., `user-self-delete.php`)
- **Classes**: Match file name (e.g., `User_Self_Delete_Core` in `user-self-delete.php`)
- **JavaScript**: `kebab-case.js`
- **CSS**: `kebab-case.css`

## Constants

### Plugin Constants
Defined in main plugin file:
```php
USER_SELF_DELETE_VERSION        // Plugin version
USER_SELF_DELETE_PLUGIN_DIR     // Plugin directory path
USER_SELF_DELETE_PLUGIN_URL     // Plugin URL
USER_SELF_DELETE_PLUGIN_FILE    // Main plugin file path
USER_SELF_DELETE_PLUGIN_BASENAME // Plugin basename
USER_SELF_DELETE_MIN_PHP        // Minimum PHP version
USER_SELF_DELETE_MIN_WP         // Minimum WordPress version
```

## Error Handling

### PHP Errors
- **Return types**: Use return types to indicate success/failure
- **WP_Error**: Use WordPress `WP_Error` class for detailed errors
- **Logging**: Use `error_log()` for debug logging (when enabled)

### JavaScript Errors
- **Try/catch**: Wrap risky operations
- **User feedback**: Show clear error messages to users
- **Console errors**: Log technical details to console

## Performance

### Database
- **Indexed fields**: Ensure frequently queried fields are indexed
- **Separate tables**: Use dedicated tables for large datasets (e.g., archive table)
- **Batch operations**: Process large datasets in batches

### Caching
- **Transients**: Use WordPress transients for temporary data
- **Options**: Use options API with autoload=false for rarely-accessed data

### Queries
- **Avoid meta queries**: Meta queries are slow; use dedicated table columns when possible
- **Limit results**: Always limit query results to prevent memory issues
- **Count queries**: Use `SELECT COUNT(*)` instead of loading all rows for counts
