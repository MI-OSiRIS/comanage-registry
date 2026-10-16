# OSiRIS / HORUS COmanage Registry 4.6.1

This branch is upstream Internet2 COmanage Registry **4.6.1** plus the OSiRIS
changes that ran in production on wsu-comanage (comanage.osris.org) under 4.2.1.

## What this branch adds to upstream 4.6.1

| Path | What | Source |
|---|---|---|
| `app/AvailablePlugin/CephProvisioner/` | OSiRIS Ceph provisioner (RGW users, creds page, COU data dirs) | copied unchanged from wsu-comanage, 2026-10-16 |
| `app/AvailablePlugin/LdapUserPosixGroupProvisioner/` | Creates a matching posixGroup for each posixAccount | from wsu-comanage; one foreign key dropped from its schema (below) |
| `app/webroot/css/fonts/Font-Awesome-4.6.3/` | Icons used by the CephProvisioner credentials page | copied from wsu-comanage |
| `app/View/Layouts/default.ctp` | One line: load Font Awesome 4.6.3 CSS | local patch (Aug 2024) |
| `app/Controller/CoPetitionsController.php` | `view()` calls `parseToken()` so unauthenticated enrollees can view their petition | local patch (Jan 2026); still not upstream in 4.6.1 |
| `osiris/upgrade-4.6.1/` | `orphans.py` (orphan check) and `orphan-fix.sql` (cleanup) | written for this upgrade |

Local changes from the 4.2.1 install that were **dropped**:

- `GrouperRestClient.php` `(array)$r` cast: fixed upstream (CO-2805).
- Debug logging in `CoInvite.php`, `CoGrouperProvisionerTarget.php`. The CoInvite line logged invite URLs (with tokens) to debug.log.
- `HomedirProvisioner` with mkdir/chown commented out: no CO has a Homedir provisioning target, so the plugin is simply not enabled.

`LdapUserPosixGroupProvisioner` schema: the `co_ldap_provisioner_target_id`
foreign key was removed. Its generated name is 68 characters (MySQL allows 64),
so it was never created and every schema update since 2017 reported
"Possibly failed to update database schema" for this plugin.

`app/tmp`, `app/webroot/local-resources` and `app/webroot/img/logo.png` are
upstream symlinks into `local/`, so they need no change.

Known wart, left as-is: `CephRgwAdminCliClient::getRgwUsersV2()` hardcodes
`/srv/comanage/comanage-registry/app/tmp/...`. It is dead code (its only caller is commented out).

## Host dependencies (not in this repo)

- `/usr/local/bin/{mkCouDir,mkCopHomedir,genUserMdCache,getAllUserMd,getUserMdCached,readCachedUserMD}.sh`:
  symlinks to `/srv/comanage/registry-current/app/AvailablePlugin/CephProvisioner/Lib/`.
  Same path in this branch, so they follow the `registry-current` switch. Puppet manages only `mkCouDir.sh`.
- sudoers: `apache` may run `mkCouDir.sh` and `mkCopHomedir.sh` as root.
- apache crontab: two `Console/cake job` runs every 5 minutes (CO 1 and CO 2).
  **They use the old tree's name** (`/srv/comanage/comanage-registry/app`), see cutover below.
- `local/` (site-specific, copied from the old tree): `Config/database.php` (MySQL `cmregistry`),
  `Config/email.php`, `Plugin/` enable links, `webroot/` (logo, CephProvisionerCredHelp.html),
  and `tmp -> /var/cache/registry` (CakePHP cache and the real logs, `logs/debug.log`, `logs/error.log`).
- `/var/www/html/registry -> /srv/comanage/registry-current/app/webroot`.

## Data cleanup needed before the schema step

The production database (as of 2026-10-16) has orphaned rows left from COUs and
LDAP provisioner targets deleted in 2017. MySQL refuses to add the 4.6.1 foreign
keys while they exist, the core schema update stops, and `upgradeVersion` then
fails in `post440` (missing `cm_cmp_enrollment_configurations.authn_events_record_apiusers`).

| Foreign key | Rows | Fix in `orphan-fix.sql` |
|---|---|---|
| `co_person_roles.cou_id` | 117 | set NULL (all deleted roles or history rows) |
| `co_petitions.cou_id` | 208 | set NULL (all 2017) |
| `co_groups.cou_id` | 129 | set NULL (99 deleted, 30 history; none live) |
| `co_enrollment_flows.authz_cou_id` | 1 | set NULL (history copy of flow 6) |
| `co_ldap_provisioner_attributes` / `_attr_groupings` / `_dns` | 41 / 1 / 1 | delete (LDAP target 2 no longer exists) |
| `co_ldap_user_posix_group_provisioner_targets` | 1 | delete (provisioning target 18 no longer exists) |

Re-run `orphans.py` right before the production upgrade; the counts must match
the comments in `orphan-fix.sql` (or be re-checked) before it is applied.

## Upgrade procedure (4.2.1 -> 4.6.1)

No version between 4.2.1 and 4.6.1 is a blocker in `UpgradeVersionShell`, so a
single `upgradeVersion` run applies the 4.3.0, 4.4.0, 4.4.2, 4.5.0 and 4.6.0 steps.

1. **Back up**: `mysqldump --single-transaction cmregistry` and the old tree.
2. **Orphan check and cleanup**: `python3 osiris/upgrade-4.6.1/orphans.py NEW OLD | mysql ... cmregistry`,
   then apply `orphan-fix.sql` and re-run the check (expect no rows).
3. **Install side by side**: `git clone -b osiris-4.6.1` to `/srv/comanage/registry-4.6.1`.
4. **Copy site config**: `cp -a` the old `local/.` into the new `local/` (this carries `local/tmp -> /var/cache/registry`).
5. **Plugin enable links** in the new `local/Plugin`: make them relative,
   `CephProvisioner`, `LdapUserPosixGroupProvisioner`, `UnixCluster` -> `../../app/AvailablePlugin/<name>`.
   Do not keep `HomedirProvisioner`.
6. **SELinux**: `chcon -R --reference=<old tree>` on the new tree.
7. **Cutover**: comment out the two apache cron jobs, stop httpd, point `registry-current`
   at `registry-4.6.1`, clear `/var/cache/registry/cache/*` files, run
   `sudo -u apache Console/cake upgradeVersion` in `app/` (check for "Possibly failed"),
   start httpd, then restore the cron jobs **with the path changed to
   `/srv/comanage/registry-current/app`**.
8. **Verify**: login, an enrollment (including a petition/invite link without being logged in),
   the Ceph creds page, a provisioning run, and the cron job logs in `/tmp/cake.job.*.log`.
9. **Rollback**: point `registry-current` back and restore the database dump (the upgrade changes the schema).

## Test instance (done 2026-10-16)

Puppet defines `comanage-test-vhost-ssl` (port 667, `/var/www/html-test`).
The test run used:

- Database `test_comanage_feature` (the `cmregistry` user already has full rights on it),
  loaded from the dump, with **every provisioning target set to status `X`** before
  the first request, so nothing reaches the real LDAP, Grouper or Ceph.
- Tree `/srv/comanage/registry-4.6.1-test` with `local/` copied from production, then:
  `local/tmp -> /var/cache/registry-test` (seeded from `app/tmp.dist`, so it never shares
  production's cache), relative plugin links, `database` set to `test_comanage_feature`,
  and every mail transport set to `Debug`.
- `/var/www/html-test/registry -> /srv/comanage/registry-4.6.1-test/app/webroot`.

Result: after `orphan-fix.sql`, `upgradeVersion` completed to 4.6.1; the only
schema warning was the posix-group foreign key name fixed above.
