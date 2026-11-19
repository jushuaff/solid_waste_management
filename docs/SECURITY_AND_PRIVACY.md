# Security and privacy notes

This document is implementation guidance, not a substitute for a formal barangay privacy notice, privacy impact assessment or security review.

## Controls provided by the Laravel source

- Passwords hashed; login/registration/password reset throttled.
- CSRF protection for HTML forms and session regeneration on login.
- Server-side role checks, resident report ownership, and crew-assignment checks.
- Dedicated status-transition state machine and MongoDB status-history records.
- Private local storage for uploaded photo evidence with authenticated download.
- Sensitive environment configuration excluded from Git.
- Mail password reset tokens are hashed and time-limited; generic response prevents email enumeration.

## Required deployment decisions

- Determine the appropriate lawful basis and purposes for residents' personal data.
- Confirm privacy notice and retention/destruction policy with adopting barangay.
- Establish named administrators, incident response contacts and audit review procedures.
- Check existing MongoDB users and indexes, upgrade MongoDB to a replica set/Atlas.
- Configure HTTPS, CSP/security headers, email sender verification and vulnerability scanning.
- Verify `APP_DEBUG=false` and backup/restore procedures.
- Perform actual manual/automated authorization and CSRF tests with multiple accounts.
- Replace sample zone/categories with approved local information.
