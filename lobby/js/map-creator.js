// Map Creator JavaScript - Sistema completo per la creazione di mappe personalizzate

class MapCreator {
    constructor() {
        this.canvas = document.getElementById('mapCanvas');
        this.ctx = this.canvas.getContext('2d');
        this.userToken = localStorage.getItem('userToken');
        
        // Stato dell'editor
        this.currentTool = 'terrain';
        this.selectedBiome = 'forest';
        this.selectedTerrain = 'grass';
        this.selectedEnemy = null;
        this.selectedObstacle = null;
        this.mapVisibility = 'private';
        
        // Dati della mappa
        this.mapData = {
            width: 800,
            height: 600,
            biome: 'forest',
            terrain_type: 'mixed',
            terrain_map: new Array(10000 * 10000).fill('grass'), // Pixel per pixel
            enemies: [],
            obstacles: [],
            name: '',
            description: '',
            is_public: false,
            game_modes: ['single_player']
        };
        
        // Controlli
        this.zoom = 1;
        this.offsetX = 0;
        this.offsetY = 0;
        this.isDragging = false;
        this.lastMouseX = 0;
        this.lastMouseY = 0;
        this.isDrawing = false;
        
        // Colori per biomi
        this.biomeColors = {
            forest: { grass: '#228B22', light_grass: '#8FBC8F', dirt: '#8B4513' },
            desert: { sand: '#F4A460', light_sand: '#DEB887', rock: '#A0522D' },
            tundra: { ice: '#E0FFFF', snow: '#FFFAFA', frozen_ground: '#B0E0E6' },
            volcanic: { lava: '#FF4500', ash: '#696969', rock: '#2F4F4F' },
            urban: { concrete: '#808080', asphalt: '#2F2F2F', rubble: '#696969' },
            lunar: { moon_surface: '#C0C0C0', crater: '#A9A9A9', dust: '#D3D3D3' }
        };
        
        this.initializeEventListeners();
        this.drawGrid();
    }
    
    initializeEventListeners() {
        // Canvas events
        this.canvas.addEventListener('mousedown', (e) => this.handleMouseDown(e));
        this.canvas.addEventListener('mousemove', (e) => this.handleMouseMove(e));
        this.canvas.addEventListener('mouseup', (e) => this.handleMouseUp(e));
        this.canvas.addEventListener('wheel', (e) => this.handleWheel(e));
        this.canvas.addEventListener('contextmenu', (e) => e.preventDefault());
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
        
        // Form events
        document.getElementById('mapName').addEventListener('input', (e) => {
            this.mapData.name = e.target.value;
        });
        
        document.getElementById('mapDescription').addEventListener('input', (e) => {
            this.mapData.description = e.target.value;
        });
        
        document.getElementById('terrainType').addEventListener('change', (e) => {
            this.mapData.terrain_type = e.target.value;
        });
        
        document.getElementById('gameMode').addEventListener('change', (e) => {
            const mode = e.target.value;
            if (mode === 'both') {
                this.mapData.game_modes = ['single_player', 'multiplayer'];
            } else {
                this.mapData.game_modes = [mode];
            }
        });
    }
    
    handleMouseDown(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = (e.clientX - rect.left - this.offsetX) / this.zoom;
        const y = (e.clientY - rect.top - this.offsetY) / this.zoom;
        
        this.isDrawing = true;
        this.lastMouseX = e.clientX - rect.left;
        this.lastMouseY = e.clientY - rect.top;
        
        if (e.button === 0) { // Left click
            this.placeTool(x, y);
        } else if (e.button === 2) { // Right click
            this.removeTool(x, y);
        }
    }
    
    handleMouseMove(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = (e.clientX - rect.left - this.offsetX) / this.zoom;
        const y = (e.clientY - rect.top - this.offsetY) / this.zoom;
        
        // Update coordinates display
        document.getElementById('coordinates').textContent = `X: ${Math.floor(x)}, Y: ${Math.floor(y)}`;
        
        if (this.isDrawing && e.buttons === 1) {
            this.placeTool(x, y);
        }
        
        // Pan con middle mouse
        if (e.buttons === 4) {
            const deltaX = e.clientX - rect.left - this.lastMouseX;
            const deltaY = e.clientY - rect.top - this.lastMouseY;
            this.offsetX += deltaX;
            this.offsetY += deltaY;
            this.lastMouseX = e.clientX - rect.left;
            this.lastMouseY = e.clientY - rect.top;
            this.redraw();
        }
    }
    
