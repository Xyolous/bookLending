-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 17, 2025 at 03:00 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `book_lending`
--

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `bid` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `date_published` date DEFAULT NULL,
  `main_type` enum('Academic','Non-academic') DEFAULT NULL,
  `specific_type` enum('Textbook','Reference Book','Dissertation','Thesis','Novel','Short Story','Poem Collection','Biography','Autobiography','Business Book') DEFAULT NULL,
  `book_location` enum('Antipolo','Binalonan','Guimba','North Manila','Quezon City') DEFAULT NULL,
  `status` enum('Available','Reserved','Borrowed') DEFAULT 'Available',
  `date_added` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`bid`, `title`, `author`, `date_published`, `main_type`, `specific_type`, `book_location`, `status`, `date_added`, `date_updated`) VALUES
(1, 'test1', 'test1', '0001-01-01', 'Academic', 'Textbook', 'Antipolo', 'Available', '2025-05-11 09:44:07', '2025-05-16 23:23:59'),
(2, 'test2', 'test2', '0002-02-02', 'Non-academic', 'Business Book', 'Binalonan', 'Reserved', '2025-05-11 09:46:16', '2025-05-16 07:55:04'),
(3, 'test3', 'test3', '0003-03-03', 'Non-academic', 'Biography', 'Guimba', 'Borrowed', '2025-05-11 09:56:52', '2025-05-16 23:23:43'),
(6, 'test 4', 'test 4', '0004-04-04', 'Academic', 'Thesis', 'North Manila', 'Borrowed', '2025-05-12 07:18:24', '2025-05-16 12:56:01');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `RID` int(11) NOT NULL,
  `UID` int(11) NOT NULL,
  `BID` int(11) NOT NULL,
  `reservation_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`RID`, `UID`, `BID`, `reservation_date`) VALUES
(31, 4, 2, '2025-05-16 07:55:04');

-- --------------------------------------------------------

--
-- Table structure for table `transaction`
--

CREATE TABLE `transaction` (
  `TID` int(11) NOT NULL,
  `UID` int(11) NOT NULL,
  `BID` int(11) NOT NULL,
  `borrow_date` date NOT NULL,
  `due_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `transaction_status` enum('Borrowed','Returned','Overdue') DEFAULT 'Borrowed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transaction`
--

