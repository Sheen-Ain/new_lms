-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql103.byetcluster.com
-- Generation Time: Sep 24, 2026 at 12:46 AM
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
-- Database: `b7_41397792_lms_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `action` varchar(500) NOT NULL,
  `section` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(10) UNSIGNED NOT NULL,
  `batch_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `priority` enum('normal','important','urgent') DEFAULT 'normal',
  `is_pinned` tinyint(1) DEFAULT 0,
  `status` enum('published','draft') DEFAULT 'published',
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `batch_id`, `title`, `content`, `priority`, `is_pinned`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Important: Assignment Submission Format (Strict Policy)', 'All students are strictly instructed to submit assignments in the correct file format.\n\nUploading screenshots, images, or photos of code (e.g., from mobile or screen captures) is NOT acceptable.\n\nYou must upload:\n\nProper source code files (e.g., .php, .py, .html, .zip, etc.)\n\nAny submission made in the form of images or screenshots will be rejected and awarded 0 marks without any exception.\n\nThis rule is mandatory for all future assignments. Make sure your files are properly uploaded before the deadline.\n\nFailure to follow instructions will directly affect your grades.', 'important', 1, 'published', 2, '2026-04-18 14:19:18', '2026-04-18 14:19:18');

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` int(10) UNSIGNED NOT NULL,
  `batch_id` int(10) UNSIGNED NOT NULL,
  `topic_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `total_marks` int(10) UNSIGNED DEFAULT 100,
  `due_date` datetime DEFAULT NULL,
  `allow_late` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive','draft') DEFAULT 'active',
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`id`, `batch_id`, `topic_id`, `title`, `description`, `total_marks`, `due_date`, `allow_late`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 2, NULL, 'Introduction to Python Programming and Development Setup', 'Is assignment ka maqsad  Python programming ki basic understanding dena hai. Is me pehle apne system par Python aur PyCharm install karenge, phir ek simple program likhenge jisme class ka use hoga aur basic data types (string, int, float) ko samjha jayega. Is ke sath  comments aur data types check karna bhi seekhenge.\nTasks:\nPython Installation Steps:\nSab se pehle official website par ja kar Python download karen\nDownload karte waqt version 3.x select karen\nInstaller run karen\nInstallation ke start par “Add Python to PATH” ka checkbox lazmi tick karen\n“Install Now” par click karen\nInstallation complete hone ke baad command prompt open karen\nCommand likhen: python --version\nAgar version show ho jaye to installation successful hai\nPyCharm Installation Steps:\nPyCharm ki official website se Community version download karen\nInstaller run karen\nNext karte hue installation complete karen\nDesktop shortcut create karne ka option select karen\nInstallation ke baad PyCharm open karen\nNew Project create karen\nPython interpreter automatically select ho jana chahiye (jo pehle install kiya tha)\nProgramming Task:\nEk class banayen jiska naam ho Student\nIs class ke andar “Hello World” print kare \nVariables aur Data Types:\nEk string variable banayen (name = \"Ali\")\nEk integer variable banayen (age = 20)\nEk float variable banayen (marks = 85.5)\nIn sab ko print karen\nHar variable ka type check karen using type()\nComments:\nSingle line comment ka use karen (#)\nMulti-line comment ke liye triple quotes \"\"\" use karen\nStudents apna code PyCharm me likhen\n .py file submit karen\nHar step clearly follow kiya hona chahiye', 100, '2026-04-12 12:00:00', 1, 'active', 25, '2026-04-11 11:17:20', '2026-04-12 09:09:21'),
(3, 3, 3, 'Practice echo and print', 'Write a PHP program that demonstrates the use of both echo and print.\nYou must use each statement 5 times to display any text of your choice.', 1, '2026-04-12 21:00:00', 1, 'active', 2, '2026-04-11 16:19:03', '2026-04-16 17:52:57'),
(4, 2, NULL, 'First week Assignment', 'day 1\n\nuser se name input lo aur print karo\nuser se age lo aur print karo\nuser se city lo aur print karo\nname aur city ko combine karke sentence banao\n2 numbers lo aur unka sum print karo\n2 numbers lo aur unka multiply print karo\n2 numbers lo aur unka divide print karo\napna introduction program banao\n\nday 2\n\nuser se age string me lo aur int me convert karo\nek integer ko float me convert karo\nek float ko int me convert karo\n3 numbers lo aur average nikaalo\nvariable ka type check karo\nek negative number lo aur print karo\nscientific notation wala number print karo (e.g 2e3)\nstring aur number ko combine karke print karo\n\nday 3\n\n5 fruits ki list banao\nek fruit add karo\nek fruit remove karo\nlist ka first element print karo\nlist ka last element print karo\nlist slicing use karo\nlist me numbers lo aur sum nikaalo\nuser se index lo aur us item ko pop karo\n\nday 4\n\nek tuple banao\ntuple ka first element print karo\ntuple slicing karo\nek string ko 5 dafa print karo\nuser ka naam lo aur uppercase me print karo\nuser ka naam lowercase me print karo\nmulti-line string print karo\ndo strings ko combine karo\n\nday 5\n\nek dictionary banao (name, age, class)\ndictionary ki keys print karo\ndictionary ki values print karo\ndictionary ke items print karo\nuser se key lo aur value print karo\ndictionary me new data add karo\ndictionary se ek item remove karo\ndo dictionaries ko combine karo', 100, '2026-04-16 12:00:00', 0, 'active', 25, '2026-04-12 14:53:22', '2026-04-12 14:53:22'),
(5, 2, NULL, '2nd week First class (Operators)', '', 100, '2026-04-19 12:00:00', 1, 'active', 25, '2026-04-18 11:09:36', '2026-04-18 16:56:04'),
(6, 3, 5, 'Even or Odd', 'Check if a number is even or odd', 1, '2026-04-19 21:00:00', 0, 'active', 2, '2026-04-18 14:05:42', '2026-04-18 14:10:20'),
(7, 3, 5, 'Pass or Fail', 'Marks 40+ is pass, below is fail', 1, '2026-04-19 21:00:00', 0, 'active', 2, '2026-04-18 14:07:09', '2026-04-18 14:09:27'),
(8, 3, 5, 'Grade Calculator', 'A/B/C/D grades using elseif', 1, '2026-04-19 21:00:00', 0, 'active', 2, '2026-04-18 14:07:54', '2026-04-18 14:10:04'),
(9, 2, NULL, '2nd week 2nd class', 'Qno:01\na = True\n b = False \nprint(a and b) \nprint(a or b) \nprint(not a) \nIs code ka output kya hoga?\nQno2: Ek program likho jo user se ek number le aur check kare ki woh positive, negative ya zero hai (simple if-elif-else use karke).\nQno3: fruits = [\"apple\", \"banana\", \"mango\"] Check karo ki \"grapes\" list mein hai ya nahi using in operator. Output batao.\nQn4: User se age input lo. Agar age 18 ya usse zyada hai to \"You can vote\" print karo, warna \"You cannot vote\".\nQno5: Do variables x = 10 aur y = 20 lo. Check karo x > 5 and y < 30 — result True ya False?\nQno6: Ek program banao jo user se ek character le aur check kare ki woh vowel (a, e, i, o, u) hai ya nahi (membership operator in use karke).\n\nQno7: •  User se ek number input lo.\n•	Agar number even hai aur 50 se bada hai to \"Big Even Number\" print karo.\n•	Agar even hai lekin 50 se chhota hai to \"Small Even Number\".\n•	Agar odd hai aur 50 se bada hai to \"Big Odd Number\".\n•	Warna \"Small Odd Number\". (Logical operators and, or use karo)\nQno8: Ek nested if likho: User se marks input lo (0-100).\n•	Agar marks > 90 → \"Grade A\"\no	Agar marks > 95 → extra \"Outstanding!\" print karo (nested if)\n•	Agar marks > 80 → \"Grade B\"\n•	Agar marks > 60 → \"Grade C\"\n•	Warna \"Fail\"\nQno9: students = [\"Ali\", \"Sara\", \"Ahmed\", \"Zainab\", \"Bilal\"] User se ek naam input lo. Agar naam list mein hai aur naam ki length 5 se zyada hai to \"Valid Senior Student\" print karo. Warna \"Not Valid\" (Logical + Membership dono use karo)\nQno10: User se temperature input lo (Celsius).\n•	Agar temperature > 35 → \"Very Hot\"\n•	Agar 25 se 35 ke beech → \"Hot\"\n•	Agar 15 se 25 ke beech → \"Pleasant\"\n•	Agar 5 se 15 ke beech → \"Cold\"\n•	Agar < 5 → \"Very Cold\" (if-elif ladder banao, logical operators use kar sakte ho)\nQno11: Ek program likho jo user se username aur password le.\n•	Agar username == \"admin\" aur password == \"12345\" to \"Login Successful\"\n•	Agar username sahi hai lekin password galat to \"Wrong Password\"\n•	Agar username galat hai to \"Invalid Username\" (Nested if use karo)\nQno12: User se teen numbers a, b, c input lo. Nested if use karke sabse bada number find karo aur print karo. (Hint: pehle a aur b compare, phir usko c se compare)\n\nQno13: •  Ek grading system banao with nested if: User se percentage lo.\n•	Agar percentage >= 90:\no	Agar percentage == 100: \"Perfect Score - A+\"\no	Warna \"A Grade\"\n•	Agar percentage >= 80: \"B Grade\"\n•	Agar percentage >= 70: \"C Grade\"\n•	Warna \"Below Average\"', 100, '2026-04-24 23:59:00', 0, 'active', 25, '2026-04-19 11:00:47', '2026-04-19 11:00:47'),
(10, 2, 6, '3rd week assignment', 'Section 1: Basics and Variables (1 to 8)\n\n1. Ek variable name mein apna naam store karo aur print se display karo. Phir uski length bhi print karo.\n\n2. Do variables a = 25 aur b = 3.5 banao. Unka sum, product aur difference print karo. Data types bhi comment mein likho.\n\n3. User se apna naam aur age input lo. Phir print karo: Hello [naam], aapki umar [age] saal hai.\n\n4. Ek integer variable num = 100 lo. Ise string mein convert karke print karo aur type bhi check karo.\n\n5. User se do numbers input lo (float), unka average calculate karke print karo.\n\n6. Multiple assignment use karke teen variables x, y, z ko ek line mein 10, 20, 30 assign karo aur sab print karo.\n\n7. Ek string sentence = \"Python is fun\" banao. Isme \"fun\" word hai ya nahi check karo using in operator.\n\n8. User se ek number input lo (string mein aayega). Ise integer mein convert karke uska square print karo.\n\nSection 2: Operators (9 to 15)\n\n9. a = 15, b = 4\n   Sab arithmetic operators (+, -, *, /, //, %, **) use karke results print karo.\n\n10. score = 50\n    Assignment operators use karke:\n    score += 10\n    score *= 2\n    score -= 15\n    Har step ke baad print karo. Final value kya hai?\n\n11. x = 10, y = 20\n    Sab comparison operators (==, !=, >, <, >=, <=) use karke True/False print karo.\n\n12. a = True, b = False\n    Logical operators (and, or, not) ke saath sab combinations print karo.\n\n13. fruits = [\"apple\", \"banana\", \"mango\", \"orange\"]\n    User se ek fruit naam input lo. Check karo ki woh list mein hai ya nahi (in aur not in dono use karke).\n\n14. num = 7\n    Compound assignment operators se:\n    num **= 2\n    num //= 3\n    num %= 5\n    Final value print karo.\n\n15. 10 + 5 * 3 - 8 / 2 ** 2 ka output kya hoga? Pehle manually calculate karo, phir code mein run karke verify karo.\n\nSection 3: If-Elif-Else and Nested If (16 to 30)\n\n16. User se ek number input lo. Agar woh even hai to \"Even Number\" print karo, warna \"Odd Number\".\n\n17. User se age input lo. Agar age >= 18 hai to \"You are eligible to vote\" print karo, warna \"You are not eligible\".\n\n18. User se temperature (Celsius) input lo.\n    Agar > 35 → Very Hot\n    25 se 35 tak → Hot\n    15 se 25 tak → Pleasant\n    < 15 → Cold\n\n19. User se ek character input lo. Check karo ki woh vowel (a,e,i,o,u) hai ya consonant. Upper aur lower dono handle karo.\n\n20. Do numbers user se lo. Bada number konsa hai woh print karo (if-else use karke).\n\n21. User se marks input lo (0-100).\n    >= 90 → A\n    >= 80 → B\n    >= 70 → C\n    >= 60 → D\n    < 60 → Fail\n\n22. User se ek number input lo. Agar woh positive hai aur even bhi hai to \"Positive Even\" print karo (and use karke).\n\n23. User se teen numbers a, b, c input lo. Sabse chhota number find karke print karo.\n\n24. User se ek number lo.\n    Agar divisible by 3 aur 5 dono hai → Divisible by 15\n    Sirf 3 se → Divisible by 3\n    Sirf 5 se → Divisible by 5\n    Warna Not divisible by 3 or 5\n\n25. User se age input lo.\n    Agar age >= 18:\n        Agar age >= 60 → Senior Citizen\n        Warna Adult\n    Warna Minor\n\n26. User se username aur password input lo.\n    Agar username == \"student\" aur password == \"pass123\" → Login Successful\n    Agar username sahi lekin password galat → Wrong Password\n    Warna Invalid Username\n\n27. User se ek string input lo.\n    Agar string ki length > 10 aur usme \"python\" word hai to Valid Long Python String\n    Agar length > 10 lekin python nahi hai to Long String\n    Warna Short String\n\n28. User se teen numbers lo. Nested if use karke sabse bada number find karo aur print karo.\n\n29. User se percentage input lo.\n    Agar >= 90:\n        Agar 100 hai to Perfect A+\n        Warna A Grade\n    >= 80 → B Grade\n    >= 70 → C Grade\n    Warna Needs Improvement', 100, '2026-05-02 13:00:00', 0, 'active', 25, '2026-04-28 04:41:53', '2026-04-28 04:41:53');

