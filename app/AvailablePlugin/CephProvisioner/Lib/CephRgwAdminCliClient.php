<?php
/**
 * COmanage Registry Ceph radosgw-admin cli client interface
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
App::uses('CephCli', 'CephProvisioner.Lib');
App::uses('CakeLog', 'Log');

class CephRgwAdminCliClient extends CephCli {

    protected $ceph = '/usr/bin/radosgw-admin';
    private $auth;

    // identifier in this context is not used, the class hard codes use of 'copersonid: XX' in the user display name field.
    // auth param options:  'rgw' or 'ldap'.  If LDAP is used then no keys are set in ceph-radosgw data 
    // and the user type is set to 'ldap' to trigger rgw lookup of LDAP credentials
    public function __construct($client_id, $cluster, $auth=CephClientEnum::Rgw, $options=array()) {
        parent::__construct($client_id, $cluster, null, $options);
        $this->auth = $auth;
    }

    // override parent ceph method to always return decoded json as associative array
    protected function ceph($op,$arrayOutput=false, $pipeInput=null) {
        $output = parent::ceph($op, $arrayOutput, $pipeInput);
        return json_decode($output, true);
    }

    public function getAuth() {
        return (int)$this->auth;
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

        $zg_data = $this->ceph("zonegroup placement list $zoneFlags");
        // decode json data into associative array 
        // $zg_data = json_decode($zg_json, true);

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

        $zp_data = $this->ceph('zone placement list $zoneFlags');
       
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

    public function getBucketList($userid) {
        // there is no  use case for listing every bucket (yet?)
        // it's a little dangerous because if there is programmer error that passes null userid then the list you get is unexpected (real story)
        //$uid_arg = ($userid == null) ? '' : "--uid=$userid";

        $uid_arg = "--uid=$userid";

        return $this->ceph("bucket list $uid_arg");
    }

    // link buckets with user if link param is true
    // unlink from user if link param is false

      /**
    * @param String: userid to work with
    * @param Array: list of buckets to link or unlink
    * @param Boolean:  Link buckets if true, unlink if false 
    **/

    public function linkBuckets($userid, $bucketlist, $link=true) {
        $action = ($link == true) ? 'link' : 'unlink';

        foreach ($bucketlist as $bucket) {
            $bstat = $this->ceph("bucket stats --bucket=$bucket");
            $bid = $bstat['id'];
            $this->ceph("bucket $action --bucket=$bucket --bucket-id=$bid --uid=$userid");
        }
    }

    /**
    * @return Array of user metadata (class methods all accept this directly and will encode to json for final use)
    **/

    public function addRgwUser($userid, $copersonid, $primaryUser=true, $accessKey=null, $secretKey=null) {

        $idattr = ($primaryUser) ? 'copersonid': 'cpownerid';

        // both required if specifying access-key
        if ($accessKey && $secretKey) { 
            $userKey = " --access-key='$accessKey' --secret-key='$secretKey'"; 
        } else {
            $userKey = '';
        }
        
        // creating the user with same access and secret specified will just return the metadata 
        // if the user exists but doesn't have the access/secret combo specified then it will be added and metadata returned
        // if user exists and we do not specify new/existing access combo then ceph will throw an error
        $md = $this->ceph("user create --display-name='$idattr:$copersonid' --uid='$userid' $userKey");
        
        /*  Optionally we could catch attempts to create same user 
        (I'd rather fail and get an error because this shouldn't be happening in current implementation)
        } catch (CephClientException $e) {
            if ($e->code == 17) {
                $md = $this->getUserMetadata($userid);
            } else { throw $e; }
        }
        */
        
        // if backend auth is ldap we want to remove the keys and set type
        if ($this->auth == CephClientEnum::RgwLdap) {
            $md['keys'] = array();
            $md['type'] = 'ldap';
            $this->setUserMetadata($userid, $md);
        }

        return $md;
    }

    /**
    * @param Array:  User metadata structure
    * @return String: Copersonid extracted from metadata, null if not found
    **/
    public function getCoPersonId($md) {

        if (!$this->verifyUserIdentifier($md)) {
            CakeLog::write('error', _txt('er.cephprovisioner.entity.arg'));
            return null;
        }

        $coPersonIdExp = explode(':', $md['display_name']);
          
        // identifier was found but our delimiter wasn't found or multiple were found, we should not ever have this happen
        if (count($coPersonIdExp) != 2) {
          throw new InternalErrorException(_txt('er.cocephprovisioner.rgw.copersonid'));
        } else {
          return $coPersonIdExp[1];
        }
    }

    public function isCoPersonSubuser($md) {
        return (strpos($md['display_name'], 'cpownerid:') === false)? false : true;
    }

    /** 
    * @param Array: User metadata structure
    * @return True if copersonid was found in expected attribute, false if not.
    * @throws InternalErrorException if the 'copersonid' identifier is not found where expected
    **/
    public function verifyUserIdentifier($md) {
        if (strpos($md['display_name'], 'copersonid:') === false && strpos($md['display_name'], 'cpownerid:') === false) {
            return false; 
        }
        return true;
    }

    public function deleteRgwUser($userid) {

        // this will introduce some overhead but a safety check is a good idea
        // userid deletes are not very frequent nor usually done in large batches
        $md = $this->getUserMetadata($userid);

        if (!$this->verifyUserIdentifier($md)) {
            CakeLog::write('error', _txt('er.cephprovisioner.entity.arg'));
            throw new InternalErrorException(_txt('er.cephprovisioner.entity'));
        }

        $this->ceph("user rm --uid=$userid");
    }

    /** 
    * @param Boolean:  enable user if true, suspend if false 
    */

    public function enableRgwUser($userid, $enable=true) {
        $action = $enable ? 'enable' : 'suspend';
        $this->ceph("user $action --uid=$userid");
    }

    /** 
    * This might be slow but we need to fetch metadata to limit this list to our managed users 
    * Whether you use this or getUserMetadata depends on if you already know the user you want or need a list to iterate
    * Including the metadata in this return avoids calls to getUserMetadata to fetch the same thing we fetched already
    * @param String:  Userid to fetch (null fetches all users).  
    * @return Array:  [ user => user meta ].  User metadata is an associative array which can be fed to setUserMetadata.
    */

    public function getRgwUsers() {
        $userList = $this->ceph("metadata list user");
        $returnList = array();
        foreach ($userList as $user) {
            $md = $this->getUserMetadata($user);
            if ($this->verifyUserIdentifier($md)) {
                $returnList[$user] = $md;
            }
        }
        return $returnList;
    }

    public function addUserKey($userid) {
        return $this->ceph("key create --uid=$userid  --gen-access-key --gen-secret");
    }

    public function removeUserKey($userid, $access_key) {
        return $this->ceph("key rm --uid=$userid --access-key=$access_key");
    }

    /**
    * @param A specific userid to fetch from radosgw. 
    * @return Array of [ userid => metadata assoc array ].  Most class methods accept the metadata array directly but will not unpack this array to get it.  
    **/

    public function getUserMetadata($userid) {
        
        try { 
            $data = $this->ceph("metadata get user:$userid");
        } catch (CephClientException $e) {
            if ($e->getCode() == 2) {
                return [];
            } 
            throw $e;
        }

        // $md = json_decode($data, true);
        return $data['data'];
    }

   /**
    * @param  String: user id to operate on
    * @param  Ceph RGW metadata structure.  Can be an array to be encoded to json or raw json.  Will automatically structure output from radosgw-admin if 'data' key is not found (only if metadata passed in as associative array)
    * @return Boolean:  true if metadata was set succesfully
    * @throws CephClientException if error 
    * @throws Exception metaData argument could not be encoded into json or is not valid json according to json_decode 
    **/

    public function setUserMetadata($userid, $metaData) {

        if (!$this->verifyUserIdentifier($metaData)) { 
            CakeLog::write('error', _txt('er.cephprovisioner.entity.arg'));
            return false; 
        }
        
        if (is_array($metaData)) { 
            // metadata put requires a json structure with key 'data' containing structure of metadata  
            if (!array_key_exists('data', $metaData)) {
                $md = array();
                $md['data'] = $metaData;
            } else {
                $md = $metaData;
            }
            $md = json_encode($md);
        } elseif (!json_decode($metaData)) {
            throw InternalErrorException("Invalid metadata structure passed to setUserMetadata");
        } else {
            $md = $metaData;
        }

        $this->ceph("metadata put user:$userid", false, $md);
        return true;
    }

    /**
    * set default placement tag for user
    * returns updated metadata (may be no change)
    **/

    public function setUserDefaultPlacement($userid, $tag, $md = null) {
        if (is_null($md)) {
            $md = $this->getUserMetadata($userid);
        }

        if (empty($md)) {
            CakeLog::write('error', "setUserDefaultPlacement: User '$userid' does not exist / empty metadata, cannot add placement tag");
            return $md;
        }

        if (!in_array($tag, $md['placement_tags'])) {
            CakeLog::write('error', "setUserDefaultPlacement: Tag '$tag' not in placement tags, cannot set default for '$userid'");
            return $md;  
        }

        if ($md['default_placement'] == $tag) {
            CakeLog::write('debug', "User $userid has default placement already set to '$tag' in metadata");
            return $md;
        } else {
            $md['default_placement'] = "$tag";
            $this->setUserMetadata($userid, $md); 
        }

        return $md;
    }

   /**
    * Add placement tag to ceph rgw user - for single tag
    * (I don't think we actually use this because we want to batch instead of calling set 
    * multiple times, but it might still have an application for a single-tag update)
    *
    * @param  String: user id to operate on
    * @param  String: Placement tag to add to user id metadata
    * @param  Array[optional]: RGW user metadata object to avoid lookup if previously obtained
    * @return Boolean: true if tag added or exists, false if error
    * @throws CephClientException if error looking up or creating user metadata
    * @throws RuntimeException if no metadata found even after supposedly creating
    **/

    public function addUserPlacementTag($userid, $tag, $md = null) {

        if (is_null($md)) {
            $md = $this->getUserMetadata($userid);
        }

        if (empty($md)) {
            CakeLog::write('error', "addUserPlacementTag: User does not exist, cannot add placement tag: $userid");
            return false;  
        }

        $tagsCurrent = $md['placement_tags'];

        if (in_array($tag, $tagsCurrent)) {
            CakeLog::write('debug', "User $userid exists and has tag $tag in metadata");
        } else {
            CakeLog::write('debug', "Adding tag $tag to metadata for user $userid");
            $md['placement_tags'][] = "$tag";
            $this->setUserMetadata($userid, $md);
         }
    }

    /**
    * @param  String:  user id to operate on
    * @param  String: placement tag to remove from user metadata.  Also removes from default placement.
    * @param  Array[optional]: RGW user metadata object to avoid lookup if previously obtained
    * @return Boolean: true if tag deleted, false if no action required
    * @throws CephClientException if error looking up or creating user metadata
    * @throws RuntimeException if no metadata found even after supposedly creating
    **/

    public function removeUserPlacementTag($userid, $tag, $md = null) {

        if (is_null($md)) {
            $md = $this->getUserMetadata($userid);
        }

        if (empty($md)) {
            CakeLog::write('info', "removeUserPlacementTag: User does not exist, cannot remove placement tag: $userid");
            return false;  
        }

        $tagsCurrent = $md['placement_tags'];

        if (in_array($tag, $tagsCurrent)) {
            CakeLog::write('info', "User $userid exists and has tag $tag in metadata - removing tag");
            Hash::remove($md['placement_tags'], "$tag");
            Hash::remove($md['default_placement'], "$tag");
            $this->setUserMetadata($userid, $md);
            return true;
        } else {
            CakeLog::write('info', "removeUserPlacementTag: User $userid does not have tag $tag");
            return false;
        }
    }
    

    /**
    * @return Boolean true if targets were removed, false if no action required
    * @throws CephClientException
    **/

    public function removePlacementTarget($dataPool, $zonegroup='default', $zone='default') {
        $zoneFlags = "--rgw-zonegroup=$zonegroup --rgw-zone=$zone";

        $zp_data = $this->ceph('zone placement list $zoneFlags');
        // $zp_data = json_decode($zp_json,true);
        $removeKey = false;

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

// ref:  gen new secret key for user:  
# radosgw-admin key create --uid=user  --gen-access-key --gen-secret
# radosgw-admin key rm --uid=user  --access-key=HUES7OY


}
