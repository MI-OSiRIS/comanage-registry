
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

class CoLdapVopersonProvisionerTarget extends CoProvisionerPluginTarget {
  // Define class name for cake
  public $name = "CoLdapVopersonProvisionerTarget";

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
    ),
    'co_ceph_provisioner_target_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false,
      'message'  => 'A Ceph provisioner target ID must be provided'
    ),
    'co_ldap_provisioner_target_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false,
      'message'  => 'An LDAP provisioner target ID must be provided'
    ),
  );

  /**
   * Provision for the specified CO Person.
   *
   * @since  COmanage Registry v3.1.0
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
        //
        if(in_array($provisioningData['CoPerson']['status'],
                    array(StatusEnum::Active,
                          StatusEnum::GracePeriod))) {
          $add = true;
        }

        $delete = true;  // for housekeeping, will remove all attributes with value matching our prefix before adding current correct ones
        break;

      case ProvisioningActionEnum::CoPersonExpired:
      case ProvisioningActionEnum::CoPersonDeleted:
        $delete = true;
        break;
      case ProvisioningActionEnum::CoPersonEnteredGracePeriod:
        // no change to user at this point
        break;
      default:
        throw new RuntimeException(_txt('er.ldapvoperson.nohandler'));
        break;
    }

    $CoLdapProvisionerTarget = ClassRegistry::init('LdapProvisioner.CoLdapProvisionerTarget');
    $CoCephProvisionerTarget = ClassRegistry::init('CephProvisioner.CoCephProvisionerTarget');

    $args = array();
    $args['conditions']['CoLdapProvisionerTarget.id'] = $coProvisioningTargetData['CoLdapVopersonProvisionerTarget']['co_ldap_provisioner_target_id'];
    $args['contain'] = false;

    $ldapTarget = $CoLdapProvisionerTarget->find('first', $args);

    $args = array();
    $args['conditions']['CoCephProvisionerTarget.id'] = $coProvisioningTargetData['CoLdapVopersonProvisionerTarget']['co_ceph_provisioner_target_id'];
    $args['contain'] = false;

    $cephTarget = $CoCephProvisionerTarget->find('first', $args);

    if(empty($ldapTarget)) {
      throw new RuntimeException(_txt('er.ldapvoperson.ldaptarget'));
    }

    if(empty($cephTarget)) {
      throw new RuntimeException(_txt('er.ldapvoperson.cephtarget'));
    }

    $cephEntityPrefix = $cephTarget['CoCephProvisionerTarget']['ceph_user_prefix'];
    $ldapIdentifierAttr = $ldapTarget['CoLdapProvisionerTarget']['dn_attribute_name'];
    $comanageIdentifierType = $ldapTarget['CoLdapProvisionerTarget']['dn_identifier_type'];
    $comanageIdentifierExtract = Hash::extract($provisioningData, "Identifier.{n}[type=$comanageIdentifierType].identifier");

    $this->log('CoLdapVopersonProvisionerTarget - comanageIdentifier ' . json_encode($comanageIdentifierExtract), 'debug');
   
    if(empty($comanageIdentifierExtract)) {
      throw new RuntimeException(_txt('er.ldapvoperson.userid'));
    }

    if(empty($cephEntityPrefix)) {
      throw new RuntimeException(_txt('er.ldapvoperson.prefix'));
    }

    $comanageIdentifier = $comanageIdentifierExtract[0];

    // assemble ceph uid from identifier and configuration used in ceph provisioner
    // hard-coding this for today...
    $uidAttr = 'voPersonApplicationUID;app-ceph';
    $uidAttrValue = "client.$cephEntityPrefix.$comanageIdentifier";

    // assemble dn from identifier and ldap provisioner config
    $dn = $ldapIdentifierAttr . 
        '=' . $comanageIdentifier . 
        ',' . $ldapTarget['CoLdapProvisionerTarget']['basedn'];

    if(empty($uidAttr)) {
        throw new UnderflowException(_txt('er.ldapvoperson.uid'));
    }

    if (empty($dn)) {
        throw new UnderflowException(_txt('er.ldapvoperson.dn'));
    }

    // Set the attribute for modification
    $attributes = array();
    $attributes[$uidAttr] = $uidAttrValue;

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
      // the lazy way ensure correctness of our managed values - delete everything under this identifier matching our prefix before recreating it
      $sdn = @ldap_search($cxn,
                          $ldapTarget['CoLdapProvisionerTarget']['basedn'],
                          "(&($uidAttr=client.$prefix*)($ldapIdentifierAttr=$comanageIdentifier)(objectClass=voPerson))",
                          array($uidAttr));
      if ($entry = @ldap_first_entry($cxn,$sdn)) {
        do {
          $moddn = @ldap_get_dn($cxn,$entry);
          $modValues = @ldap_get_values($cxn, $entry, $uidAttr);
          unset($modValues['count']);  // don't care how many there are
          $attr = array();
          $attr[$uidAttr] = $modValues;

          $this->log('CoLdapVopersonProvisionerTarget - deleting: ' . json_encode($attr) . ' from DN ' . $moddn, 'debug');
          @ldap_mod_del($cxn, $moddn, $attr);
        } while ($entry = @ldap_next_entry($cxn,$sdn));
      }
    }

    if ($add) {
      $this->log('CoLdapVopersonProvisionerTarget - adding ' . json_encode($attributes) . 'to DN ' . $moddn, 'debug');
      if(!@ldap_mod_add($cxn, $dn, $attributes)) {
        throw new RuntimeException(ldap_error($cxn), ldap_errno($cxn));
      }
    }

    // Drop the connection
    ldap_unbind($cxn);
    return true;
  }

}

