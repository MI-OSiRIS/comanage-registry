<?php
// Standalone check of the LDAP CO Group Provisioner. It loads the real plugin classes and fakes the LDAP directory
// and the COmanage tables, so it needs no CakePHP, no database and no LDAP server, and it changes nothing anywhere:
//   php app/AvailablePlugin/LdapCoGroupProvisioner/Test/Standalone/group_sync.php
// Exit status 0 if every check passes.
error_reporting(E_ALL);
$P = dirname(__DIR__, 2);

// ---- just enough of CakePHP and COmanage ------------------------------------------------------------------
class App { static function uses($a, $b) {} }
function _txt($k) { return $k; }
class StatusEnum { const Active='A'; const Expired='XP'; const GracePeriod='GP'; const Locked='LK'; const Suspended='S'; const Pending='P'; }
class SuspendableStatusEnum { const Active='A'; const Suspended='S'; }
class GroupEnum { const Standard='S'; const ActiveMembers='MA'; const Admins='A'; const AllMembers='M'; const Approvers='AP'; }
class ProvisioningActionEnum { const CoGroupAdded='GA'; const CoGroupDeleted='GD'; const CoGroupReprovisionRequested='GR'; const CoGroupUpdated='GU';
  const CoPersonAdded='PA'; const CoPersonDeleted='PD'; const CoPersonExpired='PX'; const CoPersonUpdated='PU'; const CoPersonUnexpired='PY'; }
class CakePlugin { static function loaded($p) { return true; } }
class CoProvisionerPluginTarget { public $logs = array(); public function log($m, $t = 'error') { $this->logs[] = $m; } }

// A table with the few kinds of find() condition the plugin uses
class FakeModel {
  public $alias; public $rows = array();
  function __construct($alias, $rows = array()) { $this->alias = $alias; $this->rows = $rows; }
  protected function test($row, $conds) {
    foreach($conds as $k => $v) {
      if($k === 'AND') { foreach($v as $c) { if(!$this->test($row, $c)) return false; } continue; }
      if($k === 'OR') { $any = false; foreach($v as $k2 => $v2) { if($this->test($row, is_int($k2) ? array($k2 => $v2) : array($k2 => $v2))) { $any = true; } } if(!$any) return false; continue; }
      if(is_int($k)) {
        if(!preg_match('/^\w+\.(\w+) IS NULL$/', $v, $m)) throw new Exception("fake find: $v");
        if($row[$m[1]] !== null) return false; continue;
      }
      if(!preg_match('/^\w+\.(\w+)(?: ([<>]) )?$/', $k, $m)) throw new Exception("fake find: $k");
      $val = $row[$m[1]] ?? null;
      if(!empty($m[2])) { if($val === null) return false; if($m[2] == '<' ? !($val < $v) : !($val > $v)) return false; continue; }
      if(is_array($v)) { if(!in_array($val, $v)) return false; } elseif($val != $v) return false;
    }
    return true;
  }
  function find($type, $args = array()) {
    $out = array_values(array_filter($this->rows, function($r) use ($args) { return $this->test($r, $args['conditions'] ?? array()); }));
    if($type == 'first') return $out ? array($this->alias => $out[0]) : array();
    if($type == 'all') return array_map(function($r) { return array($this->alias => $r); }, $out);
    $f = array_map(function($x) { return substr($x, strpos($x, '.') + 1); }, $args['fields']);
    $list = array(); foreach($out as $r) { $list[$r[$f[0]]] = $r[$f[1]]; } return $list;
  }
}
class ClassRegistry { static $models = array(); static function init($n) { return self::$models[$n]; } }

require "$P/Lib/LdapCoGroupSync.php";
require "$P/Model/CoLdapCoGroupProvisionerTarget.php";

