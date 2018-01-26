<?php
/**
 * COmanage Registry Ceph Provisioner COU data pools
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
    "CoCephProvisioner.CoCephProvisionerTarget",
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
   * Create or update Ceph data pools for COU Groups.  Rename existing pools if necessary
   *
   * @since  COmanage Registry v2.0.0
   * @param  Array CO Provisioning Target data
   * @param  Array CO Group data
   * @return Array Data pool names
   * @throws RuntimeException
   */

  public function updateCouDataPools($coProvisioningTargetData, $coGroupData) {
    if(empty($coGroupData['CoGroup']['cou_id'])) {
      throw new RuntimeException(_txt('er.cephprovisioner.datapool.cogroup'));
    }

    // retrieve any existing data pool records and generate list of what the data pools should be
    $oldPoolCoRecord = $this->getCouDataPools($coProvisioningTargetData,$coGroupData);
    $newPoolCoRecord = $this->createCouDataPoolList($coProvisioningTargetData,$coGroupData); 

    // to be removed
    $this->log("Ceph provisioner updateCouDataPools - existing data pools " . json_encode($oldPoolCoRecord), 'debug');
    $this->log("Ceph provisioner updateCouDataPools - New Data pools: " . json_encode($newPoolCoRecord), 'debug');

    $ceph = $this->CoCephProvisionerTarget->cephClientFactory($coProvisioningTargetData);
    $rgwa = $this->CoCephProvisionerTarget->rgwAdminClientFactory($coProvisioningTargetData);

    $pgcount = $coProvisioningTargetData['CoCephProvisionerTarget']['cou_data_pool_pgcount'];

    foreach ($newPoolCoRecord as $poolRecord) {
      $save = false;
      $poolType = $poolRecord['CoCephProvisionerDataPool']['cou_data_pool_type'];
      $newDataPool = $poolRecord['CoCephProvisionerDataPool']['cou_data_pool'];

      $storedDataPool = Hash::extract($oldPoolCoRecord, "{n}.CoCephProvisionerDataPool[cou_data_pool_type=$poolType].cou_data_pool");

      if (sizeof($storedDataPool) > 1) {
        throw new RuntimeException(_txt('er.cephprovisioner.pooltype') . $poolType);
      }

      if (sizeof($storedDataPool) == 1) {
        $oldDataPool = $storedDataPool[0];
      } else {
        $oldDataPool = $newDataPool;
        $save = true;
      } 

      // pools have same name or there is no old data pool
      if ($oldDataPool == $newDataPool) {
        // method will return true if pool is created, false if exists already, exception if operation fails
        if (!$ceph->createDataPool($newDataPool, $pgcount)) {
          $this->log("Ceph provisioner updateCouDataPools - pool already exists in Ceph: " . $newDataPool, 'info');
        }
      } else {
        // existing pool has different name than it should, rename, remove rgw placement target, and update the database record
        // placement target will be created by application association done as later provisioning step
        $ceph->renameDataPool($oldDataPool, $newDataPool);
        $rgwa->removePlacementTarget($oldDataPool);

        foreach ($oldPoolCoRecord as $dbPoolRecord) {
          if ($dbPoolRecord['CoCephProvisionerDataPool']['cou_data_pool'] == $oldDataPool) {
            $dbPoolRecord['CoCephProvisionerDataPool']['cou_data_pool'] = $newDataPool;
            $poolRecord = $dbPoolRecord;
            $save = true;
          }
        }
      }
      // save/update pool record
      if ($save) {  
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

    if ($cou = $this->getCouName($coGroupData)) {
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

  public function getCouName($coGroup) {
    $args = array();
    $dataPools = array();
    // none of this makes sense if it isn't a reserved CO:COU group
    if ($this -> CoCephProvisionerTarget -> isCouAdminOrMembersGroup($coGroup)) {
      $args['conditions']['Cou.id'] = $coGroup['CoGroup']['cou_id'];
      $args['contain'] = false;
      $cou = $this -> Cou -> find('first', $args);

      $this->log("Ceph provisioner getCouName - found cou: " .  json_encode($cou), 'debug');

      if (sizeof($cou) > 0) {
        return $cou['Cou']['name'];
      }
    }
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
 
    $dataPools = $this->find('all', $args);

    return $dataPools;

  }

}
