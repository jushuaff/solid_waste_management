# Notification Events & Recipient Rules

This guide documents the **online in-app notification** behavior in the combined MIT 103 / MIT 104 Barangay Solid Waste Management System.

## Who receives which notifications?

| Event | Resident owner / affected residents | Assigned collection crew | In-zone coordinator | Barangay admin | Superadministrator | Eco-aide / MRF staff |
| --- | --- | --- | --- | --- | --- | --- |
| Incident first submitted / Under Review | Own report | — | Yes | Yes | Yes | — |
| Incident verified / assigned / started / resolved / exception / rejected / closed | Own report | If currently assigned | Yes | Yes | Yes | — |
| Resident edits incident details or adds/removes evidence | — (they made the edit) | If assigned | Yes | Yes | Yes | — |
| Crew reassignment | Status alert to owner | New crew assigned; previous crew receives a withdrawal notice | Yes | Yes | Yes | — |
| Incident archived | Own report | If still assigned | Yes | Yes | Yes | — |
| Schedule published, revised or withdrawn | Affected area only | Affected area only | Affected area only | Yes | Yes | Affected area only |
| Special-date schedule change published, revised or withdrawn | Affected area only | Affected area only | Affected area only | Yes | Yes | Affected area only |
| Rule/guideline published, revised or withdrawn | Affected area (or all areas for global rules) | Affected area | Affected area | Yes | Yes | Affected area |
| Non-segregated inspection recorded | — | — | In-zone | Yes | Yes | — |
| MRF record changed or archived | — | — | Affected zone(s) | Yes | Yes | — |
| 48-hour overdue incident, sustained red-zone compliance | — | — | In-zone | Yes | Yes | — |

No notifications are sent to *other residents* about somebody else's private incident. A user should only receive events relevant to their current role and assigned zone. The report's former assigned crew is notified when the assignment changes, but that notification intentionally does not link to private case details now outside the crew's access.

## What makes a notification "read"?

The sidebar and header bell show an unread dot when the authenticated user has at least one `notices.is_read=false` record. The notifications inbox highlights unread items. **Opening** a notification performs an authenticated POST, marks that recipient's notification read, then redirects them to a permitted in-app page. Once every notice is read, the dot is removed on the next response.

This is **page-refresh-based** for the online-first release. WebSockets and live push are deliberately deferred. The periodic compliance/overdue checker requires Laravel's scheduler to be configured.

## Schedule and rules updates

- Creating a published schedule, schedule exception or rule alerts residents and field staff in the affected area.
- Revising an **already published** record alerts them again with the updated item details.
- Withdrawing a published record alerts the previously affected audience so outdated guidance can be disregarded.
- Changing a record from one zone to another alerts users in **both** affected zones; administrators receive one notice each.
- Draft-only changes do not create public notices. A no-op save should not create a publication notice.
- Published schedules cannot be physically deleted from the management panel; withdraw them by changing publication status instead.

## Incident workflow notes

- Report creation moves through an internal `Submitted` state to `Under Review` and produces the actionable intake notice.
- Assigning a crew member internally passes through `Verified`; only the final assignment emits a notification to avoid two alerts for a single user action.
- Subsequent status updates notify the resident, relevant administrators and coordinator, and the currently assigned crew.
- Resident edits and evidence changes alert responsible staff; incident archive events alert authorized participants.

## Local verification checklist

All of these must be exercised against your local MongoDB installation; merely committing the notification code does not establish successful delivery.

- [ ] Sign in as Pine resident, create a report, and confirm Pine coordinator, admins and superadmin see an unread notice. Ridge coordinator and other residents must not.
- [ ] Edit the pending report and add/remove evidence; confirm responsible staff receive the update without notifying unrelated residents.
- [ ] Assign the Pine crew; confirm crew inbox receives the task notice and unread dot.
- [ ] Change case status; confirm authorized recipients get one meaningful status notice.
- [ ] Reassign to another eligible crew and check the previous crew sees a withdrawal notice, not a link to a now-restricted case.
- [ ] Publish and edit a Pine schedule; confirm Pine residents, Pine staff and administrators receive notices, but Ridge residents do not.
- [ ] Withdraw the schedule and confirm a withdrawal notice; save an unchanged schedule and confirm no duplicate.
- [ ] Create/update a global barangay rule and verify all active residents receive the notice; for zone-scoped rules, only the affected area.
- [ ] Record a non-segregated inspection; verify coordinator and admin alerts.
- [ ] Edit an MRF weigh-in; verify one notice per authorized recipient even if the zone changes.
- [ ] Open a notification; confirm it becomes read and the header/sidebar dot clears when no unread notices remain.
- [ ] Attempt to access another account's notice ID and a private case link after reassignment: access must be denied or safely redirected.
- [ ] Start the Laravel scheduler and validate overdue and sustained-low-compliance scenarios without duplicated hourly notices.

See [MIT103-104.MD](../MIT103-104.MD) for overall feature status and unverified acceptance tests.
