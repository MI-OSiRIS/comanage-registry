<?php
/**
 * COmanage Registry Ceph RGW Provisioner target model
 *
 * Portions licensed to the University Corporation for Advanced Internet
 * Development, Inc. ("UCAID") under one or more contributor license agreements.
 * See the NOTICE file distributed with this work for additional information
 * regarding copyright ownership.
 *
 * UCAID licenses this file to you under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with the
 * License. You may obtain a copy of the License at:
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 * @link          http://www.internet2.edu/comanage COmanage Project
 * @package       registry-plugin
 * @since         COmanage Registry v2.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

App::uses("CoProvisionerPluginTarget", "Model");
App::uses('CephCliClient', 'CephProvisioner.Lib');
App::uses('CephRgwAdminCliClient', 'CephProvisioner.Lib');
App::uses('CephApiClient', 'CephProvisioner.Lib');
//App::uses('GrouperRestClient', 'GrouperProvisioner.Lib');
//App::uses('GrouperRestClientException', 'GrouperProvisioner.Lib');

class CoCephProvisionerTarget extends CoProvisionerPluginTarget {
  // Define class name for cake
  public $name = "CoCephProvisionerTarget";
  
  // Add behaviors
  public $actsAs = array('Containable');
  
  // Association rules from this model to other models
  public $belongsTo = array("CoProvisioningTarget");

  public $hasMany = array(
      "CoCephProvisionerDataPool" => array(
      'className' => 'CephProvisioner.CoCephProvisionerDataPool',
      'dependent' => true
    ),
  );
  
  // Default display field for cake generated views
  public $displayField = "ceph_client_name";
  
  // Validation rules for table elements
  public $validate = array(
    
    'co_provisioning_target_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'message' => 'A CO Provisioning Target ID must be provided'
    ),
      'co_ldap_provisioner_target_id' => array(
      'rule' => 'numeric',
      'required' => false,
      'message' => 'A CO LDAP Provisioning Target ID must be provided'
    ),
    'co_grouper_provisioner_target_id' => array(
      'rule' => 'numeric',
      'required' => false,
      'message' => 'A CO Grouper Provisioning Target ID must be provided'
    ),
    'rgw_url' => array (
      'rule' => array('custom','/^https?:\/\/.*/'),
      'required' => true,
      'allowEmpty' => false,
      'message'    => 'Please enter a valid http or https URL'
    ),
    'opt_rgw_admin_api' => array (
      'rule' => 'boolean',
      'on'   => false
    ),
    'opt_ceph_admin_api' => array (
      'rule' => 'boolean',
      'on'   => false
    ),
    'opt_posix_lookup_ldap' => array (
      'rule' => 'boolean',
      'on'   => false
    ),
     'ceph_admin_api_url' => array (
      'rule' => array('custom','/^https?:\/\/.*/'),
      'required' => false,
      'allowEmpty' => true,
      'message'    => 'Please enter a valid http or https URL'
    ),
     'rgw_admin_api_url' => array (
      'rule' => array('custom','/^https?:\/\/.*/'),
      'required' => false,
      'allowEmpty' => true,
      'message'    => 'Please enter a valid http or https URL'
    ),
      'rgw_access_key' => array (
      'rule' => 'notBlank',
      'required' => true,
      'allowEmpty' => false
    ),
      // secret might be blank if using an LDAP user with encoded access key
      'rgw_secret_key' => array (
      'rule' => 'notBlank',
      'required' => false,
      'allowEmpty' => true
    ),
      'ceph_client_name' => array (
      'rule' => 'notBlank',
      'required' => false,
      'allowEmpty' => true
    ),
      'ceph_cluster' => array (
      'rule' => 'notBlank',
      'required' => false,
      'allowEmpty' => true
    ),
      'ceph_config_file' => array (
      'rule' => array('custom','/^\/.+/'),
      'required' => false,
      'allowEmpty' => true
    ),
      'opt_create_cou_data_pools' => array (
      'rule' => 'boolean',
      'on'   => true
    ),
      'cou_data_pool_pgcount' => array (
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    ),
      'rgw_user_separator' => array (
      'rule' => 'notBlank',
      'required' => true,
      'allowEmpty' => false
    ),
      'ceph_user_prefix' => array (
      'rule' => 'notBlank',
      'required' => true,
      'allowEmpty' => false
    ),
  );
  
  /**
   * Provision for the specified CO Person.
   *
   * @since  COmanage Registry v2.0.0
   * @param  Array CO Provisioning Target data
   * @param  ProvisioningActionEnum Registry transaction type triggering provisioning
   * @param  Array Provisioning data, populated with ['CoPerson'] or ['CoGroup']
   * @return Boolean True on success
   * @throws RuntimeException
   */
  
  public function provision($coProvisioningTargetData, $op, $provisioningData) {
  
    $ceph = $this->cephClientFactory($coProvisioningTargetData);

    $person = isset($provisioningData['CoPerson']['id']);
    $group = isset($provisioningData['CoGroup']['id']);

    try {
      $this->log("CephProvisioner called for op: " . $op );
      switch($op) {
        case ProvisioningActionEnum::CoPersonAdded:
        case ProvisioningActionEnum::CoPersonPetitionProvisioned:
        case ProvisioningActionEnum::CoPersonPipelineProvisioned:
        case ProvisioningActionEnum::CoPersonReprovisionRequested:
        case ProvisioningActionEnum::CoPersonUnexpired:
        case ProvisioningActionEnum::CoPersonUpdated:
          $this->provisionCoPersonAction($coProvisioningTargetData,$provisioningData);
          break;
        case ProvisioningActionEnum::CoPersonUpdated:
          $this->updateCoPersonAction($coProvisioningTargetData, $provisioningData);
          break;
        case ProvisioningActionEnum::CoPersonExpired:
        case ProvisioningActionEnum::CoPersonEnteredGracePeriod:
        case ProvisioningActionEnum::CoPersonDeleted:
          $this->deleteCoPersonAction($coProvisioningTargetData, $provisioningData);
          // remove user from acls on cou bucket
          
          break;
        case ProvisioningActionEnum::CoGroupAdded:
        case ProvisioningActionEnum::CoGroupReprovisionRequested:
          $this->provisionCoGroupAction($coProvisioningTargetData,$provisioningData);
          break;
        case ProvisioningActionEnum::CoGroupUpdated:
          $this->updateCoGroupAction($coProvisioningTargetData,$provisioningData);
          break;
          // create data pool for cou
          // add data pool to rgw pools with placement tags matching admin group
          // make sure cou bucket exists named like tolower(cou-name)
          // make sure members of cou have read acl to bucket
          // make sure admins of cou have write acl to bucket
          break;
        case ProvisioningActionEnum::CoGroupDeleted:
          $this->deleteCoGroupAction($coProvisioningTargetData,$provisioningData);
          break;
        default:
          throw new RuntimeException("CephProvisioner action $op is not implemented");
          break;
      }
  } catch (CephClientException $e) {
    throw new RuntimeException("Ceph Client Error:" . $e->getMessage());
  }
    
    return true;
    
  }

  public function provisionCoPersonAction($coProvisioningTargetData,$coPersonData) {
    
    if(!isset($coPersonData['CoPerson'])) {
      $this->log("CephProvisioner provisionCoPersonAction not passed CoPerson object");
      return false;
    }

    $this->log("Ceph provisioner provisionCoPersonAction - coPerson data: " . json_encode($coPersonData), 'debug');

    // add user ceph client key 
    $this->updateCephClientKey($coProvisioningTargetData, $coPersonData);

    // Add rgw users with default placement tags 
    $this->addRgwCouUsers($coProvisioningTargetData, $coPersonData);

  }

  // the external service sync is time consuming, this seems like a good place to run it without
  // running it everytime we provision or reprovision
  public function updateCoPersonAction($coProvisioningTargetData, $coPersonData) {
    $this->syncRgwCoPeople($coProvisioningTargetData);
    $this->syncCephCoPeople($coProvisioningTargetData);
    $this->provisionCoPersonAction($coProvisioningTargetData,$coPersonData);
  }

  public function deleteCoPersonAction($coProvisioningTargetData,$coPersonData) {
    $this->syncRgwCoPeople($coProvisioningTargetData);
    $this->syncCephCoPeople($coProvisioningTargetData);
  }

  public function deleteCoGroupAction($coProvisioningTargetData, $coGroupData) {
    //$ceph = $this -> cephClientFactory($coProvisioningTargetData);
    if (!$this -> isCouAdminOrMembersGroup($coGroupData)) {
      return true;
    }

    // this does not delete pools in ceph, but removes them from comanage db and removes cou user associations in Ceph.  It also removes S3 placement targets.
    if (!$this -> CoCephProvisionerDataPool -> deleteCouDataPoolAssociations($coProvisioningTargetData, $coGroupData)) {
        throw new RuntimeException(_txt('er.cephprovisioner.datapool.delete'));
    }

    // sync users to remove those which are now invalidated due to removal of the group 
    $this->syncRgwCoPeople($coProvisioningTargetData);
    $this->syncCephCoPeople($coProvisioningTargetData);

    return true;

  }

  public function provisionCoGroupAction($coProvisioningTargetData,$coGroupData) {
    
    // nothing to provision
    if (!$this -> isCouAdminOrMembersGroup($coGroupData)) {
      return true;
    }

    if (!$couDataPools = $this -> CoCephProvisionerDataPool -> updateCouDataPools($coProvisioningTargetData, $coGroupData)) {
        throw new RuntimeException(_txt('er.cephprovisioner.datapool.provision'));
    }

    if (!$this->associatePoolsToApplications($coProvisioningTargetData,$coGroupData, $couDataPools)) {
      throw new RuntimeException(_txt('er.cephprovisioner.datapool.associate'));
    }
  }

  // the external service sync is time consuming, this seems like a good place to run it without
  // running it everytime we provision or reprovision
  public function updateCoGroupAction($coProvisioningTargetData, $coGroupData) {
    $this->syncRgwCoPeople($coProvisioningTargetData);
    $this->syncCephCoPeople($coProvisioningTargetData);
    $this->provisionCoGroupAction($coProvisioningTargetData, $coGroupData);
  }

  // Add rgw users with default placement tags (userid_couname format reserved for use by this module only)
  public function addRgwCouUsers($coProvisioningTargetData,$coPersonData) {
    $active = GroupEnum::ActiveMembers;
    $admin = GroupEnum::Admins;
    $rgwa = $this -> rgwAdminClientFactory($coProvisioningTargetData);
    $separator = $coProvisioningTargetData['CoCephProvisionerTarget']['rgw_user_separator'];

     // extract user id identifier
    $uid_identifier = IdentifierEnum::UID;
    $userid = Hash::extract($coPersonData, "Identifier.{n}[type=uid].identifier");

    if (empty($userid)) {
      throw RuntimeException(_txt('er.cephprovisioner.identifier'));
    }

    $this->log("Ceph Provisioner addRgwCouUsers found userid: " . json_encode($userid), 'debug');

    // get list of cou for which this person is active member or admin
    $active_cou = Hash::extract($coPersonData, "CoGroupMember.{n}.CoGroup[group_type=$active].cou_id");
    $admin_cou = Hash::extract($coPersonData, "CoGroupMember.{n}.CoGroup[group_type=$admin].cou_id");

    if (!empty($active_cou)) {
      // TODO: create acl on COU data bucket
      foreach ($active_cou as $cou_id) {
        if ($cou_id == null) { continue; }

        // make a data structure compatible with this function
        $coGroupData = ['CoGroup' => []];
        $coGroupData['CoGroup']['cou_id'] = $cou_id;
        $coGroupData['CoGroup']['group_type'] = GroupEnum::ActiveMembers;
        $cou_name = $this->CoCephProvisionerDataPool->getCouName($coGroupData);
        // add user as uid_cou matching what the ldap token provisioner will do
        $constructedUser = $userid[0] . $separator . strtolower($cou_name);
        $this->log("Ceph provisioner provisonCoPersonAction - constructed user: " . json_encode($constructedUser), 'debug');
        $rgwa->addUserPlacementTag($constructedUser, $cou_name);
      }
       $this->log("Ceph provisioner provisonCoPersonAction - ActiveCou: " . json_encode($active_cou), 'debug');
    }
  }

  public function syncCephCoPeople($coProvisioningTargetData) {
    $ceph = $this->cephClientFactory($coProvisioningTargetData);
    $configuredPrefix = $coProvisioningTargetData['CoCephProvisionerTarget']['ceph_user_prefix'];
    $fullPrefix = "client." . $configuredPrefix . '.';
    $CoPersonObject = ClassRegistry::init('CoPerson');

    // this only returns entities we manage (but we'll double check later too)
    $entities = $ceph->getEntities($configuredPrefix);

    foreach ($entities as $ent) {
  
      // these shouldn't happen and could lead to wrongly deleting unmanaged client entities or core cluster daemon entities, throw an exception
      if (strpos($ent, 'osd') !== false || 
          strpos($ent, 'mgr') !== false ||
          strpos($ent, $fullPrefix) === false)  { 
        throw new RuntimeException(_txt('er.cephprovisioner.entity')); 
      }

      $userid = str_replace($fullPrefix, "", $ent);
      $args = array();
      $args['conditions']['CoProvisioningTarget.id'] = $coProvisioningTargetData['CoCephProvisionerTarget']['co_provisioning_target_id'];
      $args['contain'] = false;
      $target = $this->CoProvisioningTarget->find('first', $args);
      $coId = $target['CoProvisioningTarget']['co_id'];

      try {
        $coPersonId = $CoPersonObject->idForIdentifier($coId,$userid,IdentifierEnum::UID);
      }  catch (InvalidArgumentException $e) {
        if ($e->getMessage() == 'Unknown Identifier') {
          $this->log("CephProvisioner - syncCephCoPeople identifier $userid not found - deleting ceph entity: $fullPrefix$userid - " . "Caught exception was " . $e->getCode() . ':' . $e->getMessage(), 'error');
          // all the ceph lib classes hard-code client. into operations
          $ceph->removeEntity("$configuredPrefix.$userid");
        } else {
          throw $e;
        }
      }
    }
  }

  // this checks users matching our naming pattern (uid_cou) and removes any 
  // which do not have valid uid or cou.  
  // FIXME: calling this for every person or group update won't scale well
  public function syncRgwCoPeople($coProvisioningTargetData) {
      $rgwa = $this->rgwAdminClientFactory($coProvisioningTargetData);
      $sep = $coProvisioningTargetData['CoCephProvisionerTarget']['rgw_user_separator'];
      $userList = $rgwa->listRgwUsers($sep);

      foreach ($userList as $user) {
          // sanity check, be sure this is really a managed user with our separator
          if (strpos($user, $sep) === false) { continue; }
          $this->log("CephProvisioner - syncRgwCoPeople checking user: " . json_encode($user),'debug');
          $CoGroupObject = ClassRegistry::init('CoGroup');
          $CouObject = ClassRegistry::init('Cou');
          $CoPersonObject = ClassRegistry::init('CoPerson');

          $userComp = explode('_', $user);
          $rgw_uid = $userComp[0];
          $rgw_cou = $userComp[1];

          //check cou validity
          $args = array();
          $args['conditions']['Cou.name'] = $rgw_cou;
          $args['contain'] = false;
          $couData = $CouObject->find('first', $args);
          $this->log("CephProvisioner - syncRgwCoPeople found cou: " . json_encode($couData), 'debug');
          // if cou component doesn't exist this isn't valid account
          if (empty($couData)) {
              $this->log("Ceph RGW sync - COU Component $rgw_cou not found - deleting user: " . $user, 'info');
              $rgwa->deleteRgwUser($user);
              continue;
          }

          $co_id = $couData['Cou']['co_id'];
          $cou_id = $couData['Cou']['id'];
          $cou_name = $couData['Cou']['name'];
          // COU component of name is valid, now see if any co person has the identifier
          try {
              $coPersonId = $CoPersonObject->idForIdentifier($co_id,$rgw_uid,IdentifierEnum::UID);
              $this->log("CephProvisioner - syncRgwCoPeople found CO Person ID: " . json_encode($coPersonId), 'debug');
              // now verify that the co person is in the COU group
              $coPersonGroups = $CoGroupObject -> findForCoPerson($coPersonId, null, null, null, false);
              $this->log("CephProvisioner - syncRgwCoPeople CO Person member groups found: " . json_encode($coPersonGroups), 'debug');
          } catch (InvalidArgumentException $e) {
              if ($e->getMessage() == 'Unknown Identifier') {
                  $this->log("CephProvisioner - syncRgwCoPeople person identifier component $rgw_uid not found - deleting user: " . $user . " Exception was" . $e->getCode() . ':' . $e->getMessage(), 'info');
                  $rgwa->deleteRgwUser($user);
                  continue;
              } else {
                  throw $e;
              }
          }
          // user and cou exist, now check if user is in fact a member of this cou

          $activeMemberGroup = GroupEnum::ActiveMembers;
          $couGroupMatch = Hash::extract($coPersonGroups, "{n}.CoGroup[cou_id=$cou_id][group_type=$activeMemberGroup].name");
          if (empty($couGroupMatch)) {
              $this->log("CephProvisioner - syncRgwCoPeople $rgw_uid is not in active member COU group for $cou_name - deleting RGW user");
              $rgwa->deleteRgwUser($user);
          } else {
              $this->log("CephProvisioner - syncRgwCoPeople rgw suffix matches user cou group: " . json_encode($couGroupMatch), 'debug');
          }
      }
  }

  public function associatePoolsToApplications($coProvisioningTargetData,$coGroupData, $couDataPools) {
      $rgwa = $this->rgwAdminClientFactory($coProvisioningTargetData);
      $ceph = $this->cephClientFactory($coProvisioningTargetData);
      $cou = $this->CoCephProvisionerDataPool->getCouName($coGroupData);
      
      $poolType = CephDataPoolEnum::Rgw;
      $poolName = Hash::extract($couDataPools,  "{n}.CoCephProvisionerDataPool[cou_data_pool_type=$poolType].cou_data_pool");

      if (!$poolName) { throw RuntimeException(_text('er.cephprovisioner.rgw.extract')); }

      $rgwa->addPlacementTarget($cou, $cou, $poolName[0]);

      return true;

      // do some stuff here

  }

    /**
   * Determine Ceph access capabilities for CoPerson and create client key
   *
   * @since  COmanage Registry v3.1.0
   * @param  Array CO Provisioning Target data
   * @param  coPersonData
   * @return Boolean True on success
   * @throws RuntimeException
   * @throws CephClientException
   */

  public function updateCephClientKey($coProvisioningTargetData, $coPersonData) {

    $couObject = ClassRegistry::init('Cou');
    $ceph = $this->cephClientFactory($coProvisioningTargetData);
    $caps = array('mon' => 'allow r', 'mgr' => 'allow r');
    $prefix = $coProvisioningTargetData['CoCephProvisionerTarget']['ceph_user_prefix'];

    $userid = Hash::extract($coPersonData, "Identifier.{n}[type=uid].identifier");

    if (empty($userid)) { throw new RuntimeException(_txt('er.cephprovisioner.identifier')); }
    else { $userid = $userid[0]; }

    if ($coProvisioningTargetData['CoCephProvisionerTarget']['opt_posix_lookup_ldap']) {
      $useridNumber = $this->getLdapUidNumber($coProvisioningTargetData, $coPersonData);
      $gidList = $this->getLdapGidList($coProvisioningTargetData,$coPersonData);
    } else {
      $useridNumber = Hash::extract($coPersonData, "Identifier.{n}[type=uidNumber].identifier");
      $gidList = $this->getGrouperGidList($coProvisioningTargetData, $userid);
      // append the user personal group id which won't be in grouper
      $gidNumber = Hash::extract($coPersonData, "Identifier.{n}[type=gidNumber].identifier");
      if (empty($gidNumber)) { throw new RuntimeException(_txt('er.cephprovisioner.identifier')); }
      $gidList[] = $gidNumber[0];
    }

    if (empty($useridNumber)) { throw new RuntimeException(_txt('er.cephprovisioner.identifier')); }
    else { $useridNumber = $useridNumber[0]; }
  
    // some people might be in both cou admin and member groups, keep a log of cou id to avoid making cap strings for both
    // (at some point admin vs member may indicate different cap strings but currently they do not)
    $duplicateCheck = array();

    foreach ($coPersonData['CoGroupMember'] as $group) {
      if ($this->isCouAdminOrMembersGroup($group)) {

        if (in_array($group['CoGroup']['cou_id'], $duplicateCheck)) { continue; }
        $groupNames[] = $group['CoGroup']['name'];

        $args = array();
        $args['conditions']['Cou.id'] = $group['CoGroup']['cou_id'];
        $args['contain'] = false;
        $couData = $couObject->find('first', $args);

        $couNameLower = strtolower($couData['Cou']['name']);

        $duplicateCheck[] = $group['CoGroup']['cou_id'];
        $couDataPools = $this-> CoCephProvisionerDataPool -> getCouDataPools($coProvisioningTargetData, $group);

        if (empty($couDataPools)) {
          throw new RuntimeException(_txt('er.cocephprovisioner.nopool') . ' ');
        }

        $caps['mds'][] = "allow rw path=/$couNameLower uid=$useridNumber gids=" . implode(',',$gidList); 

        $poolCount = sizeof($couDataPools);
        for ($idx = 0; $idx < $poolCount; $idx++) {
          $poolName = $couDataPools[$idx]['CoCephProvisionerDataPool']['cou_data_pool'];    
          $caps['osd'][] = "allow rw pool=$poolName";
        }
      }
    }

    $this->log("CephProvisioner updateCephClientKey - generated caps array: " . json_encode($caps),'debug');
    
    $ceph->addOrUpdateEntity($prefix . '.' . $userid,$caps);

    return true;

  }

  public function getLdapTarget($coProvisioningTargetData) {
     // Pull the LDAP configuration

    $CoLdapProvisionerTarget = ClassRegistry::init('LdapProvisioner.CoLdapProvisionerTarget');

    $args = array();
    $args['conditions']['CoLdapProvisionerTarget.id'] = $coProvisioningTargetData['CoCephProvisionerTarget']['co_ldap_provisioner_target_id'];
    $args['contain'] = false;

    $ldapTarget = $CoLdapProvisionerTarget->find('first', $args);

    if(empty($ldapTarget)) {
      throw new RuntimeException(_txt('er.cephprovisioner.noldap'));
    }
    return $ldapTarget;
  }

  public function getLdapConnection($ldapTarget) {
   
    $cxn = ldap_connect($ldapTarget['CoLdapProvisionerTarget']['serverurl']);
    
    if(!$cxn) {
      throw new RuntimeException(_txt('er.ldapprovisioner.connect'), 0x5b /*LDAP_CONNECT_ERROR*/);
    }

    if(!@ldap_bind($cxn,
                   $ldapTarget['CoLdapProvisionerTarget']['binddn'],
                   $ldapTarget['CoLdapProvisionerTarget']['password'])) {
      throw new RuntimeException(ldap_error($cxn), ldap_errno($cxn));
    }

    return $cxn;

  }

  public function getLdapUidNumber($coProvisioningTargetData, $coPersonData) {
    
    // it might be more efficient to search for memberOf and then query only those groups for gid
    // although I'm not doing that 
    $ldapTarget = $this->getLdapTarget($coProvisioningTargetData);
    $cxn = $this->getLdapConnection($ldapTarget);
    $dnString = $this->getUserDn($coProvisioningTargetData, $coPersonData);
    
    // pick up uid
    $s = @ldap_search($cxn, $dnString, "(objectClass=posixAccount)", ['uidNumber']);

    if ($s) {
      $uidResults = ldap_get_entries($cxn, $s);
      $uidList = Hash::extract($uidResults, "{n}.uidnumber.0");
      $this->log('CephProvisioner getLdapUidNumber found uid list: ' . json_encode($uidList));
      return $uidList;
    }
    
  }

  public function getUserDn($coProvisioningTargetData, $coPersonData) {
    // Pull the master DN for this person 
    $CoLdapProvisionerDn = ClassRegistry::init('LdapProvisioner.CoLdapProvisionerDn');
    
    $args = array();
    $args['conditions']['CoLdapProvisionerDn.co_ldap_provisioner_target_id'] = $coProvisioningTargetData['CoCephProvisionerTarget']['co_ldap_provisioner_target_id'];
    $args['conditions']['CoLdapProvisionerDn.co_person_id'] = $coPersonData['CoPerson']['id'];
    $args['fields'] = array('id', 'dn');
    $args['contain'] = false;
    $dn = $CoLdapProvisionerDn->find('first', $args);

    $this->log("CephProvisioner getUserDn - looked up master DN: " . json_encode($dn));
    
    if(empty($dn)) {
      throw new RuntimeException(_txt('er.cephprovisioner.nodn'));
    }

    return $dn['CoLdapProvisionerDn']['dn'];

  }

  public function getLdapGidList($coProvisioningTargetData, $coPersonData) {
    $dnString = $this->getUserDn($coProvisioningTargetData, $coPersonData);
    $ldapTarget = $this->getLdapTarget($coProvisioningTargetData);
    $cxn = $this->getLdapConnection($ldapTarget);

    $s = @ldap_search($cxn, $ldapTarget['CoLdapProvisionerTarget']['group_basedn'], "(&(objectClass=posixGroup)(uniqueMember=$dnString))", ['gidNumber']);
    
    if ($s) {
      $gidResults = ldap_get_entries($cxn, $s);
      $this->log('CephProvisioner getLdapGidList ldap query: ' . json_encode($gidResults));
      $gidList = Hash::extract($gidResults, "{n}.gidnumber.0");
      $this->log('CephProvisioner getLdapGidList found gid list: ' . json_encode($gidList));
    }
     
   return $gidList;

  }

  public function getGrouperTarget($coProvisioningTargetData) {
     // Pull the Grouper configuration and find target

    $CoGrouperProvisionerTarget = ClassRegistry::init('GrouperProvisioner.CoGrouperProvisionerTarget');

    $args = array();
    $args['conditions']['CoGrouperProvisionerTarget.id'] = $coProvisioningTargetData['CoCephProvisionerTarget']['co_grouper_provisioner_target_id'];
    $args['contain'] = false;

    $grouperTarget = $CoGrouperProvisionerTarget->find('first', $args);

    if(empty($grouperTarget)) {
      throw new RuntimeException(_txt('er.cephprovisioner.nogrouper'));
    }
    return $grouperTarget;
  }

  public function getGrouperGidList($coProvisioningTargetData, $subject) {
    $grouperTarget = $this->getGrouperTarget($coProvisioningTargetData);

    $grouperObject = ClassRegistry::init('GrouperProvisioner.CoGrouperProvisionerTarget');
    $grouperRestClient = $grouperObject->grouperRestClientFactory($grouperTarget);
    $stem = $grouperTarget['CoGrouperProvisionerTarget']['stem'];

    $body = array(
      'WsRestGetGroupsRequest' => array(
        'actAsSubjectLookup' => array(
          'subjectId' => 'GrouperSystem'
          ),
        'wsStemLookup'  => array('stemName' => $stem),
        'stemScope' => 'ALL_IN_SUBTREE',
        'subjectLookups' => array(
          array('subjectId' => $subject)
          )
        )
      );

    $body = json_encode($body);

    $request = array(
      'uri' => array(
        'path' => 'subjects'
        ),
      'body' => $body
      );

    $result = $grouperRestClient->grouperRequest($request, 201);
    $success = $result->WsGetGroupsResults->resultMetadata->success;
    if ($success != 'T') {
      $msg = 'Result from get groups was not success';
        $this->log($msg, 'error');
        $this->log('Grouper WS request was ' . print_r($request, true),'error');
        $this->log('Grouper WS result was ' . print_r($result, true), 'error');
        throw new GrouperRestClientException($msg);
    }

    $this->log('Grouper WS result was ' . print_r($result, true), 'error');
    $results = $result->WsGetGroupsResults->results;
    $gidList = array();

    foreach ($results as $r) {
      if (array_key_exists('wsGroups', $r)) {
        foreach ($r->wsGroups as $g) {
          $gidList[] = $g->idIndex;
        }
      }
    }
    return $gidList;
  }

  public function cephRgwClientFactory($coProvisioningTargetData) {
    throw CephClientException("RGW Client is not implemented");
  }

  public function rgwAdminClientFactory($coProvisioningTargetData) {
    $client_id = $coProvisioningTargetData['CoCephProvisionerTarget']['ceph_client_name'];
    $cluster = $coProvisioningTargetData['CoCephProvisionerTarget']['ceph_cluster'];

    if (empty($cluster)) { $cluster = 'ceph'; }

    if ($coProvisioningTargetData['CoCephProvisionerTarget']['opt_rgw_admin_api']) {
      throw CephClientException("RGW admin api client is not implemented");
    } else {
      try {
        $ceph = new CephRgwAdminCliClient($client_id,$cluster);
    } catch (CephCliClientException $e) {
      $this->log("CephProvisioner unable to create new CephRgwAdminCliClient: " . $e->getMessage());
      return null;
    }
    return $ceph;
    }
  }

  public function cephClientFactory($coProvisioningTargetData) {
    if ($coProvisioningTargetData['CoCephProvisionerTarget']['opt_ceph_admin_api']) {
       throw CephClientException("Admin api client is not implemented");
    } else {
      return $this->cephCliClientFactory($coProvisioningTargetData);
    }
  }

  public function cephCliClientFactory($coProvisioningTargetData) {
    $client_id = $coProvisioningTargetData['CoCephProvisionerTarget']['ceph_client_name'];
    $cluster = $coProvisioningTargetData['CoCephProvisionerTarget']['ceph_cluster'];

    if (empty($cluster)) { $cluster = 'ceph'; }
    
    try {
      $ceph = new CephCliClient($client_id,$cluster);
    } catch (CephCliClientException $e) {
      $this->log("CephProvisioner unable to create new CephCliClient: " . $e->getMessage());
      return null;
    }
    return $ceph;
  }

  public function isCouAdminOrMembersGroup($coGroup) {
    return (($coGroup['CoGroup']['group_type'] == GroupEnum::ActiveMembers
             || $coGroup['CoGroup']['group_type'] == GroupEnum::AllMembers 
             || $coGroup['CoGroup']['group_type'] == GroupEnum::Admins )
            && !empty($coGroup['CoGroup']['cou_id']));
  }


}

