<?php
/**
 * COmanage Registry Ceph RGW Provisioner target model
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
 * @since         COmanage Registry v2.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

App::uses("CoProvisionerPluginTarget", "Model");
App::uses('CephCliClient', 'CephProvisioner.Lib');
App::uses('CephRgwAdminCliClient', 'CephProvisioner.Lib');
App::uses('CephApiClient', 'CephProvisioner.Lib');
//App::uses('GrouperRestClient', 'GrouperProvisioner.Lib');
//App::uses('GrouperRestClientException', 'GrouperProvisioner.Lib');

//TODO:  Limit our use of exceptions to truly critical all-stop errors that should suspend all further execution (internal errors which indicate a serious bug in programming).  Otherwise an error message and return false so execution can continue. 

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
      "CoCephProvisionerCred" => array(
      'className' => 'CephProvisioner.CoCephProvisionerCred',
      'dependent' => true
    ), 
      "CoCephProvisionerDataPlacement" => array(
      'className' => 'CephProvisioner.CoCephProvisionerDataPlacement',
      'dependent' => true
    )
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
     'opt_rgw_ldap_auth' => array (
      'rule' => 'boolean',
      'on'   => false
    ),
      'ceph_user_prefix' => array (
      'rule' => 'notBlank',
      'required' => true,
      'allowEmpty' => false
    ),
      'opt_create_cou_data_dir' => array (
      'rule' => 'boolean',
      'on'   => true
    ),
      'cou_data_dir_command' => array(
        'rule' => 'notBlank',
        'required' => false,
        'allowEmpty' => true
    ),
      'ceph_fs_mountpoint' => array (
      'rule' => 'notBlank',
      'required' => false,
      'allowEmpty' => true
    ),
      'ceph_fs_name' => array (
      'rule' => 'notBlank',
      'required' => false,
      'allowEmpty' => true
    ),
      'opt_mds_cap_uid' => array (
      'rule' => 'boolean',
      'on'   => true
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
  
    $ceph = $this->cephClientFactory($coProvisioningTargetData);

    $person = isset($provisioningData['CoPerson']['id']);
    $group = isset($provisioningData['CoGroup']['id']);

    $this->log("CephProvisioner called for op: " . $op, 'debug');
    // $this->log("Provisioning Data: " . json_encode($provisioningData), 'debug');

    switch($op) {
      case ProvisioningActionEnum::CoPersonAdded:
      case ProvisioningActionEnum::CoPersonPetitionProvisioned:
      case ProvisioningActionEnum::CoPersonPipelineProvisioned:
      case ProvisioningActionEnum::CoPersonUnexpired:
        $this->provisionCoPersonAction($coProvisioningTargetData,$provisioningData);
        break;
      case ProvisioningActionEnum::CoPersonReprovisionRequested:
        $this->reprovisionCoPersonAction($coProvisioningTargetData, $provisioningData);
        break;
      case ProvisioningActionEnum::CoPersonUpdated:
        $this->updateCoPersonAction($coProvisioningTargetData, $provisioningData);
        break;
      case ProvisioningActionEnum::CoPersonExpired:
      case ProvisioningActionEnum::CoPersonEnteredGracePeriod:
      case ProvisioningActionEnum::CoPersonDeleted:
        $this->deleteCoPersonAction($coProvisioningTargetData, $provisioningData);
        break;
      case ProvisioningActionEnum::CoGroupAdded:
      case ProvisioningActionEnum::CoGroupReprovisionRequested:
      case ProvisioningActionEnum::CoGroupUpdated:
        $this->provisionCoGroupAction($coProvisioningTargetData,$provisioningData);
        break;
      case ProvisioningActionEnum::CoGroupDeleted:
        $this->deleteCoGroupAction($coProvisioningTargetData,$provisioningData);
        break;
      default:
        throw new RuntimeException("CephProvisioner action $op is not implemented");
        break;
    }
  
    
    return true;
    
  }

  public function provisionCoPersonAction($coProvisioningTargetData,$coPersonData) {
    
    if(!isset($coPersonData['CoPerson'])) {
      $this->log("CephProvisioner provisionCoPersonAction not passed CoPerson object");
      return false;
    }

    // $this->log("Ceph provisioner provisionCoPersonAction - coPerson data: " . json_encode($coPersonData), 'debug');

    // add user ceph client key 
    $this->updateCephClientKey($coProvisioningTargetData, $coPersonData);

    // add rgw user - if user exists then it simply returns existing metadata
    $md = $this->setRgwCoUser($coProvisioningTargetData,$coPersonData);

  }

  // similar to provision co person but with the added step of looking for existing RGW credentials to reprovision
  public function reprovisionCoPersonAction($coProvisioningTargetData, $coPersonData) {

    // add user ceph client key  (no attempt is made to re-insert existing credentials from our db but they won't be overwritten if there already)
    $this->updateCephClientKey($coProvisioningTargetData, $coPersonData);
    
     // get known credentials credentials database
    $coPersonCreds = $this->CoCephProvisionerCred->getCoPersonCreds($coPersonData['CoPerson']['id'], array(CephClientEnum::RgwLdap, CephClientEnum::Rgw));

    // user had no credentials in database, add new
    if (empty($coPersonCreds)) { 
      $md = $this->setRgwCoUser($coProvisioningTargetData,$coPersonData); 
      return true;
    }

    // sync existig credentials 
    foreach ($coPersonCreds as $cred) {
        // if user exists but doesn't have this access/secret combo then creds will be added and metadata returned
        // if user exists with exactly this combo then ceph will just return the metadata for that user
        // if user exists but info is different (display, email, etc) then the user will be modified with new info

        $this->log("reprovisionCoPersonAction: reprovision cred: " . $cred['CoCephProvisionerCred']['identifier'], 'debug');

        $md = $this->setRgwCoUser($coProvisioningTargetData, 
                                  $coPersonData, 
                                  $cred['CoCephProvisionerCred']['identifier'],
                                  $cred['CoCephProvisionerCred']['primaryid'],
                                  $cred['CoCephProvisionerCred']['userid'],
                                  $cred['CoCephProvisionerCred']['secret']);
    }
    return true;
  }

  // call provisionCoPersonAction to create new ceph users in cluster and rgw (may be needed if uid identifer changed) 
  // The sync methods will take care of removing stale ceph keys 
  // or moving rgw user info and buckets from old identifier to new one
  public function updateCoPersonAction($coProvisioningTargetData, $coPersonData) {
    // re-run provision co person to insert new identifiers
    $this->provisionCoPersonAction($coProvisioningTargetData,$coPersonData);

    // sync will remove old identifiers from rgw/ceph
    // and move rgw metadata to new user so it keeps same access,secret,quota,etc)
    $this->syncRgwCoPeople($coProvisioningTargetData);
    $this->syncCephCoPeople($coProvisioningTargetData);
    
  }

  public function deleteCoPersonAction($coProvisioningTargetData,$coPersonData) {
    $this->syncRgwCoPeople($coProvisioningTargetData);
    $this->syncCephCoPeople($coProvisioningTargetData);
  }

  public function deleteCoGroupAction($coProvisioningTargetData, $coGroupData) {
    // we only need pool deletion and delinking to happen for one of the 3 core COU groups
    if (!$this -> isCouAdminGroup($coGroupData)) {
      return true;
    }

    // admin / member group deletions only happen at COU deletion
    // delete pool records from database and unlink pools from CephFS, RGW targets
    if ($couDataPools = $this -> CoCephProvisionerDataPool -> getCouDataPools($coProvisioningTargetData, $coGroupData)) {

      // false 4th param means unlink these pools
      $this -> linkPoolsToApplications($coProvisioningTargetData,$coGroupData, $couDataPools, false);

      // this does not delete pools in ceph, but removes them from comanage db 
      $this -> CoCephProvisionerDataPool -> deleteCouDataPoolRecords($coProvisioningTargetData, $coGroupData);

    }

    return true;

  }

  // there are no groups in Ceph so this is only responsible for creating data pools matching COU name
  // it only does anything when the COU admin group is provisioned
  // access to those data pools is provisioned by co person changes
  public function provisionCoGroupAction($coProvisioningTargetData,$coGroupData) {
    // nothing to provision
    if (!$this -> isCouAdminGroup($coGroupData)) {
      return true;
    }

    if ($couDataPools = $this -> CoCephProvisionerDataPool -> updateCouDataPools($coProvisioningTargetData, $coGroupData)) {
      $this->linkPoolsToApplications($coProvisioningTargetData,$coGroupData, $couDataPools);
      
      if ( $coProvisioningTargetData['CoCephProvisionerTarget']['opt_create_cou_data_dir'] ) {
        if (!$this->createCouDataDir($coProvisioningTargetData, $coGroupData, $couDataPools)) {
            throw new RuntimeException(_txt('er.cephprovisioner.coudir'));
        }
      }
    } else {
        throw new RuntimeException(_txt('er.cephprovisioner.datapool.provision'));
    }
  }

  //  pull uid identifier from coperson data 
  public function getCoPersonUid($coPersonData) {
    $identifier = Hash::extract($coPersonData, "Identifier.{n}[type=uid].identifier");
    if (empty($identifier)) { 
      $this->log("CephProvisioner getCoPersonUid (" . $coPersonData['CoPerson']['id'] . ")" . _txt('er.cephprovisioner.identifier'), 'error');
      return null;
    } else { return $identifier[0]; }
  }

  public function setRgwCoUser($coProvisioningTargetData, $coPersonData, $rgwUserId=null, $primaryUser=true, $accessKey=null, $secretKey=null) {
    $rgwa = $this -> rgwAdminClientFactory($coProvisioningTargetData);

    // extract identifier from coperson data and see if the user info being added is for the primary co person uid (it has to be if rgwUserId is not provided)
    // addRgwUser sets a different string in display name depending on whether this is a user-defined or comanage defined uid
    $coPersonUid = $this->getCoPersonUid($coPersonData);

    if (is_null($rgwUserId)) { $rgwUserId = $coPersonUid; }

    // can't create a user with no id
    if (is_null($rgwUserId)) { 
      throw new InternalErrorException(_txt('er.cephprovisioner.identifier'));
    }

    $coPersonUser = ($rgwUserId == $coPersonUid) ? true : false;

    $email = null;  
      
    if (array_key_exists('EmailAddress', $coPersonData) && $coPersonUser) {
      foreach ($coPersonData['EmailAddress'] as $emr) 
        # use first 'official', else first of whatever is left
        switch($emr['type']) {
          case 'official':
            $email = $emr['mail'];
            break 2;
          default:
            $email = $emr['mail'];
        }
    }

    try {
      // returns existing metadata if user already defined
      $md = $rgwa->addRgwUser($rgwUserId, $coPersonData['CoPerson']['id'], $email, $coPersonUser, $accessKey, $secretKey);
    } catch (CephClientException $e) {
        // pass it up for output 
        throw new RuntimeException("$rgwUserId (coperson " . $coPersonData['CoPerson']['id'] . ') ' . $e->getMessage());
    }

    $this->saveRgwCreds($coProvisioningTargetData,$coPersonData, $rgwUserId, $md, $primaryUser);
    $this->saveRgwDefaultPlacement($coProvisioningTargetData, $coPersonData, $rgwUserId);

    // syncs placement tags, default placement, and suspended status
    $md = $this->syncRgwMeta($coProvisioningTargetData, $coPersonData, $md, $rgwUserId);

    // return user metadata for use in other methods
    return $md;
  }

  /**
  * Set default placement in database for user identifier (userid)
  * @param placement - the placement tag to set as default.  If not provided 
  **/
  public function saveRgwDefaultPlacement($coProvisioningTargetData, $coPersonData, $userid, $placement = null) {
    $rgwa = $this -> rgwAdminClientFactory($coProvisioningTargetData);

    if (is_null($placement)) {
      // set default to first COU this co person belongs to if there is no existing data
      $couList = $this->getCouList($coPersonData['CoPerson']['id']);

      if (sizeof($couList) > 0) {
         $placement = $couList[0];
        } else {
          // no COU returned, log an error (this is probably going to be causing an exception later if there is no placement data saved)
          $this->log("No COU found for $userid belonging to coperson id: " . $coPersonData['CoPerson']['id'], 'error');
          return false;
        }
    } 

    // updat existing record if exists (reprovision)
    $savedPlacement = $this->CoCephProvisionerDataPlacement->getPlacement($userid, array(CephClientEnum::Rgw, CephClientEnum::RgwLdap), true);
    $args = array();
    if (!empty($savedPlacement)) {
      $args['CoCephProvisionerDataPlacement']['id'] = $savedPlacement['CoCephProvisionerDataPlacement']['id']; 
    }

    $args['CoCephProvisionerDataPlacement']['identifier'] = $userid;
    $args['CoCephProvisionerDataPlacement']['co_ceph_provisioner_target_id'] = $coProvisioningTargetData['CoCephProvisionerTarget']['id'];
    $args['CoCephProvisionerDataPlacement']['placement'] = $placement;
    $args['CoCephProvisionerDataPlacement']['type'] = $rgwa->getAuth();


    $this->log("\nCeph Provisioner saveRgwDefaultPlacement - saving record: " . json_encode($args), 'debug'); 

    if (!$this->CoCephProvisionerDataPlacement->save($args)) {
        throw new RuntimeException(_txt('er.db.save'));
    }
    $this->CoCephProvisionerDataPlacement->clear();
  }

  // if there are buckets associated with this user link them to the primary user
  // if this is the primary user we should not be calling this function - co user deletions are handled in the syncRgwCoPeople function
  // (we may want to rethink and also use this to delete co users on deprovision too)
  public function deleteRgwCoUser($coProvisioningTargetData, $coPersonData, $rgwUserId) {
    // make sure we can't delete primary user id

    $CoPersonIdentifier = $this->getCoPersonUid($coPersonData);

    if ($CoPersonIdentifier == $rgwUserId) {
      throw new InternalErrorException(_txt('er.cephprovisioner.userid.delete'));
    }

    $rgwa = $this -> rgwAdminClientFactory($coProvisioningTargetData);

    $args = array();
    $args['CoCephProvisionerCred.identifier'] = $rgwUserId;

    if (!$this->CoCephProvisionerCred->deleteAll($args)) {
      $this->log("No matching credential found to delete from database: $rgwUserId",'error');
    } else {
      // remove data placement entry
      $args = array();
      $args['CoCephProvisionerDataPlacement.identifier'] = $rgwUserId;
      if (!$this->CoCephProvisionerDataPlacement->deleteAll($args)) {
        $this->log("Error deleting data placement preference, user will still be deleted: $rgwUserId",'error');
      }
      // move buckets from this user to primary id using coperson identifier (linking to a new user implies unlinking from old user)
      $bucketList = $rgwa->getBucketList($rgwUserId);
      $rgwa->linkBuckets($CoPersonIdentifier, $bucketList, true);
      $rgwa->deleteRgwUser($rgwUserId);
    }
  }

  /**
  * RGW creds may need to be saved for several different ops such as new access key, new userid - so it is split out here 
  */
  public function saveRgwCreds($coProvisioningTargetData,$coPersonData, $userid, $md, $primary=false) {

    $CoPersonIdentifier = $this->getCoPersonUid($coPersonData);
    $primary_trigger = false;

    // users can have multiple keys
    foreach ($md['keys'] as $index=>$keyrecord) {
      $cred = array();
      $cred['CoCephProvisionerCred']['co_person_id'] = $coPersonData['CoPerson']['id'];
      $cred['CoCephProvisionerCred']['co_ceph_provisioner_target_id'] = $coProvisioningTargetData['CoCephProvisionerTarget']['id'];
      $cred['CoCephProvisionerCred']['identifier'] = $userid;
      $cred['CoCephProvisionerCred']['type'] = CephClientEnum::Rgw;
      
      // see if there is an existing credential and if so just update it (could be reprovision, could be identifier change)
      $existingCreds = $this->CoCephProvisionerCred->getCredsForUserid($keyrecord['access_key'],array(CephClientEnum::Rgw, CephClientEnum::RgwLdap));
      if (!empty($existingCreds)) {
        $cred['CoCephProvisionerCred']['id'] = $existingCreds['CoCephProvisionerCred']['id'];
        $cred['CoCephProvisionerCred']['primaryid'] = $existingCreds['CoCephProvisionerCred']['primaryid'];
      } elseif ($primary_trigger == false) {
        // primary is considered the 'main' credential to be sorted first and not deleted (internally might be deleted and recreated, but the user can't delete it), 
        // the check is to ensure only one true can ever be set true for given user metadata set
        // it's an extra precaution in case of picking up existing MD from radosgw that was somehow never saved in db
        // usually that wouldn't happen unless there was an error or bug at some point 
        $cred['CoCephProvisionerCred']['primaryid'] = $primary;
        $primary_trigger = true;
      } else {
        $cred['CoCephProvisionerCred']['primaryid'] = false;
      }
      
      $cred['CoCephProvisionerCred']['userid'] = $keyrecord['access_key'];
      $cred['CoCephProvisionerCred']['secret'] = $keyrecord['secret_key'];
      $this->log("\nCeph Provisioner saveRgwCreds - saving credential record: " . json_encode($cred), 'debug'); 
      if (!$this->CoCephProvisionerCred->save($cred)) {
        throw new RuntimeException(_txt('er.db.save'));
      }
      $this->CoCephProvisionerCred->clear();
    }
  }

  public function addRgwAccessKey($coProvisioningTargetData,$coPersonData, $rgwUserId, $primaryUser=false) {
    $rgwa = $this -> rgwAdminClientFactory($coProvisioningTargetData);
    $md = $rgwa->addUserKey($rgwUserId);
    $this->saveRgwCreds($coProvisioningTargetData,$coPersonData, $rgwUserId, $md, $primaryUser);
  }

  public function removeRgwAccessKey($coProvisioningTargetData,$rgwUserId, $accessKey) {
    $rgwa = $this -> rgwAdminClientFactory($coProvisioningTargetData);
    $rgwa->removeUserKey($rgwUserId, $accessKey);

    $args = array();
    //$args['userid'] = $accessKey;
    if ($id = $this->CoCephProvisionerCred->field('id', array('userid' => $accessKey))) {
      $this->CoCephProvisionerCred->delete($id);
    } else {
      $this->log("Error removing access key: $rgwUserId",'error');
    }
  }

  /**
  * set RGW user metadata:  Placement tags, default placement, suspended status, more as it comes up
  * 
  * @param Array: provisioning target data
  * @param Array: co person data
  * @param Array[optional]:  RGW user metadata object.  Avoids looking up user data in function if it was previously obtained.
  * @param String: Operate on provided UID instead of uid from coperson identifier
  * @return Associative array of RGW user metadata 
  */

  // 
  public function syncRgwMeta($coProvisioningTargetData,$coPersonData, $md = null, $rgwUserId=null) {
    
    $rgwa = $this -> rgwAdminClientFactory($coProvisioningTargetData);

    if (is_null($rgwUserId)) { 
      $rgwUserId = $this->getCoPersonUid($coPersonData);
    }

    // tried to sync a user with no identifier, something has gone wrong 
    if (is_null($rgwUserId)) { 
       throw new InternalErrorException(_txt('er.cephprovisioner.identifier')); 
    }

    $this->log("Ceph Provisioner syncRgwMeta found userid: " . $rgwUserId, 'debug');

    if ($md == null) {
      $md = $rgwa->getUserMetadata($rgwUserId);
    }

    // unlikely but not impossible to have a programmer error with $md argument passed in
    if ($md['user_id'] != $rgwUserId) {
      throw new InternalErrorException(_txt('er.cephprovisioner.rgw.meta') . " - userid param: $userid, meta value: " . $md['user_id']);
    }

    // only sync data if there is a change (avoid resyncing if we are called multiple times on same user id)
    $sync_update = false;
    $suspended = 0;
    // set tags to active COU for the coperson owning this userid
    $placement_tags = $this->getCouList($coPersonData['CoPerson']['id']);
    // get the default placement (error if we can't find this)
    $default_placement = $this->CoCephProvisionerDataPlacement->getPlacement($rgwUserId, array(CephClientEnum::Rgw, CephClientEnum::RgwLdap));
    $this->log("CephProvisioner syncRgwMeta looked up default placement '" .  $default_placement . "' for userid '$rgwUserId'", 'debug');
    if ($coPersonData['CoPerson']['status'] != StatusEnum::Active) { 
      $suspended = 1; 
      $this->log("CephProvisioner - syncRgwMeta coperson owner is not in active member COU group - suspending RGW user $userid", 'debug');
    }

    if ($md['placement_tags'] != $placement_tags) {   
      $md['placement_tags'] = $placement_tags;
      $sync_update = true;
    }

    if ($md['default_placement'] != $default_placement) {
      $md['default_placement'] = $default_placement;
      $sync_update = true;
    }

    // there is a dedicated ceph command for this but since we're writing a metadata blob let's consolidate in this method
    if ($md['suspended'] != $suspended) {
      $md['suspended'] = $suspended;
      $sync_update = true;
    }

    if (empty($md['default_placement'])) {
      throw new InternalErrorException(_txt('er.cephprovisioner.rgw.placement') . " - userid: $rgwUserId");
    } else {
      $this->log("CephProvisioner syncRgwMeta looked up default placement '" .  $md['default_placement'] . "' for userid '$rgwUserId'", 'debug');
    }
     
    // if anything is new write new metadata with tags replaced and new default 
    if ($sync_update) {  $rgwa->setUserMetadata($rgwUserId,$md); }
    return $md;
  }
    
 /**
   * Determine Ceph access capabilities for CoPerson and create client key
   *
   * @since  COmanage Registry v3.1.0
   * @param  Array CO Provisioning Target data
   * @param  coPersonData
   * @return Boolean True on success
   *
   */

  public function updateCephClientKey($coProvisioningTargetData, $coPersonData, $newSecret=false) {

    $couObject = ClassRegistry::init('Cou');
    $ceph = $this->cephClientFactory($coProvisioningTargetData);
    $caps = array('mon' => 'allow r', 'mgr' => 'allow r');
    $prefix = $coProvisioningTargetData['CoCephProvisionerTarget']['ceph_user_prefix'];

    $userid = $this->getCoPersonUid($coPersonData);

    if (is_null($userid)) { return false; } 

    // remove key if this user is not currently active (can't suspend a ceph client key)
    if ($coPersonData['CoPerson']['status'] != StatusEnum::Active) { 
      $ceph->removeEntity($userid);
      return true; 
    }

    // If configured to add uid/gid info look that info up in LDAP or grouper
    // *** Related feature no longer supported *** 
    /* 
    if ($coProvisioningTargetData['CoCephProvisionerTarget']['opt_mds_cap_uid']) {

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
    }

    */ 
  
    // some people might be in both cou admin and member groups, keep a log of cou id to avoid making cap strings for both
    // (at some point admin vs member may indicate different cap strings but currently they do not)
    $duplicateCheck = array();

    //$couList = $this->getCouList($coPersonData['CoPerson']['id']);

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

        if (!$couDataPools = $this-> CoCephProvisionerDataPool -> getCouDataPools($coProvisioningTargetData, $group)) {
          $this->log(_txt('er.cocephprovisioner.nopool'), 'error');
          return false;
        }

        // *** this is no longer supported, we don't give out CephFS direct mounts and giving users RW access to FS pools was never secure ***
        /* 

        // always set rw on cou path
        $pathSpec = "allow rw path=/$couNameLower";

        // add uid/gid list if set (only if config switch was enabled earlier)
        
        if (isset($useridNumber) && isset($gidList)) {
          $pathSpec .= " uid=$useridNumber gids=" . implode(',',$gidList); 
        }

        $caps['mds'][] = $pathSpec;

        */ 

        $poolCount = sizeof($couDataPools);
        for ($idx = 0; $idx < $poolCount; $idx++) {
          $type = $couDataPools[$idx]['CoCephProvisionerDataPool']['cou_data_pool_type'];
          // skip rgw and fs pools, no direct access needed
          if ($type == CephDataPoolEnum::Rgw || $type == CephDataPoolEnum::Fs) { continue; }
          
          $poolName = $couDataPools[$idx]['CoCephProvisionerDataPool']['cou_data_pool'];    
          $caps['osd'][] = "allow rw pool=$poolName";
        }
      }
    }

    $this->log("CephProvisioner updateCephClientKey - generated caps array: " . json_encode($caps),'debug');

    // there isn't really a way to tell ceph to generate a new secret for an entity 
    // so we remove it and let it be recreated
    if ($newSecret) {
      $ceph->removeEntity($userid);
    }
    
    // if userid/entity already exists this function will just set the caps to match what we calculate they should be
    $ceph->addOrUpdateEntity($userid,$caps);

    $secret = $ceph->getKey($userid);

    // Update CephProvisionerCreds
    $cred = array();
    $cred['CoCephProvisionerCred']['co_person_id'] = $coPersonData['CoPerson']['id'];
    $cred['CoCephProvisionerCred']['co_ceph_provisioner_target_id'] = $coProvisioningTargetData['CoCephProvisionerTarget']['id'];
    $cred['CoCephProvisionerCred']['type'] = CephClientEnum::Cluster;
    $cred['CoCephProvisionerCred']['identifier'] = $userid;
    $cred['CoCephProvisionerCred']['userid'] = $userid;
    $cred['CoCephProvisionerCred']['secret'] = $secret;
    $cred['CoCephProvisionerCred']['primaryid'] = true;

    $existingCred = $this->CoCephProvisionerCred->getCredsForUserid($userid,CephClientEnum::Cluster);

    if ($existingCred) { 
      $cred['CoCephProvisionerCred']['id'] = $existingCred['CoCephProvisionerCred']['id'];
    }

    $this->log("\nCeph Provisioner updateCephClientKey - saving credential record: " . json_encode($cred), 'debug'); 
  
    if (!$this->CoCephProvisionerCred->save($cred)) {
      throw new RuntimeException(_txt('er.db.save'));
    }

    $this->CoCephProvisionerCred->clear();

    return true;

  }
  public function syncCephCoPeople($coProvisioningTargetData) {
    $ceph = $this->cephClientFactory($coProvisioningTargetData);
    $CoPersonObject = ClassRegistry::init('CoPerson');

    // this only returns entities we manage 
    $entities = $ceph->getEntities();

    foreach ($entities as $userid) {
      $args = array();
      $args['conditions']['CoProvisioningTarget.id'] = $coProvisioningTargetData['CoCephProvisionerTarget']['co_provisioning_target_id'];
      $args['contain'] = false;
      $target = $this->CoProvisioningTarget->find('first', $args);
      $coId = $target['CoProvisioningTarget']['co_id'];

      try {
        $coPersonId = $CoPersonObject->idForIdentifier($coId,$userid,IdentifierEnum::UID);
      }  catch (InvalidArgumentException $e) {
        if ($e->getMessage() == 'Unknown Identifier') {
          $this->log("CephProvisioner - syncCephCoPeople identifier $userid not found - deleting user. " . "Caught exception was " . $e->getCode() . ':' . $e->getMessage(), 'info');
          // all the ceph lib classes hard-code client. into operations
          $ceph->removeEntity($userid);
          $args = array();
          $args['CoCephProvisionerCred.identifier'] = $userid;
          $args['CoCephProvisionerCred.type'] = CephClientEnum::Cluster;
          $this->CoCephProvisionerCred->deleteAll($args);  
        } else {
          throw $e;
        }
      }
    }
  }


