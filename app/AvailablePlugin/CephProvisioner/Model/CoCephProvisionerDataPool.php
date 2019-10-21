<?php
/**
 * COmanage Registry Ceph Provisioner COU data pools
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
 * @since         COmanage Registry v0.8
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

class CoCephProvisionerDataPool extends AppModel {
  // Define class name for cake
  public $name = "CoCephProvisionerDataPool";
  
  // Add behaviors
  public $actsAs = array('Containable');
  
  // Association rules from this model to other models
  public $belongsTo = array(
    "CephProvisioner.CoCephProvisionerTarget",
    "Cou"
  );
    
  // Default display field for cake generated views
  public $displayField = "co_ceph_provisioner_target_id";
  
  // Validation rules for table elements
  public $validate = array(
    'co_ceph_provisioner_target_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'message' => 'A CO Ceph Provisioning Target ID must be provided'
    ),
    'cou_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    ),
    'cou_data_pool_type' => array(
      'rule' => 'notBlank'
    ),
    'cou_data_pool' => array(
      'rule' => 'notBlank'
    )
  );

   /**
   * Delete Ceph data pools for COU Groups from comanage database (does not delete in Ceph).  
   *
   * @since  COmanage Registry v2.0.0
   * @param  Array CO Provisioning Target data
   * @param  Array CO Group data
   * @return Boolean true if success, false if error
   * @throws RuntimeException
   * @throws CephClientException
   */

  public function deleteCouDataPoolRecords($coProvisioningTargetData, $coGroupData) {
    if(empty($coGroupData['CoGroup']['cou_id'])) {
      throw new RuntimeException(_txt('er.cephprovisioner.datapool.cogroup'));
    }

    $oldPoolCoRecord = $this->getCouDataPools($coProvisioningTargetData,$coGroupData);
    $this->log("deleteCouDataPools - getCouDataPools result: " . json_encode($oldPoolCoRecord), 'debug');

    if (empty($oldPoolCoRecord)) {
      return true;
    } else {
      if (!$this->deleteAll(['cou_id' => $coGroupData['CoGroup']['cou_id']], false)) {
        throw new RuntimeException(_txt('er.cephprovisioner.datapool.delete'));
      }
    } 
    return true;
  }

  /**
   * Create Ceph data pools for COU Groups.  Does not rename old pools or move data if COU name changed - must move data manually.
   *
   * @since  COmanage Registry v2.0.0
   * @param  Array CO Provisioning Target data
   * @param  Array CO Group data
   * @return Array Data pool names
   * @throws RuntimeException
   */

  public function updateCouDataPools($coProvisioningTargetData, $coGroupData) {
    if(empty($coGroupData['CoGroup']['cou_id'])) {
      throw new InternalErrorException(_txt('er.cephprovisioner.datapool.cogroup'));
    }

    // retrieve any existing data pool records and generate list of what the data pools should be
    $oldPoolCoRecord = $this->getCouDataPools($coProvisioningTargetData,$coGroupData);
    $newPoolCoRecord = $this->createCouDataPoolList($coProvisioningTargetData,$coGroupData); 

    $this->log("Ceph provisioner updateCouDataPools - existing data pools " . json_encode($oldPoolCoRecord), 'debug');
    $this->log("Ceph provisioner updateCouDataPools - New Data pools: " . json_encode($newPoolCoRecord), 'debug');

    $ceph = $this->CoCephProvisionerTarget->cephClientFactory($coProvisioningTargetData);
  
    $pgcount = $coProvisioningTargetData['CoCephProvisionerTarget']['cou_data_pool_pgcount'];

    // get list of pools already created in Ceph
    $existingDataPools = $ceph->listDataPools();

    // database records match desired pool names.  We only have to verify they exist in ceph
    if ($oldPoolCoRecord == $newPoolCoRecord) { 
      $dbMatch = true; 
    } else {
      // Old pools didn't match desired pool names or not in database

      // If there were pools in DB make sure they are not associated with any targets, cephfs   
      // this is a no-op if there are no records
      $this->CoCephProvisionerTarget-> linkPoolsToApplications($coProvisioningTargetData,$coGroupData, $oldPoolCoRecord, false);

      // mark for database update in either case
      $dbMatch = false;  
    }
    
    // verify each desired pool
    foreach ($newPoolCoRecord as $poolRecord) {
      $poolType = $poolRecord['CoCephProvisionerDataPool']['cou_data_pool_type'];
      $newDataPool = $poolRecord['CoCephProvisionerDataPool']['cou_data_pool'];
      // check if pool exists or create it
      if (in_array($newDataPool,$existingDataPools)) {
        $this->log("Ceph provisioner updateCouDataPools - pool already exists in Ceph: " . $newDataPool, 'info');
      } else {
        $ceph->createDataPool($newDataPool, $pgcount);
      }

      // update database with new records if necessary
      if (!$dbMatch) {
        $this->log("\nCeph Provisioner updateCouDataPools - saving record: " . json_encode($poolRecord), 'debug'); 
        if (!$this->save($poolRecord)) {
          throw new RuntimeException(_txt('er.db.save'));
        }
        $this->clear();
      }
    }  
    return $newPoolCoRecord;
  }

   /**
   * Construct data pool names for COU Groups.
   *
   * @since  COmanage Registry v2.0.3
   * @param  Array CO Group data
   * @return Array of Data pool arrays matching database retrieval format 
   * @throws RuntimeException
   */
  public function createCouDataPoolList($coProvisioningTargetData, $coGroupData) {
    $pool_types = array(
      CephDataPoolEnum::Rados,
      CephDataPoolEnum::Rgw,
      CephDataPoolEnum::Fs,
      CephDataPoolEnum::Rbd
    );    

    if ($cou = $this->CoCephProvisionerTarget->getCouName($coGroupData)) {
      $pools = array();
      $suffixes = CephDataPoolEnum::$data_pool_suffixes ;

      for ($i = 0; $i < 4; $i++) {
        $record = array();
        $record['CoCephProvisionerDataPool']['co_ceph_provisioner_target_id'] = $coProvisioningTargetData['CoCephProvisionerTarget']['id'];
        $record['CoCephProvisionerDataPool']['cou_id'] = $coGroupData['CoGroup']['cou_id'];
        $record['CoCephProvisionerDataPool']['cou_data_pool_type'] = $pool_types[$i];
        $record['CoCephProvisionerDataPool']['cou_data_pool'] = 'cou.' . $cou . $suffixes[$pool_types[$i]];
        $pools[] = $record;
      }
      return $pools;
    }

    // will be false if group wasn't cou admin/members group
    return false;
    
  }

  

  /**
  *
  * @return Array of existing COU data pools.  May be empty array if no pools defined yet.  
  **/

  public function getCouDataPools($coProvisioningTargetData, $coGroupData) {
    $args = array();
    $dataPools = array();
    $args['conditions']['CoCephProvisionerDataPool.co_ceph_provisioner_target_id'] = $coProvisioningTargetData['CoCephProvisionerTarget']['id'];
    $args['conditions']['CoCephProvisionerDataPool.cou_id'] = $coGroupData['CoGroup']['cou_id'];
    $args['contain'] = false;

    //  leave out id and created/modified so it can be used to compared to a record constructed for insertion
    $args['fields'] = [ 'CoCephProvisionerDataPool.co_ceph_provisioner_target_id', 
                        'CoCephProvisionerDataPool.cou_id',
                        'CoCephProvisionerDataPool.cou_data_pool_type',
                        'CoCephProvisionerDataPool.cou_data_pool' ];

    $dataPools = $this->find('all', $args);

    return $dataPools;

  }

}
