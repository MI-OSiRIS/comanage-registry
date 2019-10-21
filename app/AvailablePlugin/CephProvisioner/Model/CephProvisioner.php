<?php

class CephProvisioner extends AppModel {
  // Required by COmanage Plugins
  public $cmPluginType = "provisioner";

  /**
   * Expose menu items.
   *
   * @since  COmanage Registry v3.2.0
   * @return Array with menu location type as key and array of labels, controllers, actions as values.
   */
  public function cmPluginMenus() {
    return array(
      /* 
      "coconfig" => array(_txt('ct.co_ceph_provisioner_settings.pl') =>
                          array('controller' => 'co_ceph_provisioner_settings',
                                'action'     => 'configure')),
      */
      "coperson" => array(_txt('ct.co_ceph_provisioner_creds.pl') =>
                          array('controller' => "co_ceph_provisioner_creds",
                                'action'     => 'index'))
    );
  }

}

?>
