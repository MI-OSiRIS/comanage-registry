<?php
/**
 * OSiRIS LDAP CO Group Provisioner: what to change in LDAP for one group or one person.
 *
 * No CakePHP and no LDAP extension in here, so Test/Standalone/group_sync.php can run it against a fake directory.
 * The directory object passed in does the reading and writing (LdapCoGroupDirectory for a real server).
 *
 * LDAP groups are the ones Grouper used to write: objectClass posixGroup + groupOfUniqueNames, members in
 * uniqueMember, under a base such as ou=OSiRIS,ou=Groups,dc=osris,dc=org:
 *   cn=CO_members_active,<base>
 *   cn=CO_COU_<cou>_members_active,ou=<cou>,<base>              (child COUs: ou=<child>,ou=<parent>,<base>)
 * Rules:
 *  - An existing group is found by its cn anywhere under the base. Only its uniqueMember values are ever changed.
 *    Its DN, cn and gidNumber are never touched, and no group is ever deleted.
 *  - A group that isn't in LDAP yet is created in the layout above with the next free gidNumber.
 */
class LdapCoGroupSync {
  // A sync that would remove more than this many members AND more than half of a group is refused,
  // so that a bad query can't empty a group such as CO_members_active.
  const GUARD_MIN = 10;
  // New gidNumbers are taken from gidMin .. gidMin + GID_SPAN - 1
  const GID_SPAN = 1000000;

  protected $dir;
  protected $base;
  protected $gidRoot;
  protected $gidMin;
  protected $dryRun;
  protected $logger;

  /**
   * @param object   $dir      Directory adapter (see LdapCoGroupDirectory)
   * @param string   $base     DN under which this plugin's groups live
   * @param string   $gidRoot  DN under which a gidNumber must be unique (all posix groups)
   * @param integer  $gidMin   Lowest gidNumber to give a new group
   * @param boolean  $dryRun   true: read and log only, write nothing
   * @param callable $logger   function(string $message)
   */
  public function __construct($dir, $base, $gidRoot, $gidMin, $dryRun, $logger = null) {
    if(trim((string)$base) === '' || trim((string)$gidRoot) === '') {
      throw new InvalidArgumentException('LdapCoGroupSync: base DN and gid root DN are required');
    }
    if(!self::isUnder($base, $gidRoot)) {
      throw new InvalidArgumentException("LdapCoGroupSync: base '$base' is not under '$gidRoot'");
    }
    if((int)$gidMin < 1000) {
      throw new InvalidArgumentException('LdapCoGroupSync: lowest gidNumber must be 1000 or more');
    }
    $this->dir = $dir;
    $this->base = $base;
    $this->gidRoot = $gidRoot;
    $this->gidMin = (int)$gidMin;
    $this->dryRun = (bool)$dryRun;
    $this->logger = $logger;
  }

  // ---- naming helpers -------------------------------------------------------------------------------------

  // COmanage 4.x "CO:COU:ATLAS:members:active" -> the LDAP name Grouper used, "CO_COU_ATLAS_members_active"
  public static function cnForGroupName($name) {
    return str_replace(':', '_', $name);
  }

  // DNs compare without regard to case or to spaces around the separators
  public static function normDn($dn) {
    return strtolower(preg_replace('/\s*([,=])\s*/', '$1', trim($dn)));
  }

  public static function isUnder($dn, $ancestor) {
    $d = self::normDn($dn);
    $a = self::normDn($ancestor);
    return $d === $a || substr($d, -strlen(',' . $a)) === ',' . $a;
  }

  // RFC 4514 escaping of one attribute value inside a DN
  public static function escapeDnValue($v) {
    $v = (string)$v;
    $out = preg_replace('/([,+"\\\\<>;])/', '\\\\$1', $v);
    if($out !== '' && ($out[0] === '#' || $out[0] === ' ')) { $out = '\\' . $out; }
    if(strlen($out) > 1 && substr($out, -1) === ' ' && substr($out, -2) !== '\\ ') { $out = substr($out, 0, -1) . '\\ '; }
    return $out;
  }

