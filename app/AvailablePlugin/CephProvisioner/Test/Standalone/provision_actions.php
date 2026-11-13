<?php
// Standalone check of which Ceph work each provisioning action does (CoCephProvisionerTarget::provision).
// It loads the real plugin classes and fakes only the two command lines (ceph, radosgw-admin) and the credential
// table, so it needs no CakePHP, no database and no Ceph cluster, and it changes nothing anywhere:
//   php app/AvailablePlugin/CephProvisioner/Test/Standalone/provision_actions.php
// Exit status 0 if every check passes.
error_reporting(E_ALL);
$P = dirname(__DIR__, 2);
class App { static function uses($a, $b) {} }
class CakeLog { static function write($l, $m) {} }
class Configure { static function read($k) { return 0; } }
function _txt($k) { return $k; }
class InternalErrorException extends Exception {}
class CakeException extends Exception {}
class StatusEnum { const Active='A'; const Expired='XP'; const GracePeriod='GP'; const Suspended='S'; }
class IdentifierEnum { const UID='uid'; }
class ProvisioningActionEnum { const CoGroupAdded='GA'; const CoGroupDeleted='GD'; const CoGroupReprovisionRequested='GR'; const CoGroupUpdated='GU';
  const CoPersonAdded='PA'; const CoPersonDeleted='PD'; const CoPersonEnteredGracePeriod='PG'; const CoPersonExpired='PX';
  const CoPersonPetitionProvisioned='PP'; const CoPersonPipelineProvisioned='PL'; const CoPersonReprovisionRequested='PR';
  const CoPersonUnexpired='PY'; const CoPersonUpdated='PU'; }
class Hash { static function extract($d, $path) { $r = array(); foreach (($d['Identifier'] ?? array()) as $i) { if ($i['type'] == 'uid') $r[] = $i['identifier']; } return $r; } }
class CoProvisionerPluginTarget { public $logs = array(); public function log($m, $t = 'error') { $this->logs[] = "$t: $m"; } }
require "$P/Lib/CephClientException.php"; require "$P/Lib/enum.php"; require "$P/Lib/CephCli.php";
require "$P/Lib/CephCliClient.php"; require "$P/Lib/CephRgwAdminCliClient.php"; require "$P/Model/CoCephProvisionerTarget.php";

class FakeRgw extends CephRgwAdminCliClient {       // real metadata logic, fake radosgw-admin
  public $users = array(); public $calls = array(); public $failPut = array();
  protected function ceph($op, $arrayOutput = false, $pipeInput = null) {
    $this->calls[] = $op;
    if (preg_match('/^metadata get user:(.*)$/', $op, $m)) {
      if (!isset($this->users[$m[1]])) throw new CephClientException('no such user', 2);
      return array('key' => 'user:' . $m[1], 'data' => $this->users[$m[1]]);
    }
    if (preg_match('/^metadata put user:(.*)$/', $op, $m)) {
      if (in_array($m[1], $this->failPut)) throw new CephClientException('put refused', 22);
      $d = json_decode($pipeInput, true); $this->users[$m[1]] = $d['data']; return array();
    }
    throw new Exception("unexpected radosgw-admin op: $op");
  }
}
class FakeCeph extends CephCliClient {              // real entityExists/removeEntity, fake ceph
  public $entities = array(); public $calls = array(); public $failRm = false;
  protected function ceph($op, $arrayOutput = false, $pipeInput = null) {
    $this->calls[] = $op;
    if (preg_match('/^auth get-key client\.comanage\.(.*)$/', $op, $m)) {
      if (!in_array($m[1], $this->entities)) throw new CephClientException('Error ENOENT', 2);
      return 'k';
    }
    if (preg_match('/^auth rm client\.comanage\.(.*)$/', $op, $m)) {
      if ($this->failRm) throw new CephClientException('rm refused', 13);
      $this->entities = array_values(array_diff($this->entities, array($m[1]))); return '';
    }
    throw new Exception("unexpected ceph op: $op");
  }
}
class FakeCred { public $rows = array();
  function getCopersonCreds($id, $type) { $o = array(); foreach ($this->rows as $r) { if ($r['co_person_id'] == $id && in_array($r['type'], (array)$type)) $o[] = array('CoCephProvisionerCred' => $r); } return $o; } }