// An LDAP directory in memory, with the same methods as LdapCoGroupDirectory
class FakeDir {
  public $e = array(); public $writes = array(); public $closed = false;
  function grp($dn, $cn, $gid, $members = array()) { $this->e[$dn] = array('objectClass' => array('top', 'posixGroup', 'groupOfUniqueNames'), 'cn' => array($cn), 'gidNumber' => array((string)$gid), 'uniqueMember' => $members); }
  function ou($dn) { $this->e[$dn] = array('objectClass' => array('top', 'organizationalUnit')); }
  protected function groups($base) { $r = array(); foreach($this->e as $dn => $a) { if(in_array('posixGroup', $a['objectClass']) && LdapCoGroupSync::isUnder($dn, $base)) $r[$dn] = $a; } return $r; }
  function groupsByCn($base, $cn) { $r = array(); foreach($this->groups($base) as $dn => $a) { if(strcasecmp($a['cn'][0], $cn) == 0) $r[] = array('dn' => $dn, 'cn' => $a['cn'][0], 'members' => $a['uniqueMember'] ?? array()); } return $r; }
  function groupsByMember($base, $m) { $r = array(); foreach($this->groups($base) as $dn => $a) { foreach($a['uniqueMember'] ?? array() as $x) { if(LdapCoGroupSync::normDn($x) == LdapCoGroupSync::normDn($m)) { $r[] = array('dn' => $dn, 'cn' => $a['cn'][0], 'members' => $a['uniqueMember']); break; } } } return $r; }
  function gidNumbers($root) { $r = array(); foreach($this->groups($root) as $a) { $r[] = (int)$a['gidNumber'][0]; } return $r; }
  function exists($dn) { foreach($this->e as $k => $a) { if(LdapCoGroupSync::normDn($k) == LdapCoGroupSync::normDn($dn)) return true; } return false; }
  function addEntry($dn, $attrs) { if($this->exists($dn)) throw new RuntimeException("exists $dn"); $this->e[$dn] = $attrs; $this->writes[] = "add $dn"; }
  function replaceMembers($dn, $m) { $this->e[$dn]['uniqueMember'] = $m; $this->writes[] = "replace $dn"; }
  function addMember($dn, $m) { $this->e[$dn]['uniqueMember'][] = $m; $this->writes[] = "addmember $dn"; }
  function removeMember($dn, $m) { if(!in_array($m, $this->e[$dn]['uniqueMember'], true)) throw new RuntimeException("no such value $m"); $this->e[$dn]['uniqueMember'] = array_values(array_diff($this->e[$dn]['uniqueMember'], array($m))); $this->writes[] = "delmember $dn"; }
  function close() { $this->closed = true; }
  function members($dn) { $m = array_map(function($x) { return preg_replace('/^uid=([^,]+),.*/i', '$1', $x); }, $this->e[$dn]['uniqueMember'] ?? array()); sort($m); return implode(' ', $m); }
}
class T extends CoLdapCoGroupProvisionerTarget { public $dir; protected function directoryFactory($t) { return $this->dir; } }

$pass = 0; $fail = 0;
function check($name, $cond) { global $pass, $fail; if($cond) { $pass++; echo "ok    $name\n"; } else { $fail++; echo "FAIL  $name\n"; } }
function threw($fn) { try { $fn(); } catch(Exception $e) { return $e->getMessage(); } return false; }

