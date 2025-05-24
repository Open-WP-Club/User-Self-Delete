# User Self Delete

A GDPR-compliant WordPress plugin that allows users to easily delete their own accounts without unnecessary barriers, meeting Article 17 (Right to Erasure) requirements.

## Features

### 🚀 Core Functionality

- **Direct Account Deletion**: Users can delete their accounts directly from their dashboard
- **GDPR Compliant**: Meets Article 17 requirements with minimal barriers
- **Security**: Password confirmation required for account deletion
- **WooCommerce Integration**: Handles e-commerce data appropriately

### 🛡️ Security & Privacy

- **Password Verification**: Users must confirm their password before deletion
- **Audit Logging**: Optional logging of all deletion requests
- **Admin Notifications**: Optional email notifications to administrators
- **Data Anonymization**: WooCommerce orders can be anonymized instead of deleted

### 📊 Data Handling

- **Complete Erasure**: Removes all user data and metadata
- **WooCommerce Orders**: Choice between anonymization or complete deletion
- **User Posts**: Can be reassigned to admin or deleted
- **Plugin Integration**: Supports common plugins (BuddyPress, bbPress, Ultimate Member)

### ⚙️ Admin Features

- **Settings Panel**: Configure deletion behavior and notifications
- **Deletion Statistics**: View deletion trends and statistics
- **Compliance Dashboard**: GDPR compliance information and guidelines

## Installation

### Manual Installation

1. Download the plugin files
2. Upload the `user-self-delete` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin
4. Configure settings in **Settings > User Self Delete**

### Configuration

Navigate to **Settings > User Self Delete** to configure:

- **Enable Logging**: Track deletion requests for audit purposes
- **Admin Notifications**: Receive email when users delete accounts
- **Order Handling**: Choose to anonymize or delete WooCommerce orders
- **Post Handling**: Reassign user posts to admin or delete them

## Usage

### For Users

1. **WooCommerce Sites**: Go to **My Account > Dashboard**
2. **Standard WordPress**: Available in user profile
3. Click **"Delete My Account"** button
4. Review what data will be deleted
5. Enter password to confirm
6. Confirm deletion in the popup

### For Administrators

- Monitor deletion activity in the admin dashboard
- Configure data handling preferences
- Review GDPR compliance information
- Export deletion logs for auditing

## GDPR Compliance

### Article 17 - Right to Erasure

✅ **Easy Access**: Delete button prominently displayed in user dashboard  
✅ **No Unnecessary Barriers**: Only password confirmation required  
✅ **Complete Erasure**: All personal data removed  
✅ **Audit Trail**: Deletion requests logged for compliance  

### Data Processing

- **Personal Data**: Completely removed from all WordPress tables
- **Order Data**: Anonymized to maintain legal/tax compliance
- **Content**: User posts can be reassigned or deleted based on settings
- **Third-party Data**: Hooks provided for other plugins to clean up

### Legal Basis Preservation

- WooCommerce orders can be anonymized instead of deleted
- Maintains transaction records for tax and legal compliance
- Removes all personally identifiable information

## Technical Details

### What Gets Deleted

- User account and profile information
- All user metadata
- Personal comments
- WooCommerce customer data (addresses, payment info)
- Integration data from supported plugins

### Database Changes

- Creates logging table: `wp_user_self_delete_log`
- Removes user data from all relevant WordPress tables
- Anonymizes or removes WooCommerce data based on settings

### Hooks & Filters

```php
// Before user deletion
do_action('user_self_delete_before_deletion', $user_id, $user);

// After user deletion
do_action('user_self_delete_after_deletion', $user_id, $user);

// Plugin data cleanup
do_action('user_self_delete_cleanup_plugin_data', $user_id);
```

## Plugin Compatibility

### Fully Supported

- **WooCommerce**: Complete integration with order handling
- **BuddyPress**: Activity and profile data removal
- **bbPress**: Forum posts and replies handling
- **Ultimate Member**: Profile and metadata cleanup

### Extensible

- Hooks provided for other plugins to integrate
- Can be extended to support additional plugins
- Compatible with most WordPress setups

## Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **WooCommerce**: 4.0+ (optional, for e-commerce features)

## Security Considerations

- Users must enter their current password to delete account
- All deletion attempts are logged with IP addresses
- Admin notifications help monitor account deletions
- Hooks allow additional security measures

## Legal Disclaimer

This plugin helps meet GDPR requirements but does not guarantee full legal compliance. Consult with legal counsel to ensure compliance with applicable data protection laws in your jurisdiction.

## Support

For support, feature requests, or bug reports, please use the plugin support forum or contact the developer.

## Changelog

### Version 1.0.0

- Initial release
- GDPR-compliant account deletion
- WooCommerce integration
- Admin settings panel
- Audit logging
- Multi-plugin support

## License

GPL v2 or later - same as WordPress core.
