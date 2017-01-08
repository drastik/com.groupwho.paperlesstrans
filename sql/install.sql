
-- Install SQL for PaperlessTrans extension. Create a table to hold ProfileNumbers.

CREATE TABLE `civicrm_paperlesstrans_profilenumbers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'Auto ID',
  `profile_number` varchar(255) NOT NULL COMMENT 'ProfileNumber returned from PaperlessTrans',
  `ip` varchar(255) DEFAULT NULL COMMENT 'Last IP from which this customer code was accessed or created',
  `is_ach` int(11) DEFAULT NULL COMMENT 'Is ACH 1/0',
  `cid` int(10) unsigned DEFAULT '0' COMMENT 'CiviCRM contact id',
  `email` varchar(255) DEFAULT NULL COMMENT 'CiviCRM Email address',
  `recur_id` int(10) unsigned DEFAULT '0' COMMENT 'CiviCRM recurring_contribution table id',
  PRIMARY KEY ( `id` ),
  UNIQUE INDEX (`profile_number`),
  KEY (`cid`),
  KEY (`email`),
  KEY (`recur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Stores PaperlessTrans ProfileNumbers';