class T extends CoCephProvisionerTarget {
  public $rgw; public $ceph; public $did = array();
  function __construct() { $this->rgw = new FakeRgw('comanage', 'ceph'); $this->ceph = new FakeCeph('comanage', 'ceph'); $this->CoCephProvisionerCred = new FakeCred(); }
  function rgwAdminClientFactory($t) { return $this->rgw; }
  function cephClientFactory($t) { return $this->ceph; }
  function provisionCoPersonAction($t, $d) { $this->did[] = 'provision'; }
  function reprovisionCoPersonAction($t, $d) { $this->did[] = 'reprovision'; }
  function syncRgwCoPeople($t) { $this->did[] = 'syncRgw'; }
  function syncCephCoPeople($t) { $this->did[] = 'syncCeph'; }
}
function md($uid, $attr, $pid, $susp = 0) { return array('user_id' => $uid, 'display_name' => "$uid:$attr:$pid", 'suspended' => $susp, 'keys' => array()); }
function person($id, $uid, $status = 'A') { return array('CoPerson' => array('id' => $id, 'status' => $status), 'Identifier' => $uid ? array(array('type' => 'uid', 'identifier' => $uid)) : array()); }
function row($pid, $ident, $type, $primary = false) { return array('co_person_id' => $pid, 'identifier' => $ident, 'type' => $type, 'primaryid' => $primary); }
$pass = 0; $fail = 0;
function check($name, $cond) { global $pass, $fail; if ($cond) { $pass++; echo "ok    $name\n"; } else { $fail++; echo "FAIL  $name\n"; } }
function gets($t) { return count(array_filter($t->rgw->calls, fn($c) => strpos($c, 'metadata get') === 0)); }
function puts($t) { return array_values(array_map(fn($c) => substr($c, 18), array_filter($t->rgw->calls, fn($c) => strpos($c, 'metadata put') === 0))); }
$tgt = array('CoCephProvisionerTarget' => array('id' => 1));

// 1. ordinary update, everything under the current uid: no sync, no Ceph call at all
$t = new T(); $t->rgw->users = array('alice' => md('alice', 'copersonid', 7)); $t->ceph->entities = array('alice');
$t->CoCephProvisionerCred->rows = array(row(7, 'alice', 0, true), row(7, 'alice', 0), row(7, 'alice', 2, true));
$t->provision($tgt, 'PU', person(7, 'alice'));
check('PU plain: provision only, sync skipped', $t->did == array('provision'));
check('PU plain: zero Ceph calls for the check', count($t->rgw->calls) == 0 && count($t->ceph->calls) == 0);

// 2. update, person has an additional (cpownerid) RGW user: no sync, one metadata get
$t = new T(); $t->rgw->users = array('alice' => md('alice', 'copersonid', 7), 'alice-app' => md('alice-app', 'cpownerid', 7));
$t->CoCephProvisionerCred->rows = array(row(7, 'alice', 0, true), row(7, 'alice-app', 0), row(7, 'alice', 2, true));
$t->provision($tgt, 'PU', person(7, 'alice'));
check('PU with additional RGW user: sync skipped', $t->did == array('provision'));
check('PU with additional RGW user: one metadata get', gets($t) == 1 && count($t->ceph->calls) == 0);

// 3. uid renamed alice -> alicia, old primary RGW user still there: full sync runs
$t = new T(); $t->rgw->users = array('alice' => md('alice', 'copersonid', 7), 'alicia' => md('alicia', 'copersonid', 7));
$t->CoCephProvisionerCred->rows = array(row(7, 'alice', 0, true), row(7, 'alicia', 0, true));
$t->provision($tgt, 'PU', person(7, 'alicia'));
check('PU after rename (old RGW user exists): full sync runs', $t->did == array('provision', 'syncRgw', 'syncCeph'));

// 4. stale credential record whose RGW user is gone: nothing for the sync to do
$t = new T(); $t->rgw->users = array('alicia' => md('alicia', 'copersonid', 7));
$t->CoCephProvisionerCred->rows = array(row(7, 'alice', 0, true), row(7, 'alicia', 0, true));
$t->provision($tgt, 'PU', person(7, 'alicia'));
check('PU with stale RGW record (user gone): sync skipped', $t->did == array('provision'));

// 5. old ceph client key still there after rename: sync runs; key gone: skipped
$t = new T(); $t->ceph->entities = array('alice', 'alicia');
$t->CoCephProvisionerCred->rows = array(row(7, 'alice', 2, true), row(7, 'alicia', 2, true));
$t->provision($tgt, 'PU', person(7, 'alicia'));
check('PU after rename (old ceph key exists): full sync runs', $t->did == array('provision', 'syncRgw', 'syncCeph'));
$t = new T(); $t->ceph->entities = array('alicia');
$t->CoCephProvisionerCred->rows = array(row(7, 'alice', 2, true), row(7, 'alicia', 2, true));
$t->provision($tgt, 'PU', person(7, 'alicia'));
check('PU with stale ceph key record (key gone): sync skipped', $t->did == array('provision'));

// 6. expiry: key removed, own RGW users suspended, others untouched, no sync
$t = new T(); $t->ceph->entities = array('alice', 'bob');
$t->rgw->users = array('alice' => md('alice', 'copersonid', 7), 'alice-app' => md('alice-app', 'cpownerid', 7), 'alice-old' => md('alice-old', 'cpownerid', 7, 1),
                       'bob' => md('bob', 'copersonid', 8), 'svc' => array('user_id' => 'svc', 'display_name' => 'service account', 'suspended' => 0));
