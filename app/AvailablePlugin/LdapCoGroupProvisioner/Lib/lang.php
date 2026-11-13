<?php
/**
 * OSiRIS LDAP CO Group Provisioner: texts
 */

global $cm_lang, $cm_texts;

// When localizing, the number in format specifications (eg: %1$s) indicates the argument
// position as passed to _txt.  This can be used to process the arguments in
// a different order than they were passed.

$cm_ldap_co_group_provisioner_texts['en_US'] = array(
  // Titles, per-controller
  'ct.co_ldap_co_group_provisioner_targets.1'  => 'LDAP CO Group Provisioner Target',
  'ct.co_ldap_co_group_provisioner_targets.pl' => 'LDAP CO Group Provisioner Targets',

  // Error messages
  'er.ldapcogroup.config'   => 'LDAP CO Group Provisioner is not configured: choose the LDAP provisioning target, the base DN and the lowest gidNumber',
  'er.ldapcogroup.ldap'     => 'LDAP CO Group Provisioner: the chosen LDAP provisioning target was not found or has no group base DN',

  // Plugin texts
  'pl.ldapcogroup.ldap'          => 'LDAP Provisioning Target',
  'pl.ldapcogroup.ldap.desc'     => 'The server, bind account and people DNs of this target are used',
  'pl.ldapcogroup.basedn'        => 'Base DN of the CO and COU groups',
  'pl.ldapcogroup.basedn.desc'   => 'Existing groups are found by name anywhere under this DN; new ones are created under it (for example ou=OSiRIS,ou=Groups,dc=osris,dc=org)',
  'pl.ldapcogroup.gidmin'        => 'Lowest gidNumber for new groups',
  'pl.ldapcogroup.gidmin.desc'   => 'A new group gets the next free number at or above this one. Existing groups keep theirs',
  'pl.ldapcogroup.apply'         => 'Apply changes',
  'pl.ldapcogroup.apply.desc'    => 'When not set, nothing is written to LDAP: each change that would be made is logged in debug.log with "DRY RUN"'
);
