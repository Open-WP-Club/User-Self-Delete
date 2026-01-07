# Claude Code Configuration Files

This directory contains configuration files that help Claude Code understand and work with the User Self Delete plugin more effectively.

## What's in this directory

### project-info.md
High-level overview of the plugin:
- What the plugin does and why it exists
- Key features and capabilities
- System requirements
- Core concepts (soft delete, retention periods, etc.)
- Database schema overview
- Integration points with WooCommerce and other plugins

**Use this when:** You need to understand the plugin's purpose, features, or overall architecture.

### conventions.md
Coding standards and patterns used in the plugin:
- PHP type safety and strict typing rules
- WordPress Coding Standards compliance
- Security best practices (XSS prevention, SQL injection, etc.)
- File organization and naming conventions
- JavaScript and CSS standards
- Documentation requirements

**Use this when:** Writing new code or modifying existing code to maintain consistency.

### architecture.md
Technical architecture and system design:
- Detailed class hierarchy and responsibilities
- Data flow diagrams
- Database schema with full table definitions
- REST API endpoint specifications
- Frontend integration details
- WP-CLI command structure
- Action hook specifications
- Performance optimization strategies

**Use this when:** You need to understand how the plugin works internally or need to modify core functionality.

### common-tasks.md
Step-by-step guides for frequent development tasks:
- Adding new countries with retention periods
- Modifying the deletion process
- Adding custom cleanup logic
- Testing the deletion flow
- Debugging issues
- Database maintenance
- Extending the admin interface
- REST API integration examples
- Plugin compatibility additions

**Use this when:** Performing specific development tasks or maintenance work.

### quick-reference.md
Quick lookup reference for:
- File locations with line numbers
- Key constants and their values
- Database table structures
- WordPress options
- REST API endpoints
- WP-CLI commands
- Action hooks with signatures
- Common code patterns
- Troubleshooting guide
- Security notes
- Performance tips

**Use this when:** You need to quickly look up a specific detail or command.

## How Claude Code uses these files

When you start a new session with Claude Code, these files help Claude:

1. **Understand the project context** without needing lengthy explanations
2. **Follow established coding patterns** and conventions
3. **Know where to find specific functionality** in the codebase
4. **Provide better suggestions** that align with the project architecture
5. **Avoid common mistakes** by following security and performance best practices

## Keeping these files updated

These files should be updated when:

- **Major features are added** → Update project-info.md and architecture.md
- **Coding standards change** → Update conventions.md
- **New common patterns emerge** → Add to common-tasks.md
- **API changes** → Update quick-reference.md and architecture.md
- **New integrations added** → Update relevant sections across all files

## File organization tips

- Keep files focused on their specific purpose
- Use clear headers and sections for easy navigation
- Include code examples where helpful
- Reference specific file locations and line numbers when applicable
- Update version numbers when significant changes occur

## Additional resources

- Main README: `/README.md` - User-facing documentation
- GitHub: https://github.com/Open-WP-Club/User-Self-Delete
- WordPress Plugin Directory: (if published)

## Questions or improvements?

If you find these files helpful or have suggestions for improvement, please contribute back to the project!