    handleMouseUp(e) {
        this.isDrawing = false;
    }
    
    handleWheel(e) {
        e.preventDefault();
        const rect = this.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        const oldZoom = this.zoom;
        const zoomFactor = e.deltaY < 0 ? 1.1 : 0.9;
        this.zoom = Math.max(0.1, Math.min(5, this.zoom * zoomFactor));
        
        // Zoom verso il cursore
        const zoomChange = this.zoom / oldZoom;
        this.offsetX = x - (x - this.offsetX) * zoomChange;
        this.offsetY = y - (y - this.offsetY) * zoomChange;
        
        this.redraw();
    }
    
    handleKeyboard(e) {
        switch(e.key) {
            case 'Delete':
            case 'Backspace':
                this.clearSelectedArea();
                break;
            case 'Escape':
                this.deselectAll();
                break;
            case 'c':
                if (e.ctrlKey) this.copySelection();
                break;
            case 'v':
                if (e.ctrlKey) this.pasteSelection();
                break;
            case 'z':
                if (e.ctrlKey) this.undo();
                break;
        }
    }
    
    placeTool(x, y) {
        if (x < 0 || y < 0 || x >= this.mapData.width || y >= this.mapData.height) return;
        
        switch(this.currentTool) {
            case 'terrain':
                this.placeTerrain(x, y);
                break;
            case 'enemy':
                this.placeEnemy(x, y);
                break;
            case 'obstacle':
                this.placeObstacle(x, y);
                break;
            case 'eraser':
                this.eraseTool(x, y);
                break;
        }
        
        this.redraw();
        this.updateStats();
    }
    
    removeTool(x, y) {
        // Remove enemy or obstacle at position
        this.mapData.enemies = this.mapData.enemies.filter(enemy => {
            const distance = Math.sqrt((enemy.x - x) ** 2 + (enemy.y - y) ** 2);
            return distance > 20; // Remove if within 20 pixels
        });
        
        this.mapData.obstacles = this.mapData.obstacles.filter(obstacle => {
            const distance = Math.sqrt((obstacle.x - x) ** 2 + (obstacle.y - y) ** 2);
            return distance > 20;
        });
        
        this.redraw();
        this.updateStats();
    }
    
    placeTerrain(x, y) {
        const brushSize = 15;
        const centerX = Math.floor(x);
        const centerY = Math.floor(y);
        
        for (let i = -brushSize; i <= brushSize; i++) {
            for (let j = -brushSize; j <= brushSize; j++) {
                const pixelX = centerX + i;
                const pixelY = centerY + j;
                
                if (pixelX >= 0 && pixelX < this.mapData.width && 
                    pixelY >= 0 && pixelY < this.mapData.height) {
                    
                    const distance = Math.sqrt(i * i + j * j);
                    if (distance <= brushSize) {
                        const index = pixelY * this.mapData.width + pixelX;
                        this.mapData.terrain_map[index] = this.selectedTerrain;
                    }
                }
            }
        }
    }
    
    placeEnemy(x, y) {
        if (!this.selectedEnemy || this.selectedEnemy === 'eraser') return;
        
        // Check if there's already an enemy nearby
        const nearby = this.mapData.enemies.find(enemy => {
            const distance = Math.sqrt((enemy.x - x) ** 2 + (enemy.y - y) ** 2);
            return distance < 30;
        });
        
        if (!nearby) {
            this.mapData.enemies.push({
                type: this.selectedEnemy,
                x: Math.floor(x),
                y: Math.floor(y),
                health: this.getEnemyHealth(this.selectedEnemy),
                damage: this.getEnemyDamage(this.selectedEnemy),
                range: this.getEnemyRange(this.selectedEnemy)
            });
        }
    }
    
    placeObstacle(x, y) {
        if (!this.selectedObstacle || this.selectedObstacle === 'eraser') return;
        
        // Check if there's already an obstacle nearby
        const nearby = this.mapData.obstacles.find(obstacle => {
            const distance = Math.sqrt((obstacle.x - x) ** 2 + (obstacle.y - y) ** 2);
            return distance < 25;
        });
        
        if (!nearby) {
            this.mapData.obstacles.push({
                type: this.selectedObstacle,
                x: Math.floor(x),
                y: Math.floor(y),
                width: this.getObstacleWidth(this.selectedObstacle),
                height: this.getObstacleHeight(this.selectedObstacle),
                destructible: this.isObstacleDestructible(this.selectedObstacle),
                health: this.getObstacleHealth(this.selectedObstacle)
            });
        }
    }
    
