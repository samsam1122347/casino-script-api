# Security policy

## Supported versions

Security fixes are applied to the latest `main` branch of the published CrashX API and CrashX Portal repositories.

## Reporting a vulnerability

Please **do not** open public issues for security-sensitive reports.

- Email: **security@script.casino** (preferred)
- Or use your host’s private vulnerability reporting (e.g. GitHub Security Advisories) if enabled on the published repo

Include steps to reproduce, impact, and any suggested fix. We aim to acknowledge reports within a few business days.

## Operator responsibilities

CrashX is operator software. Deployers must:

- Set strong `ADMIN_PASSWORD` and rotate Filament credentials
- Keep `APP_DEBUG=false` and secrets out of version control
- Configure CORS, Sanctum stateful domains, and TLS for production
- Review wallet, withdraw, and crash rate limits before scale-out
