<?php

global $cm_lang, $cm_texts, $cm_ceph_provisioner_texts;

$cm_ceph_provisioner_texts['en_US'] = array(
    // Titles, per-controller
    'ct.co_ceph_provisioner_targets.1'   => 'Ceph Provisioner Target', 
    'ct.co_ceph_provisioner_targets.pl'   => 'Ceph Provisioner Targets',
    'ct.co_ceph_provisioner_creds.pl'   => 'Ceph Credentials',

    'ct.co_ceph_provisioner_creds.desc' => array(
        CephClientEnum::Rgw           => 'S3/RGW Access Key',
        CephClientEnum::RgwLdap       => 'S3/RGW Access Key',
        CephClientEnum::Cluster       => 'Ceph Client Key'
    ),

    'ct.co_ceph_provisioner_creds.cred' =>  'Credentials',
    'ct.co_ceph_provisioner_creds.id' =>  'User ID',

    // error texts
    'er.cephprovisioner.noldap'     => 'No ldap target found in database matching configured target id',
    'er.cephprovisioner.grouper'     => 'No grouper target found in database matching configured target id',
    'er.cephprovisioner.nodn'       => 'No LdapProvisioner DN found for user',
    'er.cephprovisioner.identifier' => 'CoPerson data does not include identifier for uid',
    'er.cephprovisioner.userid.delete'    => 'Tried to delete primary co person userid from',
    'er.cephprovisioner.nopool'          => 'Could not determine pool permissions - no COU data pools found',
    'er.cephprovisioner.entity'          => 'Danger: Operation attempted on unmanaged user, osd, or mgr key in Ceph client library',
    'er.cephprovisioner.entity.arg'      => 'Danger: User data not containing identifier passed to Ceph client lib',
    'er.cephprovisioner.rgw.meta'       => 'Error:  Passed RGW metadata object with user_id not matching coperson identifier',
    'er.cephprovisioner.rgw.placement'       => 'Error:  User placement default not found in database',
    'er.cephprovisioner.datapool.cogroup' => 'CoGroup cou_id missing from provisioning data',
    'er.cephprovisioner.datapool.rename'  => 'Error renaming COU data pools in Ceph',
    'er.cephprovisioner.datapool.provision'  => 'Error provisioning COU data pools in Ceph',
    'er.cephprovisioner.datapool.delete'    =>  'Error removing data pool records and/or ceph pool application associations',
    'er.cephprovisioner.associate'  => 'Error associating COU data pools to ceph applications',
    'er.cephprovisioner.nocou'      => 'Error looking up COU from group data',
    'er.cephprovisioner.rgw.extract'  => 'A pool name was not found in COU data pool records for RGW pool type',
    'er.cocephprovisioner.rgw.copersonid'   => 'Coperson ID was not found in radosgw user metadata',
    'er.cephprovisioner.client.param'  => 'Empty parameter passed to Ceph client class',
    'er.cephprovisioner.pooltype'  => 'Multiple pool records stored for type, not supported in this plugin.  Type:  ',
    'er.cephprovisioner.coudir'    => 'Error creating COU data dir on CephFS mountpoint',

    // Plugin texts
    'pl.cephprovisioner.info'    => 'Configure ceph provisioner',
    'pl.cephprovisioner.ceph.newkey'  => 'New Ceph client secret created',
    'pl.cephprovisioner.ceph.newkey.confirm'  => 'Regenerate Ceph client secret?  Old secret will no longer work.',
    'pl.cephprovisioner.rgw.placement.confirm'  => 'Set default data placement (new buckets only)',
    'pl.cephprovisioner.rgw.placement'  => 'Data placement default set',
    'pl.cephprovisioner.rgw.newkey'  => 'New S3 access key created',
    'pl.cephprovisioner.rgw.newkey.confirm'  => 'Add S3 access key to user',
    'pl.cephprovisioner.rgw.regenkey'  => 'S3 Acccess key replaced',
    'pl.cephprovisioner.rgw.regenkey.confirm'  => 'Replace S3 key with new?',
    'pl.cephprovisioner.rgw.rmkey'  => 'S3 access key has been deleted',
    'pl.cephprovisioner.rgw.rmkey.confirm'  => 'Remove access key',
    'pl.cephprovisioner.rgw.newid'  => 'New S3 user created',
    'pl.cephprovisioner.rgw.newid.confirm'  => 'Add new S3 user',
    'pl.cephprovisioner.rgw.newid.err' => 'Error:  User ID must be at least 8 chars and can contain only alphanumeric, hyphen, or underscore',
    'pl.cephprovisioner.rgw.newid.exists' => 'Error:  User ID already exists',
    'pl.cephprovisioner.rgw.rmid'  => 'S3 User id has been deleted',
    'pl.cephprovisioner.rgw.rmid.confirm'  => 'Remove S3 user',
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
    'pl.cephprovisioner.opt_rgw_ldap_auth'     =>  'Use RGW LDAP Auth',
    'pl.cephprovisioner.opt_rgw_ldap_auth.desc'     =>  'If set then RGW users created with type "ldap" and without access keys stored in Ceph.  RGW must be configured to lookup passwords in LDAP.',

    'pl.cephprovisioner.opt_create_cou_data_dir'      => 'Create COU data directory',
    'pl.cephprovisioner.opt_create_cou_data_dir.desc'      => 'When new COU created, also create a CephFS data directory placed on their data pool.  Must install mkCouDir.sh script and configure sudo for webserver user to run this script with needed permissions.',

    'pl.cephprovisioner.cou_data_dir_command' => 'COU dir create command',
    'pl.cephprovisioner.cou_data_dir_command.desc' => 'Full path to command that will be run to create a COU data directory.  It will be given COU name and parent path as params.  Default is "/bin/sudo /usr/local/bin/mkCouDir.sh" (install script there or link to ComanageInstallDir/app/AvailablePlugin/CephProvisioner/Lib/mkCouDir.sh)',

    'pl.cephprovisioner.ceph_fs_mountpoint'      => 'CephFS Mountpoint',
    'pl.cephprovisioner.ceph_fs_mountpoint.desc'      => 'Where CephFS is mounted, data directories are created here',

    'pl.cephprovisioner.ceph_fs_name'      => 'CephFS name',
    'pl.cephprovisioner.ceph_fs_name.desc'      => 'Name of CephFS, needed to add new data pools for CephFS access',

    'pl.cephprovisioner.opt_mds_cap_uid'       => 'Set key uid/gid limitations',
    'pl.cephprovisioner.opt_mds_cap_uid.desc'  => 'Set MDS caps on client keys limiting UID and GID to those provisioned by COManage and Grouper/LDAP'
);

?>