  // RFC 4515 escaping of one value inside a search filter
  public static function escapeFilterValue($v) {
    return str_replace(array('\\', '*', '(', ')', "\0"), array('\\5c', '\\2a', '\\28', '\\29', '\\00'), (string)$v);
  }

  // ---- one group ------------------------------------------------------------------------------------------

  /**
   * Make an LDAP group's members equal to $memberDns, creating the group if LDAP doesn't have it.
   *
   * @param string  $name        COmanage group name
   * @param array   $ouPath      Names of the group's COU and its parents, top first; empty for a CO-wide group.
   *                             Only used when the group has to be created.
   * @param array   $memberDns   DNs of the people who should be members
   * @param boolean $guard       Refuse a large removal (see GUARD_MIN)
   * @return string 'unchanged', 'updated' or 'created'
   * @throws RuntimeException    if LDAP has two groups of this name, or the guard refuses
   */
  public function syncGroup($name, array $ouPath, array $memberDns, $guard = true) {
    $cn = self::cnForGroupName($name);
    $desired = array();
    foreach($memberDns as $m) { $desired[self::normDn($m)] = $m; }

    $entry = $this->findGroup($cn);

    if(!$entry) {
      $dn = $this->base;
      foreach($ouPath as $ou) {
        $dn = 'ou=' . self::escapeDnValue($ou) . ',' . $dn;
        if(!$this->dir->exists($dn)) {
          $this->write("create $dn", function() use ($dn, $ou) {
            $this->dir->addEntry($dn, array('objectClass' => array('top', 'organizationalUnit'), 'ou' => array($ou)));
          });
        }
      }
      $dn = 'cn=' . self::escapeDnValue($cn) . ',' . $dn;
      $gid = $this->nextGid();
      $attrs = array(
        'objectClass' => array('top', 'posixGroup', 'groupOfUniqueNames'),
        'cn'          => array($cn),
        'gidNumber'   => array((string)$gid),
        'description' => array('COmanage group ' . $name)
      );
      if($desired) { $attrs['uniqueMember'] = array_values($desired); }
      $this->write("create group $dn gidNumber $gid with " . count($desired) . " member(s)", function() use ($dn, $attrs) {
        $this->dir->addEntry($dn, $attrs);
      });
      return 'created';
    }

    $current = array();
    foreach($entry['members'] as $m) { $current[self::normDn($m)] = $m; }
    $add = array_diff_key($desired, $current);
    $del = array_diff_key($current, $desired);

    if(!$add && !$del) {
      return 'unchanged';
    }

    if($guard && count($del) > self::GUARD_MIN && count($del) * 2 > count($current)) {
      throw new RuntimeException("LdapCoGroupProvisioner refuses to remove " . count($del) . " of " . count($current)
                                 . " members from $cn in one step. If that is intended, use Provision on the group itself.");
    }

    $dn = $entry['dn'];
    $this->write("group $cn: +" . count($add) . " -" . count($del) . " member(s), " . count($desired) . " after"
                 . ($add ? '; add ' . implode(' ', array_map(array($this, 'rdnValue'), $add)) : '')
                 . ($del ? '; remove ' . implode(' ', array_map(array($this, 'rdnValue'), $del)) : ''),
                 function() use ($dn, $desired) { $this->dir->replaceMembers($dn, array_values($desired)); });
    return 'updated';
  }

  /**
   * Remove every member from a group (it was deleted or suspended in COmanage). The LDAP entry stays, so its
   * gidNumber is never reused and files owned by the group keep a name.
   *
   * @return string 'absent', 'unchanged' or 'updated'
   */
  public function emptyGroup($name) {
    $cn = self::cnForGroupName($name);
    $entry = $this->findGroup($cn);
    if(!$entry) { return 'absent'; }
    if(!$entry['members']) { return 'unchanged'; }
    $dn = $entry['dn'];
    $this->write("group $cn: emptied (" . count($entry['members']) . " member(s) removed); the entry is kept",
                 function() use ($dn) { $this->dir->replaceMembers($dn, array()); });
    return 'updated';
  }

