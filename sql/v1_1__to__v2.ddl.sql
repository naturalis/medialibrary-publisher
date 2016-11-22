-- -------------------------------------------------------------------
--
-- SQL scripts that updates the medialib database schema from v1.1 to
-- v2. If you start from scratch, first run ddl_v1_1.sql.sql to create
-- the v1.1 schema; then run this script.
--
-- -------------------------------------------------------------------

-- -------------------------------------------------------------------
-- Modify existing columns
-- -------------------------------------------------------------------
ALTER TABLE  `media` CHANGE  `regno`  `regno` VARCHAR( 48 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
ALTER TABLE  `media` CHANGE  `source_file`  `source_file` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
ALTER TABLE  `media` CHANGE  `master_file`  `master_file` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
ALTER TABLE  `media` CHANGE  `www_dir`  `www_dir` VARCHAR( 127 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
ALTER TABLE  `media` CHANGE  `www_file`  `www_file` VARCHAR( 127 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;


-- -------------------------------------------------------------------
-- Add new columns
-- -------------------------------------------------------------------
ALTER TABLE  `media` ADD  `owner` VARCHAR( 16 ) NOT NULL AFTER  `producer`;
ALTER TABLE  `media` ADD  `source_file_size` INT UNSIGNED  NULL DEFAULT NULL AFTER `source_file`;
ALTER TABLE  `media` ADD  `backup_group` TINYINT UNSIGNED NOT NULL AFTER  `tar_file_id`;


-- -------------------------------------------------------------------
-- Drop (some) existing indexes
-- -------------------------------------------------------------------
ALTER TABLE media DROP INDEX regno;
ALTER TABLE media DROP INDEX local_path;
ALTER TABLE media DROP INDEX producer;
ALTER TABLE media DROP INDEX master_ok;
ALTER TABLE media DROP INDEX www_ok;


-- -------------------------------------------------------------------
-- Add indexes
-- -------------------------------------------------------------------
ALTER TABLE  `media` ADD UNIQUE  `UNIQUE_01` (  `regno` );
ALTER TABLE  `media` ADD INDEX  `source_file_created` (  `source_file_created` );
ALTER TABLE  `media` ADD INDEX  `masters_unprocessed` (  `producer` ,  `master_ok` );
ALTER TABLE  `media` ADD INDEX  `www_unprocessed` (  `producer` ,  `master_ok` ,  `www_ok` );
ALTER TABLE  `media` ADD INDEX  `offload_unprocessed` (  `backup_group` ,  `backup_ok` );



-- -------------------------------------------------------------------
-- Create deleted_media table (new in v2)
-- -------------------------------------------------------------------
CREATE TABLE deleted_media (
  id int(10) unsigned NOT NULL DEFAULT '0',
  regno varchar(48) NOT NULL,
  producer varchar(16) DEFAULT NULL,
  `owner` varchar(16) DEFAULT NULL,
  source_file varchar(255) DEFAULT NULL,
  source_file_size int unsigned DEFAULT NULL,
  source_file_created datetime DEFAULT NULL,
  master_file varchar(255) DEFAULT NULL,
  master_published datetime DEFAULT NULL,
  www_dir varchar(127) DEFAULT NULL,
  www_file varchar(127) DEFAULT NULL,
  www_published datetime DEFAULT NULL,
  tar_file_id int(10) unsigned DEFAULT NULL,
  backup_group tinyint(3) unsigned NOT NULL DEFAULT '0',
  backup_ok tinyint(1) unsigned NOT NULL DEFAULT '0',
  master_ok tinyint(1) unsigned NOT NULL DEFAULT '0',
  www_ok tinyint(1) unsigned NOT NULL DEFAULT '0',
  date_deleted timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY date_deleted (date_deleted)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- CREATE OR REPLACE VIEW oai_pmh_view AS
--	SELECT a.id, a.regno, a.producer, a.www_dir, a.www_file, a.www_published as poll_date, 'ACTIVE' as status
--	  FROM media a
--	  LEFT JOIN deleted_media b ON(a.id=b.id)
--	 WHERE (a.source_file_created>b.date_deleted OR b.id IS NULL)
--	   AND a.www_ok=1
--	 UNION ALL
--	SELECT a.id, a.regno, a.producer, a.www_dir, a.www_file, a.date_deleted, 'DELETED'
--	  FROM deleted_media a
--	  LEFT JOIN media b ON(a.id=b.id)
--	 WHERE b.id IS NULL;
	 
CREATE OR REPLACE VIEW oai_pmh_view_1 AS
	SELECT a.id, a.regno, a.producer, a.www_dir, a.www_file, a.www_published as poll_date, 'ACTIVE' as status
	  FROM media a
	  LEFT JOIN deleted_media b ON(a.id=b.id)
	 WHERE (b.id IS NULL OR b.source_file_created>b.date_deleted) 
	   AND a.www_ok=1;

CREATE OR REPLACE VIEW oai_pmh_view_2 AS
	SELECT a.id, a.regno, a.producer, a.www_dir, a.www_file, a.date_deleted as poll_date, 'DELETED' as status
	  FROM deleted_media a
	  LEFT JOIN media b ON(a.id=b.id)
	 WHERE b.id IS NULL;




