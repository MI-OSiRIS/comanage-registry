<?php
/**
 * COmanage Registry CO Ceph Provisioning Target Fields
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
 * @since         COmanage Registry v0.8.3
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

  // Determine if fields are editable
  $e = false;
  
  if(($this->action == "add" && $permissions['add']) || ($this->action == "edit" && $permissions['edit']))
    $e = true;
    
  // We shouldn't get here if we don't have at least read permission, but check just in case
  
  if(!$e && !$permissions['view'])
    return false;
  
  print $this->Form->hidden('co_id', array('default' => $cur_co['Co']['id'])) . "\n";
  print $this->Form->hidden('co_provisioning_target_id', array('default' => $vv_ptid)) . "\n";
?>
 
<table id="<?php print $this->action; ?>_co_ceph_provisioner_target" class="ui-widget">
  <tbody>
    <tr class="line1">
      <td>
        <?php print _txt('pl.cephprovisioner.rgw_url'); ?><font class="required">*</font><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.rgw_url.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('rgw_url', array('size' => 50)) : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['rgw_url'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>
     <tr class="line2">
      <td>
        <?php print _txt('pl.cephprovisioner.ceph_client_name'); ?><font class="required">*</font><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.ceph_client_name.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('ceph_client_name', array('size' => 50)) : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['ceph_client_name'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>
    <tr class="line1">
      <td>
        <?php print _txt('pl.cephprovisioner.opt_rgw_admin_api'); ?><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.opt_rgw_admin_api.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('opt_rgw_admin_api', array()) : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['opt_rgw_admin_api'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>
    <tr class="line2">
      <td>
        <?php print _txt('pl.cephprovisioner.opt_ceph_admin_api'); ?><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.opt_ceph_admin_api.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('opt_ceph_admin_api', array()) : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['opt_ceph_admin_api'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>
    <tr class="line1">
      <td>
        <?php print _txt('pl.cephprovisioner.rgw_admin_api_url'); ?><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.rgw_admin_api_url.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('rgw_admin_api_url', array('size' => 50)) : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['rgw_admin_api_url'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>
    <tr class="line2">
      <td>
        <?php print _txt('pl.cephprovisioner.ceph_admin_api_url'); ?><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.ceph_admin_api_url.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('ceph_admin_api_url', array('size' => 50)) : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['ceph_admin_api_url'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>
    <tr class="line1">
      <td>
        <?php print _txt('pl.cephprovisioner.ceph_cluster'); ?><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.ceph_cluster.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('ceph_cluster', array('size' => 20)) : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['ceph_cluster'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>
    <tr class="line2">
      <td>
        <?php print _txt('pl.cephprovisioner.ceph_config_file'); ?><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.ceph_config_file.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('ceph_config_file', array('size' => 40)) : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['ceph_config_file'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>
    <tr class="line1">
      <td>
        <?php print _txt('pl.cephprovisioner.rgw_access_key'); ?><font class="required">*</font><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.rgw_access_key.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('rgw_access_key', array('size' => 50)) : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['rgw_access_key'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>
     <tr class="line2">
      <td>
        <?php print _txt('pl.cephprovisioner.rgw_secret_key'); ?><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.rgw_secret_key.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('rgw_secret_key', array('size' => 50)) : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['rgw_secret_key'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>
     <tr class="line1">
      <td>
        <?php print _txt('pl.cephprovisioner.opt_create_cou_data_pools'); ?><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.opt_create_cou_data_pools.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('opt_create_cou_data_pools') : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['opt_create_cou_data_pools'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>
     <tr class="line2">
      <td>
        <?php print _txt('pl.cephprovisioner.cou_data_pool_pgcount'); ?><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.cou_data_pool_pgcount.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('cou_data_pool_pgcount', array('size' => 4)) : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['cou_data_pool_pgcount'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>
     <tr class="line1">
      <td>
        <?php print _txt('pl.cephprovisioner.opt_posix_lookup_ldap'); ?><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.opt_posix_lookup_ldap.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('opt_posix_lookup_ldap', array()) : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['opt_posix_lookup_ldap'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>
     <tr class="line2">
      <td>
        <strong class="fieldTitle"><?php print _txt('pl.cephprovisioner.ldap_target'); ?></strong><font class="required">*</font><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.ldap_target.desc'); ?></font>
      </td>
      <td>
        <?php
          $attrs = array();
          $attrs['empty'] = false;
          
          print $this->Form->select('co_ldap_provisioner_target_id',
                                    $vv_ldap_provisioners,
                                    $attrs);

          if($this->Form->isFieldError('co_ldap_provisioner_target_id')) {
            print $this->Form->error('co_ldap_provisioner_target_id');
          }
        ?>
      </td>
    </tr>
    <tr class="line1">
      <td>
        <strong class="fieldTitle"><?php print _txt('pl.cephprovisioner.grouper_target'); ?></strong><font class="required">*</font><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.grouper_target.desc'); ?></font>
      </td>
      <td>
        <?php
          $attrs = array();
          $attrs['empty'] = false;
          
          print $this->Form->select('co_grouper_provisioner_target_id',
                                    $vv_grouper_provisioners,
                                    $attrs);

          if($this->Form->isFieldError('co_grouper_provisioner_target_id')) {
            print $this->Form->error('co_grouper_provisioner_target_id');
          }
        ?>
      </td>
    </tr>
     <tr class="line2">
      <td>
        <?php print _txt('pl.cephprovisioner.rgw_user_separator'); ?><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.rgw_user_separator.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('rgw_user_separator', array('size' => 4, 'default' => '_')) : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['rgw_user_separator'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>
     <tr class="line1">
      <td>
        <?php print _txt('pl.cephprovisioner.ceph_user_prefix'); ?><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.ceph_user_prefix.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('ceph_user_prefix', array('size' => 25, 'default' => 'comanage')) : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['ceph_user_prefix'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>

     <tr class="line2">
      <td>
        <?php print _txt('pl.cephprovisioner.opt_create_cou_data_dir'); ?><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.opt_create_cou_data_dir.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('opt_create_cou_data_dir') : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['opt_create_cou_data_dir'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>

  <tr class="line1">
      <td>
        <?php print _txt('pl.cephprovisioner.ceph_fs_mountpoint'); ?><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.ceph_fs_mountpoint.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('ceph_fs_mountpoint', array('size' => 25)) : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['ceph_fs_mountpoint'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>

     <tr class="line2">
      <td>
        <?php print _txt('pl.cephprovisioner.ceph_fs_name'); ?><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.ceph_fs_name.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('ceph_fs_name', array('size' => 25)) : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['ceph_fs_name'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>

     <tr class="line1">
      <td>
        <?php print _txt('pl.cephprovisioner.opt_mds_cap_uid'); ?><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.opt_mds_cap_uid.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('opt_mds_cap_uid') : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['opt_mds_cap_uid'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>

     <tr class="line2">
      <td>
        <?php print _txt('pl.cephprovisioner.opt_mds_cap_idmap'); ?><br />
        <font class="desc"><?php print _txt('pl.cephprovisioner.opt_mds_cap_idmap.desc'); ?></font>
      </td>
      <td>
        <?php print ($e ? $this->Form->input('opt_mds_cap_idmap') : filter_var($co_ceph_provisioner_targets[0]['CoCephProvisionerTarget']['opt_mds_cap_idmap'],FILTER_SANITIZE_SPECIAL_CHARS)); ?>
      </td>
    </tr>
      <td>
        <i><font class="required"><?php print _txt('fd.req'); ?></font></i><br />
      </td>
      <td>
        <?php
          if($e) {
            print $this->Form->submit($submit_label);
            print '<br />' . $this->Form->button(_txt('op.reset'), 
                                      array('type'=>'reset'));
          }
        ?>
      </td>
    </tr>
  </tbody>
</table>