    eraseTool(x, y) {
        const brushSize = 20;
        
        // Remove enemies
        this.mapData.enemies = this.mapData.enemies.filter(enemy => {
            const distance = Math.sqrt((enemy.x - x) ** 2 + (enemy.y - y) ** 2);
            return distance > brushSize;
        });
        
        // Remove obstacles
        this.mapData.obstacles = this.mapData.obstacles.filter(obstacle => {
            const distance = Math.sqrt((obstacle.x - x) ** 2 + (obstacle.y - y) ** 2);
            return distance > brushSize;
        });
        
        // Reset terrain to base
        const centerX = Math.floor(x);
        const centerY = Math.floor(y);
        
        for (let i = -brushSize; i <= brushSize; i++) {
            for (let j = -brushSize; j <= brushSize; j++) {
                const pixelX = centerX + i;
                const pixelY = centerY + j;
                
                if (pixelX >= 0 && pixelX < this.mapData.width && 
                    pixelY >= 0 && pixelY < this.mapData.height) {
                    
                    const distance = Math.sqrt(i * i + j * j);
                    if (distance <= brushSize) {
                        const index = pixelY * this.mapData.width + pixelX;
                        this.mapData.terrain_map[index] = 'grass';
                    }
                }
            }
        }
    }
    
    redraw() {
        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
        
        this.ctx.save();
        this.ctx.translate(this.offsetX, this.offsetY);
        this.ctx.scale(this.zoom, this.zoom);
        
        // Draw terrain
        this.drawTerrain();
        
        // Draw grid if zoomed in
        if (this.zoom > 0.5) {
            this.drawGrid();
        }
        
        // Draw obstacles
        this.drawObstacles();
        
        // Draw enemies
        this.drawEnemies();
        
        this.ctx.restore();
    }
    
    drawTerrain() {
        const imageData = this.ctx.createImageData(this.mapData.width, this.mapData.height);
        
        for (let y = 0; y < this.mapData.height; y++) {
            for (let x = 0; x < this.mapData.width; x++) {
                const index = y * this.mapData.width + x;
                const terrainType = this.mapData.terrain_map[index];
                const color = this.getTerrainColor(terrainType);
                
                const pixelIndex = (y * this.mapData.width + x) * 4;
                const rgb = this.hexToRgb(color);
                
                imageData.data[pixelIndex] = rgb.r;     // Red
                imageData.data[pixelIndex + 1] = rgb.g; // Green
                imageData.data[pixelIndex + 2] = rgb.b; // Blue
                imageData.data[pixelIndex + 3] = 255;   // Alpha
            }
        }
        
        this.ctx.putImageData(imageData, 0, 0);
    }
    
    drawGrid() {
        if (this.zoom < 0.5) return;
        
        this.ctx.strokeStyle = 'rgba(255, 255, 255, 0.1)';
        this.ctx.lineWidth = 1 / this.zoom;
        
        const gridSize = 50;
        
        // Vertical lines
        for (let x = 0; x <= this.mapData.width; x += gridSize) {
            this.ctx.beginPath();
            this.ctx.moveTo(x, 0);
            this.ctx.lineTo(x, this.mapData.height);
            this.ctx.stroke();
        }
        
        // Horizontal lines
        for (let y = 0; y <= this.mapData.height; y += gridSize) {
            this.ctx.beginPath();
            this.ctx.moveTo(0, y);
            this.ctx.lineTo(this.mapData.width, y);
            this.ctx.stroke();
        }
    }
    
    drawEnemies() {
        this.mapData.enemies.forEach(enemy => {
            const emoji = this.getEnemyEmoji(enemy.type);
            
            this.ctx.save();
            this.ctx.font = `${24 / this.zoom}px Arial`;
            this.ctx.textAlign = 'center';
            this.ctx.textBaseline = 'middle';
            
            // Shadow
            this.ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
            this.ctx.fillText(emoji, enemy.x + 2, enemy.y + 2);
            
            // Emoji
            this.ctx.fillStyle = '#ff4444';
            this.ctx.fillText(emoji, enemy.x, enemy.y);
            
            this.ctx.restore();
        });
    }
    
