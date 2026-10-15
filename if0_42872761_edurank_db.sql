-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql312.infinityfree.com
-- Generation Time: Oct 15, 2026 at 02:19 AM
-- Server version: 11.4.13-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_42872761_edurank_db`
--

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
(17, 19, 38, '88.00', 'Good', '2026-09-19 06:03:59'),
(18, 20, 38, '88.10', 'recommend', '2026-09-19 07:31:04'),
(19, 21, 38, '0.00', 'bagus', '2026-09-19 07:31:35'),
(20, 22, 38, '90.40', 'hantar semula document tak lengkap', '2026-09-19 07:31:56'),
(21, 23, 38, '84.40', 'good student', '2026-09-19 07:32:11'),
(22, 24, 38, '85.50', 'hantar semula tajuk and file.pdf tak sama', '2026-09-19 07:32:32'),
(23, 25, 38, '86.60', 'good', '2026-09-19 07:33:42'),
(24, 26, 38, '0.00', 'Terbaik Project Final Year', '2026-09-19 07:33:59'),
(25, 27, 38, '89.80', 'Join banyak event industri', '2026-09-19 07:34:16'),
(26, 28, 38, '84.90', 'tak lengkap', '2026-09-19 07:34:26'),
(27, 29, 38, '90.00', 'Good', '2026-09-19 07:34:33'),
(28, 30, 38, '88.70', 'tak lengkap hantar semula', '2026-09-19 18:22:11'),
(29, 40, 38, '52.00', 'good', '2026-09-19 20:06:36'),
(30, 31, 38, '90.00', 'good', '2026-09-19 21:21:52');

-- --------------------------------------------------------

--
-- Table structure for table `award_applications`
--

CREATE TABLE `award_applications` (
  `application_id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `session_name` varchar(50) DEFAULT NULL,
  `application_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','verified','rejected','evaluated','nominated') DEFAULT 'pending',
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `award_applications`
--

INSERT INTO `award_applications` (`application_id`, `student_id`, `category_id`, `session_name`, `application_date`, `status`, `is_read`) VALUES
(19, 5, 1, 'Sesi 1 2026/2027', '2026-09-19 13:02:33', 'evaluated', 0),
(20, 6, 1, 'Sesi 1 2026/2027', '2026-09-19 13:28:11', 'nominated', 0),
(21, 7, 2, 'Sesi 1 2026/2027', '2026-09-19 13:36:24', 'verified', 0),
(22, 8, 3, 'Sesi 1 2026/2027', '2026-09-19 13:41:33', 'rejected', 0),
(23, 9, 4, 'Sesi 1 2026/2027', '2026-09-19 13:51:11', 'verified', 0),
(24, 10, 5, 'Sesi 1 2026/2027', '2026-09-19 13:56:10', 'rejected', 0),
(25, 11, 1, 'Sesi 1 2026/2027', '2026-09-19 14:04:02', 'verified', 0),
(26, 12, 2, 'Sesi 1 2026/2027', '2026-09-19 14:08:07', 'verified', 0),
(27, 13, 3, 'Sesi 1 2026/2027', '2026-09-19 14:13:32', 'evaluated', 0),
(28, 14, 6, 'Sesi 1 2026/2027', '2026-09-19 14:18:27', 'rejected', 0),
(29, 15, 7, 'Sesi 1 2026/2027', '2026-09-19 14:23:28', 'evaluated', 0),
(30, 16, 3, 'Sesi 1 2026/2027', '2026-09-19 17:46:38', 'rejected', 0),
(31, 17, 5, 'Sesi 1 2026/2027', '2026-09-19 17:49:25', 'verified', 0),
(32, 18, 4, 'Sesi 1 2026/2027', '2026-09-19 17:53:27', 'pending', 0),
(33, 19, 7, 'Sesi 1 2026/2027', '2026-09-19 17:57:53', 'pending', 0),
(34, 20, 5, 'Sesi 1 2026/2027', '2026-09-19 18:03:04', 'pending', 0),
(35, 21, 1, 'Sesi 1 2026/2027', '2026-09-19 18:05:15', 'pending', 0),
(36, 22, 6, 'Sesi 1 2026/2027', '2026-09-19 18:08:46', 'pending', 0),
(37, 23, 3, 'Sesi 1 2026/2027', '2026-09-19 18:13:00', 'pending', 0),
(38, 24, 1, 'Sesi 1 2026/2027', '2026-09-19 18:17:17', 'pending', 0),
(39, 25, 5, 'Sesi 1 2026/2027', '2026-09-19 18:21:46', 'pending', 0),
(40, 5, 6, 'Sesi 1 2026/2027', '2026-09-20 03:03:46', 'verified', 0);

-- --------------------------------------------------------

--
-- Table structure for table `award_categories`
--

CREATE TABLE `award_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_open` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `award_categories`
--

INSERT INTO `award_categories` (`category_id`, `category_name`, `description`, `is_open`, `created_at`) VALUES
(1, 'Anugerah Kecemerlangan Akademik dan Kokurikulum', 'Recognizes outstanding balance in academic excellence and co-curricular achievements. Minimum requirement: CGPA >= 3.60.', 1, '2026-08-11 04:36:51'),
(2, 'Anugerah Projek Terbaik', 'Honors exceptional achievement in final year projects, research, and innovation. Minimum requirement: CGPA >= 3.00.', 1, '2026-08-11 04:36:51'),
(3, 'Anugerah Khas (Industri)', 'Special award recognizing exceptional technical skills, practical application, and industry engagement. Minimum requirement: CGPA >= 3.00.', 1, '2026-08-11 04:36:51'),
(4, 'Anugerah Khas (Kepimpinan)', 'Special award honoring exemplary leadership and significant contributions to student bodies and organizations. Minimum requirement: CGPA >= 3.00.', 1, '2026-08-11 04:36:51'),
(5, 'Anugerah Khas (Sukan)', 'Special award celebrating outstanding athletic performance and representation in sports events. Minimum requirement: CGPA >= 3.00.', 1, '2026-08-11 04:36:51'),
(6, 'Anugerah Khas (Keusahawanan)', 'Special award recognizing active involvement and success in business, sales, and entrepreneurship initiatives. Minimum requirement: CGPA >= 3.00.', 1, '2026-08-11 04:36:51'),
(7, 'Anugerah Khas (Sukarelawan)', 'Special award commending impactful contributions to community service, charity, and volunteer programs. Minimum requirement: CGPA >= 3.00.', 1, '2026-08-11 04:36:51');

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `certificate_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `certificate_type` enum('a','b','c','d','e','p') NOT NULL,
  `extra_data` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`certificate_id`, `application_id`, `file_name`, `certificate_type`, `extra_data`) VALUES
