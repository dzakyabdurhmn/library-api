-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Dec 04, 2024 at 08:50 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `library`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int NOT NULL,
  `admin_username` varchar(50) NOT NULL,
  `admin_password` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `admin_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `admin_full_name` varchar(100) DEFAULT NULL,
  `admin_nik` varchar(30) NOT NULL,
  `admin_role` enum('superadmin','frontliner','warehouse') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'frontliner',
  `admin_phone` varchar(20) DEFAULT NULL,
  `admin_gender` enum('Laki-Laki','Perempuan') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Laki-Laki',
  `admin_address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_otp`
--

CREATE TABLE `admin_otp` (
  `admin_otp_otp` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `admin_otp_created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `admin_otp_expires_at` timestamp NOT NULL,
  `admin_otp_id` int NOT NULL,
  `admin_otp_email` varchar(225) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_token`
--

CREATE TABLE `admin_token` (
  `admin_token_token_id` int NOT NULL,
  `admin_token_admin_id` int NOT NULL,
  `admin_token_token` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `admin_token_created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `admin_token_expires_at` timestamp NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `author`
--

CREATE TABLE `author` (
  `author_id` int NOT NULL,
  `author_name` varchar(255) NOT NULL,
  `author_biography` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `book_id` int NOT NULL,
  `books_publisher_id` int DEFAULT NULL,
  `books_author_id` int DEFAULT NULL,
  `books_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `books_publication_year` int DEFAULT NULL,
  `books_isbn` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `books_stock_quantity` int NOT NULL DEFAULT '1',
  `books_price` decimal(10,2) DEFAULT NULL,
  `books_barcode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan`
--

CREATE TABLE `loan` (
  `loan_id` int NOT NULL,
  `loan_member_id` int NOT NULL,
  `loan_transaction_code` varchar(20) NOT NULL,
  `loan_date` datetime DEFAULT NULL,
  `loan_member_institution` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `loan_member_email` varchar(100) NOT NULL,
  `loan_member_full_name` varchar(100) DEFAULT NULL,
  `loan_member_address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loan_detail`
--

CREATE TABLE `loan_detail` (
  `loan_detail_id` int NOT NULL,
  `loan_detail_book_id` int NOT NULL,
  `loan_detail_book_title` varchar(255) NOT NULL,
  `loan_detail_book_publisher_name` varchar(255) DEFAULT NULL,
  `loan_detail_book_publisher_address` varchar(255) DEFAULT NULL,
  `loan_detail_book_publisher_phone` varchar(20) DEFAULT NULL,
  `loan_detail_book_publisher_email` varchar(100) DEFAULT NULL,
  `loan_detail_book_publication_year` int DEFAULT NULL,
  `loan_detail_book_isbn` varchar(20) DEFAULT NULL,
  `loan_detail_book_author_name` varchar(255) DEFAULT NULL,
  `loan_detail_book_author_biography` text,
  `loan_detail_status` enum('Good','Borrowed','Broken','Missing') NOT NULL,
  `loan_detail_borrow_date` datetime NOT NULL,
  `loan_detail_return_date` datetime DEFAULT NULL,
  `loan_detail_period` int NOT NULL,
  `loan_detail_loan_transaction_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `loan_detail_loan_id` int NOT NULL,
  `loan_detail_book_publisher_id` int NOT NULL,
  `loan_detail_book_author_id` int NOT NULL,
  `loan_detail_loan_duration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `member`
--

CREATE TABLE `member` (
  `member_id` int NOT NULL,
  `member_institution` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `member_email` varchar(100) NOT NULL,
  `member_full_name` varchar(100) DEFAULT NULL,
  `member_address` varchar(255) DEFAULT NULL,
  `member_job` varchar(100) DEFAULT NULL,
  `member_status` varchar(50) DEFAULT NULL,
  `member_religion` varchar(50) DEFAULT NULL,
  `member_barcode` varchar(50) DEFAULT NULL,
  `member_gender` enum('PRIA','WANITA') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `percentage`
--

CREATE TABLE `percentage` (
  `percentage_id` int NOT NULL,
  `percentage_name` varchar(255) NOT NULL,
  `percentage_object` text NOT NULL,
  `punishment_created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `percentage`
--

INSERT INTO `percentage` (`percentage_id`, `percentage_name`, `percentage_object`, `punishment_created_at`) VALUES
(1, 'keterlambatan_pengembalian', '{\"day\":\"2\"}', '2024-11-14 10:27:46'),
(2, 'missing_book_fine', '{\"fine_fee_in_percent\":\"90\"}', '2024-11-14 10:27:46'),
(3, 'broken_book_fine', '{\"fine_fee_in_percent\":\"70\"}', '2024-11-29 13:50:26');

-- --------------------------------------------------------

--
-- Table structure for table `publisher`
--

CREATE TABLE `publisher` (
  `publisher_id` int NOT NULL,
  `publisher_name` varchar(255) NOT NULL,
  `publisher_address` varchar(255) DEFAULT NULL,
  `publisher_phone` varchar(20) DEFAULT NULL,
  `publisher_email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `punishment`
--

CREATE TABLE `punishment` (
  `punishment_id` int NOT NULL,
  `member_id` int NOT NULL,
  `member_institution` varchar(50) NOT NULL,
  `member_email` varchar(100) NOT NULL,
  `member_full_name` varchar(100) DEFAULT NULL,
  `member_address` varchar(255) DEFAULT NULL,
  `member_job` varchar(100) DEFAULT NULL,
  `member_status` varchar(50) DEFAULT NULL,
  `member_religion` varchar(50) DEFAULT NULL,
  `member_barcode` varchar(50) DEFAULT NULL,
  `member_gender` enum('PRIA','WANITA') DEFAULT NULL,
  `book_id` int NOT NULL,
  `books_publisher_id` int DEFAULT NULL,
  `books_author_id` int DEFAULT NULL,
  `books_title` varchar(255) NOT NULL,
  `books_publication_year` int DEFAULT NULL,
  `books_isbn` varchar(20) DEFAULT NULL,
  `books_stock_quantity` int NOT NULL DEFAULT '1',
  `books_price` decimal(10,2) DEFAULT NULL,
  `books_barcode` varchar(50) DEFAULT NULL,
  `punishment_type` varchar(255) NOT NULL,
  `punishment_amount` decimal(10,2) NOT NULL,
  `punishment_late_days` int DEFAULT '0',
  `punishment_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_history`
--

CREATE TABLE `stock_history` (
  `history_id` int NOT NULL,
  `book_id` int NOT NULL,
  `book_name` varchar(255) NOT NULL,
  `quantity_change` int NOT NULL,
  `stock_before` int NOT NULL,
  `stock_after` int NOT NULL,
  `type` enum('masuk','keluar') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `admin_otp`
--
ALTER TABLE `admin_otp`
  ADD PRIMARY KEY (`admin_otp_id`),
  ADD KEY `admin_otp_otp` (`admin_otp_otp`);

--
-- Indexes for table `admin_token`
--
ALTER TABLE `admin_token`
  ADD PRIMARY KEY (`admin_token_token_id`),
  ADD KEY `admin_id` (`admin_token_admin_id`),
  ADD KEY `admin_token_token` (`admin_token_token`);

--
-- Indexes for table `author`
--
ALTER TABLE `author`
  ADD PRIMARY KEY (`author_id`);

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`book_id`),
  ADD KEY `fk_catalog_books_author` (`books_author_id`),
  ADD KEY `fk_catalog_books_publisher` (`books_publisher_id`);

--
-- Indexes for table `loan`
--
ALTER TABLE `loan`
  ADD PRIMARY KEY (`loan_id`),
  ADD KEY `fk_loan_member` (`loan_member_id`),
  ADD KEY `loan_transaction_code` (`loan_transaction_code`);

--
-- Indexes for table `loan_detail`
--
ALTER TABLE `loan_detail`
  ADD PRIMARY KEY (`loan_detail_id`),
  ADD KEY `loan_detail_book_id` (`loan_detail_book_id`),
  ADD KEY `loan_detail_book_publisher_id` (`loan_detail_book_publisher_id`),
  ADD KEY `loan_detail_book_author_id` (`loan_detail_book_author_id`),
  ADD KEY `loan_detail_loan_id` (`loan_detail_loan_id`);

--
-- Indexes for table `member`
--
ALTER TABLE `member`
  ADD PRIMARY KEY (`member_id`);

--
-- Indexes for table `percentage`
--
ALTER TABLE `percentage`
  ADD PRIMARY KEY (`percentage_id`);

--
-- Indexes for table `publisher`
--
ALTER TABLE `publisher`
  ADD PRIMARY KEY (`publisher_id`),
  ADD KEY `publisher_name` (`publisher_name`),
  ADD KEY `publisher_phone` (`publisher_phone`),
  ADD KEY `publisher_email` (`publisher_email`);

--
-- Indexes for table `punishment`
--
ALTER TABLE `punishment`
  ADD PRIMARY KEY (`punishment_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `stock_history`
--
ALTER TABLE `stock_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `book_id` (`book_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_otp`
--
ALTER TABLE `admin_otp`
  MODIFY `admin_otp_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_token`
--
ALTER TABLE `admin_token`
  MODIFY `admin_token_token_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `author`
--
ALTER TABLE `author`
  MODIFY `author_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `book_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan`
--
ALTER TABLE `loan`
  MODIFY `loan_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_detail`
--
ALTER TABLE `loan_detail`
  MODIFY `loan_detail_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `member`
--
ALTER TABLE `member`
  MODIFY `member_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `percentage`
--
ALTER TABLE `percentage`
  MODIFY `percentage_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `publisher`
--
ALTER TABLE `publisher`
  MODIFY `publisher_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `punishment`
--
ALTER TABLE `punishment`
  MODIFY `punishment_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_history`
--
ALTER TABLE `stock_history`
  MODIFY `history_id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_token`
--
ALTER TABLE `admin_token`
  ADD CONSTRAINT `admin_token_ibfk_1` FOREIGN KEY (`admin_token_admin_id`) REFERENCES `admin` (`admin_id`) ON DELETE CASCADE;

--
-- Constraints for table `books`
--
ALTER TABLE `books`
  ADD CONSTRAINT `books_ibfk_1` FOREIGN KEY (`books_author_id`) REFERENCES `author` (`author_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `loan`
--
ALTER TABLE `loan`
  ADD CONSTRAINT `loan_ibfk_1` FOREIGN KEY (`loan_member_id`) REFERENCES `member` (`member_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `punishment`
--
ALTER TABLE `punishment`
  ADD CONSTRAINT `punishment_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `member` (`member_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `punishment_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_history`
--
ALTER TABLE `stock_history`
  ADD CONSTRAINT `stock_history_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
