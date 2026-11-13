<!--
/**
 * OSiRIS LDAP CO Group Provisioner Target Fields
 */
-->
<?php
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
<table id="<?php print $this->action; ?>_co_ldap_co_group_provisioner_target" class="ui-widget">
  <tbody>
    <tr class="line1">
      <td>
        <strong class="fieldTitle"><?php print _txt('pl.ldapcogroup.ldap'); ?></strong><font class="required">*</font><br />
        <font class="desc"><?php print _txt('pl.ldapcogroup.ldap.desc'); ?></font>
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
    <tr class="line2">
      <td>
        <strong class="fieldTitle"><?php print _txt('pl.ldapcogroup.basedn'); ?></strong><font class="required">*</font><br />
        <font class="desc"><?php print _txt('pl.ldapcogroup.basedn.desc'); ?></font>
      </td>
      <td>
        <?php print $this->Form->input('basedn', array('type' => 'text', 'size' => 60)); ?>
      </td>
    </tr>
    <tr class="line1">
      <td>
        <strong class="fieldTitle"><?php print _txt('pl.ldapcogroup.gidmin'); ?></strong><font class="required">*</font><br />
        <font class="desc"><?php print _txt('pl.ldapcogroup.gidmin.desc'); ?></font>
      </td>
      <td>
        <?php print $this->Form->input('gid_min', array('type' => 'text', 'size' => 12)); ?>
      </td>
    </tr>
    <tr class="line2">
      <td>
        <strong class="fieldTitle"><?php print _txt('pl.ldapcogroup.apply'); ?></strong><br />
        <font class="desc"><?php print _txt('pl.ldapcogroup.apply.desc'); ?></font>
      </td>
      <td>
        <?php print $this->Form->input('apply_changes', array('type' => 'checkbox')); ?>
      </td>
    </tr>
    <tr>
      <td>
        <i><font class="required"><?php print _txt('fd.req'); ?></font></i><br />
      </td>
      <td>
        <?php
          if($e)
            print $this->Form->submit($submit_label);
        ?>
      </td>
    </tr>
  </tbody>
</table>
