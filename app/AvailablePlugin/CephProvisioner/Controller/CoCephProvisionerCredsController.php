<?php
/**
 * COmanage Registry CO Ceph Provisioner Controller
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
 * @since         COmanage Registry v3.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

// TODO:  Use the rest API and some jquery to dynamically check for existing user id before allowing user to submit form:  https://spaces.at.internet2.edu/display/COmanage/REST+API+Examples

// Requires some work, not exactly clear what is needed in terms of defining routes, but supported in 3.2.0:
// https://bugs.internet2.edu/jira/browse/CO-521

// There are some examples of using it in the provisioning targets controller (the only available REST API currently)

App::uses("StandardController", "Controller");

class CoCephProvisionerCredsController extends StandardController {
  // Class name, used by Cake
  public $name = "CoCephProvisionerCreds";
  // Establish pagination parameters for HTML views
  public $paginate = array(
    'limit' => 25,
    'order' => array(
      'type' => 'asc',
      'primaryid' => 'desc',
      'identifier' => 'asc',
    )
  );
  
  //public $uses = array('CephProvisioner.CoCephProvisionerDataPlacement');

  // This controller needs a CO Person to be set
  public $requires_person = true;

  /**
   * Callback before other controller methods are invoked or views are rendered.
   *
   * @since  COmanage Registry v2.0.0
   */
  public function beforeRender() {
    parent::beforeRender();
    $args = array('contain' => false);
    //$args['conditions']['CoCephProvisionerDataPlacement. co_ceph_provisioner_target_id'] = $this->cur_co['Co']['id'];
    //$args['conditions']['CoProvisioningTarget.plugin'] = 'LdapProvisioner';
    //$args['contain'][] = 'CoLdapProvisionerTarget';
    
    $dataPlacements = $this-> CoCephProvisionerCred->
                              CoCephProvisionerTarget->
                              CoCephProvisionerDataPlacement
                              ->find('all', $args);

    $this->set('co_ceph_provisioner_data_placements', $dataPlacements);

    $args = array();

    $cou = $this-> CoCephProvisionerCred->
                       CoCephProvisionerTarget->
                       getCouList($this->request->params['named']['copersonid']);

    $couOptions = array();
    foreach ($cou as $o) {
      $couOptions[$o] = $o;
    }

    $this->set('vv_cou_options', $couOptions);

  }

  /**
   * Determine the CO ID based on some attribute of the request.
   * This method is intended to be overridden by model-specific controllers.
   *
   * @since  COmanage Registry v0.8.5
   * @return Integer CO ID, or null if not implemented or not applicable.
   * @throws InvalidArgumentException
   */
  protected function calculateImpliedCoId($data = NULL) {
    if(!empty($this->request->params['named']['copersonid'])) {
      $coId = $this->CoCephProvisionerCred->CoPerson->field('co_id',
                                                     array('id' => $this->request->params['named']['copersonid']));
      if($coId) {
        return $coId;
      } else {
        throw new InvalidArgumentException(_txt('er.notfound',
                                                array(_txt('ct.co_people.1'),
                                                      filter_var($this->request->params['named']['copersonid'],FILTER_SANITIZE_SPECIAL_CHARS))));
      }
    }
    return parent::calculateImpliedCoId($data);
  }

  // request for new user id or access key
  public function credop() {

    // need provisioner and coperson data for (almost) every thing we might do

    // if we have it find the credential record and use it to find the relevant provisioner
    if (array_key_exists('credid',$this->request->params['named'])) {
      $args = array();
      $args['contain'] = false;
      $args['conditions']['CoCephProvisionerCred.id'] = $this->request->params['named']['credid'];
      $coCephProvisionerCredData = $this->CoCephProvisionerCred->find('first', $args);
      $provTargetId = $coCephProvisionerCredData['CoCephProvisionerCred']['co_ceph_provisioner_target_id'];
    } else {
      $provTargetId = $this->request->params['named']['pvid'];
    }

    // get provisioner id and lookup provisioner data
    $args = array();
    $args['contain'] = false;
    $args['conditions']['CoCephProvisionerTarget.id'] = $provTargetId;
    $coProvisioningTargetData = $this->CoCephProvisionerCred->CoCephProvisionerTarget->find('first', $args);

    // get coperson data including identifiers, email, and groups
    $args = array();
    $args['contain'] = [ 'Identifier', 'CoGroupMember' => [ 'CoGroup'] ]; 
    $args['conditions']['CoPerson.id'] = $this->request->params['named']['copersonid'];
    $coPersonData = $this->CoCephProvisionerCred->CoPerson->find('first', $args);

    if($this->request->is('post')) {
      if (array_key_exists('AddRgwUserid', $this->request->data)) {
        $userid = $this->request->data['AddRgwUserid']['rgw_new_userid'];
        if (
             !preg_match('/[^a-zA-Z0-9\-_]/',$userid) 
             && strlen($userid) >= 8
        ) {

          if (!$this->CoCephProvisionerCred->getCredsForIdentifier($userid, array(CephClientEnum::Rgw, CephClientEnum::RgwLdap))) {
            $this->CoCephProvisionerCred->CoCephProvisionerTarget->setRgwCoUser(
              $coProvisioningTargetData,
              $coPersonData,
              $this->request->data['AddRgwUserid']['rgw_new_userid'],
              false  // first userid is marked primary in credential table to avoid letting user delete it, others are never primary
            );
            $this->Flash->set(_txt('pl.cephprovisioner.rgw.newid'), array('key' => 'success'));
          } else {
            $this->Flash->set(_txt('pl.cephprovisioner.rgw.newid.exists'), array('key' => 'error'));
          }
        } else {
          $this->Flash->set(_txt('pl.cephprovisioner.rgw.newid.err'), array('key' => 'error'));
        }
      }

      if (array_key_exists('ChangeBucketPlacement', $this->request->data)) { 
         $this -> CoCephProvisionerCred ->
                CoCephProvisionerTarget ->
                saveRgwDefaultPlacement($coProvisioningTargetData, 
                                        $coPersonData, 
                                        $coCephProvisionerCredData['CoCephProvisionerCred']['identifier'], 
                                        $this->request->data['ChangeBucketPlacement']['rgw_placement']);

          $this -> CoCephProvisionerCred ->
                CoCephProvisionerTarget ->
                syncRgwMeta($coProvisioningTargetData,
                              $coPersonData, 
                              null, 
                              $coCephProvisionerCredData['CoCephProvisionerCred']['identifier']);

         $this->Flash->set(_txt('pl.cephprovisioner.rgw.placement'), array('key' => 'success'));
      }
    }
    
    if (array_key_exists('op',$this->request->params['named'])) {
      
      switch($this->request->params['named']['op']) {
        case ('regen_cephkey'):
          $this->CoCephProvisionerCred->CoCephProvisionerTarget->updateCephClientKey(
            $coProvisioningTargetData,
            $coPersonData,
            true   // triggers remove/add of entity to end up with new secret
          );
          $this->Flash->set(_txt('pl.cephprovisioner.ceph.newkey'), array('key' => 'success'));    
          break;
        case 'regen_rgwkey':
          // add new and remove old, there is not a 'regenerate' command
          $this->CoCephProvisionerCred->CoCephProvisionerTarget->addRgwAccessKey(
            $coProvisioningTargetData,
            $coPersonData,
            $coCephProvisionerCredData['CoCephProvisionerCred']['identifier'],
            true  // only the primary uid would generate new creds
          );

          $this->CoCephProvisionerCred->CoCephProvisionerTarget->removeRgwAccessKey(
            $coProvisioningTargetData,
            $coCephProvisionerCredData['CoCephProvisionerCred']['identifier'],
            $coCephProvisionerCredData['CoCephProvisionerCred']['userid']
          );

          $this->Flash->set(_txt('pl.cephprovisioner.rgw.newkey'), array('key' => 'success'));
          break;

        case 'new_rgwkey':
          $this->CoCephProvisionerCred->CoCephProvisionerTarget->addRgwAccessKey(
            $coProvisioningTargetData,
            $coPersonData,
            $coCephProvisionerCredData['CoCephProvisionerCred']['identifier']
          );
          $this->Flash->set(_txt('pl.cephprovisioner.rgw.newkey'), array('key' => 'success'));
          break;
        case 'remove_rgwkey':
           $this->CoCephProvisionerCred->CoCephProvisionerTarget->removeRgwAccessKey(
            $coProvisioningTargetData,
            $coCephProvisionerCredData['CoCephProvisionerCred']['identifier'],
            $coCephProvisionerCredData['CoCephProvisionerCred']['userid']
          );
          $this->Flash->set(_txt('pl.cephprovisioner.rgw.rmkey'), array('key' => 'success'));
          break;
        case 'remove_rgwuser':
          $this->CoCephProvisionerCred->CoCephProvisionerTarget->deleteRgwCoUser(
            $coProvisioningTargetData,
            $coPersonData,
            $coCephProvisionerCredData['CoCephProvisionerCred']['identifier']
          );
          $this->Flash->set(_txt('pl.cephprovisioner.rgw.rmid'), array('key' => 'success'));
          break;
        }
      }
      $this->performRedirect();
  }
  /**
   * Obtain all Ceph Provisioner creds for this co person
   *
   * @since  COmanage Registry v3.2.0
   */
  public function index() {
    parent::index();
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
    $self = (!empty($roles['copersonid'])
             && !empty($this->request->params['named']['copersonid'])
             && ($roles['copersonid'] == $this->request->params['named']['copersonid']));
    // Construct the permission set for this user, which will also be passed to the view.
    $p = array();
    // Determine what operations this user can perform
    // (re)generate a new credential for this CO Person?
    $p['generate'] = ($roles['cmadmin'] || $roles['coadmin']) || $self;

    // operate on credentials (add,remove, regenerate)
    $p['credop'] = ($roles['cmadmin'] || $roles['coadmin']) || $self;

    // View all existing CO Service Tokens (for this CO Person)?
    $p['index'] = ($roles['cmadmin'] || $roles['coadmin']) || $self;
    $this->set('permissions', $p);
    return($p[$this->action]);
  }

  /**
   * Perform a redirect back to the controller's default view.
   * - postcondition: Redirect generated
   *
   * @since  COmanage Registry v2.0.0
   */
  public function performRedirect() {
    if(!empty($this->request->params['named']['copersonid'])) {
      $this->redirect(array(
        'plugin'     => 'ceph_provisioner',
        'controller' => 'co_ceph_provisioner_creds',
        'action'     => 'index',
        'copersonid' => filter_var($this->request->params['named']['copersonid'], FILTER_SANITIZE_SPECIAL_CHARS)
      ));
    } else {
      $this->redirect('/');
    }
  }
  
}  // end of class