$BASE = 'ou=OSiRIS,ou=Groups,dc=osris,dc=org'; $ROOT = 'ou=Groups,dc=osris,dc=org';
function u($uid, $people = 'People') { return "uid=$uid,ou=$people,dc=osris,dc=org"; }
function ldap0() {
  global $BASE, $ROOT; $d = new FakeDir();
  $d->ou($BASE); $d->ou("ou=ATLAS,$BASE"); $d->ou("ou=OsirisAdmin,$BASE"); $d->ou("ou=HORUS,$BASE");
  $d->grp("cn=CO_members_active,$BASE", 'CO_members_active', 1000070, array(u('alice', 'people'), u('bob', 'people'), u('carol')));
  $d->grp("cn=CO_members_all,$BASE", 'CO_members_all', 1000071, array(u('alice'), u('bob'), u('carol'), u('dave')));
  $d->grp("cn=CO_COU_ATLAS_members_active,ou=ATLAS,$BASE", 'CO_COU_ATLAS_members_active', 1000190, array(u('alice'), u('bob')));
  $d->grp("cn=CO_COU_ATLAS_admins,ou=ATLAS,$BASE", 'CO_COU_ATLAS_admins', 1000191, array(u('alice'), u('bob')));
  $d->grp("cn=sudo-ceph-cmd,ou=OsirisAdmin,$BASE", 'sudo-ceph-cmd', 1000300, array(u('alice'), u('bob')));   // exists in LDAP only
  $d->grp("cn=alice,$ROOT", 'alice', 100020, array(u('alice')));                                               // a per-person group, outside the base
  $d->grp("cn=odd,$ROOT", 'odd', 1001091, array());                                                            // highest gid in use is outside the base
  return $d;
}

// ================= 1. the sync logic on its own =================
echo "-- sync logic\n";
$log = array(); $lg = function($m) use (&$log) { $log[] = $m; };
check('name mapping CO:COU:ATLAS:members:active', LdapCoGroupSync::cnForGroupName('CO:COU:ATLAS:members:active') == 'CO_COU_ATLAS_members_active');
check('DN compare ignores case and spaces', LdapCoGroupSync::normDn('UID=Bob, OU=People,dc=osris,dc=org') == LdapCoGroupSync::normDn('uid=bob,ou=people,dc=osris,dc=org'));
check('DN value escaping', LdapCoGroupSync::escapeDnValue('a,b+c "d"') == 'a\\,b\\+c \\"d\\"' && LdapCoGroupSync::escapeDnValue('#x ') == '\\#x\\ ');
check('filter value escaping', LdapCoGroupSync::escapeFilterValue('a*(b)\\') == 'a\\2a\\28b\\29\\5c');
check('base must be under the gid root', threw(function() { new LdapCoGroupSync(new FakeDir(), 'ou=x,dc=other', 'ou=Groups,dc=osris,dc=org', 1000070, true); }) !== false);

$d = ldap0(); $s = new LdapCoGroupSync($d, $BASE, $ROOT, 1000070, false, $lg);
check('same members (other case, other order): unchanged, no write', $s->syncGroup('CO:members:active', array(), array(u('carol'), u('BOB'), u('alice'))) == 'unchanged' && !$d->writes);
check('one added, one removed', $s->syncGroup('CO:members:active', array(), array(u('alice'), u('bob'), u('erin'))) == 'updated' && $d->members("cn=CO_members_active,$BASE") == 'alice bob erin');
$before = $d->e["cn=CO_COU_ATLAS_members_active,ou=ATLAS,$BASE"];
$s->syncGroup('CO:COU:ATLAS:members:active', array('WRONG'), array(u('alice')));
$after = $d->e["cn=CO_COU_ATLAS_members_active,ou=ATLAS,$BASE"];
check('existing group is found by name wherever it sits; DN, cn and gidNumber untouched', $after['gidNumber'] == $before['gidNumber'] && $after['cn'] == $before['cn'] && $d->members("cn=CO_COU_ATLAS_members_active,ou=ATLAS,$BASE") == 'alice' && !$d->exists("ou=WRONG,$BASE"));

