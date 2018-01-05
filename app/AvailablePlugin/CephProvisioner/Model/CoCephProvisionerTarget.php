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
      'co_ldap_provisioner_target_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'message' => 'A CO LDAP Provisioning Target ID must be provided'
    ),
  );
 
  private $cou_name;
  
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
      switch($op) {
        case ProvisioningActionEnum::CoPersonAdded:
        case ProvisioningActionEnum::CoPersonPetitionProvisioned:
        case ProvisioningActionEnum::CoPersonPipelineProvisioned:
        case ProvisioningActionEnum::CoPersonReprovisionRequested:
        case ProvisioningActionEnum::CoPersonUnexpired:
        case ProvisioningActionEnum::CoPersonUpdated:
          $this->provisionCoPersonAction($coProvisioningTargetData,$provisioningData);
          $this->updateCephClientKey($coProvisioningTargetData, $provisioningData);
          $this->syncRgwCoPeople($coProvisioningTargetData);
          break;
        case ProvisioningActionEnum::CoPersonExpired:
        case ProvisioningActionEnum::CoPersonEnteredGracePeriod:
        case ProvisioningActionEnum::CoPersonDeleted:
          $this->syncRgwCoPeople($coProvisioningTargetData);
          // remove user from acls on cou bucket
          
          break;
        case ProvisioningActionEnum::CoGroupAdded:
        case ProvisioningActionEnum::CoGroupUpdated:
        case ProvisioningActionEnum::CoGroupReprovisionRequested:
          $this->provisionCoGroupAction($coProvisioningTargetData,$provisioningData);
          $this->syncRgwCoPeople($coProvisioningTargetData);
          // create data pool for cou
          // add data pool to rgw pools with placement tags matching admin group
          // make sure cou bucket exists named like tolower(cou-name)
          // make sure members of cou have read acl to bucket
          // make sure admins of cou have write acl to bucket
          break;
        case ProvisioningActionEnum::CoGroupDeleted:
          $this->deleteCoGroupAction($coProvisioningTargetData,$provisioningData);
          $this->syncRgwCoPeople($coProvisioningTargetData);
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

  public function provisionCoPersonAction($coProvisioningTargetData,$coPersonData, $delete=false) {
    
    if(!isset($coPersonData['CoPerson'])) {
      $this->log("CephProvisioner provisionCoPersonAction not passed CoPerson object");
      return false;
    }

    $this->log("Ceph provisioner provisionCoPersonAction - coPerson data: " . json_encode($coPersonData), 'debug');

    $rgwa = $this -> rgwAdminClientFactory($coProvisioningTargetData);

    $uid_identifier = IdentifierEnum::UID;

    $userid = Hash::extract($coPersonData, "Identifier.{n}[type=uid].identifier");

    if (empty($userid)) {
      throw RuntimeException(_txt('er.cephprovisioner.identifier'));
    }

    $this->log("userid: " . json_encode($userid));

    $active = GroupEnum::ActiveMembers;
    $admin = GroupEnum::Admins;

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
        $constructedUser = $userid[0] . '_' . strtolower($cou_name);
        $this->log("Ceph provisioner provisonCoPersonAction - constructed user: " . json_encode($constructedUser), 'debug');
        $rgwa->addUserPlacementTag($constructedUser, $cou_name);
      }
       $this->log("Ceph provisioner provisonCoPersonAction - ActiveCou: " . json_encode($active_cou), 'debug');
    }
  }

  // calls syncRgwUsers which will delete identifiers that don't exist in comanage anymore
  public function syncRgwCoPeople($coProvisioningTargetData) {
    $rgwa = $this->rgwAdminClientFactory($coProvisioningTargetData);
    $rgwa -> syncRgwUsers();
  }


  public function deleteCoGroupAction($coProvisioningTargetData, $coGroupData) {
     //$ceph = $this -> cephClientFactory($coProvisioningTargetData);

    // nothing to provision
    if (!$this -> isCouAdminOrMembersGroup($coGroupData)) {
      return true;
    }

    // this does not delete pools in ceph, but removes them from comanage db and removes cou user associations in Ceph.  It also removes S3 placement targets.
    if (!$this -> CoCephProvisionerDataPool -> deleteCouDataPoolAssociations($coProvisioningTargetData, $coGroupData)) {
        throw new RuntimeException(_txt('er.cephprovisioner.datapool.delete'));
    }
    return true;

  }

  public function provisionCoGroupAction($coProvisioningTargetData,$coGroupData) {
    
    //$ceph = $this -> cephClientFactory($coProvisioningTargetData);

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

    //if (!$this->updateCephClientKey($coProvisioningTargetData,$coGroupData)) {
    //  throw new RuntimeException(_txt('er.cephprovisioner.datapool.associate'));
    //}

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

    $uid_identifier = IdentifierEnum::UID;
    $userid = Hash::extract($coPersonData, "Identifier.{n}[type=uid].identifier");

    if (empty($userid)) { throw new RuntimeException(_txt('er.cephprovisioner.identifier')); }
  
    // pull ldap config (we'll need to query for memberOf gids)
    // Pull the LDAP configuration

    $CoLdapProvisionerTarget = ClassRegistry::init('LdapProvisioner.CoLdapProvisionerTarget');

    $args = array();
    $args['conditions']['CoLdapProvisionerTarget.id'] = $coProvisioningTargetData['CoCephProvisionerTarget']['co_ldap_provisioner_target_id'];
    $args['contain'] = false;

    $ldapTarget = $CoLdapProvisionerTarget->find('first', $args);

    if(empty($ldapTarget)) {
      throw new RuntimeException(_txt('er.noldap'));
    }

    $cxn = ldap_connect($ldapTarget['CoLdapProvisionerTarget']['serverurl']);
    
    if(!$cxn) {
      throw new RuntimeException(_txt('er.ldapprovisioner.connect'), 0x5b /*LDAP_CONNECT_ERROR*/);
    }

    if(!@ldap_bind($cxn,
                   $ldapTarget['CoLdapProvisionerTarget']['binddn'],
                   $ldapTarget['CoLdapProvisionerTarget']['password'])) {
      throw new RuntimeException(ldap_error($cxn), ldap_errno($cxn));
    }

    // it might be more efficient to search for memberOf and then query only those groups for gid
    // although I'm not doing that 

    // Pull the master DN for this person to use in lookup
    
    $CoLdapProvisionerDn = ClassRegistry::init('LdapProvisioner.CoLdapProvisionerDn');
    
    $args = array();
    $args['conditions']['CoLdapProvisionerDn.co_ldap_provisioner_target_id'] = $coProvisioningTargetData['CoCephProvisionerTarget']['co_ldap_provisioner_target_id'];
    $args['conditions']['CoLdapProvisionerDn.co_person_id'] = $coPersonData['CoPerson']['id'];
    $args['fields'] = array('id', 'dn');
    $args['contain'] = false;
    $dn = $CoLdapProvisionerDn->find('first', $args);

    $this->log("CephProvisioner updateCephClientKey - looked up master DN: " . json_encode($dn));
    
    if(empty($dn)) {
      throw new RuntimeException(_txt('er.nodn'));
    }

    $dnString = $dn['CoLdapProvisionerDn']['dn'];

    // pick up uid
    $s = @ldap_search($cxn, $dnString, "(objectClass=posixAccount)", ['uidNumber']);

    if ($s) {
      $uidResults = ldap_get_entries($cxn, $s);
      //$uid = Hash::extract($provisioningData['Identifier'], "{n}[type=$idType].identifier");
      $uidList = Hash::extract($uidResults, "{n}.uidnumber.0");
      $this->log('CephProvisioner updateCephClientKey found uid list: ' . json_encode($uidList));
      $uid = $uidList[0];
    }

    // find all gid for member groups
    $s = @ldap_search($cxn, $ldapTarget['CoLdapProvisionerTarget']['group_basedn'], "(&(objectClass=posixGroup)(uniqueMember=$dnString))", ['gidNumber']);
    
    if ($s) {
      $gidResults = ldap_get_entries($cxn, $s);
      //$uid = Hash::extract($provisioningData['Identifier'], "{n}[type=$idType].identifier");
      $gidList = Hash::extract($gidResults, "{n}.gidnumber.0");
      $this->log('CephProvisioner updateCephClientKey found gid list: ' . json_encode($gidList));
    }

   /* 2017-12-19 20:51:51 Error: CoServiceToken getOrCreateCephKey found gid list: {"count":10,"0":{"gidnumber":{"count":1,"0":"1000072"},"0":"gidnumber","count":1,"dn":"cn=OSiRIS:OsirisAdmin:CO_COU_OsirisAdmin_members_all,ou=Groups,dc=osris,dc=org"},"1":{"gidnumber":{"count":1,"0":"1000071"},"0":"gidnumber","count":1,"dn":"cn=OSiRIS:OsirisAdmin:CO_COU_OsirisAdmin_members_active,ou=Groups,dc=osris,dc=org"},"2":{"gidnumber":{"count":1,"0":"1000070"},"0":"gidnumber","count":1,"dn":"cn=OSiRIS:OsirisAdmin:CO_COU_OsirisAdmin_admins,ou=Groups,dc=osris,dc=org"},"3":{"gidnumber":{"count":1,"0":"1000083"},"0":"gidnumber","count":1,"dn":"cn=OSiRIS:OsirisAdmin:test,ou=Groups,dc=osris,dc=org"},"4":{"gidnumber":{"count":1,"0":"1000089"},"0":"gidnumber","count":1,"dn":"cn=OSiRIS:OsirisAdmin:blah,ou=Groups,dc=osris,dc=org"},"5":{"gidnumber":{"count":1,"0":"1000090"},"0":"gidnumber","count":1,"dn":"cn=OSiRIS:OsirisAdmin:admin,ou=Groups,dc=osris,dc=org"},"6":{"gidnumber":{"count":1,"0":"1000191"},"0":"gidnumber","count":1,"dn":"cn=OSiRIS:ATLAS:CO_COU_ATLAS_members_all,ou=Groups,dc=osris,dc=org"},"7":{"gidnumber":{"count":1,"0":"1000192"},"0":"gidnumber","count":1,"dn":"cn=OSiRIS:ATLAS:CO_COU_ATLAS_admins,ou=Groups,dc=osris,dc=org"},"8":{"gidnumber":{"count":1,"0":"1000190"},"0":"gidnumber","count":1,"dn":"cn=OSiRIS:ATLAS:CO_COU_ATLAS_members_active,ou=Groups,dc=osris,dc=org"},"9":{"gidnumber":{"count":1,"0":"100010"},"0":"gidnumber","count":1,"dn":"cn=bmeekhof,ou=Groups,dc=osris,dc=org"}} */
    
    // some people might be in both cou admin and member groups, keep a log of cou id to avoid making cap strings for both
    // (at some point admin vs member may indicate different cap strings but currently they do not)
    $duplicateCheck = array();

    foreach ($coPersonData['CoGroupMember'] as $group) {
      if ($this->isCouAdminOrMembersGroup($group)) {
      
        if (in_array($group['CoGroup']['cou_id'], $duplicateCheck)) { continue; }

        $args = array();
        $args['conditions']['Cou.id'] = $group['CoGroup']['cou_id'];
        $args['contain'] = false;
        $couData = $couObject->find('first', $args);

        $couNameLower = strtolower($couData['Cou']['name']);

        $duplicateCheck[] = $group['CoGroup']['cou_id'];
        $couDataPools = $this-> CoCephProvisionerDataPool -> getCouDataPools($coProvisioningTargetData, $group);
        $this->log("CoServiceToken getOrCreateCephKey couDataPools: " . json_encode($couDataPools), 'error');

        if (empty($couDataPools)) {
          throw new RuntimeException(_txt('er.coservicetoken.nopool') . ' ');
        }

        $caps['mds'][] = "allow rw path=/$couNameLower uid=$uid gids=" . implode(',', $gidList);

        $poolCount = sizeof($couDataPools);
        for ($idx = 0; $idx < $poolCount; $idx++) {
          $poolName = $couDataPools[$idx]['CoCephProvisionerDataPool']['cou_data_pool'];    
          $caps['osd'][] = "allow rw pool=$poolName";
        }
      }
    }
  
    $this->log("CephProvisioner updateCephClientKey - generated caps array: " . json_encode($caps),'error');
    
    $ceph->addOrUpdateEntity($userid[0],$caps);

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

