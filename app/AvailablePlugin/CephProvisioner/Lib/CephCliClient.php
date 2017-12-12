<?php
/**
 * COmanage Registry Ceph CLI client interface
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


App::uses('CephClientException', 'CephProvisioner.Lib');
App::uses('CephCli', 'CephProvisioner.Lib');
App::uses('CakeLog', 'Log');

class CephCliClient extends CephCli {

  // map an array of daemon => caps to a string suitable for ceph auth commands
  // Example (part after client.zzz):  ceph auth caps client.zzz osd 'allow rw pool=blah' mon 'allow r'
  private function mapArrayToCapString($caps) {
    // quote each cap value
    $a = array_map(function($value) { return "'$value'"; }, $caps);

    // build cap string
    $capstring = '';
    foreach ($a as $daemon => $cap) {
        $capstring .= $daemon . ' ' . $cap . ' ';
    }
    return $capstring;
  }

  public function addEntity($id, $caps = array()) {
    $this->ceph("auth add client.$id " . $this->mapArrayToCapString($caps));
  }

  public function setCaps($id, $caps = array()) {
    $this->ceph("auth caps client.$id " . $this->mapArrayToCapString($caps)); 
  }

  // add caps in $caps to existing capabilities
  public function updateCaps($id, $caps) {
    // maybe
  }

  public function getKey($id) {
    return join('\n', $this->ceph("auth get-key client.$id " . $this->mapArrayToCapString($caps)));
  }

  public function getKeyring($id) {
    return join('\n', $this->ceph("auth get client.$id " . $this->mapArrayToCapString($caps)));
  }

  // returns true if creation succeeds, false if pool exists, exception will be thrown if command fails
  public function createDataPool($poolname, $pgcount, $size = 3) {
    // check if pool exists
    $pools = $this->ceph('osd pool ls','array');
    if (in_array($poolname, $pools)) {
        return false;
    }
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

