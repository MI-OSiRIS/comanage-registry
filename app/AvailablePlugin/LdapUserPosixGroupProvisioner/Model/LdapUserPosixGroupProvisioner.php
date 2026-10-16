<?php

App::uses('AppModel', 'Model');

class LdapUserPosixGroupProvisioner extends AppModel {
    public $cmPluginType = 'provisioner';

    // Document foreign keys
    public $cmPluginHasMany = array();

   /**
   * Expose menu items.
   *
   * @ since COmanage Registry v2.0.0
   * @ return Array with menu location type as key and array of labels, controllers, actions as values.
   */
    public function cmPluginMenus() {
        return array();
    }
}