$d = ldap0(); $s = new LdapCoGroupSync($d, $BASE, $ROOT, 1000070, false, $lg);
check('new COU group: created under ou=<cou>, ou created too', $s->syncGroup('CO:COU:NewProj:members:active', array('NewProj'), array(u('alice'))) == 'created' && $d->exists("ou=NewProj,$BASE") && $d->members("cn=CO_COU_NewProj_members_active,ou=NewProj,$BASE") == 'alice');
check('new group gets the next gidNumber not used anywhere (1001092)', $d->e["cn=CO_COU_NewProj_members_active,ou=NewProj,$BASE"]['gidNumber'] == array('1001092'));
$s->syncGroup('CO:COU:NewProj:admins', array('NewProj'), array());
check('second new group: next number, created even with no members', $d->e["cn=CO_COU_NewProj_admins,ou=NewProj,$BASE"]['gidNumber'] == array('1001093') && !isset($d->e["cn=CO_COU_NewProj_admins,ou=NewProj,$BASE"]['uniqueMember']));
$s->syncGroup('CO:COU:Sub:admins', array('HORUS', 'Sub'), array(u('bob')));
check('child COU: ou=<child>,ou=<parent>', $d->exists("cn=CO_COU_Sub_admins,ou=Sub,ou=HORUS,$BASE"));
$s->syncGroup('CO:COU:A, B:admins', array('A, B'), array());
check('name with a comma is escaped in the DN', $d->exists("cn=CO_COU_A\\, B_admins,ou=A\\, B,$BASE"));

$d = ldap0(); $many = array(); for($i = 0; $i < 30; $i++) { $many[] = u("user$i"); } $d->grp("cn=big,$BASE", 'big', 1000400, $many);
$s = new LdapCoGroupSync($d, $BASE, $ROOT, 1000070, false, $lg);
check('guard: removing 20 of 30 members is refused, nothing written', threw(function() use ($s, $many) { $s->syncGroup('big', array(), array_slice($many, 0, 10)); }) !== false && !$d->writes);
check('guard: emptying a 30-member group is refused', threw(function() use ($s) { $s->syncGroup('big', array(), array()); }) !== false && !$d->writes);
check('guard: removing 10 of 30 is allowed', $s->syncGroup('big', array(), array_slice($many, 0, 20)) == 'updated');
check('guard off (admin asked for this group): large removal allowed', $s->syncGroup('big', array(), array_slice($many, 0, 2), false) == 'updated' && count($d->e["cn=big,$BASE"]['uniqueMember']) == 2);
check('small group can lose all members', $s->syncGroup('CO:COU:ATLAS:admins', array(), array()) == 'updated' && $d->members("cn=CO_COU_ATLAS_admins,ou=ATLAS,$BASE") == '');

$d = ldap0(); $d->grp("cn=CO_members_active,ou=ATLAS,$BASE", 'CO_members_active', 1000999, array());
$s = new LdapCoGroupSync($d, $BASE, $ROOT, 1000070, false, $lg);
check('two LDAP groups with the same name: refused', threw(function() use ($s) { $s->syncGroup('CO:members:active', array(), array(u('alice'))); }) !== false && !$d->writes);

$d = ldap0(); $s = new LdapCoGroupSync($d, $BASE, $ROOT, 1000070, false, $lg);
check('emptyGroup: members removed, entry and gidNumber kept', $s->emptyGroup('CO:COU:ATLAS:admins') == 'updated' && $d->exists("cn=CO_COU_ATLAS_admins,ou=ATLAS,$BASE") && $d->e["cn=CO_COU_ATLAS_admins,ou=ATLAS,$BASE"]['gidNumber'] == array('1000191'));
check('emptyGroup of a group LDAP never had: nothing', $s->emptyGroup('CO:COU:Nope:admins') == 'absent');

$d = ldap0(); $log = array(); $s = new LdapCoGroupSync($d, $BASE, $ROOT, 1000070, true, $lg);
$s->syncGroup('CO:members:active', array(), array(u('alice'))); $s->syncGroup('CO:COU:New:admins', array('New'), array(u('bob'))); $s->emptyGroup('CO:COU:ATLAS:admins');
$s->reconcilePerson(u('bob'), array(), array('CO_members_active'), false);
check('dry run: nothing written, every change logged as DRY RUN', !$d->writes && count($log) >= 5 && count(array_filter($log, function($l) { return strpos($l, 'DRY RUN') === 0; })) == count($log));

