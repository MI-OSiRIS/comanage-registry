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
 
<div class="ui-state-highlight ui-corner-all" style="margin-top: 20px; padding: 0 .7em;"> 
  <p>
    <span class="ui-icon ui-icon-info" style="float: left; margin-right: .3em;"></span>
    <strong><?php print _txt('pl.cephprovisioner.info'); ?></strong>
  </p>
</div>
<br />
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
      <td>
        <i><font class="required"><?php print _txt('fd.req'); ?></font></i><br />
      </td>
      <td>
        <?php
          if($e) {
            print $this->Form->submit($submit_label);
            print $this->Form->button(_txt('op.reset'), 
                                      array('type'=>'reset'));
          }
        ?>
      </td>
    </tr>
  </tbody>
</table>


