CREATE TABLE IF NOT EXISTS `vote_cat_fv1` (
  `vote_code` char(6) NOT NULL,
  `vote_1` int(11) DEFAULT NULL,
  `vote_1_dt` datetime DEFAULT NULL,
  `vote_2` int(11) DEFAULT NULL,
  `vote_2_dt` datetime DEFAULT NULL,
  `vote_3` int(11) DEFAULT NULL,
  `vote_3_dt` datetime DEFAULT NULL,
  `manreg_paper_vote` int(11) DEFAULT NULL,
  PRIMARY KEY (`vote_code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `vote_cat_fv1_history` (
  `vote_code` char(6) NOT NULL,
  `vote_1` int(11) DEFAULT NULL,
  `vote_1_Dt` datetime DEFAULT NULL,
  `vote_2` int(11) DEFAULT NULL,
  `vote_2_dt` datetime DEFAULT NULL,
  `vote_3` int(11) DEFAULT NULL,
  `vote_3_dt` datetime DEFAULT NULL,
  `dt` datetime DEFAULT NULL,
  `manreg_paper_vote` int(11) DEFAULT NULL,
  KEY `vote_code` (`vote_code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `vote_cat_fv2` (
  `vote_code` char(6) NOT NULL,
  `vote_1` int(11) DEFAULT NULL,
  `vote_1_dt` datetime DEFAULT NULL,
  `vote_2` int(11) DEFAULT NULL,
  `vote_2_dt` datetime DEFAULT NULL,
  `vote_3` int(11) DEFAULT NULL,
  `vote_3_dt` datetime DEFAULT NULL,
  `manreg_paper_vote` int(11) DEFAULT NULL,
  PRIMARY KEY (`vote_code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `vote_cat_fv2_history` (
  `vote_code` char(6) NOT NULL,
  `vote_1` int(11) DEFAULT NULL,
  `vote_1_Dt` datetime DEFAULT NULL,
  `vote_2` int(11) DEFAULT NULL,
  `vote_2_dt` datetime DEFAULT NULL,
  `vote_3` int(11) DEFAULT NULL,
  `vote_3_dt` datetime DEFAULT NULL,
  `dt` datetime DEFAULT NULL,
  `manreg_paper_vote` int(11) DEFAULT NULL,
  KEY `vote_code` (`vote_code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `vote_cat_fv3` (
  `vote_code` char(6) NOT NULL,
  `vote_1` int(11) DEFAULT NULL,
  `vote_1_dt` datetime DEFAULT NULL,
  `vote_2` int(11) DEFAULT NULL,
  `vote_2_dt` datetime DEFAULT NULL,
  `vote_3` int(11) DEFAULT NULL,
  `vote_3_dt` datetime DEFAULT NULL,
  `manreg_paper_vote` int(11) DEFAULT NULL,
  PRIMARY KEY (`vote_code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `vote_cat_fv3_history` (
  `vote_code` char(6) NOT NULL,
  `vote_1` int(11) DEFAULT NULL,
  `vote_1_Dt` datetime DEFAULT NULL,
  `vote_2` int(11) DEFAULT NULL,
  `vote_2_dt` datetime DEFAULT NULL,
  `vote_3` int(11) DEFAULT NULL,
  `vote_3_dt` datetime DEFAULT NULL,
  `dt` datetime DEFAULT NULL,
  `manreg_paper_vote` int(11) DEFAULT NULL,
  KEY `vote_code` (`vote_code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `vote_cat_fv4` (
  `vote_code` char(6) NOT NULL,
  `vote_1` int(11) DEFAULT NULL,
  `vote_1_dt` datetime DEFAULT NULL,
  `vote_2` int(11) DEFAULT NULL,
  `vote_2_dt` datetime DEFAULT NULL,
  `vote_3` int(11) DEFAULT NULL,
  `vote_3_dt` datetime DEFAULT NULL,
  `manreg_paper_vote` int(11) DEFAULT NULL,
  PRIMARY KEY (`vote_code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `vote_cat_fv4_history` (
  `vote_code` char(6) NOT NULL,
  `vote_1` int(11) DEFAULT NULL,
  `vote_1_Dt` datetime DEFAULT NULL,
  `vote_2` int(11) DEFAULT NULL,
  `vote_2_dt` datetime DEFAULT NULL,
  `vote_3` int(11) DEFAULT NULL,
  `vote_3_dt` datetime DEFAULT NULL,
  `dt` datetime DEFAULT NULL,
  `manreg_paper_vote` int(11) DEFAULT NULL,
  KEY `vote_code` (`vote_code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `vote_cat_label` (
  `vote_code` char(6) NOT NULL,
  `vote_1` int(11) DEFAULT NULL,
  `vote_1_dt` datetime DEFAULT NULL,
  `vote_2` int(11) DEFAULT NULL,
  `vote_2_dt` datetime DEFAULT NULL,
  `vote_3` int(11) DEFAULT NULL,
  `vote_3_dt` datetime DEFAULT NULL,
  `manreg_paper_vote` int(11) DEFAULT NULL,
  PRIMARY KEY (`vote_code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `vote_cat_label_history` (
  `vote_code` char(6) NOT NULL,
  `vote_1` int(11) DEFAULT NULL,
  `vote_1_Dt` datetime DEFAULT NULL,
  `vote_2` int(11) DEFAULT NULL,
  `vote_2_dt` datetime DEFAULT NULL,
  `vote_3` int(11) DEFAULT NULL,
  `vote_3_dt` datetime DEFAULT NULL,
  `dt` datetime DEFAULT NULL,
  `manreg_paper_vote` int(11) DEFAULT NULL,
  KEY `vote_code` (`vote_code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