$managed = array('CO_members_active', 'CO_members_all', 'CO_COU_ATLAS_members_active', 'CO_COU_ATLAS_admins');
$d = ldap0(); $s = new LdapCoGroupSync($d, $BASE, $ROOT, 1000070, false, $lg);
$r = $s->reconcilePerson(u('bob'), array('CO_members_all'), $managed, false);
check('expired person: out of every group but the one still wanted, the LDAP-only sudo group included', $d->members("cn=CO_members_active,$BASE") == 'alice carol' && $d->members("cn=CO_COU_ATLAS_admins,ou=ATLAS,$BASE") == 'alice' && $d->members("cn=sudo-ceph-cmd,ou=OsirisAdmin,$BASE") == 'alice' && $d->members("cn=CO_members_all,$BASE") == 'alice bob carol dave');
check('expired person: value stored with another case (ou=people) is removed', !in_array('CO_members_active', array_map(function($g) { return $g['cn']; }, $d->groupsByMember($BASE, u('bob')))));
check('expired person: the per-person group outside the base is not touched', $d->members("cn=alice,$ROOT") == 'alice');
$d = ldap0(); $s = new LdapCoGroupSync($d, $BASE, $ROOT, 1000070, false, $lg);
$r = $s->reconcilePerson(u('alice'), array('CO_members_active', 'CO_members_all', 'CO_COU_ATLAS_members_active'), $managed, true);
check('active person: removed from a managed group COmanage no longer lists, kept in the LDAP-only sudo group', $r['removed'] == array('CO_COU_ATLAS_admins') && $d->members("cn=sudo-ceph-cmd,ou=OsirisAdmin,$BASE") == 'alice bob');
$r = $s->reconcilePerson(u('carol'), array('CO_members_active', 'CO_members_all', 'CO_COU_ATLAS_admins', 'CO_COU_NotInLdap_admins'), $managed, true);
check('active person: added where missing; a group not in LDAP yet is skipped', $r['added'] == array('CO_COU_ATLAS_admins') && $d->members("cn=CO_COU_ATLAS_admins,ou=ATLAS,$BASE") == 'bob carol');