-- --------------------------------------------------------

--
-- Table structure for table `assignment_files`
--

CREATE TABLE `assignment_files` (
  `id` int(10) UNSIGNED NOT NULL,
  `assignment_id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT 0,
  `file_type` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `uploaded_by` int(10) UNSIGNED NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assignment_files`
--

INSERT INTO `assignment_files` (`id`, `assignment_id`, `file_name`, `file_path`, `file_size`, `file_type`, `status`, `uploaded_by`, `uploaded_at`) VALUES
(2, 2, 'Screenshot 2026-04-11 163640.png', 'introduction_to_python_programming_and_development_setup/screenshot_2026-04-11_163640.png', 54752, 'image/png', 'active', 25, '2026-04-11 11:37:25'),
(4, 4, 'Screenshot 2026-04-12 195445.png', 'first_week_assignment/screenshot_2026-04-12_195445.png', 30068, 'image/png', 'active', 25, '2026-04-12 14:57:32'),
(5, 4, 'Screenshot 2026-04-12 195550.png', 'first_week_assignment/screenshot_2026-04-12_195550.png', 19533, 'image/png', 'active', 25, '2026-04-12 14:57:33'),
(6, 5, 'assignment.txt', '2nd_week_first_class_operators/assignment.txt', 2119, 'text/plain', 'active', 25, '2026-04-18 11:11:20'),
(7, 9, 'assignment.txt', '2nd_week_2nd_class/assignment.txt', 2539, 'text/plain', 'active', 25, '2026-04-19 11:02:08'),
(8, 10, '3rd week assignment.txt', '3rd_week_assignment/3rd_week_assignment.txt', 3823, 'text/plain', 'active', 25, '2026-04-28 04:42:57');

-- --------------------------------------------------------

--
-- Table structure for table `batches`
--

CREATE TABLE `batches` (
  `id` int(10) UNSIGNED NOT NULL,
  `course_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `max_students` int(10) UNSIGNED DEFAULT 50,
  `status` enum('active','inactive','completed') DEFAULT 'active',
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batches`
--

INSERT INTO `batches` (`id`, `course_id`, `name`, `description`, `start_date`, `end_date`, `max_students`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 2, 'Python April to june', '', '2026-04-11', '2026-05-10', 50, 'completed', 24, '2026-04-10 15:41:16', '2026-05-10 10:52:24'),
(3, 3, 'PHP Basics Level 1 - April-May 2K26', '', NULL, NULL, 50, 'active', 2, '2026-04-11 13:07:07', '2026-07-07 17:51:38');

-- --------------------------------------------------------

--
-- Table structure for table `batch_students`
--

CREATE TABLE `batch_students` (
  `id` int(10) UNSIGNED NOT NULL,
  `batch_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `enrolled_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batch_students`
--

INSERT INTO `batch_students` (`id`, `batch_id`, `student_id`, `enrolled_at`, `enrolled_by`) VALUES
(110, 2, 2, '2026-04-11 11:22:48', 1),
(122, 3, 2, '2026-04-11 16:20:35', 2);

-- --------------------------------------------------------

--
-- Table structure for table `batch_teachers`
--

CREATE TABLE `batch_teachers` (
  `id` int(10) UNSIGNED NOT NULL,
  `batch_id` int(10) UNSIGNED NOT NULL,
  `teacher_id` int(10) UNSIGNED NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batch_teachers`
--

INSERT INTO `batch_teachers` (`id`, `batch_id`, `teacher_id`, `assigned_at`, `assigned_by`) VALUES
(8, 2, 2, '2026-04-11 11:22:57', 1),
(10, 3, 2, '2026-04-11 13:07:22', 2);

-- --------------------------------------------------------

--
-- Table structure for table `blocked_users`
--

CREATE TABLE `blocked_users` (
  `id` int(10) UNSIGNED NOT NULL,
  `blocker_id` int(10) UNSIGNED NOT NULL,
  `blocked_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(10) UNSIGNED NOT NULL,
  `user1_id` int(10) UNSIGNED NOT NULL,
  `user2_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `hidden_user1` tinyint(1) DEFAULT 0,
  `hidden_user2` tinyint(1) DEFAULT 0,
  `hidden_user1_at` datetime DEFAULT NULL,
  `hidden_user2_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`id`, `user1_id`, `user2_id`, `created_at`, `updated_at`, `hidden_user1`, `hidden_user2`, `hidden_user1_at`, `hidden_user2_at`) VALUES
(1, 1, 2, '2026-03-15 10:04:00', '2026-03-15 10:04:00', 0, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive','archived') DEFAULT 'active',
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `title`, `description`, `thumbnail`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 'Python', '', NULL, 'active', 24, '2026-04-10 15:39:59', '2026-04-10 15:39:59'),
(3, 'PHP Basics', '', 'topic/final-php-basics-building-dynamic-web-applications-from-scratch-0-1024x576.jpg', 'active', 2, '2026-04-11 13:05:32', '2026-05-12 16:29:16');

-- --------------------------------------------------------

--
-- Table structure for table `course_applications`
--

CREATE TABLE `course_applications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `batch_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','test_submitted','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `applied_at` timestamp NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback_entries`
--

CREATE TABLE `feedback_entries` (
  `id` int(10) UNSIGNED NOT NULL,
  `session_id` int(10) UNSIGNED NOT NULL,
  `batch_id` int(10) UNSIGNED NOT NULL,
  `content` text NOT NULL,
  `categories` text NOT NULL DEFAULT '["general"]',
  `is_reviewed` tinyint(1) NOT NULL DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback_entries`
--

INSERT INTO `feedback_entries` (`id`, `session_id`, `batch_id`, `content`, `categories`, `is_reviewed`, `submitted_at`) VALUES
(1, 3, 2, 'Bohat shukriya sir, aap ne waqayi har cheez bohat achay aur practical tariqay se samjhayi. Main portal par apna feedback zaroor share karun ga/gi', '[\"general\"]', 1, '2026-05-10 11:03:25');

-- --------------------------------------------------------

--
-- Table structure for table `feedback_sessions`
--

CREATE TABLE `feedback_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `batch_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT 'Batch Feedback',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `activated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `activated_at` datetime DEFAULT NULL,
  `deactivated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback_sessions`
--

INSERT INTO `feedback_sessions` (`id`, `batch_id`, `title`, `description`, `is_active`, `activated_by`, `created_at`, `activated_at`, `deactivated_at`) VALUES
(3, 2, 'End of Batch Feedback', '', 0, NULL, '2026-05-10 10:54:03', '2026-05-10 15:54:03', '2026-06-06 22:05:22');

-- --------------------------------------------------------

--
-- Table structure for table `feedback_tokens`
--

CREATE TABLE `feedback_tokens` (
  `id` int(10) UNSIGNED NOT NULL,
  `session_id` int(10) UNSIGNED NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback_tokens`
--

INSERT INTO `feedback_tokens` (`id`, `session_id`, `token_hash`, `submitted_at`) VALUES
(1, 3, '6034a952fcf470e14edc71d0bb3d821dbbf1198b612cab927e2ecff3256fd3f8', '2026-05-10 11:03:25');

-- --------------------------------------------------------

--
-- Table structure for table `live_sessions`
--

CREATE TABLE `live_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `batch_id` int(10) UNSIGNED NOT NULL,
  `room_name` varchar(200) NOT NULL,
  `title` varchar(200) DEFAULT 'Live Class',
  `started_by` int(10) UNSIGNED NOT NULL,
  `status` enum('waiting','active','ended') DEFAULT 'waiting',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ended_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `live_sessions`
--

INSERT INTO `live_sessions` (`id`, `batch_id`, `room_name`, `title`, `started_by`, `status`, `started_at`, `ended_at`) VALUES
(16, 1, 'vpaas-magic-cookie-2c210a3641944493a77539ebd96748f7/eb899dc7a51abc2a9674', 'testing', 2, 'ended', '2026-03-26 08:09:35', '2026-03-26 13:12:29'),
(17, 1, 'vpaas-magic-cookie-2c210a3641944493a77539ebd96748f7/8c26a0348e694343752f', 'JS Day-2', 2, 'ended', '2026-03-26 15:04:52', '2026-03-26 20:55:17'),
(18, 2, 'vpaas-magic-cookie-2c210a3641944493a77539ebd96748f7/a5c4492ee77aff03a09b', 'Python class first', 25, 'ended', '2026-04-10 16:02:45', '2026-04-10 21:04:19'),
(19, 2, 'vpaas-magic-cookie-2c210a3641944493a77539ebd96748f7/fbc0da27b7ba5737dfe5', 'Python 1st class', 25, 'ended', '2026-04-11 09:00:48', '2026-04-11 16:07:56'),
(21, 2, 'vpaas-magic-cookie-2c210a3641944493a77539ebd96748f7/dcac7a724aa4a68828ff', 'Live Class', 25, 'ended', '2026-04-12 08:59:47', '2026-04-12 16:02:26'),
(23, 2, 'vpaas-magic-cookie-2c210a3641944493a77539ebd96748f7/952eedbb95af845cee37', '2nd week', 25, 'ended', '2026-04-18 09:17:32', '2026-04-18 14:40:56'),
(24, 2, 'vpaas-magic-cookie-2c210a3641944493a77539ebd96748f7/d6ec7256a40d3b5332d8', '2nd week 1st clas', 25, 'ended', '2026-04-18 09:43:46', '2026-04-18 16:05:16'),
(25, 2, 'vpaas-magic-cookie-2c210a3641944493a77539ebd96748f7/cbe720503257aef4f2ec', '2nd week 2nd class', 25, 'ended', '2026-04-19 09:03:23', '2026-04-19 15:48:15'),
(26, 2, 'vpaas-magic-cookie-2c210a3641944493a77539ebd96748f7/25a6a84183262b259ad4', '3rd week first class', 25, 'ended', '2026-05-01 09:37:16', '2026-05-01 16:31:35'),
(27, 2, 'vpaas-magic-cookie-2c210a3641944493a77539ebd96748f7/5f9cdc5b27ac94b1adb2', 'last week first class', 25, 'ended', '2026-05-09 08:59:23', '2026-05-10 13:51:48'),
(28, 2, 'vpaas-magic-cookie-2c210a3641944493a77539ebd96748f7/a8f52e16891d1408c40b', 'last week second class', 25, 'ended', '2026-05-10 08:57:50', '2026-05-10 15:42:53'),
(29, 3, 'vpaas-magic-cookie-2c210a3641944493a77539ebd96748f7/6be8abf3dedc5caadac9', 'admin testing', 1, 'ended', '2026-08-25 03:37:04', '2026-08-25 08:37:15');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `conversation_id` int(10) UNSIGNED NOT NULL,
  `sender_id` int(10) UNSIGNED NOT NULL,
  `content` text NOT NULL,
  `type` enum('text','deleted') DEFAULT 'text',
  `status` enum('sent','delivered','seen') DEFAULT 'sent',
  `reactions` text DEFAULT NULL,
  `deleted_for_sender` tinyint(1) DEFAULT 0,
  `deleted_for_receiver` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `is_used`) VALUES
(6, 2, '728230', '2026-08-24 16:39:49', 0);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `label`, `created_at`) VALUES
(1, 'student', 'Student', '2026-03-15 09:34:00'),
(2, 'teacher', 'Teacher', '2026-03-15 09:34:00'),
(3, 'admin', 'Administrator', '2026-03-15 09:34:00');

-- --------------------------------------------------------

--
-- Table structure for table `session_attendees`
--

CREATE TABLE `session_attendees` (
  `id` int(10) UNSIGNED NOT NULL,
  `session_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `left_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `session_attendees`
--

INSERT INTO `session_attendees` (`id`, `session_id`, `user_id`, `joined_at`, `left_at`) VALUES
(1, 5, 2, '2026-03-15 18:11:04', '2026-03-15 11:11:29'),
(2, 11, 2, '2026-03-17 05:05:52', '2026-03-16 22:05:55'),
(6, 12, 2, '2026-03-17 10:20:44', '2026-03-17 03:20:48'),
(35, 17, 7, '2026-03-26 15:05:34', '2026-03-26 20:54:08'),
(38, 17, 19, '2026-03-26 15:06:20', '2026-03-26 20:54:10'),
(39, 17, 20, '2026-03-26 15:49:47', '2026-03-26 20:55:17'),
(42, 18, 26, '2026-04-10 16:03:29', '2026-04-10 21:04:08'),
(44, 19, 31, '2026-04-11 09:01:50', '2026-04-11 16:07:56'),
(45, 19, 30, '2026-04-11 09:10:00', '2026-04-11 16:07:56'),
(47, 19, 29, '2026-04-11 09:19:32', '2026-04-11 16:07:46'),
(55, 19, 32, '2026-04-11 09:11:28', '2026-04-11 16:07:28'),
(58, 19, 34, '2026-04-11 10:17:25', '2026-04-11 16:06:37'),
(61, 19, 33, '2026-04-11 09:30:22', '2026-04-11 15:22:20'),
(65, 19, 26, '2026-04-11 09:32:43', '2026-04-11 15:37:43'),
(72, 21, 31, '2026-04-12 09:47:35', '2026-04-12 16:02:26'),
(73, 21, 30, '2026-04-12 09:11:46', '2026-04-12 16:02:26'),
(74, 21, 32, '2026-04-12 09:02:10', '2026-04-12 16:02:17'),
(75, 21, 29, '2026-04-12 10:41:23', '2026-04-12 16:02:25'),
(77, 21, 26, '2026-04-12 09:08:08', '2026-04-12 15:53:08'),
(95, 21, 2, '2026-04-12 09:28:41', '2026-04-12 14:28:58'),
(102, 23, 31, '2026-04-18 09:21:46', '2026-04-18 14:40:56'),
(104, 23, 30, '2026-04-18 09:30:46', '2026-04-18 14:40:56'),
(109, 23, 34, '2026-04-18 09:34:53', '2026-04-18 14:40:56'),
(110, 23, 32, '2026-04-18 09:26:32', '2026-04-18 14:40:56'),
(111, 23, 26, '2026-04-18 09:32:46', '2026-04-18 14:40:56'),
(112, 23, 29, '2026-04-18 09:36:27', '2026-04-18 14:40:56'),
(120, 24, 31, '2026-04-18 09:44:33', '2026-04-18 16:05:16'),
(121, 24, 34, '2026-04-18 09:45:41', '2026-04-18 16:05:16'),
(122, 24, 30, '2026-04-18 10:44:26', '2026-04-18 16:05:16'),
(123, 24, 32, '2026-04-18 09:43:59', '2026-04-18 16:05:16'),
(125, 24, 29, '2026-04-18 10:43:00', '2026-04-18 16:05:16'),
(127, 24, 26, '2026-04-18 10:43:07', '2026-04-18 16:05:16'),
(133, 25, 30, '2026-04-19 09:04:17', '2026-04-19 15:48:15'),
(135, 25, 31, '2026-04-19 10:27:01', '2026-04-19 15:39:14'),
(136, 25, 32, '2026-04-19 10:44:46', '2026-04-19 15:44:58'),
(137, 25, 29, '2026-04-19 09:11:39', '2026-04-19 14:11:54'),
(138, 25, 26, '2026-04-19 10:34:44', '2026-04-19 15:48:15'),
(141, 25, 34, '2026-04-19 09:29:30', '2026-04-19 15:48:15'),
(155, 26, 31, '2026-05-01 10:15:37', '2026-05-01 16:31:35'),
(157, 26, 30, '2026-05-01 10:17:32', '2026-05-01 15:18:12'),
(161, 26, 29, '2026-05-01 09:55:02', '2026-05-01 14:57:01'),
(167, 27, 31, '2026-05-09 10:50:21', '2026-05-09 15:50:25'),
(169, 27, 34, '2026-05-09 09:02:09', '2026-05-09 14:04:04'),
(170, 27, 32, '2026-05-09 10:09:33', '2026-05-09 15:11:51'),
(171, 27, 26, '2026-05-09 09:28:07', '2026-05-09 15:32:49'),
(177, 27, 30, '2026-05-09 10:13:13', '2026-05-09 15:33:08'),
(184, 28, 30, '2026-05-10 10:33:00', '2026-05-10 15:42:53'),
(185, 28, 26, '2026-05-10 09:55:27', '2026-05-10 15:42:53');

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `assignment_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT 0,
  `notes` text DEFAULT NULL,
  `marks` int(11) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `status` enum('submitted','graded','returned') DEFAULT 'submitted',
  `is_late` tinyint(1) DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `graded_at` datetime DEFAULT NULL,
  `graded_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `submissions`
--

INSERT INTO `submissions` (`id`, `assignment_id`, `student_id`, `file_name`, `file_path`, `file_size`, `notes`, `marks`, `feedback`, `status`, `is_late`, `submitted_at`, `graded_at`, `graded_by`) VALUES
(17, 8, 2, 'index.php', 'grade_calculator/16933_shariq_index.php', 391, '', 0, 'file is not acceptable, please ensure you upload correct file format of the assignment', 'returned', 0, '2026-04-18 17:33:26', '2026-04-18 22:34:29', 2);

-- --------------------------------------------------------

--
-- Table structure for table `tests`
--

CREATE TABLE `tests` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('entry','weekly','monthly') NOT NULL DEFAULT 'entry',
  `batch_id` int(10) UNSIGNED DEFAULT NULL,
  `time_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 30,
  `status` enum('active','inactive') DEFAULT 'active',
  `entry_active` tinyint(1) DEFAULT 0,
  `questions_locked` tinyint(1) DEFAULT 0,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tests`
--

INSERT INTO `tests` (`id`, `title`, `description`, `type`, `batch_id`, `time_minutes`, `status`, `entry_active`, `questions_locked`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Entry Test Software Developement', 'Entry Test Software Developement', 'entry', 3, 5, 'active', 0, 0, 2, '2026-03-30 04:51:31', '2026-09-24 04:41:22');

-- --------------------------------------------------------

--
-- Table structure for table `test_answers`
--

CREATE TABLE `test_answers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `attempt_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `selected_option_id` int(10) UNSIGNED DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `marks_awarded` decimal(4,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `test_attempts`
--

CREATE TABLE `test_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `test_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `application_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('in_progress','submitted','reviewed') DEFAULT 'in_progress',
  `score` decimal(6,2) DEFAULT 0.00,
  `percentage` decimal(5,2) DEFAULT 0.00,
  `total_questions` smallint(5) UNSIGNED DEFAULT 0,
  `correct_count` smallint(5) UNSIGNED DEFAULT 0,
  `wrong_count` smallint(5) UNSIGNED DEFAULT 0,
  `unanswered_count` smallint(5) UNSIGNED DEFAULT 0,
  `allow_reattempt` tinyint(1) DEFAULT 0,
  `started_at` timestamp NULL DEFAULT current_timestamp(),
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `test_options`
--

CREATE TABLE `test_options` (
  `id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `sort_order` tinyint(3) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_options`
--

INSERT INTO `test_options` (`id`, `question_id`, `option_text`, `is_correct`, `sort_order`) VALUES
(1, 1, '1', 1, 0),
(2, 1, '2', 0, 1),
(3, 1, '4', 0, 2),
(4, 1, '3', 0, 3),
(5, 2, '1', 1, 0),
(6, 2, '2', 0, 1),
(7, 2, '4', 0, 2),
(8, 2, '3', 0, 3),
(9, 3, '1', 1, 0),
(10, 3, '2', 0, 1),
(11, 3, '4', 0, 2),
(12, 3, '3', 0, 3),
(13, 4, '1', 1, 0),
(14, 4, '2', 0, 1),
(15, 4, '4', 0, 2),
(16, 4, '3', 0, 3);

-- --------------------------------------------------------

--
-- Table structure for table `test_questions`
--

CREATE TABLE `test_questions` (
  `id` int(10) UNSIGNED NOT NULL,
  `question_text` text NOT NULL,
  `is_code` tinyint(1) DEFAULT 0,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_questions`
--

INSERT INTO `test_questions` (`id`, `question_text`, `is_code`, `created_by`, `created_at`) VALUES
(1, 'What is PHP ?', 0, 2, '2026-03-30 04:51:31'),
(2, 'What is PHP ?', 0, 2, '2026-04-11 14:31:31'),
(3, 'What is PHP ?', 0, 2, '2026-04-17 10:01:42'),
(4, 'What is PHP ?', 0, 2, '2026-04-30 17:26:31');

-- --------------------------------------------------------

--
-- Table structure for table `test_question_map`
--

CREATE TABLE `test_question_map` (
  `id` int(10) UNSIGNED NOT NULL,
  `test_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `sort_order` smallint(5) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_question_map`
--

INSERT INTO `test_question_map` (`id`, `test_id`, `question_id`, `sort_order`) VALUES
(3, 1, 3, 0);

-- --------------------------------------------------------

--
-- Table structure for table `topics`
--

CREATE TABLE `topics` (
  `id` int(10) UNSIGNED NOT NULL,
  `batch_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(10) UNSIGNED DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `topics`
--

INSERT INTO `topics` (`id`, `batch_id`, `title`, `description`, `sort_order`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(3, 3, 'PHP Basics Level 1 - Day-1', '', 0, 'active', 2, '2026-04-11 18:17:29', '2026-04-11 18:17:29'),
(4, 2, 'Python first week', '', 0, 'active', 25, '2026-04-12 11:04:03', '2026-04-12 11:04:03'),
(5, 3, '2nd Session', 'Covered Variables naming, DataTypes, Operators, and Conditionals', 0, 'active', 2, '2026-04-17 16:18:53', '2026-04-18 09:36:46'),
(6, NULL, '2nd week 2nd class', '', 0, 'active', 25, '2026-04-19 10:49:33', '2026-04-19 10:49:33');

-- --------------------------------------------------------

--
-- Table structure for table `topic_files`
--

CREATE TABLE `topic_files` (
  `id` int(10) UNSIGNED NOT NULL,
  `topic_id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT 0,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `topic_files`
--

INSERT INTO `topic_files` (`id`, `topic_id`, `file_name`, `file_path`, `file_size`, `file_type`, `uploaded_by`, `status`, `uploaded_at`) VALUES
(3, 3, 'april_11.php', 'php_basics_level_1_-_day-1/april_11.php', 450, 'application/octet-stream', 2, 'active', '2026-04-11 18:17:54'),
(5, 4, 'python introducation 1.pptx', 'python_first_week/python_introducation_1.pptx', 747521, 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 25, 'active', '2026-04-12 11:04:31'),
(6, 4, 'class2nd.py', 'python_first_week/class2nd.py', 1652, 'text/x-python', 25, 'active', '2026-04-12 11:05:05'),
(7, 4, 'studentform.py', 'python_first_week/studentform.py', 971, 'text/x-python', 25, 'active', '2026-04-12 11:05:05'),
(8, 5, 'Day 2 Variables DataTypes Conditionals.pptx', '2nd_session/day_2_variables_datatypes_conditionals.pptx', 1387821, 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 2, 'active', '2026-04-17 16:19:54'),
(9, 5, 'index.php', '2nd_session/index.php', 391, 'application/octet-stream', 2, 'active', '2026-04-17 16:20:44'),
(10, 5, 'outline.php', '2nd_session/outline.php', 2474, 'application/octet-stream', 2, 'active', '2026-04-17 16:20:44'),
(11, 6, 'ifelse condition 3.pptx', '2nd_week_2nd_class/ifelse_condition_3.pptx', 108803, 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 25, 'active', '2026-04-19 10:49:54'),
(12, 6, 'operators in python.pptx', '2nd_week_2nd_class/operators_in_python.pptx', 61590, 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 25, 'active', '2026-04-19 10:49:55'),
(13, 6, 'conditionalstatement.py', '2nd_week_2nd_class/conditionalstatement.py', 1744, 'text/x-python', 25, 'active', '2026-04-19 10:50:26'),
(14, 6, 'operators3.py', '2nd_week_2nd_class/operators3.py', 1648, 'text/x-python', 25, 'active', '2026-04-19 10:50:26'),
(15, 6, 'operatorslogical.py', '2nd_week_2nd_class/operatorslogical.py', 978, 'text/x-python', 25, 'active', '2026-04-19 10:50:27');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(255) NOT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'inactive',
  `is_online` tinyint(1) DEFAULT 0,
  `last_seen` datetime DEFAULT NULL,
  `theme_preference` enum('light','dark','system') DEFAULT 'system',
  `current_role` varchar(50) DEFAULT 'student',
  `bypass_gate` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cnic` varchar(15) DEFAULT NULL,
  `user_id_number` varchar(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `gender`, `profile_picture`, `bio`, `phone`, `is_verified`, `status`, `is_online`, `last_seen`, `theme_preference`, `current_role`, `bypass_gate`, `created_at`, `updated_at`, `cnic`, `user_id_number`) VALUES
(1, 'System Admin', 'admin@lms.com', '$2y$10$jI.1wDEIvwlVrbDyZSl5fO0D/bvKhfkkM28qBqeoRkcMOimYR3Uv6', 'male', '00001_system_admin/profile.png', NULL, '', 1, 'active', 1, '2026-09-24 09:45:53', 'dark', 'admin', 1, '2026-03-15 09:34:00', '2026-09-24 04:45:53', NULL, NULL),
(2, 'Shariq Ali', 'shariqbhutto5@gmail.com', '$2y$10$17S.kCnLN40CwnLBmLOkheprvPoEM/qT2NeEocW40rMPea9IgUTVW', 'male', '16933_shariq_ali/profile.png', NULL, '', 1, 'active', 0, '2026-08-25 15:58:30', 'dark', 'admin', 0, '2026-03-15 09:37:22', '2026-08-25 10:58:30', '43205-8540801-1', '16933');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `assigned_at`, `assigned_by`) VALUES
(15, 2, 1, '2026-03-16 11:39:53', 1),
(16, 2, 2, '2026-03-16 11:39:53', 1),
(17, 2, 3, '2026-03-16 11:39:53', 1),
(70, 1, 1, '2026-08-25 03:28:47', 2),
(71, 1, 2, '2026-08-25 03:28:47', 2),
(72, 1, 3, '2026-08-25 03:28:47', 2);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `topic_id` (`topic_id`);

--
-- Indexes for table `assignment_files`
--
ALTER TABLE `assignment_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assignment_id` (`assignment_id`);

--
-- Indexes for table `batches`
--
ALTER TABLE `batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `batch_students`
--
ALTER TABLE `batch_students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_batch_student` (`batch_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `batch_teachers`
--
ALTER TABLE `batch_teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_batch_teacher` (`batch_id`,`teacher_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `blocked_users`
--
ALTER TABLE `blocked_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_block` (`blocker_id`,`blocked_id`),
  ADD KEY `idx_blocker` (`blocker_id`),
  ADD KEY `idx_blocked` (`blocked_id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_conversation` (`user1_id`,`user2_id`),
  ADD KEY `user2_id` (`user2_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `course_applications`
--
ALTER TABLE `course_applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_application` (`user_id`,`batch_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_batch` (`batch_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `feedback_entries`
--
ALTER TABLE `feedback_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_batch` (`batch_id`),
  ADD KEY `idx_reviewed` (`is_reviewed`);

--
-- Indexes for table `feedback_sessions`
--
ALTER TABLE `feedback_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_batch` (`batch_id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `fk_fs_user` (`activated_by`);

--
-- Indexes for table `feedback_tokens`
--
ALTER TABLE `feedback_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_token` (`session_id`,`token_hash`);

--
-- Indexes for table `live_sessions`
--
ALTER TABLE `live_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_room` (`room_name`),
  ADD KEY `idx_batch_status` (`batch_id`,`status`),
  ADD KEY `idx_started_by` (`started_by`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conv_created` (`conversation_id`,`created_at`),
  ADD KEY `idx_sender` (`sender_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `session_attendees`
--
ALTER TABLE `session_attendees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendee` (`session_id`,`user_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_submission` (`assignment_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `tests`
--
ALTER TABLE `tests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_batch` (`batch_id`),
  ADD KEY `idx_entry_active` (`entry_active`),
  ADD KEY `idx_locked` (`questions_locked`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `test_answers`
--
ALTER TABLE `test_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_answer` (`attempt_id`,`question_id`),
  ADD KEY `idx_attempt` (`attempt_id`),
  ADD KEY `idx_question` (`question_id`),
  ADD KEY `selected_option_id` (`selected_option_id`);

--
-- Indexes for table `test_attempts`
--
ALTER TABLE `test_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attempt` (`test_id`,`student_id`),
  ADD KEY `idx_test` (`test_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_application` (`application_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `test_options`
--
ALTER TABLE `test_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_question` (`question_id`);

--
-- Indexes for table `test_questions`
--
ALTER TABLE `test_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `test_question_map`
--
ALTER TABLE `test_question_map`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_test_question` (`test_id`,`question_id`),
  ADD KEY `idx_test` (`test_id`),
  ADD KEY `idx_question` (`question_id`);

--
-- Indexes for table `topics`
--
ALTER TABLE `topics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `topic_files`
--
ALTER TABLE `topic_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `topic_id` (`topic_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_cnic` (`cnic`),
  ADD KEY `idx_user_id_number` (`user_id_number`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_role` (`user_id`,`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=916;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `assignment_files`
--
ALTER TABLE `assignment_files`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `batches`
--
ALTER TABLE `batches`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `batch_students`
--
ALTER TABLE `batch_students`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT for table `batch_teachers`
--
ALTER TABLE `batch_teachers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `blocked_users`
--
ALTER TABLE `blocked_users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `course_applications`
--
ALTER TABLE `course_applications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `feedback_entries`
--
ALTER TABLE `feedback_entries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `feedback_sessions`
--
ALTER TABLE `feedback_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `feedback_tokens`
--
ALTER TABLE `feedback_tokens`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `live_sessions`
--
ALTER TABLE `live_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `session_attendees`
--
ALTER TABLE `session_attendees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=194;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `tests`
--
ALTER TABLE `tests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `test_answers`
--
ALTER TABLE `test_answers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `test_attempts`
--
ALTER TABLE `test_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `test_options`
--
ALTER TABLE `test_options`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `test_questions`
--
ALTER TABLE `test_questions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `test_question_map`
--
ALTER TABLE `test_question_map`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `topics`
--
ALTER TABLE `topics`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `topic_files`
--
ALTER TABLE `topic_files`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assignments_ibfk_2` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `assignment_files`
--
ALTER TABLE `assignment_files`
  ADD CONSTRAINT `assignment_files_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `batches`
--
ALTER TABLE `batches`
  ADD CONSTRAINT `batches_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `batch_students`
--
ALTER TABLE `batch_students`
  ADD CONSTRAINT `batch_students_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `batch_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `batch_teachers`
--
ALTER TABLE `batch_teachers`
  ADD CONSTRAINT `batch_teachers_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `batch_teachers_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `course_applications`
--
ALTER TABLE `course_applications`
  ADD CONSTRAINT `course_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_applications_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD CONSTRAINT `email_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback_entries`
--
ALTER TABLE `feedback_entries`
  ADD CONSTRAINT `fk_fe_session` FOREIGN KEY (`session_id`) REFERENCES `feedback_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback_sessions`
--
ALTER TABLE `feedback_sessions`
  ADD CONSTRAINT `fk_fs_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fs_user` FOREIGN KEY (`activated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `feedback_tokens`
--
ALTER TABLE `feedback_tokens`
  ADD CONSTRAINT `fk_ft_session` FOREIGN KEY (`session_id`) REFERENCES `feedback_sessions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `submissions`
--
ALTER TABLE `submissions`
  ADD CONSTRAINT `submissions_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `submissions_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tests`
--
ALTER TABLE `tests`
  ADD CONSTRAINT `tests_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tests_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `test_answers`
--
ALTER TABLE `test_answers`
  ADD CONSTRAINT `test_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `test_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `test_questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_answers_ibfk_3` FOREIGN KEY (`selected_option_id`) REFERENCES `test_options` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `test_attempts`
--
ALTER TABLE `test_attempts`
  ADD CONSTRAINT `test_attempts_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_attempts_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_attempts_ibfk_3` FOREIGN KEY (`application_id`) REFERENCES `course_applications` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `test_options`
--
ALTER TABLE `test_options`
  ADD CONSTRAINT `test_options_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `test_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `test_questions`
--
ALTER TABLE `test_questions`
  ADD CONSTRAINT `test_questions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `test_question_map`
--
ALTER TABLE `test_question_map`
  ADD CONSTRAINT `test_question_map_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_question_map_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `test_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `topics`
--
ALTER TABLE `topics`
  ADD CONSTRAINT `topics_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `topic_files`
--
ALTER TABLE `topic_files`
  ADD CONSTRAINT `topic_files_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
