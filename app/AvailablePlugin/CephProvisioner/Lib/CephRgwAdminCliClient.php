<?php
/**
 * COmanage Registry Ceph radosgw-admin cli client interface
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


// NOTE:  Both components of username are used by CephProvisioner so this implementation
// does not strip out or automatically add any components based on the 'identifier' class var
// however it is used to limit which users are returned by listRgwUsers and also is checked 
// for existence in params sent to add/delete functions

App::uses('CephClientException', 'CephProvisioner.Lib');
App::uses('CephCli', 'CephProvisioner.Lib');
App::uses('CakeLog', 'Log');

class CephRgwAdminCliClient extends CephCli {

    protected $ceph = '/usr/bin/radosgw-admin';

     public function __construct($client_id, $cluster, $identifier = '_', $options=array()) {
        parent::__construct($client_id, $cluster, $identifier, $options);
    }

    /** 
    *
    * @param String: Placement target name
    * @param Array: Placement target tags 
    * @param String: Data pool name s
    * @param String: Index pool name
    * @param String: Non-ec pool name
    * @param String: RGW zonegroup
    * @param String: RGW zone
    * @param 
    * @return true if successful update, false if no change required.  Exception if error.  
    *
    *  Notes:  
    *  In theory this function can deal with zones and zonegroups.  In reality we use it always with the 
    *  default zone and we use the same data extra and index pools for every placement target.  
    **/

    public function addPlacementTarget($target, $tags, $dataPool, $indexPool='default.rgw.buckets.index', $dataExtraPool='default.rgw.buckets.non-ec', $zonegroup='default', $zone='default') {
        
        $zoneFlags = "--rgw-zonegroup=$zonegroup --rgw-zone=$zone";

        if(!is_array($tags)) { $tags = (array) $tags; }
        $taglist = implode(',',$tags);

        $zg_json = $this->ceph("zonegroup placement list $zoneFlags");
        // decode json data into associative array 
        $zg_data = json_decode($zg_json, true);

        $placementTags = Hash::extract($zg_data, "{n}[key=$target].val.tags.{n}");

        if (!empty($placementTags)) {
            // placement target exists and has same tags, no change
            if ($placementTags == $tags) {
                CakeLog::write('debug', "Placement target $target exists with same tags '$taglist'");
            } else {
                CakeLog::write('debug', "Placement target $target exists, updating tags to '$taglist'");
                $this->ceph("zonegroup placement modify --placement-id=$target --tags=$taglist  $zoneFlags");
            } 
        } else {
            CakeLog::write('debug', "Adding new placement target $target with tags '$taglist'");
            $this->ceph("zonegroup placement add --placement-id=$target --tags=$taglist  $zoneFlags");
        }

        $zp_json = $this->ceph('zone placement list $zoneFlags');
        $zp_data = json_decode($zp_json,true);

        $zonePlacement = Hash::extract($zp_data, "{n}[key=$target].val.data_pool");

        if (!empty($zonePlacement)) {
            if($zonePlacement[0] == $dataPool) {
                CakeLog::write('info', "Zone placement key $target exists and has same data pool $dataPool");
            } else {
                CakeLog::write('info', "Zone placement key $target exists, updating data pool to $dataPool");
                $this -> ceph("zone placement modify --placement-id=$target --data-pool=$dataPool $zoneFlags");
            }
        } else {
            CakeLog::write('info', "Zone placement key $target does not exist, adding with data pool $dataPool");
            $this -> ceph("zone placement add --placement-id=$target --data-pool=$dataPool --index-pool=$indexPool --data-extra-pool=$dataExtraPool $zoneFlags");
        }   

        $this->ceph("period update --commit $zoneFlags");

        return true;
    }

    public function getUserMetadata($userid) {
        if (strpos($userid, $this->identifier) === false) { 
            throw new RuntimeException(_txt('er.cephprovisioner.entity.arg'));
        }

        try { 
            $data = $this->ceph("metadata get user:$userid");
        } catch (CephClientException $e) {
            if ($e->code == 2) {
                return [];
            } 
            throw $e;
        }

        return json_decode($data, true);
    }

    /**
    * @return Array of user metadata (class methods all accept this directly and will encode to json for final use)
    **/

    public function addRgwUser($userid) {

        if (strpos($userid, $this->identifier) === false) { 
            throw new RuntimeException(_txt('er.cephprovisioner.entity.arg'));
        }

        // add user so we can set meta-data but remove user keys (auth is from ldap tokens)
        $this->ceph("user create --display-name=$userid --uid=$userid");
        // user create outputs metadata but the structure is different from output of 
        // metadata get so we'll retrieve in a separate op to stay consistent
        $md = $this->getUserMetadata($userid);
        $md['data']['keys'] = array();
        $md['data']['type'] = 'ldap';
        $this->setUserMetadata($userid, $md);
        return $md;
    }

    public function deleteRgwUser($userid) {

        if (strpos($userid, $this->identifier) === false) { 
            throw new RuntimeException(_txt('er.cephprovisioner.entity.arg'));
        }

        $this->ceph("user rm --uid=$userid");
    }

    public function listRgwUsers() {
        $userList = json_decode($this->ceph("metadata list user"), true);
        $returnList = array();
        foreach ($userList as $user) {
            if (strpos($user, $this->identifier) !== false) {
                $returnList[] = $user;
            }
        }
        return $returnList;
    }

   /**
    * @param  String: user id to operate on
    * @param  Ceph RGW metadata structure.  Can be an array to be encoded to json or raw json.
    * @return Boolean:  true if metadata was set succesfully
    * @throws CephClientException if error 
    * @throws Exception metaData argument could not be encoded into json or is not valid json according to json_decode 
    **/

    public function setUserMetadata($userid, $metaData) {

        if (strpos($userid, $this->identifier) === false) { 
            throw new RuntimeException(_txt('er.cephprovisioner.entity.arg'));
        }

        if (is_array($metaData)) { 
            $md = json_encode($metaData);
        } elseif (!json_decode($metaData)) {
            throw Exception("Invalid metadata structure passed to setUserMetadata");
        } else {
            $md = $metaData;
        }

        $this->ceph("metadata put user:$userid", false, $md);
        return true;
    }

   /**
    * Add placement tags to ceph rgw user, create user if not exist
    *
    * @param  String: user id to operate on
    * @param  String: Placement tag to add to user id metadata
    * @return Boolean: true if tag added, false if no action required
    * @throws CephClientException if error looking up or creating user metadata
    * @throws RuntimeException if no metadata found even after supposedly creating
    **/

    public function addUserPlacementTag($userid, $tag) {

        if (strpos($userid, $this->identifier) === false) { 
            throw new RuntimeException(_txt('er.cephprovisioner.entity.arg'));
        }

        if (!in_array($userid, $this->listRgwUsers())) {
            CakeLog::write('info', "Adding user to RGW: $userid");
            $md = $this->addRgwUser($userid);  
        } else {
            $md = $this->getUserMetadata($userid);
        }

        if (!empty($md)) {
            $tagsCurrent = $md['data']['placement_tags'];
            if (in_array($tag, $tagsCurrent)) {
                CakeLog::write('info', "User $userid exists and has tag $tag in metadata");
                return false;
            } else {
                CakeLog::write('info', "Adding tag $tag to metadata for user $userid");
                $md['data']['placement_tags'][] = "$tag";
                $md['data']['default_placement'] = "$tag";
                $this->setUserMetadata($userid, $md);
                return true;
            }
        } else {
            throw RuntimeException(_txt('er.cephprovisioner.rgw.user_metadata') . $userid);
        }
    }

    /**
    * @param  String:  user id to operate on
    * @param  String: placement tag to remove from user metadata.  Also removes from default placement.
    * @return Boolean: true if tag deleted, false if no action required
    * @throws CephClientException if error looking up or creating user metadata
    * @throws RuntimeException if no metadata found even after supposedly creating
    **/

    // this is probably obsolete since users now only have one placement tag and are removed if no longer part of COU
    public function removeUserPlacementTag($userid, $tag) {

        if (strpos($userid, $this->identifier) === false) { 
            throw new RuntimeException(_txt('er.cephprovisioner.entity.arg'));
        }

        if (!in_array($userid, $this->listRgwUsers())) {
            CakeLog::write('info', "removeUserPlacementTag: User does not exist, cannot remove placement tag: $userid");
            return false;  
        } else {
            $md = $this->getUserMetadata($userid);
        }

        if (!empty($md)) {
            $tagsCurrent = $md['data']['placement_tags'];
            if (in_array($tag, $tagsCurrent)) {
                CakeLog::write('info', "User $userid exists and has tag $tag in metadata - removing tag");
                Hash::remove($md['data']['placement_tags'], "$tag");
                Hash::remove($md['data']['default_placement'], "$tag");
                $this->setUserMetadata($userid, $md);
                return true;
            } else {
                CakeLog::write('info', "removeUserPlacementTag: User $userid does not have tag $tag");
                return false;
            }
        } else {
            throw RuntimeException(_txt('er.cephprovisioner.rgw.user_metadata') . $userid);
        }
    }

    /**
    * @return Boolean true if targets were removed, false if no action required
    * @throws CephClientException
    **/

    public function removePlacementTarget($dataPool, $zonegroup='default', $zone='default') {
        $zoneFlags = "--rgw-zonegroup=$zonegroup --rgw-zone=$zone";

        $zp_json = $this->ceph('zone placement list $zoneFlags');
        $zp_data = json_decode($zp_json,true);
        $removeKey = false;

        // I can't figure out how to make Hash::extract do this
        foreach ($zp_data as $zp) {
            if ($zp['val']['data_pool'] == $dataPool) {
                $removeKey = $zp['key'];
                break;
            }
        }

        if ($removeKey) {
            CakeLog::write('info', "Removing RGW zone and zonegroup placement id $removeKey");
            $this->ceph("zone placement rm --placement-id=$removeKey");
            $this->ceph("zonegroup placement rm --placement-id=$removeKey $zoneFlags");
            $this->ceph("period update --commit $zoneFlags");
            return true;
        } 

        return false;
    }




}
