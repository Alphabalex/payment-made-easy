# Security policy

## Supported versions

Security fixes are applied to the **latest minor release** on the default branch and, when applicable, backported to the most recent previous major line if it is still within the maintainer’s support window. Use the latest tagged release where possible.

## Reporting a vulnerability

**Please do not** file a public GitHub issue for undisclosed security vulnerabilities (that can put users at risk before a fix exists).

Send details privately to:

**[babusunnah@gmail.com](mailto:babusunnah@gmail.com)**

Use the subject line **`[SECURITY] payment-made-easy`** so messages are easy to triage.

### What to include

- A clear description of the issue and its impact.
- Steps to reproduce, or a proof-of-concept, if you can share them safely.
- Affected versions or commit SHAs, if known.
- Whether you would like attribution when we publish an advisory or release notes (we are happy to credit researchers who request it).

### What to expect

- We will acknowledge receipt as soon as we can.
- We will work on a fix and coordinate disclosure (typically after a release is available).
- Please allow reasonable time for the fix to be developed and tested before public disclosure.

## Out of scope

- Social engineering or physical attacks.
- Denial-of-service against infrastructure you do not own (report abuse to the hosting provider instead).
- Issues in **third-party** payment providers (report those to the gateway’s own security program).

## Safe harbor

If you make a good-faith effort to comply with this policy and avoid privacy violations, destruction of data, or service disruption, we will not pursue legal action against you related to your research.

## Credentials in issues and pull requests

Never paste **live** API keys, webhook signing secrets, private keys, or customer PII in public issues or PRs. Use redacted examples or test keys only.
