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
App::uses('CakeLog', 'Log');

class CephCli {

    private $client_id;
    private $cluster;
    private $options;
    protected $ceph = '/usr/bin/ceph';

  public function __construct($client_id = 'admin', $cluster = 'ceph', $options = array()) {

    if (empty($client_id) || empty($cluster)) {
      throw new CephClientException(_txt('er.cephprovisioner.client.param'));
    }

    $this->client_id = $client_id;
    $this->cluster = $cluster;
    $this->options = $options;
  }

  /**
  *
  * @return Command output as a (potentially) multi-line string or with each line as array value 
  * @return String or Array depending on format arg
  * @param  Operation passwd to command line
  * @param  Return output formatted as multi-line string or array 
  * @param  Text to pipe into ceph command
  **/
  protected function ceph($op, $arrayOutput=false, $pipeInput=null) {
    $output = array();
    //$return = 1;
    $cmd = $this->ceph . ' --id=' . $this->client_id 
            . ' --cluster=' . $this->cluster . ' ' 
            . join(" ", $this->options) . ' ' 
            . $op . ' 2>&1';

    if ($pipeInput) {
      $cmd = "echo '$pipeInput' " . ' | ' . $cmd;
    } 
    
    $discard = exec($cmd, $output, $return);
    $s_output = implode("\n", $output);

    if ($return != 0) {
        if (preg_match("/\((\d+)\)/", $s_output, $errcode)) {
          $code = $errcode[1];
        } else {
          $code = 0;
        }
        if(Configure::read('debug')) {
            CakeLog::write('error', $s_output);
            CakeLog::write('error', 'Ceph CLI was ' . $cmd);
        }
      throw new CephClientException($s_output,$code);
    }

     // output returns as array
    if ($arrayOutput) {
      return $output;
    } else {
      return $s_output;
    }
  }

}