// ================= 2. through the COmanage model =================
echo "-- through the provisioning target\n";
function registry() {
  $now = date('Y-m-d H:i:s'); $past = '2020-01-01 00:00:00'; $future = '2099-01-01 00:00:00';
  $gm = function($id, $g, $p, $member = true, $from = null, $through = null) { return array('id' => $id, 'co_group_id' => $g, 'co_person_id' => $p, 'member' => $member, 'valid_from' => $from, 'valid_through' => $through); };
  ClassRegistry::$models = array(
    'LdapProvisioner.CoLdapProvisionerTarget' => new FakeModel('CoLdapProvisionerTarget', array(array('id' => 1, 'serverurl' => 'x', 'binddn' => 'x', 'password' => 'x', 'group_basedn' => 'ou=Groups,dc=osris,dc=org'))),
    'CoGroup' => new FakeModel('CoGroup', array(
      array('id' => 10, 'co_id' => 2, 'cou_id' => null, 'name' => 'CO:members:active', 'group_type' => 'MA', 'status' => 'A'),
      array('id' => 11, 'co_id' => 2, 'cou_id' => null, 'name' => 'CO:members:all', 'group_type' => 'M', 'status' => 'A'),
      array('id' => 12, 'co_id' => 2, 'cou_id' => 5, 'name' => 'CO:COU:ATLAS:members:active', 'group_type' => 'MA', 'status' => 'A'),
      array('id' => 13, 'co_id' => 2, 'cou_id' => 5, 'name' => 'CO:COU:ATLAS:admins', 'group_type' => 'A', 'status' => 'A'),
      array('id' => 14, 'co_id' => 2, 'cou_id' => 5, 'name' => 'CO:COU:ATLAS:approvers', 'group_type' => 'AP', 'status' => 'A'),
      array('id' => 15, 'co_id' => 2, 'cou_id' => null, 'name' => 'alice UnixCluster Group', 'group_type' => 'S', 'status' => 'A'),
      array('id' => 16, 'co_id' => 2, 'cou_id' => 7, 'name' => 'CO:COU:Sub:admins', 'group_type' => 'A', 'status' => 'A'))),
    'CoPerson' => new FakeModel('CoPerson', array(
      array('id' => 1, 'co_id' => 2, 'status' => 'A'), array('id' => 2, 'co_id' => 2, 'status' => 'XP'), array('id' => 3, 'co_id' => 2, 'status' => 'GP'),
      array('id' => 4, 'co_id' => 2, 'status' => 'A'), array('id' => 5, 'co_id' => 2, 'status' => 'A'), array('id' => 6, 'co_id' => 2, 'status' => 'P'))),
    'CoGroupMember' => new FakeModel('CoGroupMember', array(
      $gm(1, 12, 1), $gm(2, 12, 2), $gm(3, 12, 3), $gm(4, 12, 4, true, null, $past), $gm(5, 12, 5, false), $gm(6, 12, 6), $gm(13, 12, 4, true, $future, null),
      $gm(7, 11, 1), $gm(8, 11, 2), $gm(9, 11, 6),
      $gm(10, 13, 2), $gm(11, 13, 1), $gm(12, 15, 1), $gm(14, 10, 1), $gm(15, 16, 1))),
    'LdapProvisioner.CoLdapProvisionerDn' => new FakeModel('CoLdapProvisionerDn', array(
      array('co_ldap_provisioner_target_id' => 1, 'co_person_id' => 1, 'dn' => u('alice')), array('co_ldap_provisioner_target_id' => 1, 'co_person_id' => 2, 'dn' => u('bob')),
      array('co_ldap_provisioner_target_id' => 1, 'co_person_id' => 3, 'dn' => u('carol')), array('co_ldap_provisioner_target_id' => 1, 'co_person_id' => 4, 'dn' => u('dave')),
      array('co_ldap_provisioner_target_id' => 9, 'co_person_id' => 5, 'dn' => 'uid=erin,ou=elsewhere'))),
    'UnixCluster.UnixClusterGroup' => new FakeModel('UnixClusterGroup', array(array('id' => 1, 'co_group_id' => 15))),
    'Cou' => new FakeModel('Cou', array(array('id' => 5, 'name' => 'ATLAS', 'parent_id' => null), array('id' => 6, 'name' => 'HORUS', 'parent_id' => null), array('id' => 7, 'name' => 'Sub', 'parent_id' => 6))));
}
function target($apply = true) { return array('CoLdapCoGroupProvisionerTarget' => array('co_ldap_provisioner_target_id' => 1, 'basedn' => 'ou=OSiRIS,ou=Groups,dc=osris,dc=org', 'gid_min' => 1000070, 'apply_changes' => $apply)); }
function grpdata($id) { $g = ClassRegistry::init('CoGroup')->find('first', array('conditions' => array('CoGroup.id' => $id))); return $g; }
function persondata($id) { return ClassRegistry::init('CoPerson')->find('first', array('conditions' => array('CoPerson.id' => $id))); }

