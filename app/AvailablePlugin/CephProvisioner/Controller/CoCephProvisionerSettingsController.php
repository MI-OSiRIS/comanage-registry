<?php
// This controller is unused, there are no settings outside of the provisioner target
// An example of a future enhancement requiring settings could be to allow user to choose whether the provisioner
// displays credentials via comanage person menu- this code is a rough start on that 


/**
 * COmanage Registry Ceph Provisioner Settings Controller 
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
 * @package       registry
 * @since         COmanage Registry v2.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

App::uses("StandardController", "Controller");

class CoCephProvisionerSettingsController extends StandardController {
  // Class name, used by Cake
  public $name = "CoCephProvisionerSettings";
  public $uses = array(
    'CoProvisioningTarget',
  );
  
  // This controller needs a CO to be set
  public $requires_co = true;
  
  // Establish pagination parameters for HTML views
  public $paginate = array(
    'limit' => 25,
    'order' => array(
      'co_service_id' => 'asc'
    )
  );
  /**
   * Callback after controller methods are invoked but before views are rendered.
   *
   * @since  COmanage Registry v2.0.0
   */
  function beforeRender() {
    parent::beforeRender();
    $this->CoProvisioningTarget->bindModel(array('hasOne' =>
                                                 array('CephProvisioner.CoCephProvisionerTarget')),
                                           false);
    
    $args = array();
    $args['conditions']['CoProvisioningTarget.co_id'] = $this->cur_co['Co']['id'];
    $args['conditions']['CoProvisioningTarget.plugin'] = 'CephProvisioner';
    $args['contain'][] = 'CoCephProvisionerTarget';
    
    $cephProvisioners = $this->CoProvisioningTarget -> find('all', $args);
    $this->log('CoCephProvisionerSettingsController - query CephProvisioner:' . json_encode($cephProvisioners), 'debug');
    
    $availableCephTargets = array();
    
    foreach($cephProvisioners as $lp) {
      $availableCephTargets[ $lp['CoCephProvisionerTarget']['id'] ] = $lp['CoProvisioningTarget']['description'];
    }
    $this->set('vv_ceph_provisioners', $availableCephTargets);
  }
  
  /**
   * Configure CO Service Token Settings.
   *
   * @since  COmanage Registry v2.0.0
   */
  public function configure() {
    if($this->request->is('post')) {
      // We're processing an update
      
      $coId = $this->request->data['CoCephProvisionerSettings']['co_id'];
      
      // Unset before save
      unset($this->request->data['CoCephProvisionerSettings']['co_id']);
      
      try {
        $this->CoCephProvisionerSetting->saveMany($this->request->data['CoCephProvisionerSetting']);
        $this->Flash->set(_txt('rs.saved'), array('key' => 'success'));
      }
      catch(Exception $e) {
        $this->Flash->set($e->getMessage(), array('key' => 'error'));
      }
      
      // Redirect back to a GET
      
      $this->redirect(array('action' => 'configure', 'co' => $coId));

    } else {
      $this->CoCephProvisionerSetting->ProvisioningTarget->bindModel(array('hasOne' =>
                                                               array('CoCephProvisionerSetting')),
                                                         false);
      
      // Get a list of configured Ceph provisioners and whether they are enabled to display auth info in comanage
      
      $args = array();
      $args['conditions']['ProvisioningTarget.co_id'] = $this->cur_co['Co']['id'];
      $args['order'][] = 'ProvisioningTarget.id ASC';
      $args['contain'][] = 'CoCephProvisionerSetting';
      
      $this->set('vv_ceph_provisioners_settings', $this->CoCephProvisionerSetting->find('all', $args));
    }
  }
  
  /**
   * Authorization for this Controller, called by Auth component
   * - precondition: Session.Auth holds data used for authz decisions
   * - postcondition: $permissions set with calculated permissions
   *
   * @since  COmanage Registry v2.0.0
   * @return Array Permissions
   */
  function isAuthorized() {
    $roles = $this->Role->calculateCMRoles();
    // Construct the permission set for this user, which will also be passed to the view.
    $p = array();
    // Determine what operations this user can perform
    
    // Configure CO Service Token Settings
    $p['configure'] = ($roles['cmadmin'] || $roles['coadmin']);
    $this->set('permissions', $p);
    return $p[$this->action];
  }
  
  /**
   * Determine the conditions for pagination of the index view, when rendered via the UI.
   *
   * @since  COmanage Registry v2.0.0
   * @return Array An array suitable for use in $this->paginate
   * @throws InvalidArgumentException
   */
  function paginationConditions() {
    // We only want Settings attached to CO Services that are in our CO
    
    $ret = array();
    $ret['conditions'][0]['CoService.co_id'] = $this->cur_co['Co']['id'];
    
    return $ret;
  }
  
  /**
   * For Models that accept a CO ID, find the provided CO ID.
   * - precondition: A coid must be provided in $this->request (params or data)
   *
   * @since  COmanage Registry v2.0.0
   * @return Integer The CO ID if found, or -1 if not
   */
  public function parseCOID($data = null) {
    if($this->action == 'configure') {
      if(isset($this->request->params['named']['co'])) {
        return $this->request->params['named']['co'];
      } elseif(isset($this->request->data['CoServiceTokenSetting']['co_id'])) {
        return $this->request->data['CoServiceTokenSetting']['co_id'];
      }
    }
    return parent::parseCOID();
  }
}