(149, 19, '13DIT24F1192_Akademik_Transcript_1789822953_97.pdf', 'a', '3.80'),
(150, 19, '13DIT24F1192_Organisasi_JPP_1789822953_88.png', 'b', '{\"org_name\":\"JPP\",\"position\":\"JPP_NYDP\",\"type\":\"JPP\"}'),
(151, 19, '13DIT24F1192_Organisasi_Kelab_1789822953_55.png', 'b', '{\"org_name\":\"Clab IT\",\"position\":\"CLUB_AJK\",\"type\":\"Kelab\"}'),
(152, 19, '13DIT24F1192_Penganjuran_Prog1_1789822953_82.pdf', 'c', '{\"name\":\"Program Inovation\",\"role\":\"TIMBALAN\",\"level\":\"NEGERI\"}'),
(153, 19, '13DIT24F1192_Penganjuran_Prog2_1789822953_77.pdf', 'c', '{\"name\":\"Program Pelajar Cermelang\",\"role\":\"SU_BEND\",\"level\":\"NEGERI\"}'),
(154, 19, '13DIT24F1192_Penyertaan_Part1_1789822953_90.pdf', 'd', '{\"name\":\"Intro to Agentic AI - with Krenovator\",\"level\":\"POLITEKNIK\"}'),
(155, 19, '13DIT24F1192_Penyertaan_Part2_1789822953_63.pdf', 'd', '{\"name\":\"Program Literasi Digital Siri 2 : Privasi dan Jejak Digital\",\"level\":\"POLITEKNIK\"}'),
(156, 19, '13DIT24F1192_Penyertaan_Part3_1789822953_38.pdf', 'd', '{\"name\":\"i-MATRIX\'25 PARALLEL SESSION 2F\",\"level\":\"NEGERI\"}'),
(157, 19, '13DIT24F1192_Bonus_Profesional_1789822953_77.pdf', 'e', '{\"type\":\"Bonus_Profesional\"}'),
(158, 20, '13DIT25F1101_Akademik_Transcript_1789824491_92.pdf', 'a', '3.81'),
(159, 20, '13DIT25F1101_Organisasi_JPP_1789824491_50.pdf', 'b', '{\"org_name\":\"JPP\",\"position\":\"JPP_EXCO\",\"type\":\"JPP\"}'),
(160, 20, '13DIT25F1101_Organisasi_Kelab_1789824491_87.pdf', 'b', '{\"org_name\":\"KELAB\",\"position\":\"CLUB_PENGERUSI\",\"type\":\"Kelab\"}'),
(161, 20, '13DIT25F1101_Penganjuran_Prog1_1789824491_18.pdf', 'c', '{\"name\":\"PROGRAM\",\"role\":\"TIMBALAN\",\"level\":\"NEGERI\"}'),
(162, 20, '13DIT25F1101_Penganjuran_Prog2_1789824491_23.pdf', 'c', '{\"name\":\"PROGRAM\",\"role\":\"SU_BEND\",\"level\":\"NEGERI\"}'),
(163, 20, '13DIT25F1101_Penyertaan_Part1_1789824491_81.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"ANTARABANGSA\"}'),
(164, 20, '13DIT25F1101_Penyertaan_Part2_1789824491_72.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"POLITEKNIK\"}'),
(165, 20, '13DIT25F1101_Penyertaan_Part3_1789824491_25.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"JABATAN\"}'),
(166, 21, '13DIT25F1102_Akademik_Transcript_1789824984_24.pdf', 'a', '3.82'),
(167, 21, '13DIT25F1102_Projek_Bukti_1789824984_53.pdf', 'p', '{\"type\":\"Bukti_Pencapaian_FYP\"}'),
(168, 21, '13DIT25F1102_Projek_Industri_1789824984_85.pdf', 'p', '{\"type\":\"Sijil_Penghargaan_Industri\"}'),
(169, 22, '13DIT25F1103_Akademik_Transcript_1789825294_70.pdf', 'a', '3.84'),
(170, 22, '13DIT25F1103_Organisasi_JPP_1789825294_70.pdf', 'b', '{\"org_name\":\"JPP\",\"position\":\"JPP_YDP\",\"type\":\"JPP\"}'),
(171, 22, '13DIT25F1103_Organisasi_Kelab_1789825294_70.pdf', 'b', '{\"org_name\":\"KELAB\",\"position\":\"CLUB_PENGERUSI\",\"type\":\"Kelab\"}'),
(172, 22, '13DIT25F1103_Penganjuran_Prog1_1789825294_63.pdf', 'c', '{\"name\":\"PROGRAM\",\"role\":\"TIMBALAN\",\"level\":\"ANTARABANGSA\"}'),
(173, 22, '13DIT25F1103_Penganjuran_Prog2_1789825294_10.pdf', 'c', '{\"name\":\"PROGRAM\",\"role\":\"SU_BEND\",\"level\":\"NEGERI\"}'),
(174, 22, '13DIT25F1103_Penyertaan_Part1_1789825294_83.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"ANTARABANGSA\"}'),
(175, 22, '13DIT25F1103_Penyertaan_Part2_1789825294_98.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"JABATAN\"}'),
(176, 22, '13DIT25F1103_Penyertaan_Part3_1789825294_83.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"NEGERI\"}'),
(177, 23, '13DIT25F1104_Akademik_Transcript_1789825871_66.pdf', 'a', '3.84'),
(178, 23, '13DIT25F1104_Organisasi_JPP_1789825871_74.pdf', 'b', '{\"org_name\":\"JPP\",\"position\":\"JPP_NYDP\",\"type\":\"JPP\"}'),
(179, 23, '13DIT25F1104_Organisasi_Kelab_1789825871_94.pdf', 'b', '{\"org_name\":\"KELAB\",\"position\":\"CLUB_PENGERUSI\",\"type\":\"Kelab\"}'),
(180, 23, '13DIT25F1104_Penganjuran_Prog1_1789825871_80.pdf', 'c', '{\"name\":\"PROGRAM\",\"role\":\"SU_BEND\",\"level\":\"ANTARABANGSA\"}'),
(181, 23, '13DIT25F1104_Penganjuran_Prog2_1789825871_41.pdf', 'c', '{\"name\":\"PROGRAM\",\"role\":\"AJK\",\"level\":\"POLITEKNIK\"}'),
(182, 23, '13DIT25F1104_Penyertaan_Part1_1789825871_99.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"POLITEKNIK\"}'),
(183, 23, '13DIT25F1104_Penyertaan_Part2_1789825871_30.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"POLITEKNIK\"}'),
(184, 23, '13DIT25F1104_Penyertaan_Part3_1789825871_61.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"JABATAN\"}'),
(185, 24, '13DIT25F1105_Akademik_Transcript_1789826171_75.pdf', 'a', '3.85'),
(186, 24, '13DIT25F1105_Organisasi_JPP_1789826171_94.pdf', 'b', '{\"org_name\":\"JPP\",\"position\":\"JPP_SU_BEND\",\"type\":\"JPP\"}'),
(187, 24, '13DIT25F1105_Organisasi_Kelab_1789826171_89.pdf', 'b', '{\"org_name\":\"KELAB\",\"position\":\"CLUB_AJK\",\"type\":\"Kelab\"}'),
(188, 24, '13DIT25F1105_Penganjuran_Prog1_1789826171_84.pdf', 'c', '{\"name\":\"PROGRAM\",\"role\":\"AJK\",\"level\":\"JABATAN\"}'),
(189, 24, '13DIT25F1105_Penganjuran_Prog2_1789826171_64.pdf', 'c', '{\"name\":\"PROGRAM\",\"role\":\"SU_BEND\",\"level\":\"ANTARABANGSA\"}'),
(190, 24, '13DIT25F1105_Penyertaan_Part1_1789826171_99.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"NEGERI\"}'),
(191, 24, '13DIT25F1105_Penyertaan_Part2_1789826171_64.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"JABATAN\"}'),
(192, 24, '13DIT25F1105_Penyertaan_Part3_1789826171_60.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"NEGERI\"}'),
(193, 25, '13DIT25F1106_Akademik_Transcript_1789826642_54.pdf', 'a', '3.86'),
(194, 25, '13DIT25F1106_Organisasi_JPP_1789826642_43.pdf', 'b', '{\"org_name\":\"JPP\",\"position\":\"JPP_EXCO\",\"type\":\"JPP\"}'),
(195, 25, '13DIT25F1106_Organisasi_Kelab_1789826642_77.pdf', 'b', '{\"org_name\":\"KELAB\",\"position\":\"CLUB_SU_BEND\",\"type\":\"Kelab\"}'),
(196, 25, '13DIT25F1106_Penganjuran_Prog1_1789826642_40.pdf', 'c', '{\"name\":\"PROGRAM\",\"role\":\"AHLI\",\"level\":\"JABATAN\"}'),
(197, 25, '13DIT25F1106_Penganjuran_Prog2_1789826642_34.pdf', 'c', '{\"name\":\"PROGRAM\",\"role\":\"TIMBALAN\",\"level\":\"NEGERI\"}'),
(198, 25, '13DIT25F1106_Penyertaan_Part1_1789826642_60.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"ANTARABANGSA\"}'),
(199, 25, '13DIT25F1106_Penyertaan_Part2_1789826642_90.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"NEGERI\"}'),
(200, 25, '13DIT25F1106_Penyertaan_Part3_1789826642_19.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"POLITEKNIK\"}'),
(201, 26, '13DIT25F1107_Akademik_Transcript_1789826887_93.pdf', 'a', '3.87'),
(202, 26, '13DIT25F1107_Projek_Bukti_1789826887_70.pdf', 'p', '{\"type\":\"Bukti_Pencapaian_FYP\"}'),
(203, 26, '13DIT25F1107_Projek_Industri_1789826887_71.pdf', 'p', '{\"type\":\"Sijil_Penghargaan_Industri\"}'),
(204, 27, '13DIT25F1108_Akademik_Transcript_1789827212_96.pdf', 'a', '3.88'),
(205, 27, '13DIT25F1108_Organisasi_JPP_1789827212_82.pdf', 'b', '{\"org_name\":\"JPP\",\"position\":\"JPP_SU_BEND\",\"type\":\"JPP\"}'),
(206, 27, '13DIT25F1108_Organisasi_Kelab_1789827212_89.pdf', 'b', '{\"org_name\":\"KELAB\",\"position\":\"CLUB_PENGERUSI\",\"type\":\"Kelab\"}'),
(207, 27, '13DIT25F1108_Penganjuran_Prog1_1789827212_93.pdf', 'c', '{\"name\":\"PROGRAM\",\"role\":\"AJK\",\"level\":\"ANTARABANGSA\"}'),
(208, 27, '13DIT25F1108_Penganjuran_Prog2_1789827212_27.pdf', 'c', '{\"name\":\"PROGRAM\",\"role\":\"PENGARAH\",\"level\":\"ANTARABANGSA\"}'),
(209, 27, '13DIT25F1108_Penyertaan_Part1_1789827212_53.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"NEGERI\"}'),
(210, 27, '13DIT25F1108_Penyertaan_Part2_1789827212_40.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"POLITEKNIK\"}'),
(211, 27, '13DIT25F1108_Penyertaan_Part3_1789827212_30.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"JABATAN\"}'),
(212, 28, '13DIT25F1109_Akademik_Transcript_1789827507_22.pdf', 'a', '3.89'),
(213, 28, '13DIT25F1109_Organisasi_JPP_1789827507_20.pdf', 'b', '{\"org_name\":\"JPP\",\"position\":\"JPP_SU_BEND\",\"type\":\"JPP\"}'),
(214, 28, '13DIT25F1109_Organisasi_Kelab_1789827507_18.pdf', 'b', '{\"org_name\":\"KELAB\",\"position\":\"CLUB_PENGERUSI\",\"type\":\"Kelab\"}'),
(215, 28, '13DIT25F1109_Penganjuran_Prog1_1789827507_58.pdf', 'c', '{\"name\":\"PROGRAM\",\"role\":\"SU_BEND\",\"level\":\"POLITEKNIK\"}'),
(216, 28, '13DIT25F1109_Penganjuran_Prog2_1789827507_82.pdf', 'c', '{\"name\":\"PROGRAM\",\"role\":\"AHLI\",\"level\":\"NEGERI\"}'),
(217, 28, '13DIT25F1109_Penyertaan_Part1_1789827507_50.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"NEGERI\"}'),
(218, 28, '13DIT25F1109_Penyertaan_Part2_1789827507_24.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"NEGERI\"}'),
(219, 28, '13DIT25F1109_Penyertaan_Part3_1789827507_34.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"JABATAN\"}'),
(220, 29, '13DIT25F1110_Akademik_Transcript_1789827808_38.pdf', 'a', '3.90'),
(221, 29, '13DIT25F1110_Organisasi_JPP_1789827808_95.pdf', 'b', '{\"org_name\":\"JPP\",\"position\":\"JPP_SU_BEND\",\"type\":\"JPP\"}'),
(222, 29, '13DIT25F1110_Organisasi_Kelab_1789827808_13.pdf', 'b', '{\"org_name\":\"KELAB\",\"position\":\"CLUB_NAIB\",\"type\":\"Kelab\"}'),
(223, 29, '13DIT25F1110_Penganjuran_Prog1_1789827808_97.pdf', 'c', '{\"name\":\"PROGRAM\",\"role\":\"SU_BEND\",\"level\":\"NEGERI\"}'),
(224, 29, '13DIT25F1110_Penganjuran_Prog2_1789827808_15.pdf', 'c', '{\"name\":\"PROGRAM\",\"role\":\"PENGARAH\",\"level\":\"POLITEKNIK\"}'),
(225, 29, '13DIT25F1110_Penyertaan_Part1_1789827808_62.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"NEGERI\"}'),
(226, 29, '13DIT25F1110_Penyertaan_Part2_1789827808_84.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"POLITEKNIK\"}'),
(227, 29, '13DIT25F1110_Penyertaan_Part3_1789827808_84.pdf', 'd', '{\"name\":\"PROGRAM\",\"level\":\"NEGERI\"}'),
(228, 30, '13DIT24F1011_Akademik_Transcript_1789839998_53.pdf', 'a', '3.87'),
(229, 30, '13DIT24F1011_Organisasi_JPP_1789839998_82.jpg', 'b', '{\"org_name\":\"kantin cashier\",\"position\":\"JPP_NYDP\",\"type\":\"JPP\"}'),
(230, 30, '13DIT24F1011_Organisasi_Kelab_1789839998_88.jpg', 'b', '{\"org_name\":\"kelab japan\",\"position\":\"CLUB_PENGERUSI\",\"type\":\"Kelab\"}'),
(231, 30, '13DIT24F1011_Penganjuran_Prog1_1789839998_75.jpg', 'c', '{\"name\":\"golf\",\"role\":\"AJK\",\"level\":\"ANTARABANGSA\"}'),
(232, 30, '13DIT24F1011_Penganjuran_Prog2_1789839998_18.jpg', 'c', '{\"name\":\"tekan tubi\",\"role\":\"SU_BEND\",\"level\":\"NEGERI\"}'),
(233, 30, '13DIT24F1011_Penyertaan_Part1_1789839998_28.jpg', 'd', '{\"name\":\"accp\",\"level\":\"JABATAN\"}'),
(234, 30, '13DIT24F1011_Penyertaan_Part2_1789839998_53.jpg', 'd', '{\"name\":\"pentanque\",\"level\":\"ANTARABANGSA\"}'),
(235, 30, '13DIT24F1011_Penyertaan_Part3_1789839998_61.jpg', 'd', '{\"name\":\"maraton dungun\",\"level\":\"NEGERI\"}'),
(236, 31, '13DIT24F1018_Akademik_Transcript_1789840165_11.pdf', 'a', '4.00'),
(237, 31, '13DIT24F1018_Organisasi_JPP_1789840165_13.jpg', 'b', '{\"org_name\":\"exco\",\"position\":\"JPP_SU_BEND\",\"type\":\"JPP\"}'),
(238, 31, '13DIT24F1018_Organisasi_Kelab_1789840165_48.jpg', 'b', '{\"org_name\":\"kelab reading\",\"position\":\"CLUB_PENGERUSI\",\"type\":\"Kelab\"}'),
(239, 31, '13DIT24F1018_Penganjuran_Prog1_1789840165_19.jpg', 'c', '{\"name\":\"donation\",\"role\":\"AJK\",\"level\":\"NEGERI\"}'),
(240, 31, '13DIT24F1018_Penganjuran_Prog2_1789840165_22.jpg', 'c', '{\"name\":\"graduation\",\"role\":\"TIMBALAN\",\"level\":\"NEGERI\"}'),
(241, 31, '13DIT24F1018_Penyertaan_Part1_1789840165_97.jpg', 'd', '{\"name\":\"basketball\",\"level\":\"NEGERI\"}'),
(242, 31, '13DIT24F1018_Penyertaan_Part2_1789840165_55.jpg', 'd', '{\"name\":\"krsp\",\"level\":\"POLITEKNIK\"}'),
(243, 31, '13DIT24F1018_Penyertaan_Part3_1789840165_41.jpg', 'd', '{\"name\":\"industrial LI\",\"level\":\"NEGERI\"}'),
(244, 31, '13DIT24F1018_Bonus_Luar_1789840165_68.jpg', 'e', '{\"type\":\"Bonus_Luar\"}'),
(245, 32, '13DIT24F1020_Akademik_Transcript_1789840407_22.pdf', 'a', '3.59'),
(246, 32, '13DIT24F1020_Organisasi_JPP_1789840407_69.jpg', 'b', '{\"org_name\":\"band poli\",\"position\":\"JPP_YDP\",\"type\":\"JPP\"}'),
(247, 32, '13DIT24F1020_Organisasi_Kelab_1789840407_88.jpg', 'b', '{\"org_name\":\"kelab writing\",\"position\":\"CLUB_NAIB\",\"type\":\"Kelab\"}'),
(248, 32, '13DIT24F1020_Penganjuran_Prog1_1789840407_88.jpg', 'c', '{\"name\":\"singing\",\"role\":\"AJK\",\"level\":\"POLITEKNIK\"}'),
(249, 32, '13DIT24F1020_Penganjuran_Prog2_1789840407_29.jpg', 'c', '{\"name\":\"cyber\",\"role\":\"PENGARAH\",\"level\":\"NEGERI\"}'),
(250, 32, '13DIT24F1020_Penyertaan_Part1_1789840407_28.jpg', 'd', '{\"name\":\"discovery\",\"level\":\"NEGERI\"}'),
(251, 32, '13DIT24F1020_Penyertaan_Part2_1789840407_13.jpg', 'd', '{\"name\":\"football\",\"level\":\"NEGERI\"}'),
(252, 32, '13DIT24F1020_Penyertaan_Part3_1789840407_10.jpg', 'd', '{\"name\":\"python learn\",\"level\":\"NEGERI\"}'),
(253, 33, '13DIT24F1027_Akademik_Transcript_1789840673_39.pdf', 'a', '3.75'),
(254, 33, '13DIT24F1027_Organisasi_JPP_1789840673_92.jpg', 'b', '{\"org_name\":\"komprasi\",\"position\":\"JPP_NYDP\",\"type\":\"JPP\"}'),
(255, 33, '13DIT24F1027_Organisasi_Kelab_1789840673_81.jpg', 'b', '{\"org_name\":\"friendly debate\",\"position\":\"CLUB_NAIB\",\"type\":\"Kelab\"}'),
(256, 33, '13DIT24F1027_Penganjuran_Prog1_1789840673_58.jpg', 'c', '{\"name\":\"chess\",\"role\":\"PENGARAH\",\"level\":\"ANTARABANGSA\"}'),
(257, 33, '13DIT24F1027_Penganjuran_Prog2_1789840673_14.jpg', 'c', '{\"name\":\"dance\",\"role\":\"PENGARAH\",\"level\":\"POLITEKNIK\"}'),
(258, 33, '13DIT24F1027_Penyertaan_Part1_1789840673_56.jpg', 'd', '{\"name\":\"rugby\",\"level\":\"ANTARABANGSA\"}'),
(259, 33, '13DIT24F1027_Penyertaan_Part2_1789840673_55.jpg', 'd', '{\"name\":\"independant poli\",\"level\":\"NEGERI\"}'),
(260, 33, '13DIT24F1027_Penyertaan_Part3_1789840673_58.jpg', 'd', '{\"name\":\"basic fundamentals\",\"level\":\"JABATAN\"}'),
(261, 33, '13DIT24F1027_Bonus_Profesional_1789840673_88.jpg', 'e', '{\"type\":\"Bonus_Profesional\"}'),
(262, 34, '13DIT24F1151_Akademik_Transcript_1789840984_24.pdf', 'a', '3.32'),
(263, 34, '13DIT24F1151_Organisasi_JPP_1789840984_75.jpg', 'b', '{\"org_name\":\"jaga kebersihan\",\"position\":\"JPP_NYDP\",\"type\":\"JPP\"}'),
(264, 34, '13DIT24F1151_Organisasi_Kelab_1789840984_94.jpg', 'b', '{\"org_name\":\"kelab photography\",\"position\":\"CLUB_PENGERUSI\",\"type\":\"Kelab\"}'),
(265, 34, '13DIT24F1151_Penganjuran_Prog1_1789840984_67.jpg', 'c', '{\"name\":\"crypto\",\"role\":\"AJK\",\"level\":\"ANTARABANGSA\"}'),
(266, 34, '13DIT24F1151_Penganjuran_Prog2_1789840984_67.jpg', 'c', '{\"name\":\"silat\",\"role\":\"TIMBALAN\",\"level\":\"POLITEKNIK\"}'),
(267, 34, '13DIT24F1151_Penyertaan_Part1_1789840984_52.jpg', 'd', '{\"name\":\"pitching\",\"level\":\"NEGERI\"}'),
(268, 34, '13DIT24F1151_Penyertaan_Part2_1789840984_18.jpg', 'd', '{\"name\":\"running\",\"level\":\"POLITEKNIK\"}'),
(269, 34, '13DIT24F1151_Penyertaan_Part3_1789840984_49.jpg', 'd', '{\"name\":\"projek\",\"level\":\"JABATAN\"}'),
(270, 35, '13DIT24F1067_Akademik_Transcript_1789841115_20.pdf', 'a', '3.99'),
(271, 36, '13DIT24F1089_Akademik_Transcript_1789841326_88.pdf', 'a', '3.90'),
(272, 36, '13DIT24F1089_Organisasi_JPP_1789841326_68.jpg', 'b', '{\"org_name\":\"leadership\",\"position\":\"JPP_SU_BEND\",\"type\":\"JPP\"}'),
(273, 36, '13DIT24F1089_Organisasi_Kelab_1789841326_28.jpg', 'b', '{\"org_name\":\"multimedia\",\"position\":\"CLUB_AJK\",\"type\":\"Kelab\"}'),
(274, 36, '13DIT24F1089_Penganjuran_Prog1_1789841326_88.jpg', 'c', '{\"name\":\"bola tampar\",\"role\":\"AHLI\",\"level\":\"ANTARABANGSA\"}'),
(275, 36, '13DIT24F1089_Penyertaan_Part1_1789841326_49.jpg', 'd', '{\"name\":\"fishing\",\"level\":\"NEGERI\"}'),
(276, 37, '13DIT24F1025_Akademik_Transcript_1789841580_95.pdf', 'a', '3.57'),
(277, 37, '13DIT24F1025_Organisasi_JPP_1789841580_95.jpg', 'b', '{\"org_name\":\"industi jtmk\",\"position\":\"JPP_YDP\",\"type\":\"JPP\"}'),
(278, 37, '13DIT24F1025_Organisasi_Kelab_1789841580_30.jpg', 'b', '{\"org_name\":\"kelab keusahawaan\",\"position\":\"CLUB_NAIB\",\"type\":\"Kelab\"}'),
(279, 37, '13DIT24F1025_Penganjuran_Prog1_1789841580_34.jpg', 'c', '{\"name\":\"marketing\",\"role\":\"TIMBALAN\",\"level\":\"NEGERI\"}'),
(280, 37, '13DIT24F1025_Penyertaan_Part1_1789841580_51.jpg', 'd', '{\"name\":\"pertnadingan quiz\",\"level\":\"NEGERI\"}'),
(281, 37, '13DIT24F1025_Penyertaan_Part2_1789841580_82.jpg', 'd', '{\"name\":\"surf\",\"level\":\"NEGERI\"}'),
(282, 37, '13DIT24F1025_Bonus_Profesional_1789841580_50.jpg', 'e', '{\"type\":\"Bonus_Profesional\"}'),
(283, 38, '13DIT24F2032_Akademik_Transcript_1789841837_68.pdf', 'a', '3.89'),
(284, 38, '13DIT24F2032_Organisasi_JPP_1789841837_42.jpg', 'b', '{\"org_name\":\"organizing jtmk\",\"position\":\"JPP_EXCO\",\"type\":\"JPP\"}'),
(285, 38, '13DIT24F2032_Organisasi_Kelab_1789841837_30.jpg', 'b', '{\"org_name\":\"kelab reka cipta\",\"position\":\"CLUB_AJK\",\"type\":\"Kelab\"}'),
(286, 38, '13DIT24F2032_Penganjuran_Prog1_1789841837_56.jpg', 'c', '{\"name\":\"graphic design\",\"role\":\"AJK\",\"level\":\"JABATAN\"}'),
(287, 38, '13DIT24F2032_Penganjuran_Prog2_1789841837_78.jpg', 'c', '{\"name\":\"open speech\",\"role\":\"SU_BEND\",\"level\":\"NEGERI\"}'),
(288, 38, '13DIT24F2032_Penyertaan_Part1_1789841837_30.jpg', 'd', '{\"name\":\"fencing\",\"level\":\"NEGERI\"}'),
(289, 38, '13DIT24F2032_Bonus_Profesional_1789841837_24.jpg', 'e', '{\"type\":\"Bonus_Profesional\"}'),
(290, 39, '13DIT24F1043_Akademik_Transcript_1789842106_19.pdf', 'a', '3.22'),
(291, 39, '13DIT24F1043_Penganjuran_Prog1_1789842106_41.jpg', 'c', '{\"name\":\"robot\",\"role\":\"TIMBALAN\",\"level\":\"NEGERI\"}'),
(292, 39, '13DIT24F1043_Penganjuran_Prog2_1789842106_10.jpg', 'c', '{\"name\":\"muay tai guide\",\"role\":\"AJK\",\"level\":\"POLITEKNIK\"}'),
(293, 39, '13DIT24F1043_Penyertaan_Part1_1789842106_77.jpg', 'd', '{\"name\":\"karate\",\"level\":\"POLITEKNIK\"}'),
(294, 39, '13DIT24F1043_Penyertaan_Part2_1789842106_64.jpg', 'd', '{\"name\":\"bola takraw\",\"level\":\"ANTARABANGSA\"}'),
(295, 39, '13DIT24F1043_Bonus_Antarabangsa_1789842106_51.jpg', 'e', '{\"type\":\"Bonus_Antarabangsa\"}'),
(296, 40, '13DIT24F1192_Akademik_Transcript_1789873426_57.pdf', 'a', '3.40'),
(297, 40, '13DIT24F1192_Penganjuran_Prog1_1789873426_72.png', 'c', '{\"name\":\"Program Inovation\",\"role\":\"TIMBALAN\",\"level\":\"NEGERI\"}'),
(298, 40, '13DIT24F1192_Penganjuran_Prog2_1789873426_23.png', 'c', '{\"name\":\"Program Pelajar Cermelang\",\"role\":\"PENGARAH\",\"level\":\"ANTARABANGSA\"}');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `class_id` int(11) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`class_id`, `class_name`, `is_active`) VALUES
(1, 'DIT5A', 1),
(2, 'DIT5B', 1),
(3, 'DIT5C', 1),
(4, 'DIT5D', 1),
(5, 'DIT5E', 1);

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
(7, 19, 33, '88.00', 'Backed by a strong 88.0% Program Advisor rating, Chan Jia Hong demonstrates exemplary balanced achievement in both academic and co-curricular domains. He is strongly endorsed as a highly deserving recipient of the Anugerah Kecemerlangan Akademik dan Kokurikulum.', '2026-09-19 13:05:11'),
(8, 29, 33, '90.00', 'Fatimah binti Osman is strongly endorsed for the Anugerah Khas (Sukarelawan) in recognition of her outstanding 90.0% evaluation and exemplary commitment to community service.', '2026-09-19 23:01:12'),
(9, 29, 33, '90.00', 'Fatimah binti Osman is strongly endorsed for the Anugerah Khas (Sukarelawan) in recognition of her outstanding 90.0% evaluation and exemplary commitment to community service.', '2026-09-20 00:29:30'),
(10, 27, 33, '89.80', 'Azman is strongly endorsed for the Anugerah Khas (Industri) backed by an outstanding 89.8% rating and extensive participation in industry events. His proactive engagement and industry readiness make him an exemplary recipient of this award.', '2026-09-20 01:25:47'),
(11, 29, 33, '90.00', 'Fatimah binti Osman is strongly endorsed for the Anugerah Khas (Sukarelawan) in recognition of her outstanding 90.0% evaluation and exemplary commitment to community service.', '2026-09-20 03:11:31');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiry` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expiry`, `used`, `created_at`) VALUES
(4, 'wangzs0813@gmail.com', '5d8e02c1e7b986660b3284f4c87a1896071278fce8d1739dd6d96edcbd170aa3', '2026-09-19 22:10:36', 0, '2026-09-19 13:10:36'),
(5, 'hongc2303@gmail.com', 'c2d6f6ff71a339aa0549282bdaf8037d5473a8ea51ea1c0dbab31f3a089dc4f7', '2026-09-19 22:25:47', 1, '2026-09-19 13:25:46'),
(6, 'hongc2303@gmail.com', '04de0342ee886d5ae6135610bc8a2c07f262f75081dead821f92ae72e9726cc9', '2026-09-20 10:23:03', 1, '2026-09-20 01:23:03'),
(7, 'hongc2303@gmail.com', '8a688a4772f3a47cd0393ac60ccc51d78847ff64f7c74150a5f9e225d28478ce', '2026-09-20 10:23:06', 0, '2026-09-20 01:23:06'),
(8, 'hongc2303@gmail.com', 'e34a296795feb212ba8ddeea54a082444c46ff3a4e3a939d44577e1dab5a54ac', '2026-09-20 11:56:31', 1, '2026-09-20 02:56:31'),
(9, 'wangzs0813@gmail.com', '1cb121a6f22a562aa18a255aecf56e899be7ea4963e890d807e40575291301aa', '2026-09-20 13:07:16', 0, '2026-09-20 04:07:15'),
(10, 'wangzs0813@gmail.com', 'f0132c23bea57fd825c4ad355137577b1dff1700aefd1384f0664dc0cc202373', '2026-09-20 13:07:17', 0, '2026-09-20 04:07:16'),
(11, 'hongc2303@gmail.com', 'a1871a6d7c054c6c7ab0b4957baaf0bada39f588f97404455a5a682e7d2b4126', '2026-10-13 12:43:45', 1, '2026-10-13 03:43:45');

