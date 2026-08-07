-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 07, 2026 at 10:24 AM
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
-- Database: `edurank_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_insights`
--

CREATE TABLE `ai_insights` (
  `insight_id` int(11) NOT NULL,
  `evaluation_id` int(11) DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `recommendation` text DEFAULT NULL,
  `generated_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `application_verifications`
--

CREATE TABLE `application_verifications` (
  `verification_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `advisor_id` int(11) NOT NULL,
  `calculated_score` decimal(5,2) DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `verification_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_verifications`
--

INSERT INTO `application_verifications` (`verification_id`, `application_id`, `advisor_id`, `calculated_score`, `remarks`, `verification_date`) VALUES
(1, 6, 18, 83.00, 'no good, mohon semula, thanks', '2026-08-03 19:17:07'),
(2, 8, 18, 97.00, 'good', '2026-08-03 22:57:20'),
(3, 10, 18, 95.00, 'good', '2026-08-04 20:47:56'),
(4, 12, 18, 87.00, 'good', '2026-08-04 21:31:13'),
(5, 14, 18, 92.00, 'not done', '2026-08-04 21:39:54'),
(7, 18, 18, 105.00, 'good', '2026-08-04 21:55:10'),
(8, 20, 18, 85.40, 'good', '2026-08-05 15:52:18');

-- --------------------------------------------------------

--
-- Table structure for table `award_applications`
--

CREATE TABLE `award_applications` (
  `application_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `application_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','verified','rejected','evaluated') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `award_applications`
--

INSERT INTO `award_applications` (`application_id`, `student_id`, `category_id`, `application_date`, `status`) VALUES
(6, 2, 1, '2026-08-03 11:11:00', 'rejected'),
(8, 2, 1, '2026-08-03 14:44:10', 'evaluated'),
(10, 3, 1, '2026-08-04 12:46:46', 'evaluated'),
(12, 3, 3, '2026-08-04 13:30:41', 'evaluated'),
(14, 3, 2, '2026-08-04 13:33:36', 'rejected'),
(18, 2, 2, '2026-08-04 13:54:32', 'evaluated'),
(20, 2, 2, '2026-08-05 07:45:13', 'verified');

-- --------------------------------------------------------

--
-- Table structure for table `award_categories`
--

CREATE TABLE `award_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `award_categories`
--

INSERT INTO `award_categories` (`category_id`, `category_name`, `description`, `created_at`) VALUES
(1, 'Industry', 'For students with outstanding academic performance with CGPA 3.50 and above', '2026-07-28 09:03:04'),
(2, 'Leadership', 'For students who demonstrate exceptional leadership skills in co-curricular activities', '2026-07-28 09:03:04'),
(3, 'Sport', 'For students with outstanding achievements in sports at university/national level', '2026-07-28 09:03:04'),
(4, 'Entrepreneurship', 'For students with creative and innovative projects that benefit the community', '2026-07-28 09:03:04'),
(5, 'Volunteering', 'For students who actively contribute to community service and volunteer work', '2026-07-28 09:03:04'),
(6, 'Anugerah Academic & Cocu', 'For students who demonstrate overall excellence in both academic performance and co-curricular achievements.', '2026-08-07 06:27:51');

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `certificate_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `certificate_type` enum('a','b','c','d','e') NOT NULL,
  `extra_data` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`certificate_id`, `application_id`, `file_name`, `certificate_type`, `extra_data`) VALUES
(1, 6, 'transcript.pdf', 'a', '4.00'),
(2, 6, '1785755460_6a707744a8cf6.pdf', 'b', '{\"org_name\":\"JPP\",\"position\":\"JPP_EXCO\"}'),
(3, 6, '1785755460_6a707744a90ce.pdf', 'c', '{\"name\":\"Program Inovation\",\"role\":\"TIMBALAN\",\"level\":\"POLITEKNIK\"}'),
(4, 6, '1785755460_6a707744ab2ad.pdf', 'c', '{\"name\":\"Program Pelajar Cermelang\",\"role\":\"AJK\",\"level\":\"POLITEKNIK\"}'),
(5, 6, '1785755460_6a707744ab8de.pdf', 'd', '{\"name\":\"Intro to Agentic AI - with Krenovator\",\"level\":\"POLITEKNIK\"}'),
(6, 6, '1785755460_6a707744abc01.pdf', 'd', '{\"name\":\"Program Literasi Digital Siri 2 : Privasi dan Jejak Digital\",\"level\":\"POLITEKNIK\"}'),
(7, 6, '1785755460_6a707744abe73.pdf', 'd', '{\"name\":\"i-MATRIX\'25 PARALLEL SESSION 2F\",\"level\":\"POLITEKNIK\"}'),
(8, 8, 'transcript.pdf', 'a', '4'),
(9, 8, 'org_proof.pdf', 'b', '{\"org_name\":\"IT\",\"position\":\"JPP_YDP\"}'),
(10, 8, 'prog1_proof.pdf', 'c', '{\"name\":\"a\",\"role\":\"PENGARAH\",\"level\":\"NEGERI\"}'),
(11, 8, 'prog2_proof.pdf', 'c', '{\"name\":\"a\",\"role\":\"AHLI\",\"level\":\"POLITEKNIK\"}'),
(12, 8, 'part1_proof.pdf', 'd', '{\"name\":\"a\",\"level\":\"ANTARABANGSA\"}'),
(13, 8, 'part2_proof.pdf', 'd', '{\"name\":\"a\",\"level\":\"ANTARABANGSA\"}'),
(14, 8, 'part3_proof.pdf', 'd', '{\"name\":\"a\",\"level\":\"POLITEKNIK\"}'),
(15, 8, 'bonus_prof.pdf', 'e', '{\"type\":\"Professional_Cert\"}'),
(16, 8, 'bonus_ext.pdf', 'e', '{\"type\":\"External_Recognition\"}'),
(17, 8, 'bonus_intl.pdf', 'e', '{\"type\":\"International_Involvement\"}'),
(18, 10, 'transcript.pdf', 'a', '3.8'),
(19, 10, 'org_proof.pdf', 'b', '{\"org_name\":\"IT\",\"position\":\"JPP_SU_BEND\"}'),
(20, 10, 'prog1_proof.pdf', 'c', '{\"name\":\"a\",\"role\":\"TIMBALAN\",\"level\":\"NEGERI\"}'),
(21, 10, 'prog2_proof.pdf', 'c', '{\"name\":\"a\",\"role\":\"TIMBALAN\",\"level\":\"NEGERI\"}'),
(22, 10, 'part1_proof.pdf', 'd', '{\"name\":\"a\",\"level\":\"NEGERI\"}'),
(23, 10, 'part2_proof.pdf', 'd', '{\"name\":\"a\",\"level\":\"NEGERI\"}'),
(24, 10, 'part3_proof.pdf', 'd', '{\"name\":\"a\",\"level\":\"NEGERI\"}'),
(25, 10, 'bonus_prof.pdf', 'e', '{\"type\":\"Professional_Cert\"}'),
(26, 10, 'bonus_ext.pdf', 'e', '{\"type\":\"External_Recognition\"}'),
(27, 10, 'bonus_intl.pdf', 'e', '{\"type\":\"International_Involvement\"}'),
(28, 12, 'transcript.pdf', 'a', '3.9'),
(29, 12, 'org_proof.pdf', 'b', '{\"org_name\":\"IT\",\"position\":\"CLUB_AJK\"}'),
(30, 12, 'prog1_proof.pdf', 'c', '{\"name\":\"a\",\"role\":\"SU_BEND\",\"level\":\"NEGERI\"}'),
(31, 12, 'prog2_proof.pdf', 'c', '{\"name\":\"a\",\"role\":\"SU_BEND\",\"level\":\"NEGERI\"}'),
(32, 12, 'part1_proof.pdf', 'd', '{\"name\":\"a\",\"level\":\"POLITEKNIK\"}'),
(33, 12, 'part2_proof.pdf', 'd', '{\"name\":\"a\",\"level\":\"NEGERI\"}'),
(34, 12, 'part3_proof.pdf', 'd', '{\"name\":\"a\",\"level\":\"POLITEKNIK\"}'),
(35, 12, 'bonus_prof.pdf', 'e', '{\"type\":\"Professional_Cert\"}'),
(36, 12, 'bonus_ext.pdf', 'e', '{\"type\":\"External_Recognition\"}'),
(37, 12, 'bonus_intl.pdf', 'e', '{\"type\":\"International_Involvement\"}'),
(38, 14, 'transcript.pdf', 'a', '3.5'),
(39, 14, 'org_proof.pdf', 'b', '{\"org_name\":\"IT\",\"position\":\"CLUB_NAIB\"}'),
(40, 14, 'prog1_proof.pdf', 'c', '{\"name\":\"a\",\"role\":\"PENGARAH\",\"level\":\"ANTARABANGSA\"}'),
(41, 14, 'prog2_proof.pdf', 'c', '{\"name\":\"a\",\"role\":\"SU_BEND\",\"level\":\"ANTARABANGSA\"}'),
(42, 14, 'part1_proof.pdf', 'd', '{\"name\":\"a\",\"level\":\"ANTARABANGSA\"}'),
(43, 14, 'part2_proof.pdf', 'd', '{\"name\":\"a\",\"level\":\"ANTARABANGSA\"}'),
(44, 14, 'part3_proof.pdf', 'd', '{\"name\":\"a\",\"level\":\"ANTARABANGSA\"}'),
(45, 14, 'bonus_prof.pdf', 'e', '{\"type\":\"Professional_Cert\"}'),
(46, 14, 'bonus_ext.pdf', 'e', '{\"type\":\"External_Recognition\"}'),
(56, 18, 'transcript.pdf', 'a', '4'),
(57, 18, 'org_proof.pdf', 'b', '{\"org_name\":\"IT\",\"position\":\"JPP_NYDP\"}'),
(58, 18, 'prog1_proof.pdf', 'c', '{\"name\":\"a\",\"role\":\"PENGARAH\",\"level\":\"ANTARABANGSA\"}'),
(59, 18, 'prog2_proof.pdf', 'c', '{\"name\":\"a\",\"role\":\"PENGARAH\",\"level\":\"ANTARABANGSA\"}'),
(60, 18, 'part1_proof.pdf', 'd', '{\"name\":\"a\",\"level\":\"ANTARABANGSA\"}'),
(61, 18, 'part2_proof.pdf', 'd', '{\"name\":\"a\",\"level\":\"ANTARABANGSA\"}'),
(62, 18, 'part3_proof.pdf', 'd', '{\"name\":\"a\",\"level\":\"ANTARABANGSA\"}'),
(63, 18, 'bonus_prof.pdf', 'e', '{\"type\":\"Professional_Cert\"}'),
(64, 18, 'bonus_ext.pdf', 'e', '{\"type\":\"External_Recognition\"}'),
(65, 18, 'bonus_intl.pdf', 'e', '{\"type\":\"International_Involvement\"}'),
(66, 20, 'transcript.pdf', 'a', '3.74'),
(67, 20, 'org_proof.pdf', 'b', '{\"org_name\":\"JPP\",\"position\":\"JPP_EXCO\"}'),
(68, 20, '1785915913_6a72ea099ace8.pdf', 'c', '{\"name\":\"Program Inovation\",\"role\":\"SU_BEND\",\"level\":\"NEGERI\"}'),
(69, 20, '1785915913_6a72ea099b24a.pdf', 'c', '{\"name\":\"Program Pelajar Cermelang\",\"role\":\"SU_BEND\",\"level\":\"NEGERI\"}'),
(70, 20, '1785915913_6a72ea099b703.pdf', 'd', '{\"name\":\"Intro to Agentic AI - with Krenovator\",\"level\":\"NEGERI\"}'),
(71, 20, '1785915913_6a72ea099bbd3.pdf', 'd', '{\"name\":\"Program Literasi Digital Siri 2 : Privasi dan Jejak Digital\",\"level\":\"NEGERI\"}'),
(72, 20, '1785915913_6a72ea099bf1b.pdf', 'd', '{\"name\":\"i-MATRIX\'25 PARALLEL SESSION 2F\",\"level\":\"NEGERI\"}');

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `evaluation_id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `committee_id` int(11) DEFAULT NULL,
  `total_score` decimal(5,2) DEFAULT NULL,
  `remarks` text NOT NULL,
  `evaluation_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluations`
--

INSERT INTO `evaluations` (`evaluation_id`, `application_id`, `committee_id`, `total_score`, `remarks`, `evaluation_date`) VALUES
(1, 8, 33, 97.00, 'good', '2026-08-03 15:14:57'),
(2, 10, 33, 95.00, 'good', '2026-08-04 13:10:40'),
(3, 12, 33, 87.00, 'good', '2026-08-04 13:31:49'),
(4, 18, 33, 105.00, 'g', '2026-08-04 13:56:35');

-- --------------------------------------------------------

--
-- Table structure for table `report_logs`
--

CREATE TABLE `report_logs` (
  `log_id` int(11) NOT NULL,
  `report_title` varchar(255) NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `category_id` varchar(10) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `matric_no` varchar(20) NOT NULL,
  `programme` varchar(100) DEFAULT 'DIT',
  `semester` int(11) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `advisor_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `matric_no`, `programme`, `semester`, `phone`, `advisor_id`, `created_at`) VALUES
