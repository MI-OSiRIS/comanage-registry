<?php
/**
 * OSiRIS LDAP CO Group Provisioner Target
 *
 * Keeps the LDAP copies of COmanage's CO and COU groups in step with COmanage: the job Grouper used to do.
 * The groups are posixGroup + groupOfUniqueNames entries with uniqueMember values, in the layout Grouper wrote
 * (see Lib/LdapCoGroupSync.php), so sssd, the memberOf plugin and sudo rules on the hosts need no change.
 *
 * What is provisioned:
 *  - Groups of type Standard, Active Members, All Members and Admins. Not Approvers groups, and not the per-person
 *    groups linked to a Unix Cluster (LdapUserPosixGroupProvisioner writes those).
 *  - Members: people with a DN from the LDAP provisioner whose membership is valid now. Only people who are Active
 *    or in their Grace Period count, except in All Members groups, which list everyone COmanage provisions.
 *  - On a change to a group: that group's members. On a change to a person: that person's memberships, so
 *    expiring someone takes them out of every group at once, including Admins groups, where COmanage leaves the
 *    membership in place.
 *
 * Nothing is written unless "apply changes" is set on the target; until then every change is only logged.
 */

App::uses("CoProvisionerPluginTarget", "Model");
App::uses('LdapCoGroupSync', 'LdapCoGroupProvisioner.Lib');
App::uses('LdapCoGroupDirectory', 'LdapCoGroupProvisioner.Lib');

class CoLdapCoGroupProvisionerTarget extends CoProvisionerPluginTarget {
  // Define class name for cake
  public $name = "CoLdapCoGroupProvisionerTarget";

  // Add behaviors
  public $actsAs = array('Containable');

  // Association rules from this model to other models
  public $belongsTo = array("CoProvisioningTarget");

  // Default display field for cake generated views
  public $displayField = "co_provisioning_target_id";

