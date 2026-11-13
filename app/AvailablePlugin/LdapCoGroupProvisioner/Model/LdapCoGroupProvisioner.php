<?php
/**
 * OSiRIS LDAP CO Group Provisioner: plugin definition
 */
App::uses('AppModel', 'Model');

class LdapCoGroupProvisioner extends AppModel {
  // Required by COmanage Plugins
  public $cmPluginType = 'provisioner';

  // Document foreign keys
  public $cmPluginHasMany = array();

  public function cmPluginMenus() {
    return array();
  }
}
