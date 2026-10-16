-- OSiRIS COmanage: clear orphaned foreign keys left from COUs and LDAP
-- provisioner targets deleted in 2017, so the 4.6.1 schema can add its
-- foreign key constraints. Expected row counts (2026-10-16) in comments.
START TRANSACTION;

UPDATE cm_co_person_roles x LEFT JOIN cm_cous c ON c.id = x.cou_id
   SET x.cou_id = NULL WHERE x.cou_id IS NOT NULL AND c.id IS NULL;
SELECT 'co_person_roles.cou_id -> NULL (expect 117)' AS step, ROW_COUNT() AS n;

UPDATE cm_co_petitions x LEFT JOIN cm_cous c ON c.id = x.cou_id
   SET x.cou_id = NULL WHERE x.cou_id IS NOT NULL AND c.id IS NULL;
SELECT 'co_petitions.cou_id -> NULL (expect 208)' AS step, ROW_COUNT() AS n;

UPDATE cm_co_groups x LEFT JOIN cm_cous c ON c.id = x.cou_id
   SET x.cou_id = NULL WHERE x.cou_id IS NOT NULL AND c.id IS NULL;
SELECT 'co_groups.cou_id -> NULL (expect 129)' AS step, ROW_COUNT() AS n;

UPDATE cm_co_enrollment_flows x LEFT JOIN cm_cous c ON c.id = x.authz_cou_id
   SET x.authz_cou_id = NULL WHERE x.authz_cou_id IS NOT NULL AND c.id IS NULL;
SELECT 'co_enrollment_flows.authz_cou_id -> NULL (expect 1)' AS step, ROW_COUNT() AS n;

DELETE x FROM cm_co_ldap_provisioner_attributes x
  LEFT JOIN cm_co_ldap_provisioner_targets t ON t.id = x.co_ldap_provisioner_target_id
 WHERE x.co_ldap_provisioner_target_id IS NOT NULL AND t.id IS NULL;
SELECT 'co_ldap_provisioner_attributes deleted (expect 41)' AS step, ROW_COUNT() AS n;

DELETE x FROM cm_co_ldap_provisioner_attr_groupings x
  LEFT JOIN cm_co_ldap_provisioner_targets t ON t.id = x.co_ldap_provisioner_target_id
 WHERE x.co_ldap_provisioner_target_id IS NOT NULL AND t.id IS NULL;
SELECT 'co_ldap_provisioner_attr_groupings deleted (expect 1)' AS step, ROW_COUNT() AS n;

DELETE x FROM cm_co_ldap_provisioner_dns x
  LEFT JOIN cm_co_ldap_provisioner_targets t ON t.id = x.co_ldap_provisioner_target_id
 WHERE x.co_ldap_provisioner_target_id IS NOT NULL AND t.id IS NULL;
SELECT 'co_ldap_provisioner_dns deleted (expect 1)' AS step, ROW_COUNT() AS n;

DELETE x FROM cm_co_ldap_user_posix_group_provisioner_targets x
  LEFT JOIN cm_co_provisioning_targets t ON t.id = x.co_provisioning_target_id
 WHERE x.co_provisioning_target_id IS NOT NULL AND t.id IS NULL;
SELECT 'co_ldap_user_posix_group_provisioner_targets deleted (expect 1)' AS step, ROW_COUNT() AS n;

COMMIT;
