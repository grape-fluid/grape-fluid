CREATE TABLE `migration` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  `module` varchar(80) COLLATE utf8_czech_ci NOT NULL,
  `processed` tinyint(1) NOT NULL DEFAULT '0',
  `last_update` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

CREATE TABLE `mc_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `magic_control` varchar(255) NOT NULL,
  `template_name` varchar(64) NULL,
  `source` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

ALTER TABLE `mc_templates` ADD UNIQUE `mc_templates_magic_control_template_name` (`magic_control`, `template_name`);
