<?php
/**
 * COmanage Registry Ceph Provisioner Model
 * 
 * This contribution funded by NSF grant 1541335 for the OSiRIS project 
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
 * @since         COmanage Registry v3.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

App::uses('CephCliClient', 'CephProvisioner.Lib');
App::uses('CephRgwAdminCliClient', 'CephProvisioner.Lib');
App::uses('CephApiClient', 'CephProvisioner.Lib');

class CoCephProvisionerCred extends AppModel {
  // Define class name for cake
  public $name = "CoCephProvisionerCred";
  
  // Required by COmanage Plugins
  // To enable this plugin (even though it doesn't do anything), change the type to 'enroller'
  public $cmPluginType = "other";

  // Association rules from this model to other models
  public $belongsTo = array(
    "CoPerson",
    "CephProvisioner.CoCephProvisionerTarget"
  );

  public $actsAs = array('Containable');

  // Document foreign keys
  public $cmPluginHasMany = array();

  // Validation rules for table elements
  
  public $validate = array(
    'co_person_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    ),
    'co_ceph_provisioner_target_id' => array(
      'rule' => 'numeric',
      'required' => false,
      'allowEmpty' => true
    ),
    'identifier' => array(
      'rule' => 'notBlank',
      'required' => false,
      'allowEmpty' => true
    ),
    'userid' => array(
      'rule' => 'notBlank',
      'required' => true,
      'allowEmpty' => false
    ),
    'secret' => array(
      'rule' => 'notBlank',
      'required' => true,
      'allowEmpty' => false
    ),
    'type' => array(
      'rule' => array('inList', array(CephClientEnum::Rgw,
                                      CephClientEnum::RgwLdap,
                                      CephClientEnum::Cluster)),
      'required' => true,
      'allowEmpty' => false
    ),
     'primary' => array (
      'rule' => 'boolean',
      'on'   => false
    ),

  );
  
  // type can be an array of types
  public function getCopersonCreds($coPersonId, $type) {
    // fetch auth info cached in database
    $args = array();
    $args['conditions']['CoCephProvisionerCred.type'] = $type;
    $args['conditions']['CoCephProvisionerCred.co_person_id'] = $coPersonId;
    $args['contain'] = false;
    return $this->find('all', $args);
  }

  public function getCredsForUserid($userid,$type) {
    $args = array();
    $args['conditions']['CoCephProvisionerCred.type'] = $type;
    $args['conditions']['CoCephProvisionerCred.userid'] = $userid;
    return $this->find('first', $args);
  }

  public function getCredsForIdentifier($identifier,$type) {
    $args = array();
    $args['conditions']['CoCephProvisionerCred.type'] = $type;
    $args['conditions']['CoCephProvisionerCred.identifier'] = $identifier;
    return $this->find('all', $args);
  }
  
}