  // ---- one person -----------------------------------------------------------------------------------------

  /**
   * Bring one person's LDAP group memberships in line with COmanage.
   *
   * @param string  $memberDn    The person's DN
   * @param array   $desiredCns  LDAP names of the groups the person should be in
   * @param array   $managedCns  LDAP names of every group this plugin manages (those that exist in COmanage)
   * @param boolean $active      Is the person active? An active person is only removed from managed groups, so
   *                             groups that exist in LDAP alone (the old Grouper-only role groups) are left
   *                             as they are. A person who is not active is removed from every group under the
   *                             base that is not in $desiredCns.
   * @return array ('added' => cns, 'removed' => cns)
   */
  public function reconcilePerson($memberDn, array $desiredCns, array $managedCns, $active) {
    $norm = self::normDn($memberDn);
    $desired = array_fill_keys($desiredCns, true);
    $managed = array_fill_keys($managedCns, true);
    $res = array('added' => array(), 'removed' => array());
    $have = array();

    foreach($this->dir->groupsByMember($this->base, $memberDn) as $g) {
      $have[$g['cn']] = true;
      if(isset($desired[$g['cn']])) { continue; }
      if($active && !isset($managed[$g['cn']])) { continue; }
      // delete the value exactly as it is stored
      $stored = $memberDn;
      foreach($g['members'] as $m) { if(self::normDn($m) === $norm) { $stored = $m; break; } }
      $dn = $g['dn'];
      $cn = $g['cn'];
      $this->write("group $cn: remove " . $this->rdnValue($memberDn) . ($active ? '' : ' (person not active)'),
                   function() use ($dn, $stored) { $this->dir->removeMember($dn, $stored); });
      $res['removed'][] = $cn;
    }

    foreach(array_keys($desired) as $cn) {
      if(isset($have[$cn])) { continue; }
      $entry = $this->findGroup($cn);
      if(!$entry) { continue; }   // not in LDAP yet: provisioning the group creates it with its members
      $dn = $entry['dn'];
      $this->write("group $cn: add " . $this->rdnValue($memberDn),
                   function() use ($dn, $memberDn) { $this->dir->addMember($dn, $memberDn); });
      $res['added'][] = $cn;
    }
    return $res;
  }

  // ---- internals ------------------------------------------------------------------------------------------

  protected function findGroup($cn) {
    $found = $this->dir->groupsByCn($this->base, $cn);
    if(count($found) > 1) {
      throw new RuntimeException("LdapCoGroupProvisioner: " . count($found) . " LDAP groups are named $cn under "
                                 . $this->base . "; not touching any of them");
    }
    return $found ? $found[0] : null;
  }

  // The next free gidNumber: one above the highest in our range, and not in use anywhere under the gid root
  protected function nextGid() {
    $used = array_map('intval', $this->dir->gidNumbers($this->gidRoot));
    $top = $this->gidMin + self::GID_SPAN;
    $max = $this->gidMin - 1;
    foreach($used as $g) { if($g >= $this->gidMin && $g < $top && $g > $max) { $max = $g; } }
    $next = $max + 1;
    while(in_array($next, $used, true)) { $next++; }
    if($next >= $top) {
      throw new RuntimeException("LdapCoGroupProvisioner: no free gidNumber from " . $this->gidMin . " to " . ($top - 1));
    }
    return $next;
  }

  protected function rdnValue($dn) {
    return preg_match('/^[^=]+=((?:\\\\.|[^,])*)/', $dn, $m) ? $m[1] : $dn;
  }

  protected function write($what, $fn) {
    if($this->dryRun) {
      $this->log("DRY RUN, would: $what");
      return;
    }
    $fn();
    $this->log($what);
  }

  protected function log($msg) {
    if($this->logger) { call_user_func($this->logger, $msg); }
  }
}
