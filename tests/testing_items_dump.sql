SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;


DROP TABLE IF EXISTS `test_items`;
CREATE TABLE `test_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL DEFAULT '0',
  `children_count` mediumint(9) NOT NULL DEFAULT '0',
  `subitems_count` mediumint(9) NOT NULL DEFAULT '0',
  `title` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=8 ;

INSERT INTO `test_items` VALUES(1, 0, 2, 2, 'Item7 (top level)');
INSERT INTO `test_items` VALUES(2, 1, 1, 2, 'Item2 (child of 1)');
INSERT INTO `test_items` VALUES(3, 1, 0, 3, 'Item3 (child of 1)');
INSERT INTO `test_items` VALUES(4, 2, 2, 1, 'Item4 (child of 2)');
INSERT INTO `test_items` VALUES(5, 4, 0, 0, 'Item6 (child of 4)');
INSERT INTO `test_items` VALUES(6, 4, 0, 0, 'Item5 (child of 4)');
INSERT INTO `test_items` VALUES(7, 0, 0, 0, 'Item1 (top level)');

DROP TABLE IF EXISTS `test_items_info`;
CREATE TABLE `test_items_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL DEFAULT '',
  `price` float NOT NULL DEFAULT '0',
  `title` varchar(255) NOT NULL DEFAULT 'Rewritten title',
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_id` (`item_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=234 ;

INSERT INTO `test_items_info` VALUES(233, 1, 'Full name of item#1', 150.55, 'Rewritten title');

DROP TABLE IF EXISTS `test_items_text`;
CREATE TABLE `test_items_text` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `full_text` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_id` (`item_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=246 ;

INSERT INTO `test_items_text` VALUES(245, 1, 'Коллективное бессознательное');

DROP TABLE IF EXISTS `test_subitems`;
CREATE TABLE `test_subitems` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `num` int(11) NOT NULL DEFAULT '0',
  `type` enum('a','b') NOT NULL DEFAULT 'a',
  PRIMARY KEY (`id`),
  KEY `item_id` (`item_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=9 ;

INSERT INTO `test_subitems` VALUES(1, 1, 'SubItem1_1', 1, 'a');
INSERT INTO `test_subitems` VALUES(2, 1, 'SubItem1_2', 2, 'a');
INSERT INTO `test_subitems` VALUES(3, 2, 'SubItem2_1', 1, 'a');
INSERT INTO `test_subitems` VALUES(4, 2, 'SubItem2_2', 2, 'a');
INSERT INTO `test_subitems` VALUES(5, 3, 'SubItem3_1', 1, 'a');
INSERT INTO `test_subitems` VALUES(6, 3, 'SubItem3_2', 2, 'a');
INSERT INTO `test_subitems` VALUES(7, 3, 'SubItem3_3', 3, 'a');
INSERT INTO `test_subitems` VALUES(8, 4, 'SubItem4_1', 1, 'a');

DROP TABLE IF EXISTS `test_subsubitems`;
CREATE TABLE `test_subsubitems` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subitem_id` int(11) NOT NULL DEFAULT '0',
  `title` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `subitem_id` (`subitem_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=5 ;

INSERT INTO `test_subsubitems` VALUES(1, 3, 'SubSubItem2_1_1');
INSERT INTO `test_subsubitems` VALUES(2, 4, 'SubSubItem2_2_1');
INSERT INTO `test_subsubitems` VALUES(3, 4, 'SubSubItem2_2_2');
INSERT INTO `test_subsubitems` VALUES(4, 5, 'SubSubItem3_1_1');

DROP TABLE IF EXISTS `test_items_relations`;
CREATE TABLE `test_items_relations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `related_item_id` int(11) NOT NULL,
  `extra` enum('one','two') NOT NULL DEFAULT 'one',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=5 ;

INSERT INTO `test_items_relations` VALUES(1, 1, 2, 'one');
INSERT INTO `test_items_relations` VALUES(2, 1, 4, 'one');
INSERT INTO `test_items_relations` VALUES(3, 3, 1, 'two');
INSERT INTO `test_items_relations` VALUES(4, 3, 3, 'one');

DROP TABLE IF EXISTS `test_related_items`;
CREATE TABLE `test_related_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=5 ;

INSERT INTO `test_related_items` VALUES(1, 'Related Item #1');
INSERT INTO `test_related_items` VALUES(2, 'Related Item #2');
INSERT INTO `test_related_items` VALUES(3, 'Related Item #3');
INSERT INTO `test_related_items` VALUES(4, 'Related Item #4');

DROP TABLE IF EXISTS `item_city`;
CREATE TABLE `item_city` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_ext_id` INT(11) NOT NULL,
  `city_name` varchar(255) NOT NULL,
  `city_population` DECIMAL (3, 1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY (`item_ext_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

INSERT INTO `item_city` VALUES(1, 1, 'Saint-Petersburg', '4.5');
INSERT INTO `item_city` VALUES(2, 2, 'Moscow', '11.5');

DROP TABLE IF EXISTS `item_properties`;
CREATE TABLE `item_properties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_ext_id` INT(11) NOT NULL,
  `sku` INT(11) NOT NULL,
  `length` DECIMAL (8, 2) NOT NULL DEFAULT '0',
  `height` DECIMAL (8, 2) NOT NULL DEFAULT '0',
  `width` DECIMAL (8, 2) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY (`item_ext_id`),
  KEY (`sku`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `item_base`;
CREATE TABLE `item_base` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL ,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `item_base_ext`;
CREATE TABLE `item_base_ext` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `item_base_id` INT(11) NOT NULL,
  `width` VARCHAR(255) NOT NULL ,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`item_base_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `item_heir_ext`;
CREATE TABLE `item_heir_ext` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_base_id` INT(11) NOT NULL,
  `height` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY (`item_base_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
