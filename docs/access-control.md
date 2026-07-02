# Access control

xismarket controls staff access on four axes: **what** they can do (roles /
permissions), **who** can sign in (active accounts), **when** they can sign in
(access hours), and **where** from (approved devices). Owners and super-admins
bypass the who/when/where restrictions so they can never lock themselves out.

---

## What — roles & permissions

Every menu item and action is gated by a permission. Permissions are grouped and
assigned to **roles**; a staff member's access is the union of their roles'
permissions.

- Manage roles: **Administration → Roles** (needs `roles.manage`). Tick/untick
  permissions per role; the menu updates automatically for everyone in that role.
- Assign roles to staff: **Administration → Staff → edit**.
- Owners/super-admins see and can do everything regardless of roles.

To restrict a feature, remove its permission from the role — e.g. take
`payments.manage` off the Cashier role so cashiers can't open payment settings.

---

## Who — active accounts

Each staff account has an **Active** toggle (*Administration → Staff → edit*).

- **Inactive → cannot sign in** (login is refused; any open session is ended).
- Use this to suspend someone without deleting them (keeps their history).

---

## When — access hours

Restrict the times a staff member may sign in.

- **Role schedule** (default for its members): *Administration → Roles → edit →
  Access hours* — pick days + a time window (e.g. Cashier: Mon–Sat 07:00–20:00).
- **Per-user override**: *Staff → edit → Access hours* — overrides the role for
  that person.
- **Enforcement:** checked at login **and on every request**, so a session is
  logged out when the window closes. Message: *"Access is closed right now.
  Allowed hours: …"*.
- **No schedule = unrestricted.** A user with any unscheduled role stays
  unrestricted. Owners/super-admins always bypass.
- **Timezone:** windows compare in the store timezone — set it under
  *Administration → Branding → Timezone* (e.g. `Africa/Lagos`). **Set the
  timezone before setting hours**, or windows are off by the UTC offset.

---

## Where — approved devices

Limit sign-in to devices you've approved (e.g. shop tills only).

1. **Turn it on:** *Administration → Branding* → **"Only allow approved devices
   to sign in"** → Save. This **auto-approves the device you're on** (no
   lockout).
2. **A new device** trying to sign in is refused (*"This device isn't approved
   yet…"*) and appears as **Pending** in *Administration → Devices*.
3. **Approve / revoke / rename / remove** devices there.

- **Enforcement:** at login **and every request** — revoking a device ends its
  sessions immediately.
- **How it identifies a device:** a long-lived signed cookie per browser. So
  "a device" is really a browser profile — clearing browser data makes it a new
  pending device to re-approve.
- **Device names:** the label is auto-detected (e.g. `Chrome on Android
  (Pixel 7)`); browsers can't read the OS hostname, so **rename each device**
  to its location (e.g. "Front Counter Till") when you approve it.
- Owners/super-admins bypass, so an admin can always sign in to approve devices.

---

## Related controls

- **Dashboard summaries** are gated by `dashboard.view` — non-permitted staff see
  the dashboard without the business figures.
- All four axes combine: e.g. a Cashier who is Active, within hours, on an
  approved till, sees only the POS-relevant menu.

---

## Quick reference

| Goal | Where |
| --- | --- |
| Change what a role can do | Administration → Roles |
| Assign roles to a person | Administration → Staff → edit |
| Suspend a person | Staff → edit → untick Active |
| Limit a role's hours | Roles → edit → Access hours |
| Different hours for one person | Staff → edit → Access hours |
| Set the store timezone | Branding → Timezone |
| Require approved devices | Branding → "Only allow approved devices" |
| Approve / revoke devices | Administration → Devices |

### Anti-lockout notes

- Owners & super-admins bypass who/when/where — keep at least one such account.
- Enabling device restriction auto-approves your current device.
- Set the timezone before access hours.
