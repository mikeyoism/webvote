-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 03, 2024 at 08:43 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `webvote`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `competitionId` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `description` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `competitions`
--

CREATE TABLE `competitions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `openTime` datetime NOT NULL,
  `closeTime` datetime NOT NULL,
  `testingOpenUntilTime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entries`
--

CREATE TABLE `entries` (
  `id` int(11) NOT NULL,
  `categoryId` int(11) DEFAULT NULL,
  `entryCode` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `privileges`
--

CREATE TABLE `privileges` (
  `id` int(11) NOT NULL,
  `user` int(11) NOT NULL,
  `competition` int(11) NOT NULL,
  `privilegeLevel` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE `ratings` (
  `id` int(11) NOT NULL,
  `voteCodeId` int(11) NOT NULL,
  `categoryId` int(11) NOT NULL,
  `beerEntryId` int(11) DEFAULT NULL,
  `drankCheck` int(11) DEFAULT NULL,
  `ratingScore` int(11) DEFAULT NULL,
  `ratingComment` varchar(512) DEFAULT NULL,
  `ratingMethod` enum('web','manual') NOT NULL,
  `creationTime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `username` varchar(50) NOT NULL,
  `password` varchar(50) NOT NULL,
  `id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `votecodes`
--

CREATE TABLE `votecodes` (
  `id` int(11) NOT NULL,
  `competitionId` int(11) NOT NULL,
  `code` char(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `id` int(11) NOT NULL,
  `voteCodeId` int(11) NOT NULL,
  `categoryId` int(11) NOT NULL,
  `vote1` int(11) DEFAULT NULL,
  `vote2` int(11) DEFAULT NULL,
  `vote3` int(11) DEFAULT NULL,
  `votingMethod` enum('web','manual') NOT NULL,
  `creationTime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vote_weights_and_labels`
--

CREATE TABLE `vote_weights_and_labels` (
  `id` int(11) NOT NULL,
  `category` int(11) NOT NULL,
  `label` varchar(50) NOT NULL,
  `weight` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `competitionId` (`competitionId`);

--
-- Indexes for table `competitions`
--
ALTER TABLE `competitions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `entries`
--
ALTER TABLE `entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `categoryCodeUniqueIndex` (`categoryId`,`entryCode`),
  ADD KEY `categoryCodeIndex` (`categoryId`,`entryCode`);

--
-- Indexes for table `privileges`
--
ALTER TABLE `privileges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user` (`user`),
  ADD KEY `competition` (`competition`);

--
-- Indexes for table `ratings`
--
ALTER TABLE `ratings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categoryId` (`categoryId`),
  ADD KEY `vodeCodeId` (`voteCodeId`),
  ADD KEY `beerEntryId` (`beerEntryId`),
  ADD KEY `ratingScore` (`ratingScore`),
  ADD KEY `ratings_ibfk_3` (`categoryId`,`beerEntryId`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `votecodes`
--
ALTER TABLE `votecodes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `competitionId` (`competitionId`);

--
-- Indexes for table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `categoryId` (`categoryId`),
  ADD KEY `vodeCodeId` (`voteCodeId`),
  ADD KEY `categoryId_2` (`categoryId`,`vote1`),
  ADD KEY `categoryId_3` (`categoryId`,`vote2`),
  ADD KEY `categoryId_4` (`categoryId`,`vote3`);

--
-- Indexes for table `vote_weights_and_labels`
--
ALTER TABLE `vote_weights_and_labels`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category` (`category`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `competitions`
--
ALTER TABLE `competitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `entries`
--
ALTER TABLE `entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `privileges`
--
ALTER TABLE `privileges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ratings`
--
ALTER TABLE `ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `votecodes`
--
ALTER TABLE `votecodes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vote_weights_and_labels`
--
ALTER TABLE `vote_weights_and_labels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`competitionId`) REFERENCES `competitions` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `entries`
--
ALTER TABLE `entries`
  ADD CONSTRAINT `entries_ibfk_1` FOREIGN KEY (`categoryId`) REFERENCES `categories` (`id`);

--
-- Constraints for table `privileges`
--
ALTER TABLE `privileges`
  ADD CONSTRAINT `privileges_ibfk_1` FOREIGN KEY (`user`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `privileges_ibfk_2` FOREIGN KEY (`competition`) REFERENCES `competitions` (`id`);

--
-- Constraints for table `ratings`
--
ALTER TABLE `ratings`
  ADD CONSTRAINT `ratings_ibfk_1` FOREIGN KEY (`categoryId`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `ratings_ibfk_2` FOREIGN KEY (`voteCodeId`) REFERENCES `votecodes` (`id`);

--
-- Constraints for table `votecodes`
--
ALTER TABLE `votecodes`
  ADD CONSTRAINT `voteCodes_ibfk_1` FOREIGN KEY (`competitionId`) REFERENCES `competitions` (`id`);

--
-- Constraints for table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `votes_ibfk_1` FOREIGN KEY (`categoryId`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `votes_ibfk_2` FOREIGN KEY (`voteCodeId`) REFERENCES `votecodes` (`id`),
  ADD CONSTRAINT `votes_ibfk_3` FOREIGN KEY (`categoryId`,`vote1`) REFERENCES `entries` (`categoryId`, `entryCode`),
  ADD CONSTRAINT `votes_ibfk_4` FOREIGN KEY (`categoryId`,`vote2`) REFERENCES `entries` (`categoryId`, `entryCode`),
  ADD CONSTRAINT `votes_ibfk_5` FOREIGN KEY (`categoryId`,`vote3`) REFERENCES `entries` (`categoryId`, `entryCode`);

--
-- Constraints for table `vote_weights_and_labels`
--
ALTER TABLE `vote_weights_and_labels`
  ADD CONSTRAINT `vote_weights_and_labels_ibfk_1` FOREIGN KEY (`category`) REFERENCES `categories` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
