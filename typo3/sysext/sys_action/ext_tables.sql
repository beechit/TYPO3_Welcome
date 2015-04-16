#
# Table structure for table 'sys_action'
#
CREATE TABLE sys_action (
  uid int(11) unsigned NOT NULL auto_increment,
  pid int(11) unsigned DEFAULT '0' NOT NULL,
  tstamp int(11) unsigned DEFAULT '0' NOT NULL,
  crdate int(11) unsigned DEFAULT '0' NOT NULL,
  cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
  sorting int(10) DEFAULT '0' NOT NULL,
  title varchar(255) DEFAULT '' NOT NULL,
  description text,
  type tinyint(3) unsigned DEFAULT '0' NOT NULL,
  t1_userprefix varchar(20) DEFAULT '' NOT NULL,
  t1_copy_of_user int(11) DEFAULT '0' NOT NULL,
  t1_allowed_groups tinytext,
  t2_data blob,
  assign_to_groups int(11) DEFAULT '0' NOT NULL,
  hidden tinyint(4) DEFAULT '0' NOT NULL,
  t1_create_user_dir tinyint(4) DEFAULT '0' NOT NULL,
  t3_listPid int(11) DEFAULT '0' NOT NULL,
  t3_tables varchar(255) DEFAULT '' NOT NULL,
  t4_recordsToEdit text,

  PRIMARY KEY (uid),
  KEY cruser_id (cruser_id),
  KEY parent (pid)
);

#
# Table structure for table 'sys_action_asgr_mm'
#
CREATE TABLE sys_action_asgr_mm (
  uid_local int(11) unsigned DEFAULT '0' NOT NULL,
  uid_foreign int(11) unsigned DEFAULT '0' NOT NULL,
  sorting int(11) unsigned DEFAULT '0' NOT NULL,
  KEY uid_local (uid_local),
  KEY uid_foreign (uid_foreign)
);

