# Archive

This directory stores retired root-level maintenance, migration, diagnostic,
test, and artifact files that were removed from the public web root for
security reasons.

Categories

- Migration scripts:
  `phase1_migration.php`, `phase2_migration.php`, `phase3_migration.php`,
  `phase4_migration.php`, `phase8_migration.php`, `phase9_migration.php`,
  `phase10_migration.php`, `phase11_migration.php`, `phase12_migration.php`,
  `phase13_migration.php`, `phase14_migration.php`, `phase15_migration.php`,
  `phase16_migration.php`
- Repair scripts:
  `clinical_upgrade.php`, `cli_repair.php`, `deep_repair.php`,
  `final_deep_repair.php`, `finalize.php`, `fix_audit_infra.php`,
  `fix_data_final.php`, `fix_dates_final.php`, `fix_fieldgroup.php`,
  `fix_opnote_parent.php`, `fix_seeded_data.php`, `fix_tabs.php`,
  `fix_tabs_v2.php`, `force_save.php`, `refresh_system.php`,
  `retest_fixes.php`
- Diagnostic scripts:
  `check_db_tables.php`, `check_fg.php`, `check_templates.php`,
  `final_backend_audit.php`, `final_check_id.php`, `final_diag.php`,
  `full_verify.php`, `list_dbs.php`, `run_blocker_check.php`, `run_diag.php`,
  `verify_blockers.php`, `test_sql.php`
- Test scripts:
  `casestatus_test.php`, `pdf_only_test.php`, `qa_runtime_test.php`
- Artifact files:
  `db_check.txt`, `final_check.txt`, `raw_diag.txt`, `pdf_test_output.pdf`

Notes

- These files were previously exposed at the project root and are no longer
  web-accessible.
- After 30 days without needing historical reference, this archive can be
  removed.
- Future migration or repair scripts should live under `site/migrations/`
  behind web access protection, not at the project root.
