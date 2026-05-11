# Contributing to Payment Made Easy

Thank you for helping improve **nexuspay/payment-made-easy**. This document explains how we work together on the package.

## Code of conduct

Be respectful and constructive in issues and pull requests. Assume good intent, stay on topic, and keep feedback actionable.

## What to contribute

- Bug fixes with a clear reproduction path (or a failing test).
- Tests that cover edge cases or regressions.
- Documentation improvements (**README**, **CHANGELOG**, config comments) when behavior or configuration changes.
- Small, focused enhancements that match existing patterns in `src/`.

For large features (new gateways, breaking API changes), open an issue first so maintainers can agree on shape and compatibility targets.

## Security

Do **not** open public issues for undisclosed security problems. Do **not** paste live API keys, webhook secrets, or customer data in issues or PRs.

See **[SECURITY.md](SECURITY.md)** for how to report vulnerabilities privately and what we consider in scope.

## Local development

Requirements:

- **PHP 8.1+** (match the versions you intend to support; **Laravel 13** needs **PHP 8.3+**).
- **Composer 2.x**

From the package root:

```bash
composer install
```

The dev toolchain includes **Orchestra Testbench** and **PHPUnit**. **`stripe/stripe-php`** is a dev dependency so Stripe driver and webhook tests can run in CI and locally.

## Running tests

```bash
composer test
# or
./vendor/bin/phpunit
```

Focused runs:

```bash
composer test:unit
composer test:feature
```

All tests should pass before you open a PR. CI exercises multiple **Laravel** and **PHP** combinations (see `.github/workflows/ci.yml`); if something fails only on a specific matrix row, mention it in the PR description.

## Project conventions

- **Layering:** Keep HTTP concerns in controllers, gateway HTTP in drivers (`src/Drivers/`), webhook verification and event mapping in `src/Webhooks/`, and persistence helpers in `src/Services/` where applicable.
- **Style:** Follow existing naming (PascalCase classes, camelCase methods/properties). Prefer small, readable methods over deep nesting.
- **Config:** New behavior should be configurable under `config/payment-gateways.php` with sensible defaults and `env()` keys documented in **README** when user-facing.
- **Secrets:** Never commit credentials. Use placeholders in docs and tests.

## Pull requests

1. **Branch** from the default branch with a descriptive name (for example `fix/webhook-idempotency-ttl`).
2. **Describe** what changed and why in the PR body (not only the commit title).
3. **Scope:** Prefer one logical change per PR. Large refactors are easier to review when split.
4. **Tests:** Add or update tests for behavior changes. Bug fixes should include a regression test when practical.
5. **Docs:** Update **README** or **CHANGELOG** ([Keep a Changelog](https://keepachangelog.com/) style) when users need to know about the change (new config, breaking behavior, new gateway requirements).

## Commit messages

Use [Conventional Commits](https://www.conventionalcommits.org/) where possible:

| Prefix | Use for |
|--------|---------|
| `feat:` | New user-visible behavior |
| `fix:` | Bug fixes |
| `docs:` | Documentation only |
| `test:` | Test additions or fixes |
| `refactor:` | Internal restructuring without behavior change |
| `chore:` | Tooling, CI, dependencies without feature/fix semantics |

Example: `fix: reject webhooks when signing secret missing and required`

## License

By contributing, you agree that your contributions will be licensed under the same terms as the project (**MIT** — see [LICENSE](LICENSE)).
