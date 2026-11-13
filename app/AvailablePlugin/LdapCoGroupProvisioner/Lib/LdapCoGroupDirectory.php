<?php
/**
 * OSiRIS LDAP CO Group Provisioner: the LDAP reads and writes LdapCoGroupSync asks for, on a real server.
 */
App::uses('LdapCoGroupSync', 'LdapCoGroupProvisioner.Lib');

class LdapCoGroupDirectory {
  const NETWORK_TIMEOUT = 10;
  const LDAP_NO_SUCH_OBJECT = 0x20;

  protected $cxn = null;

  public function __construct($serverUrl, $bindDn, $password) {
    $cxn = ldap_connect($serverUrl);
    if(!$cxn) {
      throw new RuntimeException("LdapCoGroupProvisioner: cannot connect to $serverUrl");
    }
    ldap_set_option($cxn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($cxn, LDAP_OPT_NETWORK_TIMEOUT, self::NETWORK_TIMEOUT);
    if(!@ldap_bind($cxn, $bindDn, $password)) {
      throw new RuntimeException('LdapCoGroupProvisioner: bind failed: ' . ldap_error($cxn), ldap_errno($cxn));
    }
    $this->cxn = $cxn;
  }

  public function close() {
    if($this->cxn) { @ldap_unbind($this->cxn); $this->cxn = null; }
  }

  // ---- reads ----------------------------------------------------------------------------------------------

  // Groups named $cn anywhere under $base: array of ('dn', 'cn', 'members')
  public function groupsByCn($base, $cn) {
    return $this->groups($base, '(&(objectClass=posixGroup)(cn=' . LdapCoGroupSync::escapeFilterValue($cn) . '))');
  }

  // Groups under $base that have $memberDn as a uniqueMember
  public function groupsByMember($base, $memberDn) {
    return $this->groups($base, '(&(objectClass=posixGroup)(uniqueMember=' . LdapCoGroupSync::escapeFilterValue($memberDn) . '))');
  }

  // Every gidNumber of a posixGroup under $root
  public function gidNumbers($root) {
    $ret = array();
    foreach($this->search($root, '(objectClass=posixGroup)', array('gidNumber'), 'sub') as $e) {
      foreach($e['gidnumber'] ?? array() as $g) { $ret[] = (int)$g; }
    }
    return $ret;
  }

  public function exists($dn) {
    return count($this->search($dn, '(objectClass=*)', array('1.1'), 'base')) > 0;
  }

  // ---- writes ---------------------------------------------------------------------------------------------

  public function addEntry($dn, array $attrs) {
    $this->check(@ldap_add($this->cxn, $dn, $attrs), "add $dn");
  }

  // An empty list removes the attribute
  public function replaceMembers($dn, array $memberDns) {
    $this->check(@ldap_mod_replace($this->cxn, $dn, array('uniqueMember' => array_values($memberDns))), "replace members of $dn");
  }

  public function addMember($dn, $memberDn) {
    $this->check(@ldap_mod_add($this->cxn, $dn, array('uniqueMember' => array($memberDn))), "add member to $dn");
  }

  public function removeMember($dn, $memberDn) {
    $this->check(@ldap_mod_del($this->cxn, $dn, array('uniqueMember' => array($memberDn))), "remove member from $dn");
  }

  // ---- internals ------------------------------------------------------------------------------------------

  protected function groups($base, $filter) {
    $ret = array();
    foreach($this->search($base, $filter, array('cn', 'uniqueMember'), 'sub') as $e) {
      $ret[] = array('dn' => $e['dn'], 'cn' => $e['cn'][0] ?? '', 'members' => $e['uniquemember'] ?? array());
    }
    return $ret;
  }

  // Entries as ('dn' => ..., '<attribute, lower case>' => array of values). A base that doesn't exist gives none.
  protected function search($base, $filter, array $attrs, $scope) {
    if($scope == 'base') {
      $res = @ldap_read($this->cxn, $base, $filter, $attrs);
    } else {
      $res = @ldap_search($this->cxn, $base, $filter, $attrs);
    }
    if($res === false) {
      if(ldap_errno($this->cxn) == self::LDAP_NO_SUCH_OBJECT) { return array(); }
      throw new RuntimeException("LdapCoGroupProvisioner: search of $base failed: " . ldap_error($this->cxn), ldap_errno($this->cxn));
    }
    $ret = array();
    for($entry = ldap_first_entry($this->cxn, $res); $entry; $entry = ldap_next_entry($this->cxn, $entry)) {
      $row = array('dn' => ldap_get_dn($this->cxn, $entry));
      $got = ldap_get_attributes($this->cxn, $entry);
      for($i = 0; $i < $got['count']; $i++) {
        $name = $got[$i];
        $vals = $got[$name];
        unset($vals['count']);
        $row[strtolower($name)] = array_values($vals);
      }
      $ret[] = $row;
    }
    return $ret;
  }

  protected function check($ok, $what) {
    if(!$ok) {
      throw new RuntimeException("LdapCoGroupProvisioner: $what failed: " . ldap_error($this->cxn), ldap_errno($this->cxn));
    }
  }
}