    drawObstacles() {
        this.mapData.obstacles.forEach(obstacle => {
            const emoji = this.getObstacleEmoji(obstacle.type);
            
            this.ctx.save();
            this.ctx.font = `${20 / this.zoom}px Arial`;
            this.ctx.textAlign = 'center';
            this.ctx.textBaseline = 'middle';
            
            // Shadow
            this.ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
            this.ctx.fillText(emoji, obstacle.x + 1, obstacle.y + 1);
            
            // Emoji
            this.ctx.fillStyle = '#8B4513';
            this.ctx.fillText(emoji, obstacle.x, obstacle.y);
            
            this.ctx.restore();
        });
    }
    
    // Utility functions
    getTerrainColor(terrainType) {
        const biomeColors = this.biomeColors[this.selectedBiome];
        return biomeColors[terrainType] || '#228B22';
    }
    
    hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : { r: 34, g: 139, b: 34 };
    }
    
    getEnemyEmoji(type) {
        const emojis = {
            foot_soldier: '🪖',
            machine_gun_post: '🔫',
            heavy_artillery: '💥',
            sniper_post: '🎯',
            mortar: '💣'
        };
        return emojis[type] || '👹';
    }
    
    getObstacleEmoji(type) {
        const emojis = {
            house: '🏠',
            wall: '🧱',
            tree: '🌳',
            rock: '🪨',
            bunker: '🏭'
        };
        return emojis[type] || '📦';
    }
    
    getEnemyHealth(type) {
        const health = {
            foot_soldier: 50,
            machine_gun_post: 120,
            heavy_artillery: 200,
            sniper_post: 60,
            mortar: 150
        };
        return health[type] || 50;
    }
    
    getEnemyDamage(type) {
        const damage = {
            foot_soldier: 25,
            machine_gun_post: 30,
            heavy_artillery: 120,
            sniper_post: 80,
            mortar: 90
        };
        return damage[type] || 25;
    }
    
    getEnemyRange(type) {
        const range = {
            foot_soldier: 150,
            machine_gun_post: 250,
            heavy_artillery: 400,
            sniper_post: 450,
            mortar: 350
        };
        return range[type] || 150;
    }
    
    getObstacleWidth(type) {
        const width = {
            house: 64,
            wall: 32,
            tree: 40,
            rock: 48,
            bunker: 96
        };
        return width[type] || 32;
    }
    
    getObstacleHeight(type) {
        const height = {
            house: 64,
            wall: 64,
            tree: 60,
            rock: 48,
            bunker: 64
        };
        return height[type] || 32;
    }
    
    isObstacleDestructible(type) {
        const destructible = {
            house: true,
            wall: true,
            tree: true,
            rock: false,
            bunker: true
        };
        return destructible[type] || true;
    }
    
    getObstacleHealth(type) {
        const health = {
            house: 200,
            wall: 150,
            tree: 100,
            rock: null,
            bunker: 500
        };
        return health[type];
    }
    
    updateStats() {
        document.getElementById('enemyCount').textContent = this.mapData.enemies.length;
        document.getElementById('obstacleCount').textContent = this.mapData.obstacles.length;
    }
    
    // Tool controls
    zoomIn() {
        this.zoom = Math.min(5, this.zoom * 1.2);
        this.redraw();
    }
    
    zoomOut() {
        this.zoom = Math.max(0.1, this.zoom / 1.2);
        this.redraw();
    }
    
    resetZoom() {
        this.zoom = 1;
        this.offsetX = 0;
        this.offsetY = 0;
        this.redraw();
    }
    
    clearMap() {
        if (confirm('Sei sicuro di voler pulire tutta la mappa? Questa azione non può essere annullata.')) {
            this.mapData.terrain_map.fill('grass');
            this.mapData.enemies = [];
            this.mapData.obstacles = [];
            this.redraw();
            this.updateStats();
        }
    }
}

// Global variables
let mapCreator;

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    mapCreator = new MapCreator();
});

// Tool selection functions
function selectBiome(biome, element) {
    document.querySelectorAll('.biome-btn').forEach(btn => btn.classList.remove('selected'));
    element.classList.add('selected');
    mapCreator.selectedBiome = biome;
    mapCreator.mapData.biome = biome;
    mapCreator.redraw();
}

function selectTerrain(terrain, element) {
    document.querySelectorAll('.terrain-btn').forEach(btn => btn.classList.remove('selected'));
    element.classList.add('selected');
    mapCreator.selectedTerrain = terrain;
    mapCreator.currentTool = 'terrain';
    mapCreator.selectedEnemy = null;
    mapCreator.selectedObstacle = null;
    
    // Deselect other tools
    document.querySelectorAll('.enemy-btn, .obstacle-btn').forEach(btn => btn.classList.remove('selected'));
}

