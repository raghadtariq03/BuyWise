-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 03, 2025 at 03:45 PM
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
-- Database: `buywise`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `AdminID` int(10) UNSIGNED NOT NULL,
  `AdminName` varchar(150) NOT NULL,
  `AdminEmail` varchar(150) NOT NULL,
  `AdminPassword` varchar(255) NOT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`AdminID`, `AdminName`, `AdminEmail`, `AdminPassword`, `CreatedAt`) VALUES
(1, 'Islam', 'islamemad1346@gmail.com', '$2y$10$BfbplmyaHu2N74h9eNbi4Otfl/bwIRYHfDefNjkPVWTzmTbhblFWO', '2025-05-16 14:03:00');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `CategoryID` int(10) UNSIGNED NOT NULL,
  `CategoryName_en` varchar(100) DEFAULT NULL,
  `CategoryName_ar` varchar(100) DEFAULT NULL,
  `CategoryImage` text NOT NULL,
  `CategoryStatus` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`CategoryID`, `CategoryName_en`, `CategoryName_ar`, `CategoryImage`, `CategoryStatus`) VALUES
(1, 'Fashion & Accessories', 'الأزياء والإكسسوارات', '1746847253_Fashion.png', 1),
(2, 'Automotive & Tools', 'السيارات والأدوات', '1746847475_Automotive.png', 1),
(3, 'Baby & Maternity', 'الأطفال والأمومة', '1746847496_Baby.png', 1),
(4, 'Books', 'الكتب', '1746847520_Books.png', 1),
(5, 'Movies', 'الأفلام', '1746847532_Movies.png', 1),
(6, 'Sports & Fitness', 'الرياضة واللياقة البدنية', '1746847583_Sports.png', 1),
(7, 'Beauty', 'الجمال', '1746847595_Beauty.png', 1),
(8, 'Electronics', 'الإلكترونيات', '1746847607_Electronics.png', 1),
(9, 'Home', 'المنزل', '1746847620_Home.png', 1),
(10, 'Pets Care', 'رعاية الحيوانات الأليفة', '1746847637_Pets.png', 1),
(11, 'Eco-friendly', 'صديق للبيئة', '1746847658_Green.png', 1),
(12, 'Local', 'محلي', '1746847669_Local.png', 1);

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `CommentID` int(10) UNSIGNED NOT NULL,
  `UserID` int(10) UNSIGNED NOT NULL,
  `ProductID` int(10) UNSIGNED DEFAULT NULL,
  `CproductID` int(10) UNSIGNED DEFAULT NULL,
  `CommentText` text NOT NULL,
  `CommentDate` datetime DEFAULT current_timestamp(),
  `CommentStatus` tinyint(1) NOT NULL DEFAULT 1,
  `ParentCommentID` int(10) UNSIGNED DEFAULT NULL,
  `Comment_reply` text DEFAULT NULL,
  `Rating` int(11) NOT NULL DEFAULT 5,
  `CommentImage` varchar(255) DEFAULT NULL,
  `QualityRating` tinyint(3) UNSIGNED DEFAULT NULL,
  `IsFake` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `comment_likes`
--