  // Validation rules for table elements. Only the parent is required: COmanage creates the row with nothing
  // else when the provisioning target is added, and the rest is filled in on the edit page.
  public $validate = array(
    'co_provisioning_target_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'message' => 'A CO Provisioning Target ID must be provided'
    ),
    'co_ldap_provisioner_target_id' => array(
      'rule' => 'numeric',
      'required' => false,
      'allowEmpty' => true
    ),
    'basedn' => array(
      'rule' => array('maxLength', 256),
      'required' => false,
      'allowEmpty' => true
    ),
    'gid_min' => array(
      'rule' => array('range', 999, 2000000000),
      'required' => false,
      'allowEmpty' => true
    ),
    'apply_changes' => array(
      'rule' => 'boolean',
      'required' => false,
      'allowEmpty' => true
    )
  );

  // Group types written to LDAP
  protected $managedGroupTypes = array(
    GroupEnum::Standard,
    GroupEnum::ActiveMembers,
    GroupEnum::AllMembers,
    GroupEnum::Admins
  );

  // People with one of these statuses are members of a group in LDAP
  protected $activeStatuses = array(
    StatusEnum::Active,
    StatusEnum::GracePeriod
  );

  // All Members groups list everyone the LDAP provisioner keeps an entry for (as ProvisionerBehavior)
  protected $provisionedStatuses = array(
    StatusEnum::Active,
    StatusEnum::Expired,
    StatusEnum::GracePeriod,
    StatusEnum::Locked,
    StatusEnum::Suspended
  );

  /**
   * Provision for the specified CO Group or CO Person.
   *
   * @param  Array CO Provisioning Target data
   * @param  ProvisioningActionEnum Registry transaction type triggering provisioning
   * @param  Array Provisioning data, populated with ['CoPerson'] or ['CoGroup']
   * @return Boolean True on success
   * @throws RuntimeException
   */
  public function provision($coProvisioningTargetData, $op, $provisioningData) {
    $isGroup = !empty($provisioningData['CoGroup']['id']);
    $isPerson = !$isGroup && !empty($provisioningData['CoPerson']['id']);

    if(!$isGroup && !$isPerson) {
      // email lists, services
      return true;
    }

    $cfg = $coProvisioningTargetData['CoLdapCoGroupProvisionerTarget'];

    if(empty($cfg['co_ldap_provisioner_target_id']) || empty($cfg['basedn']) || empty($cfg['gid_min'])) {
      throw new InvalidArgumentException(_txt('er.ldapcogroup.config'));
    }

    $CoLdapProvisionerTarget = ClassRegistry::init('LdapProvisioner.CoLdapProvisionerTarget');

    $args = array();
    $args['conditions']['CoLdapProvisionerTarget.id'] = $cfg['co_ldap_provisioner_target_id'];
    $args['contain'] = false;
    $ldapTarget = $CoLdapProvisionerTarget->find('first', $args);

    if(empty($ldapTarget['CoLdapProvisionerTarget']['group_basedn'])) {
      throw new InvalidArgumentException(_txt('er.ldapcogroup.ldap'));
    }
    $ldapTarget = $ldapTarget['CoLdapProvisionerTarget'];

    $dir = $this->directoryFactory($ldapTarget);

    try {
      $sync = new LdapCoGroupSync($dir,
                                  $cfg['basedn'],
                                  $ldapTarget['group_basedn'],
                                  $cfg['gid_min'],
                                  empty($cfg['apply_changes']),
                                  array($this, 'logSync'));

      if($isGroup) {
        switch($op) {
          case ProvisioningActionEnum::CoGroupAdded:
          case ProvisioningActionEnum::CoGroupUpdated:
            $this->provisionCoGroup($sync, $ldapTarget, $provisioningData['CoGroup'], true);
            break;
          case ProvisioningActionEnum::CoGroupReprovisionRequested:
            // An admin asked for this group by name: no limit on how many members may be removed
            $this->provisionCoGroup($sync, $ldapTarget, $provisioningData['CoGroup'], false);
            break;
          case ProvisioningActionEnum::CoGroupDeleted:
            if($this->isManagedGroup($provisioningData['CoGroup'], $this->personalGroupIds())) {
              $sync->emptyGroup($provisioningData['CoGroup']['name']);
            }
            break;
          default:
            break;
        }
      } else {
        $this->provisionCoPerson($sync, $ldapTarget, $provisioningData['CoPerson'],
                                 $op == ProvisioningActionEnum::CoPersonDeleted);
      }
    }
    finally {
      $dir->close();
    }

    return true;
  }

  /**
   * Make one group's LDAP members match COmanage.
   */
  protected function provisionCoGroup($sync, $ldapTarget, $coGroup, $guard) {
    if(!$this->isManagedGroup($coGroup, $this->personalGroupIds())) {
      return;
    }

    if(isset($coGroup['status']) && $coGroup['status'] != SuspendableStatusEnum::Active) {
      $sync->emptyGroup($coGroup['name']);
      return;
    }

    // Current members and their person status
    $args = $this->validMembershipConditions();
    $args['conditions']['CoGroupMember.co_group_id'] = $coGroup['id'];
    $args['fields'] = array('CoGroupMember.id', 'CoGroupMember.co_person_id');
    $args['contain'] = false;
    $personIds = array_values(array_unique(ClassRegistry::init('CoGroupMember')->find('list', $args)));

    $eligible = array();

    if(!empty($personIds)) {
      $args = array();
      $args['conditions']['CoPerson.id'] = $personIds;
      $args['fields'] = array('CoPerson.id', 'CoPerson.status');
      $args['contain'] = false;

      foreach(ClassRegistry::init('CoPerson')->find('list', $args) as $id => $status) {
        if($this->isMember($status, $coGroup['group_type'], false)) {
          $eligible[] = $id;
        }
      }
    }

    $sync->syncGroup($coGroup['name'],
                     $this->couPath(!empty($coGroup['cou_id']) ? $coGroup['cou_id'] : null),
                     $this->personDns($ldapTarget, $eligible),
                     $guard);
  }

  /**
   * Make one person's LDAP group memberships match COmanage.
   */
  protected function provisionCoPerson($sync, $ldapTarget, $coPerson, $deleted) {
    $dns = $this->personDns($ldapTarget, array($coPerson['id']));

    if(empty($dns)) {
      // Never provisioned to LDAP, so in no LDAP group
      return;
    }

    $personal = $this->personalGroupIds();

    // Every group of this CO that we manage: LDAP name => type
    $args = array();
    $args['conditions']['CoGroup.co_id'] = $coPerson['co_id'];
    $args['conditions']['CoGroup.group_type'] = $this->managedGroupTypes;
    $args['fields'] = array('CoGroup.id', 'CoGroup.name', 'CoGroup.group_type', 'CoGroup.status');
    $args['contain'] = false;

    $managed = array();   // CO Group id => LDAP name
    $types = array();     // CO Group id => type
    $live = array();      // CO Group id => true if the group is active

    foreach(ClassRegistry::init('CoGroup')->find('all', $args) as $g) {
      if(in_array($g['CoGroup']['id'], $personal)) { continue; }
      $managed[ $g['CoGroup']['id'] ] = LdapCoGroupSync::cnForGroupName($g['CoGroup']['name']);
      $types[ $g['CoGroup']['id'] ] = $g['CoGroup']['group_type'];
      $live[ $g['CoGroup']['id'] ] = ($g['CoGroup']['status'] == SuspendableStatusEnum::Active);
    }

    // The person's valid memberships
    $args = $this->validMembershipConditions();
    $args['conditions']['CoGroupMember.co_person_id'] = $coPerson['id'];
    $args['fields'] = array('CoGroupMember.id', 'CoGroupMember.co_group_id');
    $args['contain'] = false;

    $desired = array();

    foreach(ClassRegistry::init('CoGroupMember')->find('list', $args) as $gid) {
      if(isset($managed[$gid]) && $live[$gid] && $this->isMember($coPerson['status'], $types[$gid], $deleted)) {
        $desired[] = $managed[$gid];
      }
    }

    $sync->reconcilePerson($dns[0],
                           array_values(array_unique($desired)),
                           array_values($managed),
                           !$deleted && in_array($coPerson['status'], $this->activeStatuses));
  }

  /**
   * Does a person with this status belong in the LDAP copy of a group of this type?
   */
  public function isMember($personStatus, $groupType, $deleted) {
    if($deleted) {
      return false;
    }
    if($groupType == GroupEnum::AllMembers) {
      return in_array($personStatus, $this->provisionedStatuses);
    }
    return in_array($personStatus, $this->activeStatuses);
  }

  /**
   * Is this a group we write to LDAP?
   */
  public function isManagedGroup($coGroup, $personalGroupIds) {
    return !empty($coGroup['name'])
           && isset($coGroup['group_type'])
           && in_array($coGroup['group_type'], $this->managedGroupTypes)
           && !in_array($coGroup['id'], $personalGroupIds);
  }

  // Find conditions for memberships that are in force now (the same test CoGroupMember uses)
  protected function validMembershipConditions() {
    $now = date('Y-m-d H:i:s', time());
    $args = array();
    $args['conditions']['CoGroupMember.member'] = true;
    $args['conditions']['AND'][] = array(
      'OR' => array(
        'CoGroupMember.valid_from IS NULL',
        'CoGroupMember.valid_from < ' => $now
      )
    );
    $args['conditions']['AND'][] = array(
      'OR' => array(
        'CoGroupMember.valid_through IS NULL',
        'CoGroupMember.valid_through > ' => $now
      )
    );
    return $args;
  }

  // IDs of the per-person groups: those linked to a Unix Cluster
  protected function personalGroupIds() {
    if(!CakePlugin::loaded('UnixCluster')) {
      return array();
    }
    $args = array();
    $args['fields'] = array('UnixClusterGroup.id', 'UnixClusterGroup.co_group_id');
    $args['contain'] = false;
    return array_values(ClassRegistry::init('UnixCluster.UnixClusterGroup')->find('list', $args));
  }

  // DNs the LDAP provisioner has recorded for these people
  protected function personDns($ldapTarget, $coPersonIds) {
    if(empty($coPersonIds)) {
      return array();
    }
    $args = array();
    $args['conditions']['CoLdapProvisionerDn.co_ldap_provisioner_target_id'] = $ldapTarget['id'];
    $args['conditions']['CoLdapProvisionerDn.co_person_id'] = $coPersonIds;
    $args['fields'] = array('CoLdapProvisionerDn.co_person_id', 'CoLdapProvisionerDn.dn');
    $args['contain'] = false;
    return array_values(array_filter(ClassRegistry::init('LdapProvisioner.CoLdapProvisionerDn')->find('list', $args)));
  }

  // Names of a COU and its parents, top first
  protected function couPath($couId) {
    $path = array();
    $Cou = ClassRegistry::init('Cou');
    $seen = array();

    while($couId && !isset($seen[$couId])) {
      $seen[$couId] = true;
      $args = array();
      $args['conditions']['Cou.id'] = $couId;
      $args['fields'] = array('Cou.id', 'Cou.name', 'Cou.parent_id');
      $args['contain'] = false;
      $cou = $Cou->find('first', $args);
      if(empty($cou['Cou']['name'])) { break; }
      array_unshift($path, $cou['Cou']['name']);
      $couId = $cou['Cou']['parent_id'];
    }
    return $path;
  }

  // Split out so a test can supply a fake directory
  protected function directoryFactory($ldapTarget) {
    return new LdapCoGroupDirectory($ldapTarget['serverurl'], $ldapTarget['binddn'], $ldapTarget['password']);
  }

  public function logSync($msg) {
    $this->log('LdapCoGroupProvisioner - ' . $msg, 'debug');
  }
}
