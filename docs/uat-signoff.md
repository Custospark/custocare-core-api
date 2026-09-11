# UAT Sign-off Sheet (per release)

To be completed for every release touching app code. No release ships to
staging/production without all boxes ticked and a named sign-off.

## Release

- Version/tag:
- Date:
- Scope (one line per change):
- Commits:

## Evidence (attach outputs)

- [ ] `composer vera:fast` green (BE) + `npm run vera:fast` green (FE)
- [ ] Targeted test suites green (names + counts):
- [ ] `tsc --noEmit` clean (FE, when frontend changed)
- [ ] Staging smoke: login flow + one core read + one core write
- [ ] Staging log sweep: no new ERROR lines
- [ ] Migrations reviewed: additive only, `--force` used, backup taken

## Sign-off

| Role | Name | Signature | Date |
|---|---|---|---|
| Developer (Mike) | | | |
| Owner (Oscar) | | | |
| Facility tester (UAT) | | | |

## Decision

- [ ] APPROVED for staging
- [ ] APPROVED for production
- [ ] REJECTED (reasons + rework):