-- --------------------------------------------------------

--
-- Table structure for table `project_award_details`
--

CREATE TABLE `project_award_details` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `project_title` varchar(255) NOT NULL,
  `project_role` varchar(100) DEFAULT NULL,
  `achievement_level` varchar(100) DEFAULT NULL,
  `project_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `group_members` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_award_details`
--

INSERT INTO `project_award_details` (`id`, `application_id`, `project_title`, `project_role`, `achievement_level`, `project_description`, `created_at`, `group_members`) VALUES
(3, 21, 'PROJECT FYP', NULL, NULL, NULL, '2026-09-19 13:36:24', '1. Ahmad bin Abdullah\n   (No. K/P: 060921110556, No. Pend: 13DIT24F1101)'),
(4, 26, 'PROJECT FYP', NULL, NULL, NULL, '2026-09-19 14:08:07', '1. Ali bin Osman\n   (No. K/P: 060928010889, No. Pend: 13DIT24F1103)');

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

--
-- Dumping data for table `report_logs`
--

INSERT INTO `report_logs` (`log_id`, `report_title`, `report_type`, `category_id`, `created_at`) VALUES
(9, 'Evaluation Report - Category ID: 1 (Sesi 1 2026/2027)', 'evaluation', '1', '2026-09-19 06:06:29'),
(10, 'Evaluation Report - All Awards', 'evaluation', 'all', '2026-09-19 15:36:27'),
(11, 'Evaluation Report - All Awards (Sesi 1 2026/2027)', 'evaluation', 'all', '2026-09-19 15:47:54'),
(12, 'Evaluation Report - All Awards', 'evaluation', 'all', '2026-09-19 18:26:40'),
(13, 'Evaluation Report - All Awards (Sesi 1 2026/2027)', 'evaluation', 'all', '2026-09-19 18:28:53'),
(14, 'Evaluation Report - All Awards (Sesi 1 2026/2027)', 'evaluation', 'all', '2026-09-19 20:15:22'),
(15, 'Evaluation Report - All Awards', 'evaluation', 'all', '2026-09-19 21:04:39'),
(16, 'Evaluation Report - All Awards (Sesi 1 2026/2027)', 'evaluation', 'all', '2026-09-19 21:06:19'),
(17, 'Evaluation Report - All Awards (Sesi 1 2026/2027)', 'evaluation', 'all', '2026-09-19 21:23:56');

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
(4, 39, '13DIT24F1178', 'DIT5E', 0, '01151383760', 38, '2026-09-19 13:00:55'),
(5, 40, '13DIT24F1192', 'DIT5E', 0, '0167567283', 38, '2026-09-19 13:01:09'),
(6, 41, '13DIT25F1101', 'DIT5D', 0, '0123456789', 38, '2026-09-19 13:17:18'),
(7, 42, '13DIT25F1102', 'DIT5D', 0, '0113458818', 38, '2026-09-19 13:31:31'),
(8, 43, '13DIT25F1103', 'DIT5D', 0, '0133369954', 38, '2026-09-19 13:38:19'),
(9, 44, '13DIT25F1104', 'DIT5D', 0, '0147786520', 38, '2026-09-19 13:45:27'),
(10, 45, '13DIT25F1105', 'DIT5D', 0, '0186497787', 38, '2026-09-19 13:52:59'),
(11, 46, '13DIT25F1106', 'DIT5C', 0, '0167894521', 38, '2026-09-19 14:01:12'),
(12, 47, '13DIT25F1107', 'DIT5C', 0, '0189945627', 38, '2026-09-19 14:05:26'),
(13, 48, '13DIT25F1108', 'DIT5C', 0, '0115624954', 38, '2026-09-19 14:10:04'),
(14, 49, '13DIT25F1109', 'DIT5C', 0, '0125948743', 38, '2026-09-19 14:15:17'),
(15, 50, '13DIT25F1110', 'DIT5C', 0, '0126594872', 38, '2026-09-19 14:20:09'),
(16, 51, '13DIT24F1011', 'DIT5A', 0, '01139989679', 38, '2026-09-19 17:42:21'),
(17, 52, '13DIT24F1018', 'DIT5A', 0, '01139989678', 38, '2026-09-19 17:43:56'),
(18, 53, '13DIT24F1020', 'DIT5A', 0, '01139989676', 38, '2026-09-19 17:50:53'),
(19, 54, '13DIT24F1027', 'DIT5A', 0, '01139989622', 38, '2026-09-19 17:54:57'),
(20, 55, '13DIT24F1151', 'DIT5A', 0, '01112234567', 38, '2026-09-19 17:59:19'),
(21, 56, '13DIT24F1067', 'DIT5B', 0, '01134567332', 38, '2026-09-19 18:04:26'),
(22, 57, '13DIT24F1089', 'DIT5B', 0, '0113282832', 38, '2026-09-19 18:07:13'),
(23, 58, '13DIT24F1025', 'DIT5B', 0, '0113975643', 38, '2026-09-19 18:10:11'),
(24, 59, '13DIT24F2032', 'DIT5B', 0, '0113998943', 38, '2026-09-19 18:14:25'),
(25, 60, '13DIT24F1043', 'DIT5B', 0, '011399443323', 38, '2026-09-19 18:19:19'),
(26, 61, '13DIT24F1195', 'DIT5E', 0, '01139989678', 38, '2026-09-19 18:25:38'),
(27, 62, '13DIT24F1102', 'DIT5E', 0, '0167567287', 38, '2026-09-20 01:16:21'),
(28, 63, '13DIT24F1190', 'DIT5E', 0, '0167567999', 38, '2026-09-20 02:56:22');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'current_session', 'Sesi 1 2026/2027', '2026-09-20 04:24:27'),
(2, 'system_status', 'open', '2026-09-08 04:37:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Student','Academic Advisor','Evaluation Committee','Admin') NOT NULL,
  `assigned_category_id` int(11) DEFAULT NULL COMMENT 'For Committee: Which award category they evaluate',
  `must_change_password` tinyint(1) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `role`, `assigned_category_id`, `must_change_password`, `is_active`, `created_at`, `updated_at`, `last_login`, `reset_token`, `reset_expires`) VALUES
