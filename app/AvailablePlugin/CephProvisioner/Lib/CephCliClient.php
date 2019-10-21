<?php
/**
 * COmanage Registry Ceph CLI client interface
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

// NOTE:  All the functions in this lib automatically add or remove the client.identifier prefix
// TODO:  Make that boolean class var for more flexibility?  Currently doesn't matter, only need one way

App::uses('CephClientException', 'CephProvisioner.Lib');
App::uses('CephCli', 'CephProvisioner.Lib');
App::uses('CakeLog', 'Log');

class CephCliClient extends CephCli {

  private $userPrefix;

  public function __construct($client_id, $cluster, $identifier = 'comanage', $options = array()) {
     parent::__construct($client_id, $cluster, $identifier, $options);
     
     if (!empty($this->identifier)) {
      $this->userPrefix = 'client.' . $this->identifier . '.';
     } else {
      $this->userPrefix = 'client.';
     }
  }

  public function getUserPrefix() {
    return $this->userPrefix;
  }

  public function addFsDataPool($poolName, $fsName){
    $this->ceph("fs add_data_pool $fsName $poolName");
  }

  // this will fail if fs data is placed in pool
  public function removeFsDataPool($poolName, $fsName){
    $this->ceph("fs rm_data_pool $fsName $poolName");
  }

  // map an array of daemon => caps to a string suitable for ceph auth commands
  // Example (part after client.zzz):
  // Array: [ 'osd' => 'allow rw pool=blah', 'mon' => 'allow r' ]  
  // String:  osd 'allow rw pool=blah' mon 'allow r'
  // If caps is an array it will be combined with comma between elements
  private function mapArrayToCapString($caps) {
    // quote each cap value
    //$a = array_map(function($value) { return "'$value'"; }, $caps);

    // build cap string
    $capstring = '';
    foreach ($caps as $daemon => $cap) {
        if (is_array($cap)) {
          $glue = ($daemon == 'mds') ? '; ' : ', ';
          $cap = implode($glue, $cap);
        }
        $capstring .= $daemon . ' ' . "'$cap'" . ' ';
    }
    return $capstring;
  }


  /**
  * query ceph cluster for all auth entities, returning only those matching our configured user prefix
  * @param userPrefix - unique prefix to username component set for all client entities:  client.prefix.user
  * @return Array of entities
  */
  public function getEntities() {
    $output = $this->ceph('auth ls', true);
    $entities = array();
    foreach ($output as $oline) {
      if (strpos($oline,$this->userPrefix) !== false &&
          strpos($oline, 'osd.') === false && 
          strpos($oline, 'mgr.') === false)  { 
        $entities[] = str_replace($this->userPrefix, '', $oline);
      }
    }
    return $entities;
  }

  public function removeEntity($id) {
    $this->ceph("auth rm " . $this->userPrefix . $id);
  }

  public function addEntity($id, $caps = array()) {
    $this->ceph("auth add " . $this->userPrefix . $id . ' ' . $this->mapArrayToCapString($caps));
  }

  // adds entity if not existing, recreates with caps specified otherwise
  public function addOrUpdateEntity($id, $caps = array()) {
    try {
      $this->addEntity($id,$caps);
    } catch (CephClientException $e) {
      $this->setCaps($id,$caps);
    }
  }

  public function getOrCreateKey($id, $caps = array()) {
    return $this->ceph("auth get-or-create-key " . $this->userPrefix . $id . ' ' . $this->mapArrayToCapString($caps));
  }

  public function setCaps($id, $caps = array()) {
    $this->ceph("auth caps " . $this->userPrefix . $id . ' ' . $this->mapArrayToCapString($caps));
  }

  public function getKey($id) {
    return $this->ceph("auth get-key " . $this->userPrefix . $id);
  }

  public function getKeyring($id, $format='array') {
    $output =  $this->ceph("auth get " . $this->userPrefix . $id, true);
    // remove the 'exported keyring for xxx' output line
    array_shift($output);
    if ($format == 'string') {
      return join('\n', $output);
    } else {
      return $output;
    }
  }

  // keyring should be an array as output by getKeyring function
  // returns an array suitable for feeding to addEntity or setCaps
  public function formatKeyringToCapsArray($keyring) {
    // strip formatting tabs from output
    $fixedKeyring = preg_replace('/[\t\"]/', '', $keyring);
    //str_replace('\t', '', $keyring);
    // skip the client identifier and key
    $capsArray = array_slice($fixedKeyring, 2);
    $returnCapsArray = array();

    foreach($capsArray as $cap) {
      $capLine = explode(' = ', $cap);
      $capLine = str_replace('caps ', '', $capLine);
      $returnCapsArray[$capLine[0]] = $capLine[1];
    }
    return $returnCapsArray;
  }

  /**
  * @return Array:  ceph data pools
  **/
  public function listDataPools() {
    // check if pool exists
    return $this->ceph('osd pool ls',true);
  }

  /** 
  * @param String: pool name
  * @param String: placement group count
  * @param Integer:  Pool size (replication count)
  * @return Boolean true if creation succeeds.  
  * Ceph command will throw exception if pool exists so check it first
  **/
  public function createDataPool($poolname, $pgcount, $size = 3) {
    $this->ceph("osd pool create $poolname $pgcount $pgcount");
    $this->ceph("osd pool set $poolname size $size");
    return true;
  }

  public function renameDataPool($oldDataPool, $newDataPool) {
    $this->ceph("osd pool rename $oldDataPool $newDataPool");
    return true;
  }

  public function enableDataPoolApplication($dataPool, $appEnum) {
    $appString = CephDataPoolEnum::$data_pool_apps[$appEnum];
    $this->ceph("osd pool application enable $dataPool $appString");
    return true;
  }

  public function associateCoGroupsToPool($poolname, $coperson) {
    //
  }

//  ceph auth get-or-create client.fs.userhome mon 'allow r' osd 'allow rw pool=cephfs_users' mds 'allow r, allow rw path=/user'

}

