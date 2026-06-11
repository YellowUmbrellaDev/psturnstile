# AGENTS.md

Guidance for AI agents working on this repository.

## Project

This repository contains the `psturnstile` PrestaShop module. The module source
lives at the repository root so development is simple, but release/test ZIPs must
contain a top-level `psturnstile/` directory for PrestaShop installation.

## Important Paths

- `psturnstile.php` - main PrestaShop module class.
- `src/` - PHP services, controllers, and form classes.
- `config/` - PrestaShop/Symfony routes and services.
- `views/` - Smarty/Twig templates.
- `.github/workflows/release.yml` - automated release workflow.
- `justfile` - local development commands.
- `LICENSE` - custom non-OSI license terms.

## Rules

- Keep the main module class thin. Put real logic in `src/Service` or form/controller classes.
- Do not invent PrestaShop or Symfony classes. Verify APIs against existing code/docs.
- Do not auto-inject Turnstile into arbitrary theme markup with fragile selectors.
- Keep the ZIP installable as `psturnstile/` at archive root.
- Preserve the custom license and upstream attribution requirements.
- Do not commit or push unless the user explicitly asks.

## Checks

Run before reporting completion:

```bash
just check
```

Useful commands:

```bash
just --list
just validate
just lint
just package
coderabbit review --agent -t uncommitted --base master
```

`just package` creates `build/psturnstile.zip` for testing in a PrestaShop shop.
It is not the release versioning source.

## Release Flow

Merges/pushes to `master` trigger `.github/workflows/release.yml`.
The workflow bumps a semver git tag, creates a ZIP with top-level `psturnstile/`,
and publishes a GitHub Release with generated release notes from commits.

## PrestaShop Notes

- Target PrestaShop 9.
- Composer autoload must use `prepend-autoloader: false`.
- Configuration keys must remain prefixed with `PSTURNSTILE_`.
- Server-side Turnstile validation is mandatory; client-side widget rendering is not enough.
- Custom form protection is rule-based and depends on `cf-turnstile-response` being submitted.

## Agent Coordination

- Prefer subagents for implementation/review tasks when work is non-trivial.
- Use `mid-dev` for normal implementation and `opus-reviewer` for final review.
- Keep changes minimal and focused.
- Treat CodeRabbit findings as candidates: verify before applying.
