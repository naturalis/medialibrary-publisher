-- Add hash and backup date for individual files --
ALTER TABLE `media` 
ADD `source_file_sha256` CHAR(64) NULL AFTER `source_file_created`, 
ADD `source_file_backup_created` DATETIME NULL AFTER `source_file_sha256`;

-- Move tar backup date to media table --
UPDATE media AS t1
JOIN tar_file AS t2 ON t1.tar_file_id = t2.id
SET t1.source_file_backup_created = t2.backup_created
WHERE t1.source_file_backup_created IS NULL;