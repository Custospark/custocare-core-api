# Custocare Compliance Evidence Pack

Generated from `Health_Guidelines_Compliance.ipynb` (source of truth).
Share with MoH / PDPO / assessors as the defense summary.

## Status board

| ID | Theme | Requirement | Status | Evidence / Task |
|---:|---|---|---|---|
| 1 | SCOPE | Patient registration + unique ID + longitudinal records | PASS | Keep: patient_uuid + MRN hash; no task |
| 2 | SCOPE | Internal + external referrals | PASS | Keep referral flows; verify external-facility addressing |
| 3 | SCOPE | Stock management + surveillance reporting | PASS | Inventory + lab present; add notifiable-disease report export |
| 4 | SCOPE | Save-blocking validation + decision support | PASS | ClinicalRangeCheck gate on vitals create (550b7c8) + 7 tests; alerts already existed |
| 5 | SEC | RBAC + least privilege + facility scoping | PASS | Spatie roles active; review role matrix quarterly |
| 6 | SEC | PII encrypted; passwords + MFA + timeouts | PASS | PasswordRules 12/mixed/digit/symbol both stacks parity-tested (d952b08); MFA enforced at login; secure cookies prod-default |
| 7 | SEC | Tamper-proof audit logs, reviewed | PASS | Append-only enforced + tested (c9835b8); monthly audit:review rota scheduled |
| 8 | SEC | Automated offsite backups + monthly restore tests | PARTIAL | backup_records log + daily backup:verify + backup:record in deploy flow (22dcf33); offsite restore drill pending |
| 9 | SEC | Annual pen test OWASP Top10, no vuln >= 7.0 CVSS | GAP | Commission external pen test; file report for DHTR dossier |
| 10 | SEC | Change control: test env, backups, off-hours, signed records | PARTIAL | change_requests signed log + Vera gates + UAT sheet (94e152f); off-hours scheduling = process |
| 11 | SEC | Downtime SOP: paper fallback, 24h back-entry, incident logs | PARTIAL | Downtime SOP cell + paper-pack checklist in notebook; printed packs + drill = Oscar |
| 12 | PRIV | Prior consent incl. parental + nominee flows | PASS | Minor guardian + staff-witness enforcement with tests (3af8e76); nominee hierarchy documented |
| 13 | PRIV | Privacy notice: 9 items, displayed + website + languages | PARTIAL | GET /api/compliance/privacy-notice (d0117dc) + consent_form_version linkage; physical display at reception pending Oscar |
| 14 | PRIV | Breach: PDPO immediately, subjects 24h if high risk | PARTIAL | breach_incidents table + BreachService SLAs + breach:drill (1e32ded); public display + live drill = Oscar |
| 15 | PRIV | Retention 5y min + disposal law + processor 10-day delete | PARTIAL | retention schedule + weekly retention:review + audit certs (f5c2cdc); authorized disposal execution ongoing |
| 16 | PRIV | DSAR: access/correct/erase/block, 5-day SLA, downstream notify | PASS | Register + SLA + terminal guards + staff endpoints + downstream endpoint (02904f2) |
| 17 | PRIV | No offshore storage without PDPO auth + adequacy + MoH consent | PASS | UG hosting + transfer register with adequacy gates (c7e40b2) |
| 18 | PRIV | DPO appointed + PDPO registration + annual staff training | ORG | Oscar: appoint DPO, register product, schedule yearly training |
| 19 | INTEROP | DHIS2 HMIS push + NHIE registries + HL7/FHIR + ICD | PARTIAL | Dhis2Adapter dry-run + hmis:push monthly + FHIR Patient stub + ICD check (8e2aa6d); live MoH instance + UIDs = Oscar |
| 20 | INTEROP | Open documented APIs for third parties | PASS | docs/api-access.md access process (56885c4) |
| 21 | GOV | DHTR registration + approval chain (retroactive) | ORG | Oscar: register on DHTR, seek User-Department sponsorship |
| 22 | GOV | Signed UAT per release + same version everywhere | PASS | docs/uat-signoff.md per-release sheet (62c8445) |
| 23 | GOV | Isolated training instance + 5-day/70% training regime | GAP | Provision training env; record attendance + scores |
| 24 | GOV | Quarterly self-assessment + annual external assessment | ORG | Oscar: calendarize with MoH; keep evidence pack |
| 25 | GOV | Scale dossiers: transition/sustainability/capacity/spec/requirements | ORG | Assemble from runbooks + this notebook when scaling |

_Regenerate via the notebook flow; full Q&A lives in the export script history._