(1, 34, '13DIT24F1195', 'DIT', 5, '01139989677', 18, '2026-07-28 09:03:51'),
(2, 36, '13DIT24F1192', 'DIT', 5, '0167567283', 18, '2026-08-03 10:56:06'),
(3, 38, '13DIT24F1178', 'DIT', 5, '01136951645', 18, '2026-08-04 12:44:52');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Student','Academic Advisor','Evaluation Committee') NOT NULL,
  `must_change_password` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `role`, `must_change_password`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Ramlah Binti Md. Zain', 'ramlah@edurank.com', '$2y$10$8.cWDgJ5AdOjnvc2s.ArC.jq5v.luEC3zL6VOYImL34WWOMRB9EkC', 'Academic Advisor', 0, 1, '2026-07-28 09:03:04', '2026-08-05 05:20:31'),
(2, 'Mohd Hasrizam Bin Hasan', 'hasrizam@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-08-05 05:19:47'),
(3, 'Nor Syahadataini Binti Awang', 'syahadataini@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(4, 'Syaiful Bacchtiar Bin Nen @ Shahinan', 'syaiful@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(5, 'Kamalul Hayat Bin Raman', 'kamalul@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(6, 'Suziwati Binti Yusof', 'suziwati@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(7, 'Hairi Bin Alias', 'hairi@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(8, 'Murniyati Binti Abdul', 'murniyati@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(9, 'Mazlina Binti Mustapha', 'mazlina.mustapha@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(10, 'Suzana Binti Yusof', 'suzana.yusof@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(11, 'Fauziah Binti Basok', 'fauziah@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(12, 'Wan Haniza Binti Wan Hassim', 'wan.haniza@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(13, 'Raja Intan Sariah Binti Raja Mahmood', 'raja.intan@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(14, 'Nor Hayati Binti Mohammed Sani', 'nor.hayati@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(15, 'Zukia Aniza Binti Ibrahim', 'zukia@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(16, 'Nor Azlina Binti Ibrahim', 'nor.azlina@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(17, 'Zuraini Binti Abdul Rajab', 'zuraini@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(18, 'Rislah Binti Zakria', 'rislah@edurank.com', '$2y$10$DsnUzym0EhgCVcQLDH7Yt.hrE7m45gjW3HCGBeNv6i..ZrNxhdF2C', 'Academic Advisor', 0, 1, '2026-07-28 09:03:04', '2026-08-05 07:46:36'),
(19, 'Efeza Binti Che Apandey', 'efeza@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(20, 'Zarina Binti Musa', 'zarina.musa@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(21, 'Nor Aznira Binti Yusoff', 'nor.aznira@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(22, 'Shaifatul Ain Binti Mohamad', 'shaifatul@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(23, 'Siti Nasrah Binti Mukhtar', 'siti.nasrah@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(24, 'Mazidah Binti Musa', 'mazidah@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(25, 'Nor Aidawati Binti Abdillah', 'aidawati@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(26, 'Nor Zilaila Binti Jaafar', 'zilaila@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(27, 'Norhayati Saadah Binti Che Abd Razak', 'norhayati.saadah@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(28, 'Zainal Fitri Bin Mohd Zolkifli', 'zainal.fitri@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(29, 'Syahieda Binti Zakaria', 'syahieda@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(30, 'Nur Syarafina Binti Abdul Rahman', 'syarafina@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(31, 'Wan Noor Aishah Binti Wan Chek', 'wan.noor@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(32, 'Liyana Binti Mat Rani', 'liyana@edurank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-07-28 09:03:04', '2026-07-28 09:03:04'),
(33, 'Committee', 'committee', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Evaluation Committee', 0, 1, '2026-07-28 09:03:04', '2026-08-04 14:34:09'),
(34, 'SANJEEVAN A/L P S RAMESH KUMAR', 'kingsanjeevan5@gmail.com', '$2y$10$yypJUWVnvqgJMYz2loI5K.XtonTO1mrPG51tjxzgMfUiXk8oSAYja', 'Student', 0, 1, '2026-07-28 09:03:51', '2026-08-05 05:17:28'),
(36, 'Chan Jia Hong', 'hongc2303@gmail.com', '$2y$10$L.V0baGCow9QA4iU65NU5O9c8frd1FTXEvd0wr2nilhJJ9agMPwyS', 'Student', 0, 1, '2026-08-03 10:56:06', '2026-08-04 15:04:38'),
(37, 'Test PA Advisor', 'testpa@edu.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', 1, 1, '2026-08-03 11:52:19', '2026-08-05 05:19:47'),
(38, 'WANG ZHAO SHI', 'wang@gmail.com', '$2y$10$P62QpMW1r1PQKtJ0T7H0FeKmEbG.GzmPP6rpbnFoyLGR6u9uCaMFy', 'Student', 0, 1, '2026-08-04 12:44:52', '2026-08-05 05:17:28');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ai_insights`
--
ALTER TABLE `ai_insights`
  ADD PRIMARY KEY (`insight_id`),
  ADD KEY `evaluation_id` (`evaluation_id`);

--
-- Indexes for table `application_verifications`
--
ALTER TABLE `application_verifications`
  ADD PRIMARY KEY (`verification_id`),
  ADD UNIQUE KEY `application_id` (`application_id`),
  ADD KEY `advisor_id` (`advisor_id`);

--
-- Indexes for table `award_applications`
--
ALTER TABLE `award_applications`
  ADD PRIMARY KEY (`application_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `award_categories`
--
ALTER TABLE `award_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`certificate_id`),
  ADD KEY `application_id` (`application_id`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`evaluation_id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `committee_id` (`committee_id`);

--
-- Indexes for table `report_logs`
--
ALTER TABLE `report_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `matric_no` (`matric_no`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `pa_id` (`advisor_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ai_insights`
--
ALTER TABLE `ai_insights`
  MODIFY `insight_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `application_verifications`
--
ALTER TABLE `application_verifications`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `award_applications`
--
ALTER TABLE `award_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `award_categories`
--
ALTER TABLE `award_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `certificate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `evaluation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `report_logs`
--
ALTER TABLE `report_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_insights`
--
ALTER TABLE `ai_insights`
  ADD CONSTRAINT `ai_insights_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`evaluation_id`) ON DELETE CASCADE;

--
-- Constraints for table `application_verifications`
--
ALTER TABLE `application_verifications`
  ADD CONSTRAINT `application_verifications_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `award_applications` (`application_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `application_verifications_ibfk_2` FOREIGN KEY (`advisor_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `award_applications`
--
ALTER TABLE `award_applications`
  ADD CONSTRAINT `award_applications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `award_applications_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `award_categories` (`category_id`);

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `award_applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `award_applications` (`application_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`committee_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`advisor_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
