# Staff design coverage

37 primary role screens are mapped below. Context states share live components rather than copying demonstration data.

| Role | Screen | Figma node | Route |
|---|---|---|---|
| cvu_head | Dashboard | 21:1035 | /workspace/dashboard |
| cvu_head | Complaints & Concern | 21:1192 | /workspace/complaints |
| cvu_head | Lost & Found | 21:1357 | /workspace/lost-found |
| cvu_head | Re-Admission | 21:1525 | /workspace/readmission |
| cvu_head | ID Replacement | 21:1738 | /workspace/id-replacement |
| cvu_head | Community Service | 21:1918 | /workspace/community |
| cvu_head | Seminar | 21:2458 | /workspace/seminars |
| cvu_head | Clearance | 21:2612 | /workspace/clearance |
| cvu_head | Reports | 21:2762 | /workspace/reports |
| cvu_head | Settings | 21:2927 | /workspace/settings |
| sfu_head | Scholarship & Financial Unit | 21:4687 | /workspace/dashboard |
| sfu_head | Tuition Fee Discount Applications | 21:4940 | /workspace/applications |
| sfu_head | Scholarships & Financial Reports | 21:5164 | /workspace/reports |
| sfu_head | Settings | 21:5326 | /workspace/settings |
| slu_head | Dashboard | 21:5440 | /workspace/dashboard |
| slu_head | Organization | 21:5590 | /workspace/organizations |
| slu_head | Proposals | 21:5811 | /workspace/proposals |
| slu_head | Events & Activities | 21:6006 | /workspace/events |
| slu_head | Achievements | 21:6500 | /workspace/achievements |
| slu_head | Leaderboards & Points | 21:6621 | /workspace/points |
| slu_head | Reports | 21:6827 | /workspace/reports |
| slu_head | Portal Content | 21:6931 | /workspace/content |
| slu_head | Settings | 21:7496 | /workspace/settings |
| assistant | Dashboard | 21:3418 | /workspace/dashboard |
| assistant | Report inbox | 21:3658 | /workspace/inbox |
| assistant | Student Leadership | 21:3777 | /workspace/leadership |
| assistant | Scholarship & Financial | 21:3875 | /workspace/scholarship |
| assistant | Character & Values | 21:3973 | /workspace/values |
| assistant | Submission History | 21:4071 | /workspace/history |
| assistant | Settings | 21:4221 | /workspace/settings |
| admin | Admin Dashboard | 26:1615 | /workspace/dashboard |
| admin | Reports for review | 26:1810 | /workspace/inbox |
| admin | Student Leadership | 26:1904 | /workspace/leadership |
| admin | Scholarship & Financial | 26:1991 | /workspace/scholarship |
| admin | Character & Values | 26:2078 | /workspace/values |
| admin | Review History | 26:2223 | /workspace/history |
| admin | Settings | 26:2339 | /workspace/settings |

## Known differences requiring further design work

- Some contextual review panels use full detail pages or native dialogs instead of the exact Figma placement and dimensions.
- SFU dashboard review-panel placement and some module-specific table geometry require further visual matching.
- Organization and community-service forms save records immediately; their separate draft states are not implemented.
- External MIS checks and outbound SMTP are not connected. Local account checks and in-app notifications are implemented.
- All point-rule fields are stored, but only achievement scoring and verified event attendance currently have automatic triggers.
- Full canvas fidelity has not been validated across every contextual state.
