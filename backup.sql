-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 13, 2024 at 02:59 AM
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
-- Database: `esoda_api_login`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `nik` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `username`, `password`, `email`, `full_name`, `nik`) VALUES
(1, 'shyakinardaz', '$2y$10$eYOjSJ0.w.OEeQUm4Kp0.uW65kIclpGjTWP0/KqhVmSYmjNvBJxHK', 'shyakinard@gmail.coms', 'raden ajeng shyakina rahmadyani ariniputri', '8989011133'),
(2, 'hanaa', '$2y$10$PIzIEbKXeXdqoMh6APfaie3bcPDQKEaShCjv6N3srq0/tvmiaTl/O', 'shyakinard@gmail.coms', 'raden ajeng shyakina rahmadyani ariniputri', '8989011133'),
(3, 'hanaass', '$2y$10$Da7HoTwXkrsmYoA.apswZepfGPq3xQMWqbgQ.jjxQQPJPMJ4DGSem', 'shyakinard@gmail.coms', 'raden ajeng shyakina rahmadyani ariniputri', '8989011133'),
(4, 'adminnya rapi', '$2y$10$3bTp9JaRFraYc2Xg/SNaoO6HHLNCw3sFZVAd76tA.oScqV5uj4mLW', 'rapiadmin@gmail.coms', 'abdul rafi', '123456789');

-- --------------------------------------------------------

--
-- Table structure for table `author`
--

