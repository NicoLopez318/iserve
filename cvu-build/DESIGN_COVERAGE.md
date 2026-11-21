# Figma implementation coverage

Source: https://www.figma.com/design/RDadFEcQ8raHxNun53JKKF/Untitled?node-id=0-1

The design is a canvas with many repeated form states and loose components. Shared components and live states implement those variants.

| Figma frames | Implemented destination |
|---|---|
| 1:3770, 1:3825 | /register, /login |
| 1:2879 and employee/guardian variants | /profile/setup |
| 1:3439 | /transactions |
| 1:2753, 1:3561, 1:3719 | /apply/leadership → request confirmation |
| 1:3588, 1:3619, 1:3644 | /apply/scholarship → request confirmation |
| 1:2786, 1:2968, 1:3655 | /apply/complaint → request confirmation |
| 1:2817, 1:3121, 1:3666 | /apply/id-replacement → request confirmation |
| 1:2848, 1:2956, 1:3327, 1:3383, 1:3697 | /home?choose=lost-found → /apply/lost or /apply/found |
| 1:2930, 1:3059, 1:3708 | /apply/readmission → request confirmation |
| 1:3177, 1:3677 | /apply/clearance → request confirmation / appointment details |
| 1:3228, 1:3291 | /apply/seminar → /portal/leadership/events |
| 1:3237, 1:3257, 1:3268, 1:3279 | /community-link, email verification, active/no-record states |
| 1:249, 1:371, 1:475, 1:570, 1:657, 1:739 | /portal/values/{dashboard,violations,community-service,journal,profile,settings} |
| 1:1022, 1:814 | /portal/leadership/dashboard, /portal/leadership/events |
| 1:1582, 1:1747 | /portal/leadership/organization, /portal/leadership/members |
| 1:1894 | /portal/leadership/leaderboards |
| 1:2078, 1:2251, 1:2570 | /portal/leadership/profile, /apply/achievement, /portal/leadership/archives |
| 1:2275, 1:2624 | /apply/activity, /portal/leadership/settings |
| 1:1270, 1:1342, 1:1413, 1:1514 | /portal/scholar/{dashboard,tuition,profile,settings} |
| Staff flow, extended beyond editable student designs | /staff, /staff/reports |
| 1:3872–1:3884, seven image-only frames | /, /services, /units/leadership, /units/scholarship, /units/values, /announcements, /about |

Detailed design responses are preserved in design-reference/. Downloaded original assets are in public/design-assets; assets.json is the provenance manifest. Inter and Montserrat are bundled locally.

## Deliberate flow corrections
- Success confirmations identify the actual transaction instead of copying the complaints message into unrelated forms.
- Forms use the authenticated profile; they cannot submit a different user's student ID to acquire another record.
- A scholarship applicant cannot choose their own discount rate.
- Net tuition is positive original charges less approved savings, rather than the negative total shown in the sample.
- Progress uses verified hours, not a static 70% label.
- Events award EXP only after attendance and reflection verification, once per source.
- Final completion requires journals as well as hours.
- Email verification uses signed, expiring verification links; there is no hardcoded verification code.
- Mobile download action leads to the available web portal because no APK was supplied.
- Static design sample names/numbers become live data. Demo avatars remain original design placeholders until a profile photo is uploaded.

## Local source completion — 2026-10-02
The user supplied Untitled.fig. Its embedded canvas, node schema and original images were decoded locally. All 4,974 nodes and every top-level frame were inventoried; a copy of that inventory is included in design-reference/figma-frame-inventory.json. Original photo/emblem crops for the seven public pages have source hashes and crop coordinates in public/design-assets/public/provenance.json.

Added public pages, searchable/filterable service directory, unit navigation, database announcements with office authorization, complaint categories/additional fields, readmission subject code, ID location, all fourteen pre/post activity document slots with 10 MB limits, transaction Next/Back steps, four journal modules and private activity drafts. Retained draft files remain private and move into the submitted request.

Remaining design differences: full pixel comparison of all portal/form frames is not verified. Activity editor and archive screens use shared layouts. Edit Moderators is implemented with organization-scoped selection and president/Leadership staff authorization. Public leadership retains the design's Catalog being finalized state. The responsive web portal replaces the supplied APK banner action because no APK was supplied. These limitations are recorded rather than claiming exact full-design completion.
