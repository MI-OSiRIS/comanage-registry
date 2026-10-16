<?php
/**
 * COmanage Registry Ceph Provisioner Data Placement model
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

class CoCephProvisionerDataPlacement extends AppModel {
  // Define class name for cake
  public $name = "CoCephProvisionerDataPlacement";
  
  // Required by COmanage Plugins
  // To enable this plugin (even though it doesn't do anything), change the type to 'enroller'
  public $cmPluginType = "other";

  // Association rules from this model to other models
  public $belongsTo = array(
    "CephProvisioner.CoCephProvisionerTarget"
  );

  public $actsAs = array('Containable');

  // Document foreign keys
  public $cmPluginHasMany = array();

  // Validation rules for table elements
  
  public $validate = array(
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
    'placement' => array(
      'rule' => 'notBlank',
      'required' => false,
      'allowEmpty' => true
    )
  );
  
  /**
  * type can be an array of types
  * this currently doesn't limit by provisioning target, but it probably should
  * @param returnModel:  return the whole model associative array from query, otherwise just return string for placement
  * @return String: Data placement for identifier.  Could be empty string.  If returnModel then returns array data model results.  
  */ 
  public function getPlacement($identifier, $type, $returnModel=false) {
    $args = array();
    $args['conditions']['CoCephProvisionerDataPlacement.type'] = $type;
    $args['conditions']['CoCephProvisionerDataPlacement.identifier'] = $identifier;
    $args['contain'] = false;
    $qr = $this->find('first', $args);

    if ($returnModel) {
      return $qr;
    } 

    if (!empty($qr)) { return $qr['CoCephProvisionerDataPlacement']['placement']; }
    else { return null; }

  }
  
}
