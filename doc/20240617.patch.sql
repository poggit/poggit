DROP TABLE IF EXISTS `spoon_desc`;
ALTER TABLE `known_spoons` DROP COLUMN `php`,
    DROP COLUMN `indev`,
    DROP COLUMN `supported`,
    DROP COLUMN `pharDefault`;
