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


App::uses('CephClientException', 'CephProvisioner.Lib');
App::uses('CakeLog', 'Log');

class CephCli {

    private $client_id;
    private $cluster;
    private $options;
    // identifier used to denote users managed by this library
    // usage is implementation specific in RGW and cluster client 
    protected $identifier;
    protected $ceph = '/usr/bin/ceph';
    public $debug_output = false;

  public function __construct($client_id = 'admin', $cluster = 'ceph', $identifier = null, $options = array()) {

    if (empty($client_id) || empty($cluster)) {
      throw new CephClientException(_txt('er.cephprovisioner.client.param'));
    }

    $this->client_id = $client_id;
    $this->cluster = $cluster;
    $this->identifier = $identifier;
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
  protected function cephold($op, $arrayOutput=false, $pipeInput=null) {
    if(Configure::read('debug')) {
        CakeLog::write('debug', 'inside CephCli-->ceph ');
        CakeLog::write('debug', '#debuggingbyma');
    }

    $output = array();
    //$return = 1;
    $cmd = $this->ceph . ' --id=' . $this->client_id 
            . ' --cluster=' . $this->cluster . ' ' 
            . join(" ", $this->options) . ' ' 
            . $op . ' 2>&1';

    if(Configure::read('debug')) {
        CakeLog::write('debug', 'inside CephCli-->ceph ');
        CakeLog::write('debug', 'inside CephCli-->ceph cmd without pipeInput is' . $cmd );
        CakeLog::write('debug', '#debuggingbyma');
    }

    if ($pipeInput) {
      $cmd = "echo '$pipeInput' " . ' | ' . $cmd;
    } 
    
    $discard = exec($cmd, $output, $return);
    if(Configure::read('debug')) {
        CakeLog::write('debug', 'inside CephCli-->ceph '); 
        CakeLog::write('debug', 'inside CephCli-->ceph discard value is' . $discard );
        CakeLog::write('debug', '#debuggingbyma');
    }
    $s_output = implode("\n", $output);

    if(Configure::read('debug')) { 
      CakeLog::write('debug', 'Ceph CLI was ' . $cmd);
      CakeLog::write('debug', 'Ceph command output: ' . $s_output);
    }

    if ($return != 0) {
      throw new CephClientException('Ceph: ' . $s_output,$return);
    }

    // output returns as array
    if ($arrayOutput) {
      return $output;
    } else {
      return $s_output;
    }
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
    if(Configure::read('debug')) {
        CakeLog::write('debug', 'inside CephCli-->cephv2 ');
        CakeLog::write('debug', '#debuggingbyma');
    }

    $output = array();
    //$return = 1;
    $cmd = $this->ceph . ' --id=' . $this->client_id 
            . ' --cluster=' . $this->cluster . ' ' 
            . join(" ", $this->options) . ' ' 
            . $op . ' 2>&1';
    if(Configure::read('debug')) {
        CakeLog::write('debug', 'inside CephCli-->cephv2');
        CakeLog::write('debug', 'inside CephCli-->cephv2 cmd without pipeInput is' . $cmd );
        CakeLog::write('debug', '#debuggingbyma');
    }

    if ($pipeInput) {
      $cmd = "echo '$pipeInput' " . ' | ' . $cmd;
    } 
    
    //Adding some logic to improve the performance of getting user metadata
    //Workaround
    $useCache=0;
    if(Configure::read('debug')) {
        CakeLog::write('debug', 'inside CephCli-->cephv2 ifcond is it md get op?? -->' . str_contains($cmd,'metadata get user:') );
        CakeLog::write('debug', '#debuggingbyma');
    }
    if (str_contains($cmd,'metadata get user:')) {
        //This is a metadata get op
        //$cacheMdCmd = '' . $cmd;
        //$discard = exec($cacheMdCmd);
        $user = substr($op, strpos($op, ":") + 1);
        $readCacheCMD = '/usr/local/bin/readCachedUserMD.sh ' . $user;
        if(Configure::read('debug')) {
            CakeLog::write('debug', 'inside CephCli-->cephv2 readCacheCMD -->' . $readCacheCMD );
            CakeLog::write('debug', '#debuggingbyma');
        }        
        $discard = exec($readCacheCMD, $output, $return);
        if ( $return == 0 ){
        //output now has the cached data
        if(Configure::read('debug')) {
            CakeLog::write('debug', 'inside CephCli-->cephv2 first call to readCacheCMD returned 0' );
            CakeLog::write('debug', '#debuggingbyma');
        } 
        $useCache=1;
        }else{
            //fetch md data for all users and populate the cache files, then read from cache for the target user    
            $genCacheMdCmd = '/usr/local/bin/genUserMdCache.sh';
            $discard = exec($genCacheMdCmd);
            $readCacheCMD = '/usr/local/bin/readCachedUserMD.sh ' . $user;
            unset($output);
            $discard = exec($readCacheCMD, $output, $return);
            if ( $return == 0 ){
                //output now has the cached data (Accepted age of cached data is hard coded in the readCachedUserMD.sh script)
                $useCache=1;
                if(Configure::read('debug')) {
                    CakeLog::write('debug', 'inside CephCli-->cephv2 second call to readCacheCMD returned 0' );
                    CakeLog::write('debug', '#debuggingbyma');
                } 
            }
        }
    }
    if ($useCache == 0){
        unset($output);
        CakeLog::write('debug', 'inside CephCli-->cephv2 Not using cache b\c useCache = ' . $useCache);
        $discard = exec($cmd, $output, $return);
    }
    if(Configure::read('debug')) {
        CakeLog::write('debug', 'inside CephCli-->cephv2 '); 
        CakeLog::write('debug', 'inside CephCli-->cephv2 discard value is' . $discard );
        CakeLog::write('debug', '#debuggingbyma');
    }
    $s_output = implode("\n", $output);

    if(Configure::read('debug')) { 
      CakeLog::write('debug', 'Ceph CLI was ' . $cmd);
      CakeLog::write('debug', 'Ceph command output: ' . $s_output);
    }

    if ($return != 0) {
      throw new CephClientException('Ceph: ' . $s_output,$return);
    }

    // output returns as array
    if ($arrayOutput) {
      return $output;
    } else {
      return $s_output;
    }
  }

}