(1, 'Ramlah Binti Md. Zain', 'ramlah@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-09-08 14:18:00', NULL, NULL, NULL),
(2, 'Mohd Hasrizam Bin Hasan', 'mohd.hasrizam@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-09-08 14:18:00', NULL, NULL, NULL),
(3, 'Nor Syahadataini Binti Awang', 'syahada@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-09-08 14:18:00', NULL, NULL, NULL),
(4, 'Syaiful Bacchtiar Bin Nen @ Shahinan', 'syaiful@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:03:27', NULL, NULL, NULL),
(5, 'Kamalul Hayat Bin Raman', 'kamal@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:03:57', NULL, NULL, NULL),
(6, 'Suziwati Binti Yusof', 'suziwati@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:04:13', NULL, NULL, NULL),
(7, 'Hairi Bin Alias', 'hairi@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:04:31', NULL, NULL, NULL),
(8, 'Murniyati Binti Abdul', 'murniyati@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-11 05:19:39', NULL, NULL, NULL),
(9, 'Mazlina Binti Mustapha', 'mazlina@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:05:08', NULL, NULL, NULL),
(10, 'Suzana Binti Yusof', 'suzana@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:05:24', NULL, NULL, NULL),
(11, 'Fauziah Binti Basok', 'fauziah@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:05:54', NULL, NULL, NULL),
(12, 'Wan Haniza Binti Wan Hassim', 'whaniza@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:06:11', NULL, NULL, NULL),
(13, 'Raja Intan Sariah Binti Raja Mahmood', 'r.intan.sariah@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:06:26', NULL, NULL, NULL),
(14, 'Nor Hayati Binti Mohammed Sani', 'nor.hayati@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:06:47', NULL, NULL, NULL),
(15, 'Zukia Aniza Binti Ibrahim', 'zukia.aniza@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:07:04', NULL, NULL, NULL),
(16, 'Nor Azlina Binti Ibrahim', 'nor.azlina@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:08:02', NULL, NULL, NULL),
(17, 'Zuraini Binti Abdul Rajab', 'zuraini@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:08:21', NULL, NULL, NULL),
(18, 'Rislah Binti Zakria', 'rislah@psmza.edu.my', '$2y$10$MNahMO5fHieDjBNyXEMALODmxmT/g8JR98ioJEdB0aEQ1z5JmxD.C', 'Academic Advisor', NULL, 0, 1, '2026-07-28 01:03:04', '2026-09-09 11:27:34', NULL, NULL, NULL),
(19, 'Efeza Binti Che Apandey', 'efeza@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:08:58', NULL, NULL, NULL),
(20, 'Zarina Binti Musa', 'zarina_musa@moh.gov.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:09:20', NULL, NULL, NULL),
(21, 'Nor Aznira Binti Yusoff', 'aznira@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:10:44', NULL, NULL, NULL),
(22, 'Shaifatul Ain Binti Mohamad', 'shaifatulain@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-09-08 14:18:00', NULL, NULL, NULL),
(23, 'Siti Nasrah Binti Mukhtar', 'nasrah@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:11:27', NULL, NULL, NULL),
(24, 'Mazidah Binti Musa', 'mazidah@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:12:00', NULL, NULL, NULL),
(25, 'Nor Aidawati Binti Abdillah', 'nor.aidawati@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:12:19', NULL, NULL, NULL),
(26, 'Nor Zilaila Binti Jaafar', 'zilaila@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-07 06:12:49', NULL, NULL, NULL),
(27, 'Norhayati Saadah Binti Che Abd Razak', 'norhayati.saadah@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-11 05:20:52', NULL, NULL, NULL),
(28, 'Zainal Fitri Bin Mohd Zolkifli', 'zainalfitri@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-09-08 14:18:00', NULL, NULL, NULL),
(29, 'Syahieda Binti Zakaria', 'syahieda@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-09-08 14:18:00', NULL, NULL, NULL),
(30, 'Nur Syarafina Binti Abdul Rahman', 'syarafina@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-09-08 14:18:00', NULL, NULL, NULL),
(31, 'Wan Noor Aishah Binti Wan Chek', 'wnaishah@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-08-11 05:20:09', NULL, NULL, NULL),
(32, 'Liyana Binti Mat Rani', 'liyana@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 1, 1, '2026-07-28 01:03:04', '2026-09-08 14:18:00', NULL, NULL, NULL),
(33, 'Committee', 'committee@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Evaluation Committee', 2, 0, 1, '2026-07-28 01:03:04', '2026-09-20 04:23:30', NULL, NULL, NULL),
(34, 'Admin', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', NULL, 0, 1, '2026-08-10 04:07:25', '2026-09-08 05:49:29', NULL, NULL, NULL),
(38, 'PA Test', 'pa@psmza.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Academic Advisor', NULL, 0, 1, '2026-09-19 02:14:37', '2026-09-19 02:14:37', NULL, NULL, NULL),
(39, 'WANG ZHAO SHI', 'wangzs0813@gmail.com', '$2y$10$cvMpJccB9or1kaEoFoWGcejuHKwWi32S/bSnGs4WZ8OnimTu4Er8K', 'Student', NULL, 1, 1, '2026-09-19 13:00:55', '2026-09-19 13:00:55', NULL, NULL, NULL),
(40, 'CHAN JIA HONG', 'hongc2303@gmail.com', '$2y$12$CbykmTjQXUC9dxqiXWzEgeVD.mOuQ83kYE6ZvahBP66OcZTwKs7oW', 'Student', NULL, 0, 1, '2026-09-19 13:01:09', '2026-10-13 03:44:42', NULL, NULL, NULL),
(41, 'AHMAD BIN ABDULLAH', 'ahmad@gmail.com', '$2y$10$BVMErzhfRYpaeS68dZrIiueMNSUOOdYOypvv5H72NWRSARtqqWm7e', 'Student', NULL, 1, 1, '2026-09-19 13:17:18', '2026-09-19 13:17:18', NULL, NULL, NULL),
(42, 'MUHAMMAD BIN HASSAN', 'muhammad@gmail.com', '$2y$10$IlSBNIsiqQWEV0IjIEslPeqdnl/FjrJ9F87YyJ1dWlAbSsjMC8x8.', 'Student', NULL, 1, 1, '2026-09-19 13:31:31', '2026-09-19 13:31:31', NULL, NULL, NULL),
(43, 'ALI BIN OSMAN', 'ali@gmail.com', '$2y$10$tFiDYyYtp9HalZ555CyQd.Uo5XN1V.jq1tEt1J7phS4LvPp5kRh5C', 'Student', NULL, 1, 1, '2026-09-19 13:38:19', '2026-09-19 13:38:19', NULL, NULL, NULL),
(44, 'SITI BINTI ABDULLAH', 'siti@gmail.com', '$2y$10$LGXcxCvoZIQlcTsU/uNgFO6L4GAfvtM2tk647HbVu6i697FzLGGUm', 'Student', NULL, 1, 1, '2026-09-19 13:45:27', '2026-09-19 13:45:27', NULL, NULL, NULL),
(45, 'AMINAH BINTI BAKAR', 'aminah@gmail.com', '$2y$10$tuqJD4TXhI3gnL6ARs8qAugiyf2ewGSnkiT6Wkr6/p5Z16Mo7cFmO', 'Student', NULL, 1, 1, '2026-09-19 13:52:59', '2026-09-19 13:52:59', NULL, NULL, NULL),
(46, 'ABU BIN BAKAR', 'abu@gmail.com', '$2y$10$Rwv41QYDZVlcyPpy7vWWTOIDHFp003T9lCcQlIBLG6Jd7aywZcq16', 'Student', NULL, 1, 1, '2026-09-19 14:01:12', '2026-09-19 14:01:12', NULL, NULL, NULL),
(47, 'ISMAIL BIN YUSOF', 'ismail@gmail.com', '$2y$10$CBmwrFv44Cf58tBQggpeVOg75rDiZiG3zYhSrsCxleZND81l7xUFG', 'Student', NULL, 1, 1, '2026-09-19 14:05:26', '2026-09-19 14:05:26', NULL, NULL, NULL),
(48, 'AZMAN BIN RAZAK', 'azman@gmail.com', '$2y$10$Ff.YasNGAyaVrT76ERhWdO4Y6lHJsmhKujvOwkXiQgJTXd.AO6QBu', 'Student', NULL, 1, 1, '2026-09-19 14:10:04', '2026-09-19 14:10:04', NULL, NULL, NULL),
(49, 'FAIZAL BIN OMAR', 'faizal@gmail.com', '$2y$10$5Oo1gxXMO0RyJMc3aDxbFudamr6pw0xNg7GY3zKNhgPwlPWIujKSC', 'Student', NULL, 1, 1, '2026-09-19 14:15:17', '2026-09-19 14:15:17', NULL, NULL, NULL),
(50, 'FATIMAH BINTI OSMAN', 'fatimah@gmail.com', '$2y$10$dME91quez98dXG03IFsIBuEnbX107DVdhL5/euNHtWCiHXK9OqIQK', 'Student', NULL, 1, 1, '2026-09-19 14:20:09', '2026-09-19 14:20:09', NULL, NULL, NULL),
(51, 'HARSHAVARTHAN', 'harsha@gmail.com', '$2y$10$t3nHiUtwHsts7VHNhDjQfO.LQrwygOBQvKP6d5jYSiWkSbRNO7F1.', 'Student', NULL, 1, 1, '2026-09-19 17:42:21', '2026-09-19 17:42:21', NULL, NULL, NULL),
(52, 'LEE YI PENG', 'lee@gmail.com', '$2y$10$SlDNvthBGHgAiaO6Dn.EF.imQg9KjwKLhBsfoWgWpeAlMiaskPBra', 'Student', NULL, 1, 1, '2026-09-19 17:43:56', '2026-09-19 17:43:56', NULL, NULL, NULL),
(53, 'EZRA KAVINRAJ', 'ezra@gmail.com', '$2y$10$4o1coPAAFKcYh1NFymogT.199IpT0Z0W0rciRyEAESJSJxDyfzwqm', 'Student', NULL, 1, 1, '2026-09-19 17:50:53', '2026-09-19 17:50:53', NULL, NULL, NULL),
(54, 'SOH LI JEE', 'so@gmail.com', '$2y$10$Fue5N22YKYXGsErrJ5YnReac8iFZCcUFYpVb0O/.8s8/BzbYanrLS', 'Student', NULL, 1, 1, '2026-09-19 17:54:57', '2026-09-19 17:54:57', NULL, NULL, NULL),
(55, 'AIMAN FIKRI', 'aiman@gmail.com', '$2y$10$mTffsOlbjXVsQO/fW/YtTuqpGhtFdDjUhf.szVNVNDOKanOCK2YK6', 'Student', NULL, 1, 1, '2026-09-19 17:59:19', '2026-09-19 17:59:19', NULL, NULL, NULL),
(56, 'YUVA', 'yuva@gmail.com', '$2y$10$ChQA5IqjjdnTkH1BUGMzku5JhG0Pc3aVk5kW2opzw8UmfgFnLOyyK', 'Student', NULL, 1, 1, '2026-09-19 18:04:26', '2026-09-19 18:04:26', NULL, NULL, NULL),
(57, 'IQMAL BIN SHARIF', 'iqmal@gmail.com', '$2y$10$OS4dlFllW0ReXr2WzS59N.bVZwfmF/FJZ308JUO3Dpoopp8Tpe9IG', 'Student', NULL, 1, 1, '2026-09-19 18:07:13', '2026-09-19 18:07:13', NULL, NULL, NULL),
(58, 'NOR SITI BINTI ZULMAN', 'nor@gmail.com', '$2y$10$mAX/sTcrglPUke4/9cOBhewrGGs2XKlqLNaoG.h4Bjsm/f6ktcLQO', 'Student', NULL, 1, 1, '2026-09-19 18:10:11', '2026-09-19 18:10:11', NULL, NULL, NULL),
(59, 'PRIYA VARUN', 'priya@gmail.com', '$2y$10$DA4oS9CCj/yCnhLsl/T4RucVhWFjpHoLYWXMcgZwig2b1KMMUNNYa', 'Student', NULL, 1, 1, '2026-09-19 18:14:25', '2026-09-19 18:14:25', NULL, NULL, NULL),
(60, 'FENG YI SHANG', 'feng@gmail.com', '$2y$10$yzmqxCXO9FP1k6a35l4AdOqWlhDw1X6EsVjfVLx90nN/agkJ1QTxK', 'Student', NULL, 1, 1, '2026-09-19 18:19:19', '2026-09-19 18:19:19', NULL, NULL, NULL),
(61, 'SANJEEVAN A/L P S RAMESH KUMAR', 'kingsanjeevan5@gmail.com', '$2y$10$7ri0NMHPKTN86EH/m9BTO.zZAZhyPIfiT1vFxHNPQ84hCR7V0Edoq', 'Student', NULL, 1, 1, '2026-09-19 18:25:38', '2026-09-19 18:25:38', NULL, NULL, NULL),
(62, 'CHAN JIA ZHI', 'jia@gmail.com', '$2y$10$7VbdYW7.Td9P/4hMKQgLGeffQNWLV8GD5P212Y9T0s6ic3wF1Qaie', 'Student', NULL, 1, 1, '2026-09-20 01:16:21', '2026-09-20 01:16:21', NULL, NULL, NULL),
(63, 'WANG JIA CHENG', 'wangjc@gmail.com', '$2y$10$j8V1hCYIvkKVFHf3BEZSSuBxk9d6yE9aQ4GNowZh5M3viAeAUAEO2', 'Student', NULL, 1, 1, '2026-09-20 02:56:22', '2026-09-20 02:56:22', NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

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
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`class_id`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`evaluation_id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `committee_id` (`committee_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `project_award_details`
--
ALTER TABLE `project_award_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`);

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
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

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
-- AUTO_INCREMENT for table `application_verifications`
--
ALTER TABLE `application_verifications`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `award_applications`
--
ALTER TABLE `award_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `award_categories`
--
ALTER TABLE `award_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `certificate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=299;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `evaluation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `project_award_details`
--
ALTER TABLE `project_award_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `report_logs`
--
ALTER TABLE `report_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- Constraints for dumped tables
--

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
-- Constraints for table `project_award_details`
--
ALTER TABLE `project_award_details`
  ADD CONSTRAINT `project_award_details_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `award_applications` (`application_id`) ON DELETE CASCADE;

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
