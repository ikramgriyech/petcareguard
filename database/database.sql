-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 13, 2025 at 04:32 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `petcaredb`
--

-- --------------------------------------------------------

--
-- Table structure for table `animal`
--

CREATE TABLE `animal` (
  `AnimalID` int(11) NOT NULL,
  `Name` varchar(100) DEFAULT NULL,
  `Breed` varchar(100) DEFAULT NULL,
  `BirthYear` int(11) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `SpeciesID` int(11) DEFAULT NULL,
  `UserID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `animal`
--

INSERT INTO `animal` (`AnimalID`, `Name`, `Breed`, `BirthYear`, `Description`, `SpeciesID`, `UserID`) VALUES
(1, 'Max', 'Golden Retriever', 2020, 'Friendly dog', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `can_guard`
--

CREATE TABLE `can_guard` (
  `ProfileID` int(11) NOT NULL,
  `SpeciesID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `can_guard`
--

INSERT INTO `can_guard` (`ProfileID`, `SpeciesID`) VALUES
(2, 2);

-- --------------------------------------------------------

--
-- Table structure for table `carerequest`
--

CREATE TABLE `carerequest` (
  `RequestID` int(11) NOT NULL,
  `StartDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  `SpecialInstructions` text DEFAULT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `ProfileID` int(11) DEFAULT NULL,
  `UserID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `carerequest`
--

INSERT INTO `carerequest` (`RequestID`, `StartDate`, `EndDate`, `SpecialInstructions`, `Status`, `ProfileID`, `UserID`) VALUES
(1, '2024-12-15', '2024-12-20', 'Needs daily walks', 'Declined', 1, 1),
(2, '2024-12-22', '2024-12-25', 'Special diet', 'Pending', 2, 1),
(3, '2025-06-13', '2025-06-15', 'Please care for my pet, specifically for Cat species.', 'Pending', 2, 1),
(4, '2025-06-21', '2025-06-20', '', 'Pending', 2, 1),
(5, '2025-06-26', '2025-06-27', '', 'Approved', 1, 1),
(6, '2025-06-20', '2025-06-21', '', 'Approved', 1, 1),
(7, '2025-06-29', '2025-06-22', '', 'Approved', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `guardianprofile`
--

CREATE TABLE `guardianprofile` (
  `ProfileID` int(11) NOT NULL,
  `Photo` varchar(255) DEFAULT NULL,
  `Bio` text DEFAULT NULL,
  `PricePerNight` decimal(10,2) DEFAULT NULL,
  `Status` enum('Active','Inactive') DEFAULT 'Active',
  `UserID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guardianprofile`
--

INSERT INTO `guardianprofile` (`ProfileID`, `Photo`, `Bio`, `PricePerNight`, `Status`, `UserID`) VALUES
(1, NULL, '', 250.02, 'Active', 1),
(2, NULL, 'Experienced dog sitter', 25.00, 'Active', 2),
(3, NULL, 'Cat care specialist', 20.00, 'Active', 3);

-- --------------------------------------------------------

--
-- Table structure for table `review`
--

CREATE TABLE `review` (
  `ReviewID` int(11) NOT NULL,
  `Rating` int(11) DEFAULT NULL CHECK (`Rating` between 1 and 5),
  `Comment` text DEFAULT NULL,
  `_Date` date DEFAULT NULL,
  `ProfileID` int(11) DEFAULT NULL,
  `UserID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `species`
--

CREATE TABLE `species` (
  `SpeciesID` int(11) NOT NULL,
  `Name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `species`
--

INSERT INTO `species` (`SpeciesID`, `Name`) VALUES
(1, 'Dog'),
(2, 'Cat'),
(3, 'Bird');

-- --------------------------------------------------------

--
-- Table structure for table `_user`
--

CREATE TABLE `_user` (
  `UserID` int(11) NOT NULL,
  `FullName` varchar(100) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `Password` varchar(100) DEFAULT NULL,
  `PhoneNumber` varchar(20) DEFAULT NULL,
  `City` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `_user`
--

INSERT INTO `_user` (`UserID`, `FullName`, `Email`, `Password`, `PhoneNumber`, `City`) VALUES
(1, 'abdelhay', 'hamza.naciri@gmail.com', '$2y$10$QzJeRvayFD1Z1QuBQS.99urDH43WKTQCKA/tCAXIoXoWgXoZLmwG2', '+212 635 84 86 83', 'Agadir'),
(2, 'Sara El Amrani', 'sara@example.com', '$2y$10$QzJeRvayFD1Z1QuBQS.99urDH43WKTQCKA/tCAXIoXoWgXoZLmwG2', '+212 600 123 456', 'Casablanca'),
(3, 'Laila Gharbi', 'laila@example.com', '$2y$10$QzJeRvayFD1Z1QuBQS.99urDH43WKTQCKA/tCAXIoXoWgXoZLmwG2', '+212 600 789 123', 'Rabat'),
(4, 'Youssef Benali', 'youssef@example.com', '$2y$10$QzJeRvayFD1Z1QuBQS.99urDH43WKTQCKA/tCAXIoXoWgXoZLmwG2', '+212 600 456 789', 'Marrakech');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `animal`
--
ALTER TABLE `animal`
  ADD PRIMARY KEY (`AnimalID`),
  ADD KEY `SpeciesID` (`SpeciesID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `can_guard`
--
ALTER TABLE `can_guard`
  ADD PRIMARY KEY (`ProfileID`,`SpeciesID`),
  ADD KEY `SpeciesID` (`SpeciesID`);

--
-- Indexes for table `carerequest`
--
ALTER TABLE `carerequest`
  ADD PRIMARY KEY (`RequestID`),
  ADD KEY `ProfileID` (`ProfileID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `guardianprofile`
--
ALTER TABLE `guardianprofile`
  ADD PRIMARY KEY (`ProfileID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `review`
--
ALTER TABLE `review`
  ADD PRIMARY KEY (`ReviewID`),
  ADD KEY `ProfileID` (`ProfileID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `species`
--
ALTER TABLE `species`
  ADD PRIMARY KEY (`SpeciesID`);

--
-- Indexes for table `_user`
--
ALTER TABLE `_user`
  ADD PRIMARY KEY (`UserID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `animal`
--
ALTER TABLE `animal`
  MODIFY `AnimalID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `carerequest`
--
ALTER TABLE `carerequest`
  MODIFY `RequestID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `guardianprofile`
--
ALTER TABLE `guardianprofile`
  MODIFY `ProfileID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `review`
--
ALTER TABLE `review`
  MODIFY `ReviewID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `species`
--
ALTER TABLE `species`
  MODIFY `SpeciesID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `_user`
--
ALTER TABLE `_user`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `animal`
--
ALTER TABLE `animal`
  ADD CONSTRAINT `animal_ibfk_1` FOREIGN KEY (`SpeciesID`) REFERENCES `species` (`SpeciesID`),
  ADD CONSTRAINT `animal_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `_user` (`UserID`);

--
-- Constraints for table `can_guard`
--
ALTER TABLE `can_guard`
  ADD CONSTRAINT `can_guard_ibfk_1` FOREIGN KEY (`ProfileID`) REFERENCES `guardianprofile` (`ProfileID`),
  ADD CONSTRAINT `can_guard_ibfk_2` FOREIGN KEY (`SpeciesID`) REFERENCES `species` (`SpeciesID`);

--
-- Constraints for table `carerequest`
--
ALTER TABLE `carerequest`
  ADD CONSTRAINT `carerequest_ibfk_1` FOREIGN KEY (`ProfileID`) REFERENCES `guardianprofile` (`ProfileID`),
  ADD CONSTRAINT `carerequest_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `_user` (`UserID`);

--
-- Constraints for table `guardianprofile`
--
ALTER TABLE `guardianprofile`
  ADD CONSTRAINT `guardianprofile_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `_user` (`UserID`);

--
-- Constraints for table `review`
--
ALTER TABLE `review`
  ADD CONSTRAINT `review_ibfk_1` FOREIGN KEY (`ProfileID`) REFERENCES `guardianprofile` (`ProfileID`),
  ADD CONSTRAINT `review_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `_user` (`UserID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