// verify users against comanage copersonid and remove / rename as necessary

  public function syncRgwCoPeople($coProvisioningTargetData) {
      $rgwa = $this->rgwAdminClientFactory($coProvisioningTargetData);
      $userList = $rgwa->getRgwUsers();

      foreach ($userList as $user => $md) {
          $coPersonId = $rgwa->getCoPersonId($md);
          $isCoPersonSubuser = $rgwa->isCoPersonSubuser($md);

          // sanity check, would not ever expect this to be the case
          // getCoPersonId will have generated an error message already
          if (is_null($coPersonId)) { continue; }
          
          $this->log("CephProvisioner - syncRgwCoPeople checking user: " . json_encode($user) . ' copersonid: ' . $coPersonId,'debug');
          
          $CoPersonObject = ClassRegistry::init('CoPerson');
          //$CoGroupObject = ClassRegistry::init('CoGroup');
          $CoIdentifierObject = ClassRegistry::init('Identifier');
         
          // look for coperson with this id and see if the identifier matches the rgw user
          // if not we have a rename situation
          
          $args = array();
          $args['conditions']['CoPerson.id'] = $coPersonId;
          $args['condition']['CoPerson.deleted'] = 0;
          $args['contain'] = false;
          $coPersonData = $CoPersonObject->find('first', $args);

          $args = array();
          $args['conditions']['Identifier.co_person_id'] = $coPersonId;
          $args['conditions']['Identifier.type'] = IdentifierEnum::UID;
          $args['fields'] = ['Identifier.identifier'];
          $args['contain'] = false;
          $CoIdentifierData = $CoIdentifierObject->find('first', $args);
          
          if (empty($coPersonData) || empty($CoIdentifierData)) {
               if (empty($CoIdentifierData)) { 
                $this->log("CephProvisioner - syncRgwCoPeople deleting user $user - No identifier found for co person id $coPersonId", 'debug');
              } else {
                $this->log("CephProvisioner - syncRgwCoPeople deleting user $user - non-existent or deleted coperson id: $coPersonId" , 'debug');
              }
              // Relatively safe - this will only work if no buckets associated with user 
              $args = array();
              $args['CoCephProvisionerCred.identifier'] = $user;
              $args['CoCephProvisionerCred.type'] = array(CephClientEnum::Rgw, CephClientEnum::RgwLdap);
              $rgwa->deleteRgwUser($user);
              $this->CoCephProvisionerCred->deleteAll($args);  // might fail, we don't care
              continue;
          }

          $CoPersonIdentifier = $CoIdentifierData['Identifier']['identifier'];

          // if this is a coPersonSubuser then it's fine that the identifier doesn't match, otherwise indicates 
          // that it is an old identifier leftover from rename
          if ($CoPersonIdentifier != $user && !$isCoPersonSubuser) {
            // delete
            $this->log("CephProvisioner - syncRgwCoPeople user changed: " . json_encode($user) . ' copersonid: ' . $coPersonId,'debug');
            // person was renamed - set new user metadata to match old
            // requires removing old access key first - we have all the metadata so we'll use it to both
            // unset the old key and for pushing into new user after removing fields we don't want to push

            $deprovisionMd = $md;
            $provisionMd = $md;
            
            foreach ($deprovisionMd['keys'] as $key) {
              // explicitely remove the key from the old user or it will conflict, modifying metadata is not enough
              $rgwa->removeUserKey($user, $key['access_key']);
            }
            // remove info from old user 
            $deprovisionMd['keys'] = [];
            $deprovisionMd['swift_keys'] = [];
            $deprovisionMd['subusers'] = [];

            // set the relevant meta info to new user 
            $provisionMd['key'] = "user:$CoPersonIdentifier";
            $provisionMd['user_id'] = $CoPersonIdentifier;

            // everything else (access keys, subusers, swift keys) will be automatically altered to match the user key
            // (by ceph, not in this function) 
            $rgwa->setUserMetadata($user, $deprovisionMd);
            $rgwa->setUserMetadata($CoPersonIdentifier, $provisionMd);

            // move buckets from this user to new user matching coperson identifier (linking to a new user implies unlinking from old user)
            $bucketList = $rgwa->getBucketList($user);
            $rgwa->linkBuckets($CoPersonIdentifier, $bucketList, true);

            // user can be moved now
            $this->log("CephProvisioner - syncRgwCoPeople deleting user with copersonid: $coPersonId RGW uid: $user because identifer changed to: " . $CoPersonIdentifier , 'debug');
            
            $rgwa->deleteRgwUser($user);
            
            // remove creds that were automatically inserted when the new identifier was created
            // we'll be updating the previous credential record with creds copied from old user metadata  (or inserting new if it is somehow gone)

            $args = array();
            $args['CoCephProvisionerCred.identifier'] = array($CoPersonIdentifier);
            $args['CoCephProvisionerCred.type'] = array(CephClientEnum::Rgw, CephClientEnum::RgwLdap);
            //'CoCephProvisionerCred.type' => CephClientEnum::RgwLdap;
            $this->log("deleting ". json_encode($args));
            $this->CoCephProvisionerCred->deleteAll($args);  
            
            $args = array();
            $args['CoCephProvisionerDataPlacement.identifier'] = $user;
            $this->CoCephProvisionerDataPlacement->deleteAll($args);
            
            // if there were already creds in db with same access/secret this will just update them to new identifier
            $this->saveRgwCreds($coProvisioningTargetData,$coPersonData, $CoPersonIdentifier, $provisionMd, true);
            $this->saveRgwDefaultPlacement($coProvisioningTargetData, $coPersonData, $CoPersonIdentifier, $provisionMd['default_placement']);
            
            // there is no need to check for active membership, they are deleted
            continue;             
          }
      }
  }

  public function getCouList($coPersonId) {
    $CoGroupObject = ClassRegistry::init('CoGroup');
    $coPersonGroups = $CoGroupObject -> findForCoPerson($coPersonId, null, null, null, false);
    $rv = array();

    foreach ($coPersonGroups as $coGroup) {
      // only interested in required COU member groups
      if (!$this->isCouMembersGroup($coGroup)) { continue; }
      $cou = $this->getCouName($coGroup);
      // The CO (not COU) group that everyone belongs to returns false from getCouName
      if ($cou) { $rv[] = $cou; }
      $this->log("Ceph provisioner getCouList - found cou: " .  json_encode($cou), 'debug');
    }
      // people will generally be in both _active and _all groups so remove any duplicates
      return array_unique($rv);
  }

  public function getCouName($coGroup) {
    $args = array();
    // none of this makes sense if it isn't a reserved CO:COU group
    if ($this -> isCouAdminOrMembersGroup($coGroup)) {
      $args['conditions']['Cou.id'] = $coGroup['CoGroup']['cou_id'];
      $args['contain'] = false;
      $cou = $this -> CoCephProvisionerDataPool -> Cou -> find('first', $args);

      $this->log("Ceph provisioner getCouName - found cou: " .  json_encode($cou), 'debug');

      if (sizeof($cou) > 0) {
        return $cou['Cou']['name'];
      }
    }
      return false;
  }

  public function linkPoolsToApplications($coProvisioningTargetData,$coGroupData, $couDataPools, $link=true) {
      $rgwa = $this->rgwAdminClientFactory($coProvisioningTargetData);
      $ceph = $this->cephClientFactory($coProvisioningTargetData);
      
      if (!$cou = $this->getCouName($coGroupData)) { 
        throw new RuntimeException(_txt('er.cephprovisioner.nocou'));
      }

      $fsName = $coProvisioningTargetData['CoCephProvisionerTarget']['ceph_fs_name'];
      
      foreach ($couDataPools as $pool) {
        $poolType = $pool['CoCephProvisionerDataPool']['cou_data_pool_type'];
        $poolName = $pool['CoCephProvisionerDataPool']['cou_data_pool'];

        // no need to unset the pool application if we are unlinking the pool
        $link ? $ceph->enableDataPoolApplication($poolName, $poolType) : false;

        // removal from FS will fail if data still placed in pool (which is good)
        switch ($poolType) {
          case CephDataPoolEnum::Rgw:
            $link ? $rgwa->addPlacementTarget($cou, $cou, $poolName) : $rgwa->removePlacementTarget($poolName);
            break;
          case CephDataPoolEnum::Fs:
            $link ? $ceph->addFsDataPool($poolName, $fsName) : $ceph->removeFsDataPool($poolName, $fsName);
            break;
        }
      }

      // unused, but I always forget how to use hash::extract so leave this here so I can reference it
      //$poolName = Hash::extract($couDataPools,  "{n}.CephProvisionerDataPool[cou_data_pool_type=$poolType].cou_data_pool");


  }

  /**
  * create COU data directory under configured mount point.  Helper script does not do anything to modify dir if it exists.
  * @param provisioning target data
  * @param provisioning group data (to extract COU name)
  * @param couDataPools (retrieve with CephProvisionerDataPool methods)
  * @return Boolean depending on success
  * 
  *
  * There is no param or function equivalent to delete data dir - danger of deleting data someone may have wanted archived
  **/
  public function createCouDataDir($coProvisioningTargetData,$coGroupData, $couDataPools) {
  
    $mountDir = $coProvisioningTargetData['CoCephProvisionerTarget']['ceph_fs_mountpoint'];
    $baseCommand = $coProvisioningTargetData['CoCephProvisionerTarget']['cou_data_dir_command'];

    if (!$cou = $this->getCouName($coGroupData)) { 
        $this->log(_txt('er.cephprovisioner.nocou'), 'error');
        return false;
    }

    $couLC = strtolower ($cou);

    # no need to call out if the directory exists already
    if (file_exists("$mountDir/$couLC")) { return true; }

    if (empty($baseCommand)) {
      $baseCommand = '/bin/sudo -n /usr/local/bin/mkCouDir.sh';
    }

    $fullCommand = $baseCommand . ' ' . $cou  . ' ' . $mountDir . ' 2>&1';

    $this->log("CephProvisioner - Creating COU data directory: '$fullCommand' ", 'debug');

    # passwordless sudo for this command has to be setup for this to work
    # you must also symlink it from somewhere in path to /comanage/app/AvailablePlugin/CephProvisioner/Lib/mkCouDir.sh
    # path has to also be allowed by sudo config (secure_path setting)
    # For example, in /etc/sudoers:  apache ALL=(root) NOPASSWD: /usr/local/bin/mkCouDir.sh   
    exec($fullCommand, $output, $return);

    if ($return != 0) {
      $this->log("CephProvisioner - Command returned $return while creating COU data directory with output:  " . join(' ; ',$output), 'error');
      return false;
    }
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
    $auth = $coProvisioningTargetData['CoCephProvisionerTarget']['opt_rgw_ldap_auth'];

    if (empty($cluster)) { $cluster = 'ceph'; }

    if ($coProvisioningTargetData['CoCephProvisionerTarget']['opt_rgw_admin_api']) {
      throw CephClientException("RGW admin api client is not implemented");
    } else {
      try {
        $ceph = new CephRgwAdminCliClient($client_id,$cluster, $auth);
      } catch (CephCliClientException $e) {
        $this->log("CephProvisioner unable to create new CephRgwAdminCliClient: " . $e->getMessage());
        return null;
      }
    }
    return $ceph;
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
    $prefix = $coProvisioningTargetData['CoCephProvisionerTarget']['ceph_user_prefix'];

    if (empty($cluster)) { $cluster = 'ceph'; }
    
    try {
      $ceph = new CephCliClient($client_id,$cluster,$prefix);
    } catch (CephCliClientException $e) {
      $this->log("CephProvisioner unable to create new CephCliClient: " . $e->getMessage());
      return null;
    }
    return $ceph;
  }

  // convenience wrappers
  public function isCouAdminOrMembersGroup($coGroup) {
    $CoGroupObject = ClassRegistry::init('CoGroup');
    return $CoGroupObject->isCouAdminOrMembersGroup($coGroup);
  }

  public function isCouAdminGroup($coGroup) {
    $CoGroupObject = ClassRegistry::init('CoGroup');
    return $CoGroupObject->isCouAdminGroup($coGroup);
  }

  public function isCouMembersGroup($coGroup) {
    $CoGroupObject = ClassRegistry::init('CoGroup');
    return $CoGroupObject->isCouMembersGroup($coGroup);
  }

}

