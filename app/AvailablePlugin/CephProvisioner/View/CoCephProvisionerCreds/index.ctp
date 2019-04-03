<?php
/**
 * COmanage Registry Ceph Provisioner Creds index
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
 * @package       registry
 * @since         COmanage Registry v3.2.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */


  // Globals
  global $cm_lang, $cm_texts, $cm_ceph_provisioner_texts;

  // script with keyDownload function to download client key data blob
  print $this->Html->script('CephProvisioner.script.js');
  // style additions / over-rides
  print $this->Html->css('CephProvisioner.style.css');

  // Add breadcrumbs
  print $this->element("coCrumb");
  
  $this->Html->addCrumb(_txt('ct.co_ceph_provisioner_creds.pl'));

  // Add page title
  $params = array();
  $params['title'] = _txt('ct.co_ceph_provisioner_creds.pl');

  // Add top links
  $params['topLinks'] = array();
  
  print $this->element("pageTitleAndButtons", $params);
  
  // to trigger input rows, etc on first instance of given key types
  $first = array();

?>

<table id="co_ceph_provisioner_creds" class="ui-widget">
  <thead>
    <tr class="ui-widget-header">
      <th style="width: 15%;"><?php print $this->Paginator->sort('CoCephProvisionerCreds.type', _txt('fd.description')); ?></th>
      <th><?php print _txt('ct.co_ceph_provisioner_creds.id'); ?></th>
      <th><?php print _txt('ct.co_ceph_provisioner_creds.cred'); ?></th>
    </tr>
  </thead>
  
  <tbody>
    <?php $i = 0; ?>
    <?php foreach ($co_ceph_provisioner_creds as $c):  ?>
      <tr class="line<?php print ($i % 2)+1; ?>">
        <td>
        <?php
            // will re-use this later when creating an input row
            $row_desc = filter_var($cm_ceph_provisioner_texts[$cm_lang]['ct.co_ceph_provisioner_creds.desc'][$c['CoCephProvisionerCred']['type']], FILTER_SANITIZE_SPECIAL_CHARS);
            print $row_desc;

        ?>
        </td>
        <td>
        <?php 

          // define strings formatting each credential type and the actions that are relevant
          // if a given action is not relevant leave it defined as empty string
          $userid_add = '';
          $userid_remove = '';
          $cred_regen = '';
          $cred_add = '';
          $cred_remove = ''; 
          $cred_regen = '';
          $input_row = '';
          $placement_row = '';
          $placement_button = '';
          $download_title = 'Download Credential File';
          $regen_title = 'Regenerate Key';

          // there are only two possibilities here
          $cred_type = ($c['CoCephProvisionerCred']['type'] == CephClientEnum::Cluster) ? 'cluster' : 'rgw';
          $cred_regen_common = '<a href="nothing" aria-label="Regenerate Key" title="Regenerate Key"><i class="fa fa-refresh fa-2x" aria-hidden="true"></i></a>';

          if ($cred_type == 'cluster') {
            $userid = filter_var($c['CoCephProvisionerTarget']['ceph_user_prefix'], FILTER_SANITIZE_SPECIAL_CHARS)
                      . '.'
                      . filter_var($c['CoCephProvisionerCred']['userid'], FILTER_SANITIZE_SPECIAL_CHARS);

            // setup keyring formatting for display and download
            $credential = '[client.' 
                          . filter_var($c['CoCephProvisionerTarget']['ceph_user_prefix'])
                          . '.' 
                          . filter_var($c['CoCephProvisionerCred']['userid'], FILTER_SANITIZE_SPECIAL_CHARS)
                          . '] <br />' 
                          . '&nbsp;&nbsp;&nbsp;key = ' 
                          . filter_var($c['CoCephProvisionerCred']['secret'], FILTER_SANITIZE_SPECIAL_CHARS);
        
            $credential_plain = '[client.' 
                                . $c['CoCephProvisionerTarget']['ceph_user_prefix'] 
                                . '.' 
                                . $c['CoCephProvisionerCred']['userid']
                                . ']\n'
                                . '\tkey = ' 
                                . $c['CoCephProvisionerCred']['secret'];

            // setup download onclick link
            $download = "keyDownload('$credential_plain','ceph.client." 
                        . $c['CoCephProvisionerTarget']['ceph_user_prefix'] 
                        . '.' 
                        . $c['CoCephProvisionerCred']['userid']
                        . ".keyring')";

             $regen_key_url = $this->Html->url(             
              array(
                'plugin'       => 'ceph_provisioner',
                'controller'   => 'co_ceph_provisioner_creds',
                'action'       => 'credop',
                'op'           => 'regen_cephkey',
                'credid'       => $c['CoCephProvisionerCred']['id'],
                'copersonid'   => $this->request->params['named']['copersonid'],
              ));  


            $cred_regen =  "<a href='#' onclick='javascript: js_confirm_generic(\"" 
                          . _txt('pl.cephprovisioner.ceph.newkey.confirm') . "\", \"$regen_key_url\")' " 
                          . "aria-label='$regen_title' title='$regen_title'>"
                          . '<i class="fa fa-refresh fa-2x" aria-hidden="true"></i></a>';
          } 

          if ($cred_type == 'rgw') {
            // we don't store the co person identifier in the credentials database but we should
            // use it here for user id because that is what you would need for S3 ACL
            $userid_add_title = "Add New S3 Userid";
            $userid_remove_title = "Remove S3 Userid";
            $key_add_title = 'Add new S3 access key';
            $key_remove_title = 'Remove S3 access key';
            $placement_title = 'Default Bucket Placement';
            $userid = filter_var($c['CoCephProvisionerCred']['identifier'], FILTER_SANITIZE_SPECIAL_CHARS);
            $credential = 'access_key: '
                          . filter_var($c['CoCephProvisionerCred']['userid'], FILTER_SANITIZE_SPECIAL_CHARS)
                          . '<br />'
                          . 'secret_key: ' 
                          . filter_var($c['CoCephProvisionerCred']['secret'], FILTER_SANITIZE_SPECIAL_CHARS);
                          
            $credential_plain = '# Boto config file ~/.aws/config \n'
                                . '[default]\n'
                                . 'aws_access_key_id=' . $c['CoCephProvisionerCred']['userid'] . '\n'
                                . 'aws_secret_access_key=' . $c['CoCephProvisionerCred']['secret'] . '\n';

            // download in format suitable to save as `/.aws/config
            $download = "keyDownload('$credential_plain', 'config')";

            $add_key_url = $this->Html->url(             
              array(
                'plugin'       => 'ceph_provisioner',
                'controller'   => 'co_ceph_provisioner_creds',
                'action'       => 'credop',
                'op'           => 'new_rgwkey',
                'credid'       => $c['CoCephProvisionerCred']['id'],
                'copersonid'   => $this->request->params['named']['copersonid'],
              )); 

            $remove_key_url = $this->Html->url(             
              array(
                'plugin'       => 'ceph_provisioner',
                'controller'   => 'co_ceph_provisioner_creds',
                'action'       => 'credop',
                'op'           => 'remove_rgwkey',
                'credid'       => $c['CoCephProvisionerCred']['id'],
                'copersonid'   => $this->request->params['named']['copersonid'],
              ));

            $regen_key_url = $this->Html->url(             
              array(
                'plugin'       => 'ceph_provisioner',
                'controller'   => 'co_ceph_provisioner_creds',
                'action'       => 'credop',
                'op'           => 'regen_rgwkey',
                'credid'       => $c['CoCephProvisionerCred']['id'],
                'copersonid'   => $this->request->params['named']['copersonid'],
              ));  

            $remove_userid_url = $this->Html->url(
              array(
                'plugin'       => 'ceph_provisioner',
                'controller'   => 'co_ceph_provisioner_creds',
                'action'       => 'credop',
                'op'           => 'remove_rgwuser',
                'credid'       => $c['CoCephProvisionerCred']['id'],
                'copersonid'   => $this->request->params['named']['copersonid'],
              ));
            
            if (!array_key_exists($cred_type, $first)) { 
              $first[$cred_type] = true;

              $input_row =  '<tr id="rgw_input_row" style="display:none;">
                            <td>Enter New Id: </td>'
                          . '<td colspan="2">'
                          . $this->Form->create("AddRgwUserid",
                            array('url' => array('pvid' => $c['CoCephProvisionerTarget']['id'], 'copersonid' => $this->request->params['named']['copersonid'], 'controller' => 'co_ceph_provisioner_creds', 'action' => 'credop'), 'inputDefaults' => array('style' => 'display: inline-block; margin-right: 20px', 'label' => false, 'div' => false)))
                          . $this->Form->Input('rgw_new_userid')
                          . '<a href="#" id="newuser" aria-label="Confirm User ID" title="Confirm User ID" onclick="validateAndSubmitUser(\'' 
                          . _txt('pl.cephprovisioner.rgw.newid.confirm') . '\');">' 
                          . '<i class="fa fa-user-plus fa-2x" aria-hidden="true"></i></a>'
                          . '<span style="margin-left: 10px;" id="newuser_validate" class="required"></span>'
                          . $this->Form->end()
                          . '</td></tr>';

              $userid_add = "<a onclick=\"$('#rgw_input_row').toggle('fade');\""
                            . "href='#' id='#rgw_newid' aria-label='$userid_add_title' title='$userid_add_title'>"
                            . '<i class="fa fa-plus-circle fa-2x" aria-hidden="true"></i></a>';
                            
            } elseif (!array_key_exists($userid, $first)) {
              $userid_remove ="<a href='#' onclick='javascript: js_confirm_generic(\"" 
                            . _txt('pl.cephprovisioner.rgw.rmid.confirm') . ": $userid ?\", \"$remove_userid_url\")' "
                            . " id='#rgw_rmid' aria-label='$userid_remove_title' title='$userid_remove_title'>"
                            . '<i class="fa fa-minus-circle fa-2x" aria-hidden="true"></i></a>';
             
            }

            // only print access key remove button if this is not the first userid row, and only print add button if it is the first row 
            if (!array_key_exists($userid, $first)) {
              $first[$userid] = true;

              $currentPlacement = Hash::extract($co_ceph_provisioner_data_placements,  "{n}.CoCephProvisionerDataPlacement[identifier=$userid].placement");

              $placement_button =  "<a onclick=\"$('#rgw_placement_row_$userid').toggle('fade');\""
                            . "href='#' id='#rgw_placement' aria-label='$placement_title' title='$placement_title'>"
                            . '<i class="fa fa-archive fa-2x" aria-hidden="true"></i></a>';

               $placement_row = "<tr id='rgw_placement_row_$userid' style='display:none;'>"
                          . '<td>Default Bucket Data Placement: </td>'
                          . '<td colspan="2">'
                          . $this->Form->create("ChangeBucketPlacement",
                            array('url' => array('credid' => $c['CoCephProvisionerCred']['id'],'pvid' => $c['CoCephProvisionerTarget']['id'], 'copersonid' => $this->request->params['named']['copersonid'], 'controller' => 'co_ceph_provisioner_creds', 'action' => 'credop'), 'inputDefaults' => array('style' => 'display: inline-block; margin-right: 20px', 'label' => false, 'div' => false)))
                          . $this->Form->Input('rgw_placement', array('options' => $vv_cou_options, 'selected' => $currentPlacement))
                          . '<a href="#" id="placement" aria-label="Set Data Placement" title="Set Data Placement" onclick="validateAndSubmitPlacement(\'' 
                          . _txt('pl.cephprovisioner.rgw.placement.confirm') . '\',\'' . $userid . '\');">' 
                          . '<i class="fa fa-check-square fa-2x" aria-hidden="true"></i></a>'
                          . $this->Form->end();

              $cred_add = "<a href='#' onclick='javascript: js_confirm_generic(\"" 
                        . _txt('pl.cephprovisioner.rgw.newkey.confirm') . ': ' . $userid . " ?\", \"$add_key_url\")' " 
                        . "aria-label='$key_add_title' title='$key_add_title'>"
                        . "<i class='fa fa-plus-circle fa-2x' aria-hidden='true'></i></a>";
              
              $cred_regen = "<a href='#' onclick='javascript: js_confirm_generic(\"" 
                          . _txt('pl.cephprovisioner.rgw.regenkey.confirm') . "\", \"$regen_key_url\")' " 
                          . "aria-label='$regen_title' title='$regen_title'>"
                          . '<i class="fa fa-refresh fa-2x" aria-hidden="true"></i></a>';
                            
            } else {
              $cred_remove = "<a href='#' onclick='javascript: js_confirm_generic(\"" 
                            . _txt('pl.cephprovisioner.rgw.rmkey.confirm') . " from $userid: " . filter_var($c['CoCephProvisionerCred']['userid'], FILTER_SANITIZE_SPECIAL_CHARS) . " ?\", \"$remove_key_url\")' "
                            . "aria-label='$key_remove_title' title='$key_remove_title'>"
                            . "<i class='fa fa-minus-circle fa-2x' aria-hidden='true'></i></a>";
            }
          }  // if cred_type == rgw
          ?> 
          <div class='cred-actions'>
          <?php print $userid_add; ?>
          <?php print $userid_remove; ?>
          <?php print $placement_button; ?>
        </div>
          <?php print $userid; ?>
        </td>
        <td>
          <div class="cred-actions">
            <a href="#" aria-label="<?php print $download_title;?>" title="<?php print $download_title;?>" onclick="<?php print $download;?>"><i class="fa fa-download fa-2x" aria-hidden="true"></i></a>
  
            <?php
            print $cred_regen; 
            print $cred_add;
            print $cred_remove; 
            // there is a good reason why the td below is not on a newline - extra spaces after credential may be interpreted as a space and be included in a copy/paste (firefox)
            ?>
          </div>
            <?php print $credential; ?></td>
      </tr>
      <?php 
      // may be empty
      print $input_row;
      print $placement_row;
      $i++; 
      endforeach; 
      ?>
  </tbody>
  <tfoot>
    <tr class="ui-widget-header">
      <th colspan="3">
      </th>
    </tr>
  </tfoot>
</table>

<?php 
$help_include = APP . WEBROOT_DIR . DS . 'local-resources' . DS . 'CephProvisionerCredHelp.html';
if(is_readable($help_include)) {
  include($help_include);
}
?>
