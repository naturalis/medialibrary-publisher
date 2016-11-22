CREATE TABLE IF NOT EXISTS media (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  regno varchar(48) NOT NULL,
  producer varchar(16) DEFAULT NULL,
  source_file varchar(127) DEFAULT NULL,
  source_file_created datetime DEFAULT NULL,
  master_file varchar(127) DEFAULT NULL,
  master_published datetime DEFAULT NULL,
  www_dir varchar(95) DEFAULT NULL,
  www_file varchar(63) DEFAULT NULL,
  www_published datetime DEFAULT NULL,
  tar_file_id int(10) unsigned DEFAULT NULL,
  backup_ok tinyint(1) unsigned NOT NULL DEFAULT '0',
  master_ok tinyint(1) unsigned NOT NULL DEFAULT '0',
  www_ok tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (id),
  UNIQUE KEY regno (regno,producer),
  KEY tar_file_id (tar_file_id),
  KEY local_path (www_file),
  KEY master_ok (master_ok),
  KEY www_ok (www_ok,master_ok),
  KEY producer (producer,backup_ok)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS tar_file (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(63) NOT NULL,
  remote_dir varchar(95) DEFAULT NULL,
  backup_created datetime DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