function selectEnemy(enemy, element) {
    if (enemy === 'eraser') {
        mapCreator.currentTool = 'eraser';
        mapCreator.selectedEnemy = null;
    } else {
        document.querySelectorAll('.enemy-btn').forEach(btn => btn.classList.remove('selected'));
        element.classList.add('selected');
        mapCreator.selectedEnemy = enemy;
        mapCreator.currentTool = 'enemy';
    }
    
    // Deselect other tools
    document.querySelectorAll('.terrain-btn, .obstacle-btn').forEach(btn => btn.classList.remove('selected'));
}

function selectObstacle(obstacle, element) {
    if (obstacle === 'eraser') {
        mapCreator.currentTool = 'eraser';
        mapCreator.selectedObstacle = null;
    } else {
        document.querySelectorAll('.obstacle-btn').forEach(btn => btn.classList.remove('selected'));
        element.classList.add('selected');
        mapCreator.selectedObstacle = obstacle;
        mapCreator.currentTool = 'obstacle';
    }
    
    // Deselect other tools
    document.querySelectorAll('.terrain-btn, .enemy-btn').forEach(btn => btn.classList.remove('selected'));
}

function selectVisibility(visibility, element) {
    document.querySelectorAll('.toggle-option').forEach(btn => btn.classList.remove('selected'));
    element.classList.add('selected');
    mapCreator.mapVisibility = visibility;
    mapCreator.mapData.is_public = (visibility === 'public');
}

// Action functions
function goBack() {
    window.location.href = 'index.html';
}

function testMap() {
    if (mapCreator.mapData.enemies.length === 0) {
        alert('⚠️ Aggiungi almeno un nemico per testare la mappa!');
        return;
    }
    
    alert('🎮 Funzionalità di test in sviluppo! Salva la mappa e testala dalla lobby.');
}

async function saveMap() {
    if (!mapCreator.mapData.name.trim()) {
        alert('⚠️ Inserisci un nome per la mappa!');
        return;
    }
    
    if (mapCreator.mapData.enemies.length === 0) {
        if (!confirm('⚠️ La mappa non ha nemici. Vuoi salvarla comunque?')) {
            return;
        }
    }
    
    showLoading(true);
    
    try {
        // Convert terrain_map (1D array) to tiles (2D array) format expected by PHP API
        const tiles = [];
        for (let y = 0; y < mapCreator.mapData.height; y++) {
            const row = [];
            for (let x = 0; x < mapCreator.mapData.width; x++) {
                const index = y * mapCreator.mapData.width + x;
                row.push(mapCreator.mapData.terrain_map[index] || 'grass');
            }
            tiles.push(row);
        }
        
        // Prepare map data in the format expected by PHP API
        const mapDataForAPI = {
            width: mapCreator.mapData.width,
            height: mapCreator.mapData.height,
            biome: mapCreator.selectedBiome,
            terrain_type: mapCreator.mapData.terrain_type,
            tiles: tiles,
            enemies: mapCreator.mapData.enemies,
            obstacles: mapCreator.mapData.obstacles
        };
        
        const response = await fetch('/user/src/usermaps.php/usermaps/create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                token: mapCreator.userToken,
                name: mapCreator.mapData.name,
                description: mapCreator.mapData.description || '',
                is_public: mapCreator.mapData.is_public || false,
                map_data: mapDataForAPI
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(`✅ Mappa "${mapCreator.mapData.name}" salvata con successo!\nID: ${result.map_id}`);
            // Opzionalmente torna alla lobby
            if (confirm('Vuoi tornare alla lobby?')) {
                window.location.href = 'index.html';
            }
        } else {
            alert('❌ Errore nel salvataggio: ' + (result.error || 'Errore sconosciuto'));
        }
    } catch (error) {
        console.error('Errore salvataggio mappa:', error);
        alert('❌ Errore di connessione durante il salvataggio');
    } finally {
        showLoading(false);
    }
}

function showLoading(show) {
    document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
}

// Zoom controls
function zoomIn() {
    mapCreator.zoomIn();
}

function zoomOut() {
    mapCreator.zoomOut();
}

function resetZoom() {
    mapCreator.resetZoom();
}

function clearMap() {
    mapCreator.clearMap();
}
