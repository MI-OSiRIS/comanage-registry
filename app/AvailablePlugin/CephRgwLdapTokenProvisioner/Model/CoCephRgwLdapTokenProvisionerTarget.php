<?php
/**
 * COmanage Registry LDAP Service Token Provisioner Target Model
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

class CoCephRgwLdapTokenProvisionerTarget extends CoProvisionerPluginTarget {
  // Define class name for cake
  public $name = "CoCephRgwLdapTokenProvisionerTarget";
  
  // Add behaviors
  public $actsAs = array('Containable');
  
  // Association rules from this model to other models
  public $belongsTo = array("CoProvisioningTarget", "CoService");
  
  // Default display field for cake generated views
  public $displayField = "co_service_id";
  
  // Validation rules for table elements
  public $validate = array(
    'co_provisioning_target_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'message' => 'A CO Provisioning Target ID must be provided'
    )
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
    $modify = false;
    
    // This may not be exactly the right handling, but this is a temporary plugin
    // that will be replaced when merged into core code
    
    switch($op) {
      case ProvisioningActionEnum::CoPersonAdded:
      case ProvisioningActionEnum::CoPersonPetitionProvisioned:
      case ProvisioningActionEnum::CoPersonPipelineProvisioned:
      case ProvisioningActionEnum::CoPersonReprovisionRequested:
      case ProvisioningActionEnum::CoPersonUnexpired:
        $modify = true;
        break;
      case ProvisioningActionEnum::CoPersonExpired:
      case ProvisioningActionEnum::CoPersonEnteredGracePeriod:
      case ProvisioningActionEnum::CoPersonUnexpired:
      case ProvisioningActionEnum::CoPersonUpdated:
        if(in_array($provisioningData['CoPerson']['status'],
                    array(StatusEnum::Active,
                          StatusEnum::Expired,
                          StatusEnum::GracePeriod,
                          StatusEnum::Suspended))) {
          $modify = true;
        }
        break;
      case ProvisioningActionEnum::CoPersonDeleted:
      case ProvisioningActionEnum::CoGroupAdded:
      case ProvisioningActionEnum::CoGroupDeleted:
      case ProvisioningActionEnum::CoGroupUpdated:
      case ProvisioningActionEnum::CoGroupReprovisionRequested:
        break;
      default:
        throw new RuntimeException("Not Implemented");
        break;
    }
    
    if(!$modify) {
      return true;
    }

    // Pull the master DN for this person to verify they exist 
    
    $CoLdapProvisionerDn = ClassRegistry::init('LdapProvisioner.CoLdapProvisionerDn');
    
    $args = array();
    $args['conditions']['CoLdapProvisionerDn.co_ldap_provisioner_target_id'] = $coProvisioningTargetData['CoCephRgwLdapTokenProvisionerTarget']['co_ldap_provisioner_target_id'];
    $args['conditions']['CoLdapProvisionerDn.co_person_id'] = $provisioningData['CoPerson']['id'];
    $args['fields'] = array('id', 'dn');
    $args['contain'] = false;
    
    $dn = $CoLdapProvisionerDn->find('first', $args);

    $this->log("DN: " . json_encode($dn));
    
    if(empty($dn)) {
      throw new RuntimeException(_txt('er.nodn'));
    }

    // construct the COU-suffixed usernames that RGW will use

    $CoGroupObject = ClassRegistry::init('CoGroup');
    $CouObject = ClassRegistry::init('Cou');
    $CoPersonObject = ClassRegistry::init('CoPerson');

    $CoGroups = $CoGroupObject->findForCoPerson($provisioningData['CoPerson']['id']);
    $baseDN = $coProvisioningTargetData['CoCephRgwLdapTokenProvisionerTarget']['user_basedn'];

    $rgwDnList = array();

    foreach ($CoGroups as $group) {
      if ($CoGroupObject->isCouMembersGroup($group)) {
        if ($group['CoGroup']['group_type'] == GroupEnum::ActiveMembers) { 
          $args = array();
          $args['conditions']['Cou.id'] = $group['CoGroup']['cou_id'];
          $args['contain'] = false;
          $couData = $CouObject->find('first', $args);
          $couName = strtolower($couData['Cou']['name']);
          $idType = IdentifierEnum::UID;
          $uid = Hash::extract($provisioningData['Identifier'], "{n}[type=$idType].identifier");
          if (empty($uid)) { 
            throw new RuntimeException('Person ID ' . $provisioningData['CoPerson']['id'] . ': ' . _txt('er.identifier')); 
          }

          $rgwDnList[] = 'uid=' . $uid[0] . '_' . $couName . ',' . $baseDN;
          
          $this->log("CephRgwLdapToken - provision identifier data: " . json_encode($provisioningData['Identifier']), 'debug');
          $this->log("CephRgwLdapToken - provision UID extracted: " . json_encode($uid), 'debug');
          $this->log("CephRgwLdapToken - provision RGW DN list: " . json_encode($rgwDnList), 'debug');
        }
      }
    }
    
    // Pull the LDAP configuration
    
    $CoLdapProvisionerTarget = ClassRegistry::init('LdapProvisioner.CoLdapProvisionerTarget');
    
    $args = array();
    $args['conditions']['CoLdapProvisionerTarget.id'] = $coProvisioningTargetData['CoCephRgwLdapTokenProvisionerTarget']['co_ldap_provisioner_target_id'];
    $args['contain'] = false;
    
    $ldapTarget = $CoLdapProvisionerTarget->find('first', $args);
    
    if(empty($ldapTarget)) {
     throw new RuntimeException(_txt('er.noldap'));
    }
    
    // Pull the desired token
    // For the time being the token is the same for all COU
    
    $CoServiceToken = ClassRegistry::init('CoServiceToken.CoServiceToken');
    
    $args = array();
    $args['conditions']['CoServiceToken.co_service_id'] = $coProvisioningTargetData['CoCephRgwLdapTokenProvisionerTarget']['co_service_id'];
    $args['conditions']['CoServiceToken.co_person_id'] = $provisioningData['CoPerson']['id'];
    $args['contain'] = false;
    
    $token = $CoServiceToken->find('first', $args);
    
    $attributes = array();
    
    if($modify && !empty($token['CoServiceToken']['token'])) {
      $attributes['userPassword'] = $token['CoServiceToken']['token'];
    } else {
      // nothing to provision
      return true;
      // $attributes['userPassword'] = '';
    }
    
    // Bind to the server
    
    $cxn = ldap_connect($ldapTarget['CoLdapProvisionerTarget']['serverurl']);
    
    if(!$cxn) {
      throw new RuntimeException(_txt('er.ldapprovisioner.connect'), 0x5b /*LDAP_CONNECT_ERROR*/);
    }
    
    // Modify the LDAP entry

     // Use LDAP v3 (this could perhaps become an option at some point), although note
    // that ldap_rename (used below) *requires* LDAP v3.
    ldap_set_option($cxn, LDAP_OPT_PROTOCOL_VERSION, 3);
    
    if(!@ldap_bind($cxn,
                   $ldapTarget['CoLdapProvisionerTarget']['binddn'],
                   $ldapTarget['CoLdapProvisionerTarget']['password'])) {
      throw new RuntimeException(ldap_error($cxn), ldap_errno($cxn));
    }
    
    foreach ($rgwDnList as $rgwDN) {
      // see if user exists, add or modify as appropriate
      $s = @ldap_search($cxn, $rgwDN, "(objectClass=account)", ['dn']);
      if ($s) {
        $ret = ldap_get_entries($cxn, $s);
        $this->log("CephRgwLdapToken - provision LDAP search for existing: " . json_encode($ret), 'debug');
      }

      if ($ret['count'] == 0 || $s == false) {
        $attributes['objectClass'] = ['account', 'simpleSecurityObject'];
        if(!@ldap_add($cxn, $rgwDN, $attributes)) {
          throw new RuntimeException(ldap_error($cxn), ldap_errno($cxn));
        }
      } else {
        if(!@ldap_mod_replace($cxn, $rgwDN, $attributes)) {
          throw new RuntimeException(ldap_error($cxn), ldap_errno($cxn));
        }
      }
    }

    // query all the ldap entries of type 'account' created by this plugin and verify each one is valid

    $s = @ldap_search($cxn, $baseDN, "(objectClass=account)", ['dn']);
    $ret = ldap_get_entries($cxn, $s);

    if ($ret['count'] > 0) {
      $this->log("CephRgwLdapToken - provision verify users LDAP all search: " . json_encode($ret), 'debug');
      array_shift($ret); // drop the first value which is the count of results
      foreach ($ret as $index => $value) {
        $dn = $value['dn'];
        $dn_uid_raw = explode(',',$dn)[0];
        $dn_uid = explode('=',$dn_uid_raw)[1];
        $this->log("dn_uid: " . json_encode($dn_uid));
        $uid_comp = explode('_', $dn_uid);
        $rgw_uid = $uid_comp[0];
        $rgw_cou = $uid_comp[1];
        $args = array();
        $args['conditions']['Cou.name'] = $rgw_cou;
        $args['contain'] = false;
        $couData = $CouObject->find('first', $args);
        // if cou component doesn't exist this isn't valid account
        if (empty($couData)) {
          $this->log("CephRgwLdapToken - provision verify user COU Component not found: " . $rgw_cou, 'debug');
          @ldap_delete($cxn, $dn);
          continue;

        }
        $this->log("CephRgwLdapToken - provision verify search found COU: " . json_encode($couData), 'debug');
        $co_id = $couData['Cou']['co_id'];
        $cou_id = $couData['Cou']['id'];
        $cou_name = $couData['Cou']['name'];

        // COU component of name is valid, now see if any co person has the identifier
        try {
          $coPersonId = $CoPersonObject->idForIdentifier($co_id,$rgw_uid,IdentifierEnum::UID);
        } catch (InvalidArgumentException $e) {
          if ($e->getMessage() == 'Unknown Identifier') {
            $this->log("Person identifier not found: " . $rgw_uid . " Exception was" . $e->getCode() . ':' . $e->getMessage());
            @ldap_delete($cxn, $dn);
          } else {
            throw $e;
          }
        }
        $this->log("CephRgwLdapToken - provision verify CO Person Id search found: " . json_encode($coPersonId), 'debug');

        // user and cou exist, now check if user is in fact a member of this cou
        $coPersonGroups = $CoGroupObject -> findForCoPerson($coPersonId, null, null, null, false);
        $activeMemberGroup = GroupEnum::ActiveMembers;
        $couGroupMatch = Hash::extract($coPersonGroups, "{n}.CoGroup[cou_id=$cou_id][group_type=$activeMemberGroup].name");
        if (empty($couGroupMatch)) {
          $this->log("CephRgwLdapToken - $rgw_uid is not in active member COU group for $cou_name - deleting ldap record", 'info');
          @ldap_delete($cxn, $dn);
        } else {
          $this->log("CephRgwLdapToken - rgw suffix matches user $rgw_uid cou group: " . json_encode($couGroupMatch),'debug');
        }
      }
    }

    // Drop the connection
    ldap_unbind($cxn);

    return true;
  }
}
