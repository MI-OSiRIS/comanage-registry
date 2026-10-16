<?php
/**
 * COmanage Registry Ceph Provisioner Model
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

class CoCephProvisionerCreds extends AppModel {
  // Define class name for cake
  public $name = "CoCephProvisionerCreds";
  
  // Required by COmanage Plugins
  // To enable this plugin (even though it doesn't do anything), change the type to 'enroller'
  public $cmPluginType = "other";

  // Association rules from this model to other models
  public $belongsTo = array(
    "CoPerson",
    "CephProvisioner.CoCephProvisionerTarget"
  );

  public $actsAs = array('Containable');

  // Document foreign keys
  public $cmPluginHasMany = array();

  // Validation rules for table elements
  
  public $validate = array(
    'co_person_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    ),
    'co_ceph_provisioner_target_id' => array(
      'rule' => 'numeric',
      'required' => false,
      'allowEmpty' => true
    ),
    'userid' => array(
      'rule' => 'notBlank',
      'required' => true,
      'allowEmpty' => false
    ),
    'secret' => array(
      'rule' => 'notBlank',
      'required' => true,
      'allowEmpty' => false
    ),
    'type' => array(
      'rule' => array('inList', array(CephClientEnum::Rgw,
                                      CephClientEnum::RgwLdap,
                                      CephClientEnum::Cluster)),
      'required' => true,
      'allowEmpty' => false
    )
  );
  
  public function getCopersonCreds($coPersonId, $type) {
    // fetch auth info cached in database
    $args = array();
    $args['conditions']['CoCephProvisionerCreds.type'] = $type;
    $args['conditions']['CoCephProvisionerCreds.co_person_id'] = $coPersonID;
    $args['contain'] = false;
    return $this->find('all', $args);
  }

  public function getCredsForUserid($userid,$type) {
    $args = array();
    $args['conditions']['CoCephProvisionerCreds.type'] = $type;
    $args['conditions']['CoCephProvisionerCreds.userid'] = $userid;
    return $this->find('first', $args);
  }

  /**
   * Regenerate ceph auth
   *
   * @since  COmanage Registry v2.0.0
   * @param  Integer                $coPersonId      CO Person to generate token for
   * @param  Integer                $coServiceId     CO Service to generate token for
   * @param  CoServiceTokenTypeEnum $tokenType       Type of token to generate
   * @param  Integer                $actorCoPersonId CO Person ID of actor sending the notification
   * @throws LogicException
   * @throws RuntimeException
   */
  public function generate($coPersonId,
                           $coServiceId,
                           $tokenType=CoServiceTokenTypeEnum::Plain15,
                           $actorCoPersonId=null,
                           $cephProvisionerId=null,
                           $ldapProvisionerId=null) {
    $token = null;
    // retrieve existing token
    // doing this here because the existence of a token is used to determine
    // whether we regenerate or just retrieve the initially provisioned provisioned ceph key 
    $curToken = $this->retrieveToken($coServiceId,$coPersonId);
    switch($tokenType) {
      case CoServiceTokenTypeEnum::Plain08:
      case CoServiceTokenTypeEnum::Plain15:
      case CoServiceTokenTypeEnum::Plain30:
      case CoServiceTokenTypeEnum::CephRgwToken:
        // Note we use Security::randomBytes() rather than php random_bytes, which was not added until 7.0
        //$token = substr(preg_replace("/[^a-zA-Z0-9#-/<-@{-~!^_]+/", "", base64_encode(Security::randomBytes(64))),
        //                0,
        //                (integer)$tokenType);
        $token = substr(base64_encode(Security::randomBytes(64)),
                        0,
                        (integer)$tokenType);
        break;
      case CoServiceTokenTypeEnum::CephKey:
        $token = $this->regenCephKey($coPersonId, $cephProvisionerId, $ldapProvisionerId, $curToken);
        break;
      default:
        throw new LogicException(_txt('er.notimpl'));
        break;
    }
    if(!$token) {
      throw new RuntimeException(_txt('er.coservicetoken.fail'));
    }
    $newToken = array(
      'co_service_id' => $coServiceId,
      'co_person_id'  => $coPersonId,
      'token'         => $token,
      'token_type'    => $tokenType
    );
    if(!empty($curToken['CoServiceToken']['id'])) {
      $newToken['id'] = $curToken['CoServiceToken']['id'];
    }
    $this->clear();
    if(!$this->save($newToken)) {
      throw new RuntimeException(_txt('er.db.save-a', array('CoServiceToken')));
    }
    // Pull the service name for the history record
    $serviceName = $this->CoService->field('name', array('CoService.id' => $coServiceId));
    // Cut history
    $this->CoPerson->HistoryRecord->record($coPersonId,
                                           null,
                                           null,
                                           $actorCoPersonId,
                                           // XXX If this moves to core code, assign ActionEnums
                                           'XTOK',
                                           _txt('pl.coservicetoken.history', array($serviceName,
                                                                                   _txt('en.coservicetoken.tokentype', null, $tokenType))));
    return $token;
  }
  // look up existing entity and key capabilities, rm and recreate with new shared secret
  // there is no way in ceph to simply regenerate the secret for an entity
  private function regenCephKey($coPersonId, $cephProvisionerId, $ldapProvisionerId, $curToken) {
    $cephProvisionerTarget = ClassRegistry::init('CoCephProvisioner.CoCephProvisionerTarget');
    $coPersonObject = ClassRegistry::init('CoPerson');
    $args = array();
    $args['conditions']['CoPerson.id'] = $coPersonId;
    $arcs['contain'] = false;
    $coPersonData = $coPersonObject -> find('first', $args);
    $idType = IdentifierEnum::UID;
    $uid = Hash::extract($coPersonData['Identifier'], "{n}[type=$idType].identifier");
    // get ceph provisioner data
    $args = array();
    $args['conditions']['CoCephProvisionerTarget.id'] = $cephProvisionerId;
    $args['contain'] = false;
    $cephProvisionerTargetData = $cephProvisionerTarget -> find('first', $args);
    $cephClient = $cephProvisionerTarget -> cephClientFactory($cephProvisionerTargetData);
    $this->log("regenCephKey - looking up uid:" . json_encode($uid));
    $cephKeyring = $cephClient->getKeyring($uid[0]);
    $this->log("regenCephKey - cephKeyring found:" . json_encode($cephKeyring));
    $capsArray = $cephClient->formatKeyringToCapsArray($cephKeyring);
    $this->log("regenCephKey - result of formatKeyringToCapsArray:" . json_encode($capsArray));
    if (!empty($capsArray)) {
      if (!empty($curToken['CoServiceToken']['id'])) {
        $cephClient->removeEntity($uid[0]);
      }
      return $cephClient->getOrCreateKey($uid[0], $capsArray);
    }
  }
}
