# Custocare API Access (third parties)

How external systems integrate with Custocare — the documented, gated path
the guidelines demand (open APIs, reasonable third-party access, DPA cover).

## Environments

| Environment | Base URL |
|---|---|
| Staging | `https://custocare-staging-api.custospark.com/api` |
| Production | `https://custocareai-api.custospark.com/api` |

## Authentication

Laravel Sanctum personal access tokens (Bearer). Tokens are scoped per
integration; compromised tokens are revoked immediately and revocation is
logged in the audit trail.

## Versioning

Breaking changes ship under a new `/api/vN` prefix; the previous version is
supported for 6 months with sunset headers. Additive fields never break.

## Requesting access (process)

1. Applicant states use case, data elements needed, and retention.
2. Custocare checks data minimization (least necessary only) + legal basis.
3. Signed Data Processing Agreement (purposes, duration, deletion within 10
   business days of cessation, no sub-processors without written consent).
4. Cross-border applicants additionally need: PDPO authorisation reference +
   adequacy proof + MoH written consent (recorded in `data_transfer_records`).
5. Scoped token issued on staging; production promotion after integration test.

## Standards roadmap

HL7 FHIR resource stubs and DHIS2 aggregate push are tracked as GAP-19.
ICD-coded diagnoses already flow in clinical encounters.
