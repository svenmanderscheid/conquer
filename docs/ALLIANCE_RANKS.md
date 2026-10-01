# Alliance member ranks

Union of Kingdoms exposes the existing five alliance roles consistently as R1–R5. The role enum remains authoritative; `role_level` is a synchronized numeric representation, never a client-controlled permission.

| Rank | Role | Existing authority |
| --- | --- | --- |
| R1 | Member (`member`) | Ordinary membership functions |
| R2 | Veteran (`veteran`) | Ordinary membership functions; honorary seniority |
| R3 | Officer (`officer`) | Planning, notices, polls, pinned alliance messages and alliance structures |
| R4 | Deputy leader (`vice_leader`) | R3 rights plus recruitment, research, diplomacy and managing R1–R3 |
| R5 | Alliance leader (`leader`) | R4 rights plus managing R1–R4 and the existing leader-only controls |

Members cannot change their own rank, peers or higher ranks. The server supplies `members[].assignable_roles` to the rank form and revalidates the current membership while holding the existing alliance lock. R5 is assigned exclusively by confirmed leadership transfer. The previous leader becomes R1, preserving the existing game rule. Transfer requires both the leader role and the alliance's designated `leader_id`.

`src/Game/Alliance/AllianceRank.php` defines the server mapping. `assets/js/alliance-ranks.js` provides localized presentation in the alliance overview, member list, rank management, profiles, planning and alliance chat. English remains the default and fallback. Badges use the shared UI colors and do not represent item rarity.

Chat badges show current membership in the message's alliance and world. The live response includes the complete current member-role list so loaded older messages also update after a promotion, demotion or departure. Delayed history responses cannot restore an outdated rank.

## Existing installations

Apply `migrations/0124_alliance_member_ranks.sql` through the normal migration process. It reconciles numeric ranks from roles. When the designated leader is already a member of that exact alliance and world, it makes that member R5 and demotes additional leader rows to R1. It never invents missing memberships or selects a replacement leader for inconsistent legacy data. The migration is idempotent. This implementation was tested in disposable databases; the user's existing database was not migrated during the task.

## Verification

- `tests/alliance_ranks.php`: 509 assertions covering every actor/target rank combination and requested rank, server-provided choices, manipulated values, scope boundaries, persistence, replay, recruitment, leadership races and migration repair.
- `tests/alliance_ranks_ui.cjs`: 183 pure presentation and permission-form checks.
- `tests/alliance_ranks_app.cjs`: real application at 1280×800, 390×844, 320×568 and 568×320; persisted promotion/demotion, public profiles, R3/R4 restrictions, leadership transfer and EN/DE/FR presentation.
- `tests/community_social.php` and `tests/community_chat_app.cjs`: sender-specific badges, world/alliance scoping, older-message cache updates and preserved compact chat geometry.
- Existing community, recruitment, kingdom, localization, migration and isolated panel suites remain part of the regression checks.

Run database suites serially: game advisory locks are shared across disposable databases. Evidence for this change is under `artifacts/alliance-ranks-2026-10-01/`. The first app run exposed undersized save buttons; the corrected run is in `app-recheck/`. The legacy kingdom regression fixture now explicitly creates a supported 256×256 test world without altering its assertions or production data.
