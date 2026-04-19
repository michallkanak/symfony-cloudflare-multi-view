# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [BETA] [0.1.0] - 2026-04-19

### Added

- Cloudflare GraphQL API client with adaptive rate-limit handling (`X-RateLimit-Remaining` header support)
- Multi-account support — each account forms an independent group on the dashboard
- Domain discovery command: `cf-multi-view:fetch-domains`
- Traffic statistics synchronization command: `cf-multi-view:sync-stats`
- Maintenance commands: `cf-multi-view:purge-stats`, `cf-multi-view:delete-account`
- Secure, password-protected dashboard with optional `secure_dashboard` flag and Logout functionality
- Modern Glassmorphism UI with dark mode support
- Interactive frontend with group-based traffic analysis (Chart.js)
- External HTML Tooltips for charts to prevent clipping and improve data readability
- "Blur Mode" for privacy — toggle visibility of domain names on the dashboard
- "Last data update" indicator based on actual database record update time (`updatedAt`)
- Time range selector: 1h, 3h, 6h, 12h, 24h, 48h, 7d
- Internationalization (i18n): English, Polish, French, German, Spanish, Italian, Czech, Swedish
- Doctrine ORM entities: `CfMultiViewDomain`, `CfMultiViewTrafficStat`
- Configurable display timezone support for dashboard analytics
- PHPStan level 8 compliance and comprehensive test suite
- Support for PHP 8.1, 8.2, 8.3
- Support for Symfony 5.4, 6.4 and 7.x
