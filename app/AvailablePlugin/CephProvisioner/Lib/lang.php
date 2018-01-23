<?php

global $cm_lang, $cm_texts, $cm_ceph_provisioner_texts;

$cm_ceph_provisioner_texts['en_US'] = array(
    // Titles, per-controller
    'ct.co_ceph_provisioner_targets.1'   => 'Ceph Provisioner Target', 
    'ct.co_ceph_provisioner_targets.pl'   => 'Ceph Provisioner Targets',

    // error texts
    'er.cephprovisioner.noldap'     => 'No ldap target found in database matching configured target id',
    'er.cephprovisioner.grouper'     => 'No grouper target found in database matching configured target id',
    'er.cephprovisioner.nodn'       => 'No LdapProvisioner DN found for user',
    'er.cephprovisioner.identifier' => 'CoPerson has no identifier for uid',
    'er.cephprovisioner.nopool'          => 'Could not determine pool permissions - no COU data pools found',
    'er.cephprovisioner.entity'          => 'Danger: found unmanaged user, osd, or mgr key in list of Ceph entities returned from client lib',
    'er.cephprovisioner.entity.arg'      => 'Danger: User not containing unique identifier passed to Ceph client lib',
    'er.cephprovisioner.datapool.cogroup' => 'CoGroup cou_id missing from provisining data',
    'er.cephprovisioner.datapool.rename'  => 'Error renaming COU data pools in Ceph',
    'er.cephprovisioner.datapool.provision'  => 'Error provisioning COU data pools in Ceph',
    'er.cephprovisioner.datapool.delete'    =>  'Error removing data pool records and/or ceph pool application associations',
    'er.cephprovisioner.associate'  => 'Error associating COU data pools to ceph applications',
    'er.cephprovisioner.rgw.extract'  => 'A pool name was not found in COU data pool records for RGW pool type',
    'er.cephprovisioner.rgw.user_metadata'  => 'Unknown error creating or looking up user metadata: ',
    'er.cephprovisioner.client.param'  => 'Empty parameter passed to Ceph client class',
    'er.cephprovisioner.pooltype'  => 'Multiple pool records stored for type, not supported in this plugin.  Type:  ',

    // Plugin texts
    'pl.cephprovisioner.info'    => 'Configure ceph provisioner',
    'pl.cephprovisioner.ldap_target'  => 'LDAP Lookup Target',
    'pl.cephprovisioner.ldap_target.desc'  => 'LDAP provisioning target to use for group posix id number lookup when generating Ceph client keys.  Ignored unless LDAP Posix Lookup option is set',
    'pl.cephprovisioner.grouper_target'  => 'Grouper Lookup Target',
    'pl.cephprovisioner.grouper_target.desc'  => 'Grouper provisioning target to use for group posix id number lookup when generating Ceph client keys.  If LDAP Posix Lookup is set this is ignored',
    'pl.cephprovisioner.opt_posix_lookup_ldap'      => 'Lookup posix GID using LDAP',
    'pl.cephprovisioner.opt_posix_lookup_ldap.desc'      => 'When generating Ceph client keys lookup the user group memberships and corresponding posix GID from LDAP provisioner target.  Prefer to lookup directly in grouper if part of infrastructure.',
    'pl.cephprovisioner.rgw_url'      => 'RGW URL',
    'pl.cephprovisioner.rgw_url.desc'      => 'URL to reach Ceph RGW, required to provision COU buckets and ACLs',
    'pl.cephprovisioner.ceph_client_name'      => 'Ceph Client ID',
    'pl.cephprovisioner.ceph_client_name.desc'      => 'Ceph Client ID used for --id arg to ceph binary (key client.{id})',
    'pl.cephprovisioner.opt_rgw_admin_api'      => 'Use RGW admin API',
    'pl.cephprovisioner.opt_rgw_admin_api.desc'      => 'Use RGW Admin API instead of radosgw-admin binary',
    'pl.cephprovisioner.opt_ceph_admin_api'      => 'Use Ceph admin REST API',
    'pl.cephprovisioner.opt_ceph_admin_api.desc'      => 'Use the REST API provided by ceph-rest-api daemon instead of ceph binary',
    'pl.cephprovisioner.rgw_admin_api_url'      => 'RGW admin API URL',
    'pl.cephprovisioner.rgw_admin_api_url.desc'      => 'URL to reach Ceph RGW admin API',
    'pl.cephprovisioner.ceph_admin_api_url'      => 'Ceph admin API URL',
    'pl.cephprovisioner.ceph_admin_api_url.desc'      => 'URL to reach Ceph admin REST API',
    'pl.cephprovisioner.ceph_cluster'      => 'Ceph Cluster Name',
    'pl.cephprovisioner.ceph_cluster.desc'      => 'Name of ceph cluster passed to --cluster arg of ceph binary',
    'pl.cephprovisioner.ceph_config_file'      => 'Ceph Config File',
    'pl.cephprovisioner.ceph_config_file.desc'      => 'Path to Ceph config file (otherwise use default /etc/ceph/{cluster}.conf',
    'pl.cephprovisioner.rgw_access_key'      => 'RGW Access Key',
    'pl.cephprovisioner.rgw_access_key.desc'      => 'Access key used to authenticate to RGW to create COU buckets and ACLs',
    'pl.cephprovisioner.rgw_secret_key'      => 'RGW Secret Key',
    'pl.cephprovisioner.rgw_secret_key.desc'      => 'Secret key used to authenticate to RGW',
    'pl.cephprovisioner.opt_create_cou_data_pools'      => 'Create COU data pools',
    'pl.cephprovisioner.opt_create_cou_data_pools.desc'      => 'When new COU created, also create and link buckets/dirs to data pools matching COU name',
    'pl.cephprovisioner.cou_data_pool_pgcount'      => 'COU Data Pool PG Count',
    'pl.cephprovisioner.cou_data_pool_pgcount.desc'      => 'Placement group number specified when creating new COU data pools ',
    'pl.cephprovisioner.ceph_user_prefix'   => 'Ceph User Prefix',
    'pl.cephprovisioner.ceph_user_prefix.desc'   => 'String prefixed to all ceph user entities created by comanage, must be unique.  Default is "comanage".  Example generated client id:  client.comanage.example',
    'pl.cephprovisioner.rgw_user_separator'     =>  'RGW User Separator',
    'pl.cephprovisioner.rgw_user_separator.desc'     =>  'String used to separate 2 part (user and cou) user identities for RGW users.  Default is "_".  Example generated user:  example_couname repeated for each user member cou',

);

?>
