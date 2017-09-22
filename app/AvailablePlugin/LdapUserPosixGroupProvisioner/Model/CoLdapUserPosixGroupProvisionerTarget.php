
<?php
/**
 * COmanage Registry LDAP User posixGroup target model
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

class CoLdapUserPosixGroupProvisionerTarget extends CoProvisionerPluginTarget {
  // Define class name for cake
  public $name = "CoLdapUserPosixGroupProvisionerTarget";

  // Add behaviors
  public $actsAs = array('Containable');

  // Association rules from this model to other models
  public $belongsTo = array("CoProvisioningTarget");

  // Default display field for cake generated views
  public $displayField = "co_provisioning_target_id";

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
    // not relevant for groups
    if (isset($provisioningData['CoGroup']['id'])) {
      return true;
    }

    $add = false;
    $delete = false;
    $modify = false;

    switch($op) {
      case ProvisioningActionEnum::CoPersonAdded:
      case ProvisioningActionEnum::CoPersonUnexpired:
      case ProvisioningActionEnum::CoPersonPetitionProvisioned:
      case ProvisioningActionEnum::CoPersonPipelineProvisioned:
      case ProvisioningActionEnum::CoPersonReprovisionRequested:
      case ProvisioningActionEnum::CoPersonUpdated:
        // just reprovision the group if update, but only if the person is still active or in grace
        if(in_array($provisioningData['CoPerson']['status'],
                    array(StatusEnum::Active,
                          StatusEnum::GracePeriod))) {
          $add = true;
        }

        $delete = true;  // for housekeeping, it may fail
        break;

      case ProvisioningActionEnum::CoPersonExpired:
      case ProvisioningActionEnum::CoPersonDeleted:
        $delete = true;
        break;
      case ProvisioningActionEnum::CoPersonEnteredGracePeriod:
        // no change to user group at this point
        break;
      default:
        throw new RuntimeException("Provisioning action not handled");
        break;
    }

    $CoLdapProvisionerTarget = ClassRegistry::init('LdapProvisioner.CoLdapProvisionerTarget');

    $args = array();
    $args['conditions']['CoLdapProvisionerTarget.id'] = $coProvisioningTargetData['CoLdapUserPosixGroupProvisionerTarget']['co_ldap_provisioner_target_id'];
    $args['contain'] = false;

    $ldapTarget = $CoLdapProvisionerTarget->find('first', $args);

    if(empty($ldapTarget)) {
      throw new RuntimeException("No valid ldap provisioner specified");
    }

    //Debugger::log($provisioningData);
    // assemble group attributes from identifiers
    foreach($provisioningData['Identifier'] as $identifier) {
      if(!empty($identifier['type'])
         && !empty($identifier['identifier'])
         && $identifier['status'] == StatusEnum::Active) {

            if ($identifier['type'] == $ldapTarget['CoLdapProvisionerTarget']['dn_identifier_type']) {
                $dn = "cn=" . $identifier['identifier']
                    . "," . $ldapTarget['CoLdapProvisionerTarget']['group_basedn'];

                $member = $ldapTarget['CoLdapProvisionerTarget']['dn_identifier_type']
                        . "=" . $identifier['identifier']
                        . "," . $ldapTarget['CoLdapProvisionerTarget']['basedn'];

                $cn = $identifier['identifier'];
            }

            if ($identifier['type'] == 'gidNumber') {
                 $gidNumber = $identifier['identifier'];
            }
      }
    }

    if(empty($gidNumber)) {
        throw new UnderflowException('gidNumber not set');
    }

    if (empty($dn)) {
        throw new UnderflowException('dn not set');
    }

    // Modify the LDAP entry

    $attributes = array();

    $attributes['uniqueMember'] = $member;
    $attributes['cn'] = $cn;
    $attributes['gidNumber'] = $gidNumber;
    $attributes['objectClass'] = [ 'top','posixGroup','groupOfUniqueNames' ];

    // Bind to the server

    $cxn = ldap_connect($ldapTarget['CoLdapProvisionerTarget']['serverurl']);

    if(!$cxn) {
      throw new RuntimeException(_txt('er.ldapprovisioner.connect'), 0x5b /*LDAP_CONNECT_ERROR*/);
    }

    // Use LDAP v3 (this could perhaps become an option at some point), although note
    // that ldap_rename (used below) *requires* LDAP v3.
    ldap_set_option($cxn, LDAP_OPT_PROTOCOL_VERSION, 3);

    if(!@ldap_bind($cxn,
                   $ldapTarget['CoLdapProvisionerTarget']['binddn'],
                   $ldapTarget['CoLdapProvisionerTarget']['password'])) {
      throw new RuntimeException(ldap_error($cxn), ldap_errno($cxn));
    }

    if ($delete) {
      // this should catch when the dn is changed, such as uid modification
      $sdn = @ldap_search($cxn,
                          $ldapTarget['CoLdapProvisionerTarget']['group_basedn'],
                          "(&(gidNumber=$gidNumber)(objectClass=posixGroup))",
                          array('dn'));
      if ($sdn) {
        $entry = @ldap_first_entry($cxn,$sdn);
        do {
          $rmdn = @ldap_get_dn($cxn,$entry);
          // Debugger::log($rmdn);
          @ldap_delete($cxn, $rmdn);
        } while ($entry = @ldap_next_entry($cxn,$sdn));
      }
      @ldap_delete($cxn, $dn);

    }

    if ($add) {
      if(!@ldap_add($cxn, $dn, $attributes)) {
        throw new RuntimeException(ldap_error($cxn), ldap_errno($cxn));
      }
    }

    if ($modify) {
      if(!@ldap_mod_replace($cxn, $dn, $attributes)) {
        throw new RuntimeException(ldap_error($cxn), ldap_errno($cxn));
      }
    }

    // Drop the connection
    ldap_unbind($cxn);
    return true;
  }

}

