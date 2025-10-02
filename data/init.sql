-- Tank Game Database - Complete Initialization Script
-- Consolidated from all SQL files in the project
--

CREATE DATABASE IF NOT EXISTS `tank-game`;
USE `tank-game`;

--
-- 1. CORE TABLES - Users and Authentication
--

-- Create Users table
CREATE TABLE IF NOT EXISTS Users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
);

-- Create Tokens table for authentication
CREATE TABLE IF NOT EXISTS Tokens (
    token VARCHAR(255) PRIMARY KEY,
    user_id INT NOT NULL,
    expiration TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- Create indexes for performance
CREATE INDEX idx_tokens_token ON Tokens(token);
CREATE INDEX idx_tokens_expiration ON Tokens(expiration);

-- 
-- 2. GAME MAPS AND TERRAIN
-- 

-- Game maps table
CREATE TABLE IF NOT EXISTS game_maps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    biome VARCHAR(100) DEFAULT 'mixed',
    seed INT DEFAULT NULL,
    cover_path TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO game_maps (id, name, description, biome, seed) VALUES 
(1, 'Prateria Verde', 'Una vasta prateria con erba verde e fiori.', 'Prateria', 12345), 
(2, 'Foresta Misteriosa', 'Una foresta densa e misteriosa', 'Foresta', 23456), 
(3, 'Deserto Arido', 'Un deserto caldo e secco', 'Deserto', 34567),
(4, 'Tundra Ghiacciata', 'Una tundra fredda e ghiacciata', 'Tundra', 45678), 
(5, 'Palude Verde', 'Una palude verdeggiante', 'Palude', 56789), 
(6, 'Montagne Rocciose', 'Montagne alte e rocciose', 'Montagna', 67890),
(7, 'Steppa Dorata', 'Una steppa dorata al tramonto', 'Steppa', 78901), 
(8, 'Giungla Tropicale', 'Una giungla tropicale lussureggiante', 'Giungla', 89012), 
(9, 'Isola Vulcanica', 'Un isola con un vulcano attivo', 'Vulcano', 90123), 
(10, 'Citta Abbandonata', 'Una citta abbandonata e in rovina', 'Urbano', 01234);

-- Biomes for map generation
CREATE TABLE IF NOT EXISTS biomes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_maps_id INT NOT NULL,
    label TEXT DEFAULT NULL,
    FOREIGN KEY (game_maps_id) REFERENCES game_maps(id) ON DELETE CASCADE
);

-- Terrain types for each biome
CREATE TABLE IF NOT EXISTS terrain_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    biome_id INT NOT NULL,
    type TEXT NOT NULL,        -- main_ground, secondary_ground, etc.
    color TEXT NOT NULL,       -- hex color like "#8BC34A"
    texture_pattern TEXT NOT NULL,
    FOREIGN KEY (biome_id) REFERENCES biomes(id) ON DELETE CASCADE
);

-- Game tanks table
CREATE TABLE IF NOT EXISTS game_tanks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    cover_path TEXT NOT NULL,
    price INTEGER NOT NULL DEFAULT 0,
    description TEXT DEFAULT NULL
);

-- User owned tanks table
CREATE TABLE IF NOT EXISTS UserOwnedTanks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INTEGER NOT NULL,
    tank_id INTEGER NOT NULL,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (tank_id) REFERENCES game_tanks(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_tank (user_id, tank_id)
);

-- Trigger per assegnare automaticamente il tank base a ogni nuovo utente
DELIMITER $$
CREATE TRIGGER assign_default_tank_after_user_insert
    AFTER INSERT ON Users
    FOR EACH ROW
BEGIN
    -- Assegna il tank con prezzo più basso (tank base) al nuovo utente
    INSERT INTO UserOwnedTanks (user_id, tank_id)
    SELECT NEW.id, t.id
    FROM game_tanks t
    WHERE t.price = (SELECT MIN(price) FROM game_tanks)
    LIMIT 1;
END$$
DELIMITER ;

-- 
-- 3. TANK WEAPONS AND CUSTOMIZATION
-- 

