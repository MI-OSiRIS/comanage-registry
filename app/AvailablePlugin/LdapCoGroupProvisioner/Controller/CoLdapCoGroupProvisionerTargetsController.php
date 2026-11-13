<?php
/**
 * OSiRIS LDAP CO Group Provisioner Targets Controller
 */
App::uses("SPTController", "Controller");

class CoLdapCoGroupProvisionerTargetsController extends SPTController {
  // Class name, used by Cake
  public $name = "CoLdapCoGroupProvisionerTargets";

  // Establish pagination parameters for HTML views
  public $paginate = array(
    'limit' => 25,
    'order' => array(
      'co_ldap_provisioner_target_id' => 'asc'
    )
  );

  public $uses = array(
    'LdapCoGroupProvisioner.CoLdapCoGroupProvisionerTarget',
    'CoProvisioningTarget'
  );

  function beforeRender() {
    parent::beforeRender();

    // Pull the LDAP Provisioners of this CO: we use the server, bind account and people DNs of one of them

    $this->CoProvisioningTarget->bindModel(array('hasOne' =>
                                                 array('LdapProvisioner.CoLdapProvisionerTarget')),
                                           false);

    $args = array();
    $args['conditions']['CoProvisioningTarget.co_id'] = $this->cur_co['Co']['id'];
    $args['conditions']['CoProvisioningTarget.plugin'] = 'LdapProvisioner';
    $args['contain'][] = 'CoLdapProvisionerTarget';

    $ldapProvisioners = $this->CoProvisioningTarget->find('all', $args);

    $availableTargets = array();

    foreach($ldapProvisioners as $lp) {
      $availableTargets[ $lp['CoLdapProvisionerTarget']['id'] ] = $lp['CoProvisioningTarget']['description'];
    }

    $this->set('vv_ldap_provisioners', $availableTargets);
  }

  /**
   * Authorization for this Controller, called by Auth component
   *
   * @return Array Permissions
   */
  function isAuthorized() {
    $roles = $this->Role->calculateCMRoles();

    // Construct the permission set for this user, which will also be passed to the view.
    $p = array();

    // Delete an existing CO Provisioning Target?
    $p['delete'] = ($roles['cmadmin'] || $roles['coadmin']);

    // Edit an existing CO Provisioning Target?
    $p['edit'] = ($roles['cmadmin'] || $roles['coadmin']);

    // View all existing CO Provisioning Targets?
    $p['index'] = ($roles['cmadmin'] || $roles['coadmin']);

    // View an existing CO Provisioning Target?
    $p['view'] = ($roles['cmadmin'] || $roles['coadmin']);

    $this->set('permissions', $p);
    return($p[$this->action]);
  }
}
