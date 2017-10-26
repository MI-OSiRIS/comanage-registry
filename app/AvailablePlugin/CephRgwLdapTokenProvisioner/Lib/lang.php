<?php
/**
 * COmanage Registry Ceph RGW LDAP Token Provisioner Plugin Language File
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
  
global $cm_lang, $cm_texts;

// When localizing, the number in format specifications (eg: %1$s) indicates the argument
// position as passed to _txt.  This can be used to process the arguments in
// a different order than they were passed.

$cm_ceph_rgw_ldap_token_provisioner_texts['en_US'] = array(
  // Titles, per-controller
  'ct.co_ceph_rgw_ldap_token_provisioner_targets.1'  => 'Ceph RGW LDAP Token Provisioner Target',
  'ct.co_ceph_rgw_ldap_token_provisioner_targets.pl' => 'Ceph RGW Ldap Token Provisioner Targets',
  
  // Error messages
  'er.nodn'             => 'User DN not found in LDAP',
  'er.noldap'             => 'LDAP Target not found',
  'er.identifier'         => 'User has no identifier set matching type UID',
  
  // Plugin texts
  'pl.rgw_ldap.ldap'            => 'LDAP Provisioning Target',
  'pl.rgw_ldap.service'         => 'CO Service',
  'pl.rgw_ldap.userbasedn'          => 'Base DN to hold RGW users'

);
