--
-- Database: `scripts`
--

-- --------------------------------------------------------

--
-- Table structure for table `reddittotg`
--

CREATE TABLE `reddittotg` (
  `id` varchar(7) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `result` varchar(5) NOT NULL DEFAULT 'ok'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `reddittotg`
--
ALTER TABLE `reddittotg`
  ADD UNIQUE KEY `id` (`id`);