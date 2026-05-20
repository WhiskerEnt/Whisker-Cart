<?php
/**
 * WHISKER — Build version (single source of truth)
 *
 * This file ships with each release and is overwritten by the auto-updater
 * the same way every other code file is overwritten. Industry standard:
 * the running version is a code-level fact, NOT an operator-editable
 * config value.
 *
 * Why not in config/config.php?
 *   - The previous design tried to maintain version inside config.php
 *     (which is `protected` from updates because it holds operator
 *     settings — DB creds via database.php sister-file, base_url, salt,
 *     timezone). The updater had to surgically rewrite a single line in
 *     the protected file using preg_replace, which is fragile (whitespace,
 *     quoting, and any operator hand-edits would break the regex), and
 *     when the regex didn't match the version stayed pinned forever.
 *   - "What version are we running?" is the kind of question every
 *     deploy should answer the same way: by reading the shipped artifact.
 *     Config files are for site-specific decisions, not build identity.
 *
 * Bumping the version: edit the literal below and ship.
 *
 * Other version-tracking surfaces in the system (deliberately):
 *   - wk_settings (system, last_version)
 *     What schema version was last successfully migrated. Bumped by
 *     MigrationService::checkAndRun() after a successful run; can lag
 *     this constant briefly during a release window where files have
 *     been copied but migrations haven't run yet.
 *   - storage/.installed JSON
 *     Records the version at FIRST install (audit trail). Never updated.
 */

return '1.3.0';
