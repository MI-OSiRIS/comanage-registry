<?php
/**
 * COmanage Registry CO Service Tokens Index View
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
 * @since         COmanage Registry v2.0.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

  // Add breadcrumbs
  print $this->element("coCrumb");

  $this->Html->addCrumb(_txt('ct.co_service_tokens.pl'));

  // Add page title
  $params = array();
  $params['title'] = _txt('ct.co_service_tokens.pl');

  // Add top links
  $params['topLinks'] = array();

  print $this->element("pageTitleAndButtons", $params);

  // Determine which services have tokens set
  $tokensSet = Hash::extract($co_service_tokens, '{n}.CoServiceToken.co_service_id');

?>

<table id="co_service_tokens" class="ui-widget" style="table-layout: fixed;">
  <thead>
    <tr class="ui-widget-header">
      <th style="width: 20%;"><?php print $this->Paginator->sort('CoService.name', _txt('fd.name')); ?></th>
      <th style="width: 60%;"><?php print _txt('fd.token'); ?></th>
      <th style="width: 20%;"><?php print _txt('fd.actions'); ?></th>
    </tr>
  </thead>

  <tbody>
    <?php $i = 0; ?>
    <?php foreach ($vv_co_services as $c): ?>
    <tr class="line<?php print ($i % 2)+1; ?>">
      <td>
        <?php
          if(!empty($c['CoService']['service_url'])) {
            print $this->Html->link(
              $c['CoService']['name'],
              $c['CoService']['service_url']
            );
          } else {
            print filter_var($c['CoService']['name'], FILTER_SANITIZE_SPECIAL_CHARS);
          }
        ?>
      </td>
       <td style="word-wrap: break-word;">
        <?php

          $co_service_id = $c['CoService']['id'];
          $token_type = Hash::extract($co_service_tokens, "{n}.CoServiceToken[co_service_id=$co_service_id].token_type");

          if(in_array($co_service_id, $tokensSet)) {
            $token = Hash::extract($co_service_tokens, "{n}.CoServiceToken[co_service_id=$co_service_id].token");
          } else {
            print _txt('pl.coservicetoken.token.no');
          }

          switch($token_type[0]) {
            case CoServiceTokenTypeEnum::CephRgwToken:
              foreach ($vv_co_person_rgw_ids as $rgw_id) {
                $encoding_description = "<font style='font-weight: bold;'>S3 Access Key for ". $rgw_id['cou_formatted'] . " COU: </font><br /><hr> ";
                $encoding = base64_encode(json_encode(
                  [ "RGW_TOKEN" =>
                    [
                      "version" => 1,
                      "type" => "ldap",
                      "id" => $rgw_id['uid'] . '_' . $rgw_id['cou'],
                      "key" => $token[0]
                    ]
                  ]));
                print $encoding_description . filter_var( $encoding, FILTER_SANITIZE_SPECIAL_CHARS) . '<br /><hr>';
              }
              break;
            default:
              print filter_var( $token[0], FILTER_SANITIZE_SPECIAL_CHARS);
              break;
          }

          

        ?>
      <td>
        <?php
          // Link to generate a new token

          $txtkey = "";

          if(in_array($c['CoService']['id'], $tokensSet)) {
            // Token exists
            $txtkey = 'pl.coservicetoken.confirm.replace';
          } else {
            $txtkey = 'pl.coservicetoken.confirm';
          }

          print '<button type="button" class="provisionbutton" title="' . _txt('pl.coservicetoken.generate')
                . '" onclick="javascript:js_confirm_generic(\''
                . _txt($txtkey, array(filter_var(_jtxt($c['CoService']['name']),FILTER_SANITIZE_STRING))) . '\',\''    // dialog body text
                . $this->Html->url(              // dialog confirm URL
                    array(
                      'plugin'       => 'co_service_token',
                      'controller'   => 'co_service_tokens',
                      'action'       => 'generate',
                      'tokensetting' => $c['CoServiceTokenSetting']['id'],
                      'copersonid'   => $this->request->params['named']['copersonid']
                    )
                  ) . '\',\''
                . _txt('pl.coservicetoken.generate') . '\',\''    // dialog confirm button
                . _txt('op.cancel') . '\',\''    // dialog cancel button
                . _txt('pl.coservicetoken.generate') . '\',[\''   // dialog title
                . ''  // dialog body text replacement strings
                . '\']);">'
                . _txt('pl.coservicetoken.generate')
                . '</button>';
        ?>
      </td>
    </tr>
    <?php $i++; ?>
    <?php endforeach; ?>
  </tbody>

  <tfoot>
    <tr class="ui-widget-header">
      <th colspan="4">
      </th>
    </tr>
  </tfoot>
</table>