CREATE TABLE `author` (
  `author_id` int NOT NULL,
  `author_name` varchar(255) NOT NULL,
  `biography` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `author`
--

INSERT INTO `author` (`author_id`, `author_name`, `biography`) VALUES
(3, 'olivia saputra tere females', 'dia adalah seorang kapiten yang mempunyai pedang panjang'),
(4, 'dzakina nana', 'aku adalah seorang cowo  yang menikah dengan kina'),
(5, 'Updated Author', 'Updated biography of the author.'),
(7, 'olivia Tere', 'dia adalah seorang kapiten yang mempunyai pedang panjang yang baik');

-- --------------------------------------------------------

--
-- Table structure for table `catalog_books`
--

CREATE TABLE `catalog_books` (
  `book_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `publisher_id` int DEFAULT NULL,
  `publication_year` int DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `stock_quantity` int NOT NULL DEFAULT '1',
  `author_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `catalog_books`
--

INSERT INTO `catalog_books` (`book_id`, `title`, `publisher_id`, `publication_year`, `isbn`, `stock_quantity`, `author_id`) VALUES
(1, 'Hantu bawah meja', 1, 2004, '7822923', 73, 3),
(2, 'ruang hampa', 1, 2030, '782793283', 66, 4),
(38, 'ruang hampa fps', 1, 2030, '782793283', 6, 4),
(40, 'ruang hampa di bawah poo galon', 1, 2030, '7827932111283', 1, 4),
(41, 'ruang hampa di bawah poo galon', 1, 2030, '7827932111283', 1, 4),
(42, 'ruang hampa di bawah poo galon', 1, 2030, '7827932111283', 1, 4),
(43, 'ruang hampa di bawah poo ahaa galon', 1, 2030, '7827932111283', 1, 4),
(44, 'ruang hampa di bawah poo ah jaja aa galon', 1, 2030, '7827932111283', 1, 4),
(45, 'ruang hampa di bawah poo ah babaja aa galon', 1, 2030, '7827932111283', 1, 4),
(46, 'ruang hampa di bsss awah poo ah babaja aa galon', 1, 2030, '7827932111283', 1, 4),
(47, 'ruang hampa di bsss awah poo ah babaja aa galon', 1, 2030, '7827932111283', 1, 4),
(48, 'ruang hampa di bsss awah poo ah babaja aa galon', 1, 2030, '7827932111283', 15, 4),
(49, 'ruang hampa di bsss awah poo ah babaja aa galon', 1, 2030, '7827932111283', 0, 4),
(50, 'ruang hampa di bsss awah poo ah babaja aa galon', 1, 2030, '7827932111283', 0, 4),
(51, 'ruang hampa di bsss awah poo ah babaja aa galon', 1, 2030, '7827932111283', 1, 4);

-- --------------------------------------------------------

--
-- Table structure for table `loan_book`
--

CREATE TABLE `loan_book` (
  `loan_id` int NOT NULL,
  `book_id` int NOT NULL,
  `book_title` varchar(255) NOT NULL,
  `publisher_name` varchar(255) DEFAULT NULL,
  `publisher_address` varchar(255) DEFAULT NULL,
  `publisher_phone` varchar(20) DEFAULT NULL,
  `publisher_email` varchar(100) DEFAULT NULL,
  `publication_year` int DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `author_name` varchar(255) DEFAULT NULL,
  `author_biography` text,
  `status` enum('Good','Borrowed','Broken','Missing') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `loan_book`
--

INSERT INTO `loan_book` (`loan_id`, `book_id`, `book_title`, `publisher_name`, `publisher_address`, `publisher_phone`, `publisher_email`, `publication_year`, `isbn`, `author_name`, `author_biography`, `status`) VALUES
(13, 48, 'ruang hampa di bsss awah poo ah babaja aa galon', NULL, NULL, NULL, NULL, 2030, '7827932111283', NULL, NULL, 'Good'),
(14, 1, 'Hantu bawah meja', NULL, NULL, NULL, NULL, 2004, '7822923', NULL, NULL, 'Missing'),
(15, 2, 'ruang hampa', NULL, NULL, NULL, NULL, 2030, '782793283', NULL, NULL, 'Good'),
(16, 48, 'ruang hampa di bsss awah poo ah babaja aa galon', 'Unknown Publisher', 'Unknown Address', 'Unknown Phone', 'Unknown Email', 2030, '7827932111283', 'Unknown Author', 'No Biography Available', 'Borrowed'),
(17, 1, 'Hantu bawah meja', 'Unknown Publisher', 'Unknown Address', 'Unknown Phone', 'Unknown Email', 2004, '7822923', 'Unknown Author', 'No Biography Available', 'Borrowed'),
(18, 2, 'ruang hampa', 'Unknown Publisher', 'Unknown Address', 'Unknown Phone', 'Unknown Email', 2030, '782793283', 'Unknown Author', 'No Biography Available', 'Borrowed');

-- --------------------------------------------------------

--
-- Table structure for table `loan_user`
--

CREATE TABLE `loan_user` (
  `loan_id` int NOT NULL,
  `transaction_id` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `user_id` int NOT NULL,
  `loan_date` datetime DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `loan_user`
--

INSERT INTO `loan_user` (`loan_id`, `transaction_id`, `user_id`, `loan_date`, `username`, `email`, `full_name`, `address`) VALUES
(13, '13082024-001', 2, '2024-08-13 02:54:30', 'shyakina', 'shyakina@gmail.com', 'babayo', 'jln melati'),
(14, '13082024-001', 2, '2024-08-13 02:54:30', 'shyakina', 'shyakina@gmail.com', 'babayo', 'jln melati'),
(15, '13082024-001', 2, '2024-08-13 02:54:30', 'shyakina', 'shyakina@gmail.com', 'babayo', 'jln melati'),
(16, '13082024-002', 2, '2024-08-13 02:58:49', 'shyakina', 'shyakina@gmail.com', 'babayo', 'jln melati'),
(17, '13082024-002', 2, '2024-08-13 02:58:49', 'shyakina', 'shyakina@gmail.com', 'babayo', 'jln melati'),
(18, '13082024-002', 2, '2024-08-13 02:58:49', 'shyakina', 'shyakina@gmail.com', 'babayo', 'jln melati');

-- --------------------------------------------------------

--
-- Table structure for table `member`
--

CREATE TABLE `member` (
  `user_id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `member`
--

INSERT INTO `member` (`user_id`, `username`, `email`, `full_name`, `address`) VALUES
(2, 'shyakina', 'shyakina@gmail.com', 'babayo', 'jln melati'),
(3, 'shyakinard', 'shyakinard@gmail.com', 'raden ajeng shyakina rahmadyani ariniputri', 'jln jaktim as'),
(4, 'shyakinardaz', 'shyakinard@gmail.coms', 'raden ajeng shyakina rahmadyani ariniputri', 'jln jaktim as'),
(5, 'shyakinaraad', 'shyakinarswswd@gmail.com', 'raden ajeng shyakina rahmadyani ariniputri', 'jln jaktim as'),
(6, 'dimas', 'dimas@gmail.com', 'dimas al rafi\'i', 'jln hello world');

-- --------------------------------------------------------

--
-- Table structure for table `publisher`
--

CREATE TABLE `publisher` (
  `publisher_id` int NOT NULL,
  `publisher_name` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `publisher`
--

INSERT INTO `publisher` (`publisher_id`, `publisher_name`, `address`, `phone`, `email`) VALUES
(1, 'La tahzan', '123 Publisher Address, City, Country', '1234567890', 'publisher@example.com');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `author`
--
ALTER TABLE `author`
  ADD PRIMARY KEY (`author_id`);

--
-- Indexes for table `catalog_books`
--
ALTER TABLE `catalog_books`
  ADD PRIMARY KEY (`book_id`),
  ADD KEY `fk_publisher` (`publisher_id`),
  ADD KEY `author_id_3` (`author_id`) USING BTREE;

--
-- Indexes for table `loan_book`
--
ALTER TABLE `loan_book`
  ADD PRIMARY KEY (`loan_id`,`book_id`);

--
-- Indexes for table `loan_user`
--
ALTER TABLE `loan_user`
  ADD PRIMARY KEY (`loan_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `member`
--
ALTER TABLE `member`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `publisher`
--
ALTER TABLE `publisher`
  ADD PRIMARY KEY (`publisher_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `author`
--
ALTER TABLE `author`
  MODIFY `author_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `catalog_books`
--
ALTER TABLE `catalog_books`
  MODIFY `book_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `loan_user`
--
ALTER TABLE `loan_user`
  MODIFY `loan_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `member`
--
ALTER TABLE `member`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `publisher`
--
ALTER TABLE `publisher`
  MODIFY `publisher_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `catalog_books`
--
ALTER TABLE `catalog_books`
  ADD CONSTRAINT `fk_author` FOREIGN KEY (`author_id`) REFERENCES `author` (`author_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  ADD CONSTRAINT `fk_publisher` FOREIGN KEY (`publisher_id`) REFERENCES `publisher` (`publisher_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `loan_book`
--
ALTER TABLE `loan_book`
  ADD CONSTRAINT `loan_book_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loan_user` (`loan_id`);

--
-- Constraints for table `loan_user`
--
ALTER TABLE `loan_user`
  ADD CONSTRAINT `loan_user_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `member` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