INSERT INTO `transaction` (`TID`, `UID`, `BID`, `borrow_date`, `due_date`, `return_date`, `transaction_status`) VALUES
(1, 7, 6, '2025-05-14', '2025-05-15', NULL, 'Borrowed'),
(2, 6, 2, '2025-05-14', '2025-05-28', '2025-05-14', 'Returned'),
(3, 4, 1, '2025-05-14', '2025-05-28', '2025-05-14', 'Returned'),
(4, 7, 6, '2025-05-14', '2025-05-28', '2025-05-14', 'Returned'),
(5, 7, 6, '2025-05-14', '2025-05-28', '2025-05-14', 'Returned'),
(6, 7, 6, '2025-05-14', '2025-05-15', '2025-05-16', 'Returned'),
(7, 7, 1, '2025-05-15', '2025-05-29', '2025-05-16', 'Returned'),
(8, 7, 6, '2025-05-16', '2025-05-30', NULL, 'Borrowed'),
(9, 7, 3, '2025-05-17', '2025-05-31', NULL, 'Borrowed');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `UID` int(11) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `user_type` enum('Admin','User') DEFAULT 'User',
  `user_role` enum('Guest','Student','Faculty') DEFAULT NULL,
  `school_id` varchar(255) DEFAULT NULL,
  `campus` enum('Antipolo','Binalonan','Guimba','North Manila','Quezon City') DEFAULT NULL,
  `course` enum('BS Medical Technology','BS Nursing','BS Nutrition & Dietetics','BS Pharmacy','BS Physical Therapy','BS Psychology','BS Radiologic Technology','BS Criminology','BS Accountancy','BS Business Administration','BS Information Technology','BS Hospitality Management','BS Tourism Management','BA Communication','BP Administration') DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `date_registered` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_last_login` timestamp NULL DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`UID`, `first_name`, `last_name`, `user_type`, `user_role`, `school_id`, `campus`, `course`, `email`, `password`, `date_registered`, `date_last_login`, `address`, `contact`) VALUES
(1, 'Xyg Fred', 'Tejada', 'Admin', NULL, NULL, NULL, NULL, 'admin', '$2y$10$57sCqhTmvtsK7KiovH09aePAKVQcbGerm5XVm08ztmwSD3u/zgxwK', '2025-05-11 08:23:12', '2025-05-17 00:53:26', NULL, NULL),
(4, 'f', 'f', 'User', 'Faculty', '2025-F-0001', 'Quezon City', NULL, 'f@gmail.com', '$2y$10$RSkCm/h9vGRpQ7ZMk9p.l..zdSJs1OB/dvX9gdgfnuoIFMvLmX8jW', '2025-05-11 10:49:42', '2025-05-16 07:55:01', NULL, NULL),
(6, 's', 's', 'User', 'Student', '2025-S-0001', 'Guimba', 'BS Medical Technology', 's@gmail.com', '$2y$10$05I4JYnS7IB7VF12dAq0TOeZXgMOMN/fr9lpUEz3sVRkkQjj2Lw8u', '2025-05-11 10:51:26', '2025-05-16 07:54:45', NULL, NULL),
(7, 'g', 'g', 'User', 'Guest', '', 'Guimba', '', 'g@gmail.com', '$2y$10$l/Lr.JTKqZ4FzWoFyZ3hS.QBtEDyxpmXOjdUlSXBDRFfzBavBwPPa', '2025-05-11 10:52:05', '2025-05-17 00:53:34', NULL, NULL),
(8, 'g2', 'g2', 'User', 'Guest', NULL, 'Binalonan', NULL, 'g2@gmail.com', '$2y$10$fxzB4cGZ0.abDRaXqDs6N.EcZuMG4br0jDaZ9R/STaB5EBS.9hu7K', '2025-05-12 02:01:51', '2025-05-15 14:27:59', NULL, NULL),
(9, 'f2', 'f2', 'User', 'Faculty', '2025-F-0002', 'Binalonan', NULL, 'f2@gmail.com', '$2y$10$A27Wwn.oiLtC6sncknHB.OMAuIyd5j6Gjofbym5aphRp4cSuwXAwy', '2025-05-12 02:02:38', NULL, NULL, NULL),
(10, 's2', 's2', 'User', 'Student', '2025-S-0002', 'North Manila', 'BS Tourism Management', 's2@gmail.com', '$2y$10$exCBVA0US.Tn0Mvvk.X3WOOIWGGxgsNyJGDKGEmWnqRNGgDTWhzj2', '2025-05-12 02:03:12', NULL, NULL, NULL),
(11, 'Jayvee', 'Escabal', 'User', 'Student', '22-AC0000309', 'Antipolo', 'BS Information Technology', 'jayvee@gmail.com', '$2y$10$N8GOwGrFR6LUeD/GU44.BOCgbTYUP3G19zpQOSUy4TbzyN0.u4y9u', '2025-05-13 11:57:08', '2025-05-13 12:09:55', 'asdas', '09123456789'),
(12, 'test', 'test', 'User', 'Student', NULL, 'Antipolo', NULL, 'test@gmail.com', '$2y$10$c8eLmu8aIWSwLwWWRpAa4u1TzwLeFh5PQAI4AMZKAizowpAktp4qm', '2025-05-16 07:20:22', '2025-05-16 07:33:07', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`bid`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`RID`),
  ADD KEY `UID` (`UID`),
  ADD KEY `BID` (`BID`);

--
-- Indexes for table `transaction`
--
ALTER TABLE `transaction`
  ADD PRIMARY KEY (`TID`),
  ADD KEY `UID` (`UID`),
  ADD KEY `BID` (`BID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`UID`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `bid` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `RID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `transaction`
--
ALTER TABLE `transaction`
  MODIFY `TID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `UID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`UID`) REFERENCES `users` (`UID`) ON DELETE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`BID`) REFERENCES `books` (`bid`) ON DELETE CASCADE;

--
-- Constraints for table `transaction`
--
ALTER TABLE `transaction`
  ADD CONSTRAINT `transaction_ibfk_1` FOREIGN KEY (`UID`) REFERENCES `users` (`UID`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaction_ibfk_2` FOREIGN KEY (`BID`) REFERENCES `books` (`bid`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
