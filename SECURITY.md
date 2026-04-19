# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | ✅ |

## Reporting a Vulnerability

We take the security of this project seriously. If you have discovered a security vulnerability, please report it responsibly.

### How to Report

1. **Do NOT** open a public GitHub issue for security vulnerabilities
2. Email the maintainer directly at **kanakmichal [at] gmail.com**
3. Include as much information as possible:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if any)

### What to Expect

- **Acknowledgment**: We will acknowledge your report within 48 hours
- **Investigation**: We will investigate and validate the vulnerability
- **Fix**: We will work on a fix and coordinate disclosure
- **Credit**: We will credit you in the release notes (unless you prefer anonymity)

### Security Best Practices

When using this project:

1. **Update regularly**: Keep all dependencies up to date
2. **Secure API tokens**: Never commit Cloudflare API tokens or Account IDs to the repository
3. **Environment variables**: Store sensitive credentials in `.env.local` or equivalent — never in `.env` committed to version control
4. **Secure dashboard**: Use the `secure_dashboard` option and a strong password for the stats dashboard

## Dependencies

We regularly update dependencies to patch known vulnerabilities. Run these commands to check:

```bash
# Check PHP dependencies
composer audit
```
