-- Poggit-Delta
--
-- Copyright (C) 2018-2019 Poggit
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU Affero General Public License as published
-- by the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU Affero General Public License for more details.
--
-- You should have received a copy of the GNU Affero General Public License
-- along with this program.  If not, see <https://www.gnu.org/licenses/>.

INSERT INTO api_version (api, incompatible, minimumPhp, downloadLink)
VALUES
       ('3.0.0-ALPHA11', 1, '7.2', 'https://github.com/pmmp/PocketMine-MP/releases/download/api%2F3.0.0-ALPHA11/PocketMine-MP_1.7dev-677_07bf1c9e_API-3.0.0-ALPHA11.phar'),
       ('3.0.0-ALPHA12', 1, '7.2', 'https://github.com/pmmp/PocketMine-MP/releases/download/api%2F3.0.0-ALPHA12/PocketMine-MP.phar'),
       ('3.0.0', 1, '7.2', 'https://github.com/pmmp/PocketMine-MP/releases/download/3.0.0/PocketMine-MP.phar'),
       ('3.0.1', 0, '7.2', 'https://github.com/pmmp/PocketMine-MP/releases/download/3.0.1/PocketMine-MP.phar'),
       ('3.0.2', 0, '7.2', 'https://github.com/pmmp/PocketMine-MP/releases/download/3.0.2/PocketMine-MP.phar'),
       ('3.1.0', 0, '7.2', 'https://github.com/pmmp/PocketMine-MP/releases/download/3.1.0/PocketMine-MP.phar'),
       ('3.2.0', 0, '7.2', 'https://github.com/pmmp/PocketMine-MP/releases/download/3.2.0/PocketMine-MP.phar'),
       ('3.2.1', 0, '7.2', 'https://github.com/pmmp/PocketMine-MP/releases/download/3.2.1/PocketMine-MP.phar'),
       ('3.2.2', 0, '7.2', 'https://github.com/pmmp/PocketMine-MP/releases/download/3.2.2/PocketMine-MP.phar'),
       ('3.3.0', 0, '7.2', 'https://github.com/pmmp/PocketMine-MP/releases/download/3.3.0/PocketMine-MP.phar'),
       ('3.4.0', 0, '7.2', 'https://github.com/pmmp/PocketMine-MP/releases/download/3.4.0/PocketMine-MP.phar'),
       ('3.5.0', 0, '7.2', 'https://github.com/pmmp/PocketMine-MP/releases/download/3.5.0/PocketMine-MP.phar'),
       ('3.5.1', 0, '7.2', 'https://github.com/pmmp/PocketMine-MP/releases/download/3.5.1/PocketMine-MP.phar');

INSERT INTO api_version_description (versionId, value) SELECT id, 'New XP API' FROM `api_version` WHERE api = '3.0.0-ALPHA11';
INSERT INTO api_version_description (versionId, value) SELECT id, 'TextFormat::colorize()' FROM `api_version` WHERE api = '3.0.0-ALPHA11';
INSERT INTO api_version_description (versionId, value) SELECT id, 'Block->onUpdate() removed' FROM `api_version` WHERE api = '3.0.0-ALPHA12';
INSERT INTO api_version_description (versionId, value) SELECT id, 'Crafting rewrite' FROM `api_version` WHERE api = '3.0.0-ALPHA12';
INSERT INTO api_version_description (versionId, value) SELECT id, 'Tile NBT no longer retained' FROM `api_version` WHERE api = '3.0.0';
INSERT INTO api_version_description (versionId, value) SELECT id, 'Added Snooze' FROM `api_version` WHERE api = '3.0.0';
INSERT INTO api_version_description (versionId, value) SELECT id, '1.5.0 support' FROM `api_version` WHERE api = '3.1.0';
INSERT INTO api_version_description (versionId, value) SELECT id, 'Added Form (interface only)' FROM `api_version` WHERE api = '3.2.0';
INSERT INTO api_version_description (versionId, value) SELECT id, 'Internet functions moved from Utils to Internet' FROM `api_version` WHERE api = '3.2.0';
INSERT INTO api_version_description (versionId, value) SELECT id, 'Client version 1.7.0' FROM `api_version` WHERE api = '3.3.0';
INSERT INTO api_version_description (versionId, value) SELECT id, 'ClosureTask' FROM `api_version` WHERE api = '3.4.0';