registry(); $t = new T(); $t->dir = ldap0();
$t->provision(target(), 'GU', grpdata(12));
check('group update: active and grace-period people with a current membership (expired, lapsed, future, non-member, pending, no DN: out)', $t->dir->members("cn=CO_COU_ATLAS_members_active,ou=ATLAS,$BASE") == 'alice carol' && $t->dir->closed);
$t->dir = ldap0(); $t->provision(target(), 'GU', grpdata(11));
check('All Members group keeps expired people (pending person without LDAP entry: out)', $t->dir->members("cn=CO_members_all,$BASE") == 'alice bob');
$t->dir = ldap0(); $t->provision(target(), 'GU', grpdata(13));
check('Admins group: an expired admin is left out though COmanage still lists them', $t->dir->members("cn=CO_COU_ATLAS_admins,ou=ATLAS,$BASE") == 'alice');
$t->dir = ldap0(); $t->provision(target(), 'GU', grpdata(14)); $t->provision(target(), 'GA', grpdata(15));
check('Approvers group and per-person (Unix Cluster) group: ignored', !$t->dir->writes && !$t->dir->groupsByCn($BASE, 'CO_COU_ATLAS_approvers'));
$t->dir = ldap0(); $t->provision(target(), 'GA', grpdata(16));
check('new group in a child COU: created under ou=Sub,ou=HORUS', $t->dir->members("cn=CO_COU_Sub_admins,ou=Sub,ou=HORUS,$BASE") == 'alice');
$t->dir = ldap0(); $g = grpdata(13); $g['CoGroup']['status'] = 'S'; $t->provision(target(), 'GU', $g);
check('suspended group: emptied', $t->dir->members("cn=CO_COU_ATLAS_admins,ou=ATLAS,$BASE") == '');
$t->dir = ldap0(); $t->provision(target(), 'GD', grpdata(12));
check('deleted group: emptied, entry kept', $t->dir->members("cn=CO_COU_ATLAS_members_active,ou=ATLAS,$BASE") == '' && $t->dir->exists("cn=CO_COU_ATLAS_members_active,ou=ATLAS,$BASE"));
$t->dir = ldap0(); $t->provision(target(false), 'GU', grpdata(12)); $t->provision(target(false), 'PX', persondata(2));
check('apply changes off: nothing written, DRY RUN lines logged', !$t->dir->writes && count(array_filter($t->logs, function($l) { return strpos($l, 'DRY RUN') !== false; })) >= 2);

$t = new T(); $t->dir = ldap0(); $t->provision(target(), 'PX', persondata(2));
check('person expired: out of active, admins and LDAP-only groups; still in All Members', $t->dir->members("cn=CO_members_active,$BASE") == 'alice carol' && $t->dir->members("cn=CO_COU_ATLAS_admins,ou=ATLAS,$BASE") == 'alice' && $t->dir->members("cn=sudo-ceph-cmd,ou=OsirisAdmin,$BASE") == 'alice' && $t->dir->members("cn=CO_members_all,$BASE") == 'alice bob carol dave');
$t->dir = ldap0(); $t->provision(target(), 'PD', persondata(2));
check('person deleted: out of All Members too', $t->dir->members("cn=CO_members_all,$BASE") == 'alice carol dave');
$t->dir = ldap0(); $t->dir->e["cn=CO_COU_ATLAS_admins,ou=ATLAS,$BASE"]['uniqueMember'] = array(u('bob')); $t->provision(target(), 'PU', persondata(1));
check('active person updated: added to the admins group they belong to; LDAP-only sudo group untouched', $t->dir->members("cn=CO_COU_ATLAS_admins,ou=ATLAS,$BASE") == 'alice bob' && $t->dir->members("cn=sudo-ceph-cmd,ou=OsirisAdmin,$BASE") == 'alice bob');
$t->dir = ldap0(); $t->provision(target(), 'PU', persondata(4));
check('active person whose membership lapsed: removed from that managed group only', $t->dir->members("cn=CO_members_all,$BASE") == 'alice bob carol' && count($t->dir->writes) == 1);
$t->dir = ldap0(); $t->provision(target(), 'PU', persondata(6));
check('person never provisioned to LDAP: nothing done', !$t->dir->writes);
$t->dir = ldap0();
check('target not configured: error, nothing written', threw(function() use ($t) { $t->provision(array('CoLdapCoGroupProvisionerTarget' => array('co_ldap_provisioner_target_id' => null, 'basedn' => '', 'gid_min' => null, 'apply_changes' => true)), 'GU', grpdata(12)); }) !== false && !$t->dir->writes);
check('email list or service data: ignored', $t->provision(target(), 'XX', array('CoService' => array('id' => 1))) === true);

echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
