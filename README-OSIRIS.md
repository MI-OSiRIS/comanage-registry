# OSiRIS / HORUS COmanage Registry 4.6.1

This branch is upstream Internet2 COmanage Registry **4.6.1** plus the OSiRIS
changes that ran in production on wsu-comanage (comanage.osris.org) under 4.2.1.

## What this branch adds to upstream 4.6.1

| Path | What | Source |
|---|---|---|
| `app/AvailablePlugin/CephProvisioner/` | OSiRIS Ceph provisioner (RGW users, creds page, COU data dirs) | copied unchanged from wsu-comanage, 2026-10-16 |
| `app/AvailablePlugin/LdapUserPosixGroupProvisioner/` | Creates a matching posixGroup for each posixAccount | copied unchanged from wsu-comanage, 2026-10-16 |
| `app/webroot/css/fonts/Font-Awesome-4.6.3/` | Icons used by the CephProvisioner credentials page | copied from wsu-comanage |
| `app/View/Layouts/default.ctp` | One line: load Font Awesome 4.6.3 CSS | local patch (Aug 2024) |
| `app/Controller/CoPetitionsController.php` | `view()` calls `parseToken()` so unauthenticated enrollees can view their petition | local patch (Jan 2026); still not upstream in 4.6.1 |

Local changes from the 4.2.1 install that were **dropped**:

- `GrouperRestClient.php` `(array)$r` cast: fixed upstream (CO-2805).
- Debug logging in `CoInvite.php`, `CoGrouperProvisionerTarget.php`. The CoInvite line logged invite URLs (with tokens) to debug.log.
- `HomedirProvisioner` with mkdir/chown commented out: no CO has a Homedir provisioning target, so the plugin is simply not enabled.

`app/webroot/local-resources` and `app/webroot/img/logo.png` are upstream symlinks into `local/`, so they need no change.

Known wart, left as-is: `CephRgwAdminCliClient::getRgwUsersV2()` hardcodes
`/srv/comanage/comanage-registry/app/tmp/...`. It is dead code (its only caller is commented out).

## Host dependencies (not in this repo)

- `/usr/local/bin/mkCouDir.sh`: Puppet (`modules/comanage`) links it to
  `registry-current/app/AvailablePlugin/CephProvisioner/Lib/mkCouDir.sh`. Same path in this branch, so no Puppet change is needed.
- `/usr/local/bin/mkCopHomedir.sh`, `genUserMdCache.sh`, `readCachedUserMD.sh`: called by CephProvisioner; check how they are linked on the host.
- sudoers entries letting `apache` run those scripts.
- `local/Config/database.php` (MySQL `cmregistry`), `local/Config/email.php`, `local/webroot/` and `local/Plugin/` enable links: site-specific, copied from the old tree.
- `/var/www/html/registry -> /srv/comanage/registry-current/app/webroot`.

## Upgrade procedure (4.2.1 -> 4.6.1)

No version between 4.2.1 and 4.6.1 is a blocker in `UpgradeVersionShell`, so a
single `upgradeVersion` run applies the 4.3.0, 4.4.0, 4.4.2, 4.5.0 and 4.6.0 steps.

1. **Back up** the database (`mysqldump --single-transaction cmregistry`) and the old tree.
2. **Install side by side**: check out this branch at `/srv/comanage/registry-4.6.1`.
3. **Copy site config**: copy `local/` from `/srv/comanage/comanage-registry` into the new tree, and copy `app/tmp` (keeps `cache/users-md`).
4. **Fix plugin enable links** in the new `local/Plugin` so they are relative, not via `registry-current`:
   `CephProvisioner`, `LdapUserPosixGroupProvisioner`, `UnixCluster` -> `../../app/AvailablePlugin/<name>`.
   Do not recreate `HomedirProvisioner`.
5. **SELinux**: match contexts to the old tree (`app/tmp` must be writable by httpd).
6. **Cut over**: stop httpd, point `registry-current` at `registry-4.6.1`, run
   `./Console/cake upgradeVersion` in `app/` as the web server user, start httpd.
7. **Verify**: login, an enrollment (incl. an invite link without being logged in), the Ceph creds page, and a provisioning run.
8. **Rollback**: point `registry-current` back and restore the database dump (the upgrade changes the schema).

## Test first (port 667 vhost)

Puppet already defines `comanage-test-vhost-ssl` (port 667, `/var/www/html-test`).
Use a copy of the database (for example `cmregistry_test`) and a separate tree.
**Before the first request, disable all provisioning targets in the copy**, or the test
instance will provision to the real LDAP, Grouper and Ceph.