-- Tank weapons table
CREATE TABLE IF NOT EXISTS TankWeapons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('cannon', 'machine_gun', 'heavy_artillery') NOT NULL,
    damage INTEGER NOT NULL,
    range_distance INTEGER NOT NULL,
    reload_time INTEGER NOT NULL,
    price INTEGER DEFAULT 0,
    description TEXT
);

-- User tank customizations
CREATE TABLE IF NOT EXISTS UserTankCustomizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INTEGER NOT NULL,
    tank_id INTEGER NOT NULL,    -- 0 = inventory, >0 = specific tank
    weapon_id INTEGER NOT NULL,
    slot_position INTEGER NOT NULL, -- 0 = inventory, 1-4 = tank slots
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_tank_slot (user_id, tank_id, slot_position),
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (weapon_id) REFERENCES TankWeapons(id) ON DELETE CASCADE
);

-- 
-- 4. ENEMIES AND OBSTACLES
-- 

-- Enemy types for AI opponents
CREATE TABLE IF NOT EXISTS EnemyTypes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('foot_soldier', 'heavy_artillery', 'mortar', 'machine_gun_post', 'sniper_post') NOT NULL,
    health INTEGER NOT NULL,
    damage INTEGER NOT NULL,
    range_distance INTEGER NOT NULL,
    reload_time INTEGER NOT NULL,
    can_penetrate_cover BOOLEAN DEFAULT FALSE,
    sprite_path VARCHAR(255),
    description TEXT
);

-- Map obstacles for cover and strategy
CREATE TABLE IF NOT EXISTS MapObstacles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('house', 'wall', 'rock', 'tree', 'bunker') NOT NULL,
    provides_cover BOOLEAN DEFAULT TRUE,
    destructible BOOLEAN DEFAULT FALSE,
    health INTEGER DEFAULT NULL,
    sprite_path VARCHAR(255),
    width INTEGER DEFAULT 32,
    height INTEGER DEFAULT 32
);

-- 
-- 5. USER STATISTICS AND RECORDS
-- 

