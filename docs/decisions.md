# Architecture Decision Records

> Each entry records a design decision, its context, and the trade-offs considered.
> Read this before starting any feature to avoid repeating past mistakes.

---

## 2026-05-18: Team Structure Mirrored from Frontend

**Context:** The Backend project needs the same sub-agent team, handoff chain, and quality gates as the Frontend to ensure consistent execution across both stacks.

**Decision:**
- Adopted the same 5-agent team: Sage (Planning), Blue (Architect), Rex (Code), Vera (Test), Quill (Docs)
- Same handoff chain: Mike → Sage → Blue* → Rex → Vera → Quill → Mike → Oscar
- Blue skipped for changes ≤2 files; Sage hands directly to Rex
- Added Retro step after every feature to catch recurring issues before they compound
- Added Branch & PR Workflow section for when collaborators join
- Added Pre-Commit Hooks section (PHP syntax check via `php -l` on staged files)

**Trade-offs:**
- Identical structure across FE and BE means slightly different pre-commit tooling (eslint vs php -l) but the same mental model
- Retro adds ~30s per feature but prevents compounding design debt

---

## 2026-05-18: Pre-Commit Hooks via Husky + Lint-Staged

**Context:** Vera catches issues before commits but is manual. Automating syntax checks on every commit prevents trivial errors from reaching the remote.

**Decision:**
- Installed `husky` + `lint-staged` in the Backend project
- Pre-commit hook runs `php -l` on staged `.php` files
- Hook does NOT run full PHPUnit or migration tests (too slow for pre-commit — those remain Vera's job)

**Trade-offs:**
- `php -l` only catches parse errors, not logic bugs — but that's the right scope for a pre-commit fast guard
- Full test suite stays in Vera's domain; pre-commit is purely a syntax gate