$t->CoCephProvisionerCred->rows = array(row(7, 'alice', 0, true), row(7, 'alice-app', 0), row(7, 'alice-old', 0), row(7, 'gone', 0), row(7, 'bob', 0), row(7, 'svc', 0), row(8, 'bob', 0, true));
$t->provision($tgt, 'PX', person(7, 'alice', 'XP'));
check('PX: no provision, no sync', $t->did == array());
check('PX: ceph key of the person removed, others kept', $t->ceph->entities == array('bob'));
check('PX: primary and additional RGW users suspended', $t->rgw->users['alice']['suspended'] === 1 && $t->rgw->users['alice-app']['suspended'] === 1);
check('PX: already-suspended user not rewritten', puts($t) == array('alice', 'alice-app'));
check('PX: RGW user of another person untouched', $t->rgw->users['bob']['suspended'] === 0);
check('PX: unmanaged RGW user untouched', $t->rgw->users['svc']['suspended'] === 0);

// 7. expiry when the key removal fails: RGW users still suspended, error raised
$t = new T(); $t->ceph->failRm = true; $t->rgw->users = array('alice' => md('alice', 'copersonid', 7));
$t->CoCephProvisionerCred->rows = array(row(7, 'alice', 0, true));
$thrown = false; try { $t->provision($tgt, 'PX', person(7, 'alice', 'XP')); } catch (RuntimeException $e) { $thrown = true; }
check('PX with failing key removal: RGW user still suspended, error raised', $thrown && $t->rgw->users['alice']['suspended'] === 1);

// 8. expiry when one RGW change fails: the other is still made, error raised
$t = new T(); $t->rgw->failPut = array('alice'); $t->rgw->users = array('alice' => md('alice', 'copersonid', 7), 'alice-app' => md('alice-app', 'cpownerid', 7));
$t->CoCephProvisionerCred->rows = array(row(7, 'alice', 0, true), row(7, 'alice-app', 0));
$thrown = false; try { $t->provision($tgt, 'PX', person(7, 'alice', 'XP')); } catch (RuntimeException $e) { $thrown = true; }
check('PX with one failing RGW change: other user suspended, error raised', $thrown && $t->rgw->users['alice-app']['suspended'] === 1);

// 9. expiry of a person without uid: no key call, recorded RGW users still suspended
$t = new T(); $t->rgw->users = array('alice-app' => md('alice-app', 'cpownerid', 7)); $t->CoCephProvisionerCred->rows = array(row(7, 'alice-app', 0));
$t->provision($tgt, 'PX', person(7, null, 'XP'));
check('PX without uid: no ceph call, recorded RGW user suspended', count($t->ceph->calls) == 0 && $t->rgw->users['alice-app']['suspended'] === 1);

// 10. unexpire to Active: provision, additional users re-enabled; unexpire to Suspended: stays suspended
$t = new T(); $t->rgw->users = array('alice' => md('alice', 'copersonid', 7, 1), 'alice-app' => md('alice-app', 'cpownerid', 7, 1));
$t->CoCephProvisionerCred->rows = array(row(7, 'alice', 0, true), row(7, 'alice-app', 0));
$t->provision($tgt, 'PY', person(7, 'alice', 'A'));
check('PY to Active: provision, all RGW users re-enabled', $t->did == array('provision') && $t->rgw->users['alice-app']['suspended'] === 0 && $t->rgw->users['alice']['suspended'] === 0);
$t = new T(); $t->rgw->users = array('alice-app' => md('alice-app', 'cpownerid', 7, 1)); $t->CoCephProvisionerCred->rows = array(row(7, 'alice-app', 0));
$t->provision($tgt, 'PY', person(7, 'alice', 'S'));
check('PY to Suspended: provision, RGW users stay suspended', $t->did == array('provision') && $t->rgw->users['alice-app']['suspended'] === 1);

// 11. other actions
$t = new T(); $t->provision($tgt, 'PG', person(7, 'alice', 'GP')); check('PG: nothing done, no Ceph call', $t->did == array() && !$t->rgw->calls && !$t->ceph->calls);
$t = new T(); $t->provision($tgt, 'PD', person(7, 'alice', 'D')); check('PD: full sync still runs', $t->did == array('syncRgw', 'syncCeph'));
$t = new T(); $t->provision($tgt, 'PA', person(7, 'alice')); check('PA: provision only', $t->did == array('provision'));
$t = new T(); $t->provision($tgt, 'PR', person(7, 'alice')); check('PR: reprovision only', $t->did == array('reprovision'));
echo "\n$pass passed, $fail failed\n"; exit($fail ? 1 : 0);
