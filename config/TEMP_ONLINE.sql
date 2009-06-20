CREATE TABLE `TEMP_ONLINE` (
  `toid` int(5) NOT NULL auto_increment,
  `uid` int(5) NOT NULL,
  `cid` varchar(255) NOT NULL,
  `timeout` tinyint(4) NOT NULL default '0',
  PRIMARY KEY  (`toid`),
  UNIQUE KEY `uid` (`uid`),
  UNIQUE KEY `cid` (`cid`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=latin1
