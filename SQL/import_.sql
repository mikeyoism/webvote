-- phpMyAdmin SQL Dump
-- version 3.5.8.1
-- http://www.phpmyadmin.net
--
-- Host: 10.246.16.193:3306
-- Generation Time: Nov 02, 2014 at 08:10 PM
-- Server version: 5.5.38-MariaDB-1~wheezy
-- PHP Version: 5.3.3-7+squeeze15

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: replace with your database server name
--
use `NameOfYourDatabase`;
-- --------------------------------------------------------

--
-- Table structure for table `vote_cat_example`
--

CREATE TABLE IF NOT EXISTS `vote_cat_example` (
  `vote_code` char(3) NOT NULL,
  `vote_1` int(11) DEFAULT NULL,
  `vote_1_dt` datetime DEFAULT NULL,
  `vote_2` int(11) DEFAULT NULL,
  `vote_2_dt` datetime DEFAULT NULL,
  `vote_3` int(11) DEFAULT NULL,
  `vote_3_dt` datetime DEFAULT NULL,
  `vote_4` int(11) DEFAULT NULL,
  `vote_4_dt` datetime DEFAULT NULL,
  `vote_5` int(11) DEFAULT NULL,
  `vote_5_dt` datetime DEFAULT NULL,
  `vote_6` int(11) DEFAULT NULL,
  `vote_6_dt` datetime DEFAULT NULL,
  `vote_7` int(11) DEFAULT NULL,
  `vote_7_dt` datetime DEFAULT NULL,
  `manreg_paper_vote` int(11) DEFAULT NULL,
  PRIMARY KEY (`vote_code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `vote_cat_example_history`
--

CREATE TABLE IF NOT EXISTS `vote_cat_example_history` (
  `vote_code` char(3) NOT NULL,
  `vote_1` int(11) DEFAULT NULL,
  `vote_1_Dt` datetime DEFAULT NULL,
  `vote_2` int(11) DEFAULT NULL,
  `vote_2_dt` datetime DEFAULT NULL,
  `vote_3` int(11) DEFAULT NULL,
  `vote_3_dt` datetime DEFAULT NULL,
  `vote_4` int(11) DEFAULT NULL,
  `vote_4_dt` datetime DEFAULT NULL,
  `vote_5` int(11) DEFAULT NULL,
  `vote_5_dt` datetime DEFAULT NULL,
  `vote_6` int(11) DEFAULT NULL,
  `vote_6_dt` datetime DEFAULT NULL,
  `vote_7` int(11) DEFAULT NULL,
  `vote_7_dt` datetime DEFAULT NULL,
  `dt` datetime DEFAULT NULL,
  `manreg_paper_vote` int(11) DEFAULT NULL,
  KEY `vote_code` (`vote_code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


-- --------------------------------------------------------

--
-- Table structure for table `vote_codes`
--

CREATE TABLE IF NOT EXISTS `vote_codes` (
  `vote_code` char(3) NOT NULL,
  `generated_DT` datetime DEFAULT NULL,
  PRIMARY KEY (`vote_code`),
  KEY `vote_code` (`vote_code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `vote_codes_allocation_log`
--

CREATE TABLE IF NOT EXISTS `vote_codes_allocation_log` (
  `vote_code` char(3) NOT NULL,
  `allocation_DT` datetime DEFAULT NULL,
  `old_code` char(3) DEFAULT NULL COMMENT '!=NULL, kod ändrad från detta, indikerar att enhet delas av flera användare',
  `illegal_attempt` int(11) DEFAULT NULL COMMENT '1 = ogiltig kod någon försöker spara röst med',
  `manreg_paper_vote` int(11) DEFAULT NULL COMMENT 'om allokerad vid reg av pappersröst',
  KEY `vote_code` (`vote_code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Log som visar varje förfrågan mot vote_codes. använd distinc';

-- --------------------------------------------------------

--
-- Table structure for table `vote_users`
--

CREATE TABLE IF NOT EXISTS `vote_users` (
  `username` varchar(20) NOT NULL,
  `password` varchar(20) NOT NULL,
  `priviledge` int(11) DEFAULT NULL,
  PRIMARY KEY (`username`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='admin users';

-- --------------------------------------------------------

--
-- Table structure for table `vote_visitor_stat`
--

CREATE TABLE IF NOT EXISTS `vote_visitor_stat` (
  `vote_code` char(3) NOT NULL,
  `gender` varchar(1) DEFAULT NULL,
  `age` varchar(3) DEFAULT NULL,
  `location` varchar(50) DEFAULT NULL,
  `firstSM` int(11) DEFAULT NULL,
  PRIMARY KEY (`vote_code`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='användarstatistik för skojs skull, valfritt';

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