CREATE TABLE `comment_likes` (
  `LikeID` int(10) UNSIGNED NOT NULL,
  `CommentID` int(10) UNSIGNED NOT NULL,
  `UserID` int(10) UNSIGNED NOT NULL,
  `LikeDate` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `CompanyID` int(10) UNSIGNED NOT NULL,
  `CompanyName` varchar(255) NOT NULL,
  `CompanyEmail` varchar(255) NOT NULL,
  `CompanyPassword` varchar(255) NOT NULL,
  `CompanyLogo` varchar(255) DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `CompanyStatus` tinyint(1) DEFAULT 0,
  `Verified` tinyint(1) DEFAULT 0,
  `VerifyToken` varchar(64) DEFAULT NULL,
  `Country` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_products`
--

CREATE TABLE `company_products` (
  `ProductID` int(10) UNSIGNED NOT NULL,
  `CategoryID` int(10) UNSIGNED NOT NULL,
  `CompanyID` int(10) UNSIGNED NOT NULL,
  `ProductName` varchar(100) NOT NULL,
  `ProductImage` text NOT NULL,
  `ProductDescription` text DEFAULT NULL,
  `ProductPrice` decimal(10,2) NOT NULL,
  `ProductStatus` tinyint(1) DEFAULT 0,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `IsLocal` tinyint(1) DEFAULT 0,
  `LocalProductNumber` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_product_images`
--

CREATE TABLE `company_product_images` (
  `ImageID` int(10) UNSIGNED NOT NULL,
  `ProductID` int(10) UNSIGNED NOT NULL,
  `ImageName` varchar(255) NOT NULL,
  `UploadedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_vouchers`
--

CREATE TABLE `company_vouchers` (
  `VoucherID` int(10) UNSIGNED NOT NULL,
  `CompanyID` int(10) UNSIGNED NOT NULL,
  `VoucherCode` varchar(50) NOT NULL,
  `Discount` decimal(5,2) NOT NULL,
  `ExpiryDate` date NOT NULL,
  `MinPoints` int(10) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `recipient_id` int(10) UNSIGNED NOT NULL,
  `sender_id` int(10) UNSIGNED DEFAULT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `recipient_type` enum('user','admin') DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `ProductID` int(10) UNSIGNED NOT NULL,
  `CategoryID` int(10) UNSIGNED NOT NULL,
  `UserID` int(10) UNSIGNED NOT NULL,
  `ProductName` varchar(100) NOT NULL,
  `ProductImage` text NOT NULL,
  `ProductDescription` text DEFAULT NULL,
  `ProductStatus` tinyint(1) DEFAULT 1,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `ProductRating` int(11) DEFAULT 0,
  `IsFake` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `ImageID` int(10) UNSIGNED NOT NULL,
  `ProductID` int(10) UNSIGNED NOT NULL,
  `ImageName` varchar(255) NOT NULL,
  `UploadedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reported_comments`
--

CREATE TABLE `reported_comments` (
  `ReportID` int(10) UNSIGNED NOT NULL,
  `CommentID` int(10) UNSIGNED NOT NULL,
  `UserID` int(10) UNSIGNED NOT NULL,
  `ReportReason` varchar(255) NOT NULL,
  `ReportDate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reported_reviews`
--

CREATE TABLE `reported_reviews` (
  `ReportID` int(10) UNSIGNED NOT NULL,
  `UserID` int(10) UNSIGNED NOT NULL,
  `CommentID` int(10) UNSIGNED DEFAULT NULL,
  `ReportDate` datetime NOT NULL DEFAULT current_timestamp(),
  `Reason` varchar(255) DEFAULT 'Fake Review',
  `ProductID` int(10) UNSIGNED DEFAULT NULL,
  `ReviewContent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `UserID` int(10) UNSIGNED NOT NULL,
  `UserName` varchar(200) NOT NULL,
  `UserEmail` varchar(100) NOT NULL,
  `UserPassword` varchar(255) NOT NULL,
  `UserBirth` date DEFAULT NULL,
  `UserGender` enum('Male','Female') NOT NULL,
  `UserAddress` varchar(255) DEFAULT NULL,
  `UserPhone` varchar(20) DEFAULT NULL,
  `UserStatus` tinyint(1) NOT NULL DEFAULT 1,
  `DeactivateUntil` datetime DEFAULT NULL,
  `Avatar` varchar(255) DEFAULT 'MaleDef.png',
  `Bio` text DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reset_token` varchar(64) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `points` int(11) DEFAULT 0,
  `badge` varchar(50) DEFAULT 'Normal',
  `badge_rank` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `set_avatar_by_gender` BEFORE INSERT ON `users` FOR EACH ROW BEGIN
  IF (NEW.Avatar IS NULL OR NEW.Avatar = '') AND NEW.UserGender = 'Female' THEN
    SET NEW.Avatar = 'FemDef.png';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_vouchers`
--

CREATE TABLE `user_vouchers` (
  `ID` int(10) UNSIGNED NOT NULL,
  `UserID` int(10) UNSIGNED NOT NULL,
  `VoucherID` int(10) UNSIGNED NOT NULL,
  `AssignedAt` datetime DEFAULT current_timestamp(),
  `IsRedeemed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`AdminID`),
  ADD UNIQUE KEY `AdminEmail` (`AdminEmail`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`CategoryID`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`CommentID`),
  ADD KEY `idx_product_comments` (`ProductID`),
  ADD KEY `idx_comment_replies` (`Comment_reply`(768)),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `fk_parent_comment` (`ParentCommentID`),
  ADD KEY `comments_ibfk_3` (`CproductID`);

--
-- Indexes for table `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD PRIMARY KEY (`LikeID`),
  ADD UNIQUE KEY `unique_like` (`CommentID`,`UserID`),
  ADD KEY `comment_likes_ibfk_2` (`UserID`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`CompanyID`),
  ADD UNIQUE KEY `CompanyEmail` (`CompanyEmail`);

--
-- Indexes for table `company_products`
--
ALTER TABLE `company_products`
  ADD PRIMARY KEY (`ProductID`),
  ADD KEY `CategoryID` (`CategoryID`),
  ADD KEY `CompanyID` (`CompanyID`);

--
-- Indexes for table `company_product_images`
--
ALTER TABLE `company_product_images`
  ADD PRIMARY KEY (`ImageID`),
  ADD KEY `ProductID` (`ProductID`);

--
-- Indexes for table `company_vouchers`
--
ALTER TABLE `company_vouchers`
  ADD PRIMARY KEY (`VoucherID`),
  ADD KEY `CompanyID` (`CompanyID`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipient_id` (`recipient_id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`ProductID`),
  ADD KEY `CategoryID` (`CategoryID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`ImageID`),
  ADD KEY `FK_ProductImages` (`ProductID`);

--
-- Indexes for table `reported_comments`
--
ALTER TABLE `reported_comments`
  ADD PRIMARY KEY (`ReportID`),
  ADD KEY `CommentID` (`CommentID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `reported_reviews`
--
ALTER TABLE `reported_reviews`
  ADD PRIMARY KEY (`ReportID`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `CommentID` (`CommentID`),
  ADD KEY `ProductID` (`ProductID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`UserID`),
  ADD UNIQUE KEY `UserEmail` (`UserEmail`);

--
-- Indexes for table `user_vouchers`
--
ALTER TABLE `user_vouchers`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `VoucherID` (`VoucherID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `AdminID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `CategoryID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `CommentID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=193;

--
-- AUTO_INCREMENT for table `comment_likes`
--
ALTER TABLE `comment_likes`
  MODIFY `LikeID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `CompanyID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `company_products`
--
ALTER TABLE `company_products`
  MODIFY `ProductID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `company_product_images`
--
ALTER TABLE `company_product_images`
  MODIFY `ImageID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `company_vouchers`
--
ALTER TABLE `company_vouchers`
  MODIFY `VoucherID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=329;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `ProductID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=136;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `ImageID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `reported_comments`
--
ALTER TABLE `reported_comments`
  MODIFY `ReportID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `reported_reviews`
--
ALTER TABLE `reported_reviews`
  MODIFY `ReportID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `UserID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `user_vouchers`
--
ALTER TABLE `user_vouchers`
  MODIFY `ID` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_3` FOREIGN KEY (`CproductID`) REFERENCES `company_products` (`ProductID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_parent_comment` FOREIGN KEY (`ParentCommentID`) REFERENCES `comments` (`CommentID`) ON DELETE CASCADE;

--
-- Constraints for table `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD CONSTRAINT `comment_likes_ibfk_1` FOREIGN KEY (`CommentID`) REFERENCES `comments` (`CommentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `comment_likes_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `company_products`
--
ALTER TABLE `company_products`
  ADD CONSTRAINT `fk_company_products_category` FOREIGN KEY (`CategoryID`) REFERENCES `categories` (`CategoryID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_company_products_company` FOREIGN KEY (`CompanyID`) REFERENCES `companies` (`CompanyID`) ON DELETE CASCADE;

--
-- Constraints for table `company_product_images`
--
ALTER TABLE `company_product_images`
  ADD CONSTRAINT `company_product_images_ibfk_1` FOREIGN KEY (`ProductID`) REFERENCES `company_products` (`ProductID`) ON DELETE CASCADE;

--
-- Constraints for table `company_vouchers`
--
ALTER TABLE `company_vouchers`
  ADD CONSTRAINT `company_vouchers_ibfk_1` FOREIGN KEY (`CompanyID`) REFERENCES `companies` (`CompanyID`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`UserID`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_user` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE,
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`CategoryID`) REFERENCES `categories` (`CategoryID`) ON DELETE CASCADE,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `FK_ProductImages` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`) ON DELETE CASCADE;

--
-- Constraints for table `reported_comments`
--
ALTER TABLE `reported_comments`
  ADD CONSTRAINT `reported_comments_ibfk_1` FOREIGN KEY (`CommentID`) REFERENCES `comments` (`CommentID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `reported_comments_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `reported_reviews`
--
ALTER TABLE `reported_reviews`
  ADD CONSTRAINT `fk_reported_reviews_comment` FOREIGN KEY (`CommentID`) REFERENCES `comments` (`CommentID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reported_reviews_product` FOREIGN KEY (`ProductID`) REFERENCES `products` (`ProductID`) ON DELETE CASCADE,
  ADD CONSTRAINT `reported_reviews_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE,
  ADD CONSTRAINT `reported_reviews_ibfk_3` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `user_vouchers`
--
ALTER TABLE `user_vouchers`
  ADD CONSTRAINT `user_vouchers_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_vouchers_ibfk_2` FOREIGN KEY (`VoucherID`) REFERENCES `company_vouchers` (`VoucherID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