-- User statistics and progression
CREATE TABLE IF NOT EXISTS UserStats (
    user_id INT PRIMARY KEY,
    level INT DEFAULT 1,
    total_points INT DEFAULT 0,
    experience INT DEFAULT 0,
    total_kills INT DEFAULT 0,
    total_deaths INT DEFAULT 0,
    matches_played INT DEFAULT 0,
    total_playtime INT DEFAULT 0, -- in seconds
    credits INT DEFAULT 500,     -- game currency
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- Game records for score tracking
CREATE TABLE IF NOT EXISTS GameRecords (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    score INT NOT NULL DEFAULT 0,
    kills INT DEFAULT 0,
    deaths INT DEFAULT 0,
    duration INT NOT NULL,       -- in seconds
    map_id INT DEFAULT 1,
    tank_id INT DEFAULT 1,
    game_mode ENUM('single_player', 'multiplayer') DEFAULT 'single_player',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- 
-- 6. MULTIPLAYER SYSTEM
-- 

-- Game matches for multiplayer
CREATE TABLE IF NOT EXISTS GameMatches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_by_user_id INT NOT NULL,
    map_id INT NOT NULL DEFAULT 1,
    max_players INT NOT NULL DEFAULT 2,
    current_players INT NOT NULL DEFAULT 0,
    status ENUM('waiting', 'in_progress', 'completed', 'cancelled') DEFAULT 'waiting',
    game_mode ENUM('standard', 'local_coop', 'team_vs_team') DEFAULT 'standard',
    is_local_multiplayer BOOLEAN DEFAULT FALSE,
    max_tanks INT DEFAULT 2, -- Numero massimo di carri armati (per local coop)
    players_per_tank INT DEFAULT 1, -- Giocatori per carro armato (1=standard, 2=cooperativo)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    FOREIGN KEY (created_by_user_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (map_id) REFERENCES game_maps(id) ON DELETE CASCADE
);

-- Players in each match with enhanced local multiplayer support
CREATE TABLE IF NOT EXISTS GameMatchPlayers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    user_id INT NOT NULL,
    tank_id INT DEFAULT 1,
    tank_slot_number INT DEFAULT 1, -- Quale carro armato nella partita (1, 2, etc.)
    player_role ENUM('driver', 'gunner', 'both') DEFAULT 'both', -- Ruolo del giocatore
    control_scheme INT DEFAULT 1, -- Schema di controlli (1=WASD+Space, 2=Arrow+Enter, etc.)
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('waiting', 'ready', 'playing', 'left', 'disconnected') DEFAULT 'waiting',
    UNIQUE KEY unique_match_user (match_id, user_id),
    FOREIGN KEY (match_id) REFERENCES GameMatches(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (tank_id) REFERENCES game_tanks(id) ON DELETE SET NULL
);

-- Local multiplayer tank assignments
CREATE TABLE IF NOT EXISTS LocalMultiplayerTanks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    tank_slot_number INT NOT NULL, -- 1, 2, 3, 4
    tank_model_id INT DEFAULT 1,
    driver_player_id INT DEFAULT NULL,
    gunner_player_id INT DEFAULT NULL,
    spawn_x INT DEFAULT 0,
    spawn_y INT DEFAULT 0,
    team_id INT DEFAULT 1, -- Per modalità team vs team
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (match_id) REFERENCES GameMatches(id) ON DELETE CASCADE,
    FOREIGN KEY (tank_model_id) REFERENCES game_tanks(id) ON DELETE SET NULL,
    FOREIGN KEY (driver_player_id) REFERENCES GameMatchPlayers(id) ON DELETE SET NULL,
    FOREIGN KEY (gunner_player_id) REFERENCES GameMatchPlayers(id) ON DELETE SET NULL,
    UNIQUE KEY unique_match_tank_slot (match_id, tank_slot_number)
);

-- Friend invites system
CREATE TABLE IF NOT EXISTS FriendInvites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    match_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined', 'expired') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL,
    FOREIGN KEY (from_user_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (to_user_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (match_id) REFERENCES GameMatches(id) ON DELETE CASCADE
);

-- 
-- USER-CREATED MAPS SYSTEM
-- 

-- User created custom maps
CREATE TABLE IF NOT EXISTS UserCreatedMaps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    biome VARCHAR(100) DEFAULT 'mixed',
    terrain_type VARCHAR(100) DEFAULT 'mixed',
    terrain_map LONGTEXT, -- JSON array of terrain data
    enemies_data LONGTEXT, -- JSON array of enemy positions and types
    obstacles_data LONGTEXT, -- JSON array of obstacle positions and types
    width INT DEFAULT 800,
    height INT DEFAULT 600,
    is_public BOOLEAN DEFAULT FALSE,
    game_modes JSON, -- ['single_player', 'multiplayer']
    difficulty_rating ENUM('easy', 'medium', 'hard', 'extreme') DEFAULT 'medium',
    play_count INT DEFAULT 0,
    rating_sum INT DEFAULT 0,
    rating_count INT DEFAULT 0,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    cover_path TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    INDEX idx_public_maps (is_public, status),
    INDEX idx_user_maps (user_id, status)
);

-- Map ratings and reviews
CREATE TABLE IF NOT EXISTS MapRatings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    map_id INT NOT NULL,
    user_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_map_rating (map_id, user_id),
    FOREIGN KEY (map_id) REFERENCES UserCreatedMaps(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- Map play statistics
CREATE TABLE IF NOT EXISTS MapPlayStats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    map_id INT NOT NULL,
    user_id INT NOT NULL,
    game_mode ENUM('single_player', 'multiplayer') NOT NULL,
    score INT DEFAULT 0,
    kills INT DEFAULT 0,
    deaths INT DEFAULT 0,
    completion_time INT DEFAULT NULL, -- in seconds
    completed BOOLEAN DEFAULT FALSE,
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (map_id) REFERENCES UserCreatedMaps(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- Map favorites/bookmarks
CREATE TABLE IF NOT EXISTS UserMapFavorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    map_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_map_favorite (user_id, map_id),
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE,
    FOREIGN KEY (map_id) REFERENCES UserCreatedMaps(id) ON DELETE CASCADE
);

-- Map collections/categories (for organizing custom maps)
CREATE TABLE IF NOT EXISTS MapCollections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES Users(id) ON DELETE CASCADE
);

-- Map-to-collection relationships
CREATE TABLE IF NOT EXISTS MapCollectionItems (
    id INT AUTO_INCREMENT PRIMARY KEY,
    collection_id INT NOT NULL,
    map_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_collection_map (collection_id, map_id),
    FOREIGN KEY (collection_id) REFERENCES MapCollections(id) ON DELETE CASCADE,
    FOREIGN KEY (map_id) REFERENCES UserCreatedMaps(id) ON DELETE CASCADE
);

-- 
-- 7. INITIAL DATA - Maps
-- 

INSERT IGNORE INTO game_maps (id, name, description, biome, seed, cover_path) VALUES
(1, 'Campagna Prosperosa', 'Una mappa con ampie pianure e foreste verdi', 'forest', 123456, '/assets/covers/1.png'),
(2, 'Deserto Infuocato', 'Terreno desertico con rocce e canyon', 'desert', 234567, '/assets/covers/2.png'),
(3, 'Polo Perpetuo', 'Paesaggio innevato e ghiacciato', 'tundra', 345678, '/assets/covers/3.png'),
(4, 'Luna Silente', 'Superficie lunare con crateri e rocce', 'lunar', 456789, '/assets/covers/4.png'),
(5, 'Fattoria Abbandonata', 'Terreni agricoli dimenticati', 'farmland', 567890, '/assets/covers/5.png'),
(6, 'Giungla Remota', 'Fitta vegetazione tropicale', 'jungle', 111222, '/assets/covers/6.png'),
(7, 'Città Dimenticata', 'Rovine urbane e detriti', 'urban', 333444, '/assets/covers/7.png'),
(8, 'Zona Contaminata', 'Area radioattiva con vegetazione mutata', 'contaminated', 555666, '/assets/covers/8.png'),
(9, 'Vulcano Furioso', 'Terreno vulcanico con lava e cenere', 'volcanic', 777888, '/assets/covers/9.png'),
(10, 'Trincea Insidiosa', 'Campo di battaglia con trincee e fango', 'battlefield', 999000, '/assets/covers/10.png');

-- Insert biomes for each map
INSERT IGNORE INTO biomes (id, game_maps_id, label) VALUES
(1, 1, 'Foresta Mista'),
(2, 1, 'Pianura Erbosa'),
(3, 2, 'Deserto Sabbioso'),
(4, 2, 'Rocce del Canyon'),
(5, 3, 'Tundra Ghiacciata'),
(6, 3, 'Neve Profonda'),
(7, 4, 'Superficie Lunare'),
(8, 4, 'Crateri Profondi'),
(9, 5, 'Campi Abbandonati'),
(10, 5, 'Fienili Diroccati'),
(11, 6, 'Giungla Densa'),
(12, 6, 'Radura Tropicale'),
(13, 7, 'Strade Urbane'),
(14, 7, 'Edifici Crollati'),
(15, 8, 'Terra Contaminata'),
(16, 8, 'Pozze Radioattive'),
(17, 9, 'Lava Solidificata'),
(18, 9, 'Campi di Cenere'),
(19, 10, 'Trincee Fangose'),
(20, 10, 'Crateri da Bomba');

-- Insert terrain types for biomes
INSERT IGNORE INTO terrain_types (biome_id, type, color, texture_pattern) VALUES
-- Campagna Prosperosa
(1, 'main_ground', '#228B22', 'forest_floor'),
(1, 'secondary_ground', '#8FBC8F', 'grass_patches'),
(2, 'main_ground', '#9ACD32', 'grassland'),
(2, 'secondary_ground', '#32CD32', 'rich_grass'),
-- Deserto Infuocato
(3, 'main_ground', '#F4A460', 'sand_dunes'),
(3, 'secondary_ground', '#DEB887', 'light_sand'),
(4, 'main_ground', '#A0522D', 'rocky_terrain'),
(4, 'secondary_ground', '#8B4513', 'dark_rocks'),
-- Polo Perpetuo
(5, 'main_ground', '#E0FFFF', 'ice_sheet'),
(5, 'secondary_ground', '#B0E0E6', 'frozen_ground'),
(6, 'main_ground', '#FFFAFA', 'deep_snow'),
(6, 'secondary_ground', '#F0F8FF', 'snow_drifts'),
-- Luna Silente
(7, 'main_ground', '#696969', 'moon_surface'),
(7, 'secondary_ground', '#808080', 'lunar_dust'),
(8, 'main_ground', '#2F4F4F', 'crater_floor'),
(8, 'secondary_ground', '#708090', 'crater_walls'),
-- Fattoria Abbandonata
(9, 'main_ground', '#8B7355', 'plowed_earth'),
(9, 'secondary_ground', '#D2691E', 'dried_crops'),
(10, 'main_ground', '#8B4513', 'old_wood'),
(10, 'secondary_ground', '#A0522D', 'rusted_metal');

-- Insert tank data
INSERT IGNORE INTO game_tanks (id, name, cover_path, price, description) VALUES
(1, 'Tank Standard', '/assets/tanks/1.png', 0, 'Tank base con caratteristiche equilibrate. Perfetto per iniziare!'),
(2, 'Tank Pesante', '/assets/tanks/2.png', 500, 'Tank corazzato con alta resistenza ma velocità ridotta'),
(11, 'Tank Veloce', '/assets/tanks/tank11.png', 300, 'Tank leggero e veloce, ideale per manovre rapide'),
(12, 'Tank d\'Assalto', '/assets/tanks/tank12.png', 800, 'Tank d\'assalto con potenza di fuoco elevata'),
(13, 'Tank Sniper', '/assets/tanks/tank13.png', 600, 'Tank specializzato per attacchi a lunga distanza');

-- 
-- 8. INITIAL DATA - Weapons
-- 

INSERT IGNORE INTO TankWeapons (name, type, damage, range_distance, reload_time, price, description) VALUES
('Cannone Base', 'cannon', 100, 300, 2000, 0, 'Cannone standard incluso con ogni tank'),
('Mitragliatrice Leggera', 'machine_gun', 25, 200, 500, 800, 'Sparo rapido con danni ridotti'),
('Mitragliatrice Pesante', 'machine_gun', 40, 250, 800, 1500, 'Maggiori danni e gittata rispetto alla versione leggera'),
('Cannone Potenziato', 'cannon', 150, 350, 1800, 2000, 'Versione migliorata del cannone base'),
('Artiglieria Pesante', 'heavy_artillery', 200, 400, 3000, 3500, 'Massimi danni ma ricarica molto lenta'),
('Cannone Rapido', 'cannon', 80, 280, 1200, 1200, 'Cannone con ricarica veloce'),
('Mitragliatrice Doppia', 'machine_gun', 35, 220, 600, 2200, 'Due mitragliatrici sincronizzate');

-- 
-- 9. INITIAL DATA - Enemies
-- 

INSERT IGNORE INTO EnemyTypes (name, type, health, damage, range_distance, reload_time, can_penetrate_cover, sprite_path, description) VALUES
('Soldato Semplice', 'foot_soldier', 50, 20, 150, 1500, FALSE, '/assets/enemies/soldato.jpg', 'Fanteria base con fucile'),
('Soldato Elite', 'foot_soldier', 80, 35, 180, 1200, FALSE, '/assets/enemies/soldato.jpg', 'Soldato veterano con equipaggiamento migliore'),
('Postazione Mitragliatrice', 'machine_gun_post', 120, 30, 250, 800, FALSE, '/assets/enemies/postazione-mitragliatrice.png', 'Postazione fissa con mitragliatrice'),
('Artiglieria Pesante', 'heavy_artillery', 200, 120, 400, 4000, FALSE, '/assets/enemies/artiglieria_pesante.jpg', 'Cannone anticarro di grosso calibro'),
('Postazione Mortaio', 'mortar', 150, 90, 350, 3500, TRUE, '/assets/enemies/soldato.jpg', 'Mortaio che può colpire dietro le coperture'),
('Cecchino', 'sniper_post', 60, 80, 450, 2500, FALSE, '/assets/enemies/soldato.jpg', 'Tiratore scelto con fucile di precisione'),
('Artiglieria Rapida', 'heavy_artillery', 160, 100, 380, 2800, FALSE, '/assets/enemies/artiglieria_pesante.jpg', 'Cannone con cadenza di tiro elevata');

-- 
-- 10. INITIAL DATA - Obstacles
-- 

INSERT IGNORE INTO MapObstacles (name, type, provides_cover, destructible, health, sprite_path, width, height) VALUES
('Casa di Mattoni', 'house', TRUE, TRUE, 300, '/assets/obstacles/casa.jpg', 64, 64),
('Casa di Legno', 'house', TRUE, TRUE, 150, '/assets/obstacles/casa.jpg', 64, 64),
('Muro di Cemento', 'wall', TRUE, TRUE, 200, '/assets/obstacles/muro.jpg', 32, 64),
('Muro di Mattoni', 'wall', TRUE, TRUE, 180, '/assets/obstacles/muro.jpg', 32, 64),
('Roccia Grande', 'rock', TRUE, FALSE, NULL, '/assets/obstacles/roccia.jpg', 48, 48),
('Albero Grosso', 'tree', TRUE, TRUE, 100, '/assets/obstacles/albero.jpg', 40, 60),
('Bunker Militare', 'bunker', TRUE, TRUE, 500, '/assets/obstacles/casa.jpg', 96, 64),
('Barriera Metallica', 'wall', TRUE, TRUE, 120, '/assets/obstacles/muro.jpg', 64, 32),
('Casa Diroccata', 'house', TRUE, TRUE, 80, '/assets/obstacles/casa.jpg', 64, 64),
('Muro Distrutto', 'wall', TRUE, TRUE, 60, '/assets/obstacles/muro.jpg', 32, 48);

-- 
-- 11. INITIALIZE USER DATA
-- 

-- Initialize stats for all existing users
INSERT IGNORE INTO UserStats (user_id, level, total_points, credits)
SELECT id, 1, 0, 500 FROM Users;

-- 
-- 12. SAMPLE DATA FOR TESTING
-- 

-- Insert test users
INSERT IGNORE INTO Users (username, password) VALUES
('player1', 'password123'),
('player2', 'password123'),
('testuser', 'password123');

-- Insert test tokens  
INSERT IGNORE INTO Tokens (user_id, token, expiration) VALUES
((SELECT id FROM Users WHERE username='player1' LIMIT 1), 'TEST_TOKEN_1', DATE_ADD(NOW(), INTERVAL 24 HOUR)),
((SELECT id FROM Users WHERE username='player2' LIMIT 1), 'TEST_TOKEN_2', DATE_ADD(NOW(), INTERVAL 24 HOUR)),
((SELECT id FROM Users WHERE username='testuser' LIMIT 1), 'TEST_GAME_TOKEN', DATE_ADD(NOW(), INTERVAL 24 HOUR));

-- Insert sample game records for testing
INSERT IGNORE INTO GameRecords (user_id, score, kills, deaths, duration, map_id, tank_id) 
SELECT id, 1000, 5, 1, 300, 1, 1 FROM Users WHERE username='player1' LIMIT 1;

-- 
-- Database initialization complete!
-- This file consolidates all previous SQL files:
-- - init-simple.sql
-- - create_stats_tables.sql  
-- - create_simple_tables.sql
-- - create_multiplayer_tables.sql
-- - create_multiplayer_simple.sql
-- - create_missing_tables.sql
-- - extend-db.sql
-- 
