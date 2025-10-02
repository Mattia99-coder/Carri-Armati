// Negozio Tank Game - Sistema completo di acquisti e personalizzazione
let userCredits = 0;
let userToken = localStorage.getItem('userToken');
let isLoading = false;
let userOwnedTanks = []; // Tank posseduti dall'utente
let tankCustomizations = {}; // Personalizzazioni tank
let userPurchasedItems = { weapons: [] }; // Oggetti acquistati
let targetTankId = null; // Tank da personalizzare (da URL)

// Dati del negozio
const shopData = {
    tanks: [], // Caricato dinamicamente dal database
    weapons: [] // Caricato dinamicamente dal database
};

// Inizializzazione del negozio
async function initShop() {
    console.log('🛒 Inizializzazione negozio...');
    
    // Controlla se c'è un token nei parametri URL
    const urlParams = new URLSearchParams(window.location.search);
    const urlToken = urlParams.get('token');
    
    if (urlToken && !userToken) {
        console.log('🔑 Token trovato nei parametri URL, salvando...');
        userToken = urlToken;
        localStorage.setItem('userToken', userToken);
    }
    
    if (!userToken) {
        alert('⚠️ Devi effettuare il login per accedere al negozio!');
        window.location.href = '../user/src/index.php';
        return;
    }
    
    // Controlla se c'è un tank specifico da personalizzare
    targetTankId = urlParams.get('customize');
    if (targetTankId) {
        targetTankId = parseInt(targetTankId);
        console.log('🎨 Tank target per personalizzazione:', targetTankId);
    }
    
    showLoading(true);
    
    try {
        await Promise.all([
            loadUserCredits(),
            loadTanks(),
            loadWeapons(),
            loadUserPurchases(),
            loadUserOwnedTanks(),
            loadTankCustomizations()
        ]);
        
        // Renderizza tutti i contenuti
        renderMyTanks(); // Nuova sezione personalizzazione
        renderTanks();
        renderWeapons();
        updateCreditsDisplay();
        
        // Se c'è un tank specifico da personalizzare, aprilo automaticamente
        if (targetTankId && userOwnedTanks.some(tank => tank.id === targetTankId)) {
            setTimeout(() => {
                customizeTankModal(targetTankId);
                // Scorri alla sezione personalizzazione
                scrollToSection('customization-section');
            }, 500);
        } else if (targetTankId) {
            alert('❌ Non possiedi questo tank o non è stato trovato!');
        }
        
        console.log('✅ Negozio inizializzato con successo');
    } catch (error) {
        console.error('❌ Errore inizializzazione negozio:', error);
        alert('🚨 Errore caricamento negozio. Riprova più tardi.');
    } finally {
        showLoading(false);
    }
}

// Carica i crediti dell'utente
async function loadUserCredits() {
    try {
        console.log('💰 Caricamento crediti utente...');
        
        const response = await fetch(`../../user/src/tank-customization.php/user-stats?token=${userToken}`);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.stats) {
            userCredits = parseInt(data.stats.credits) || 0;
            console.log(`✅ Crediti caricati: ${userCredits}`);
        } else {
            console.warn('⚠️ Crediti non trovati, usando valore predefinito');
            userCredits = 500; // Default credits
        }
    } catch (error) {
        console.error('❌ Errore caricamento crediti:', error);
        userCredits = 500; // Fallback credits
    }
}

// Carica tank posseduti dall'utente
async function loadUserOwnedTanks() {
    try {
        console.log('🚗 Caricamento tank posseduti...');
        
        const response = await fetch(`../../user/src/tank-customization.php/user-tanks?token=${userToken}`);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.tanks) {
            userOwnedTanks = data.tanks;
            console.log(`✅ Tank posseduti caricati: ${userOwnedTanks.length}`);
        } else {
            userOwnedTanks = [];
            console.log('ℹ️ Nessun tank posseduto trovato');
        }
    } catch (error) {
        console.error('❌ Errore caricamento tank posseduti:', error);
        userOwnedTanks = [];
    }
}

// Carica personalizzazioni tank
async function loadTankCustomizations() {
    try {
        console.log('🎨 Caricamento personalizzazioni tank...');
        
        const response = await fetch(`../../user/src/tank-customization.php/customizations?token=${userToken}`);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.customizations) {
            tankCustomizations = data.customizations;
            console.log(`✅ Personalizzazioni caricate: ${Object.keys(tankCustomizations).length}`);
        } else {
            tankCustomizations = {};
            console.log('ℹ️ Nessuna personalizzazione trovata');
        }
    } catch (error) {
        console.error('❌ Errore caricamento personalizzazioni:', error);
        tankCustomizations = {};
    }
}

// Carica i tank dal database
async function loadTanks() {
    try {
        console.log('🚗 Caricamento carri armati dal database...');
        
        // Utilizziamo il nuovo endpoint per i tank
        const response = await fetch('../../user/src/tank-customization.php/tanks');
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.tanks && data.tanks.length > 0) {
            // Mappa i tank dal database con prezzi definiti
            shopData.tanks = data.tanks.map((tank, index) => ({
                id: tank.id,
                name: tank.name,
                description: getTankDescription(tank.name),
                image: tank.cover_path || `../../assets/tanks/${tank.id}.png`,
                price: getTankPrice(index), // Prezzi crescenti
                owned: false, // Sarà aggiornato da loadUserPurchases
                stats: getTankStats(tank.name)
            }));
            
            console.log(`✅ Caricati ${shopData.tanks.length} carri armati dal database`);
        } else {
            throw new Error('Nessun tank trovato nel database');
        }
    } catch (error) {
        console.error('❌ Errore caricamento tank dal database:', error);
        
        // Fallback con tank predefiniti se il database non risponde
        console.warn('⚠️ Database non disponibile, usando tank predefiniti');
        shopData.tanks = [
            { 
                id: 1, 
                name: 'Tank Standard', 
                price: 0, 
                description: 'Carro armato base per iniziare la tua carriera militare', 
                image: '../../images/carro_armato_1.png', 
                owned: true,
                stats: { speed: 50, armor: 100, firepower: 75 }
            },
            { 
                id: 2, 
                name: 'Tank Pesante', 
                price: 800, 
                description: 'Corazzato massiccia resistenza e potenza di fuoco superiore', 
                image: '../../assets/tanks/2.png', 
                owned: false,
                stats: { speed: 30, armor: 150, firepower: 120 }
            },
            { 
                id: 11, 
                name: 'Tank Veloce', 
                price: 600, 
                description: 'Veicolo leggero ad alta mobilità per tattiche hit-and-run', 
                image: '../../assets/tanks/tank11.png', 
                owned: false,
                stats: { speed: 80, armor: 80, firepower: 85 }
            },
            { 
                id: 12, 
                name: 'Tank d\'Assalto', 
                price: 1200, 
                description: 'Perfetto equilibrio tra velocità, corazza e potenza di fuoco', 
                image: '../../assets/tanks/tank12.png', 
                owned: false,
                stats: { speed: 60, armor: 120, firepower: 110 }
            },
            { 
                id: 13, 
                name: 'Tank Sniper', 
                price: 1500, 
                description: 'Specializzato in combattimenti a lunga distanza con precisione letale', 
                image: '../../assets/tanks/tank13.png', 
                owned: false,
                stats: { speed: 40, armor: 90, firepower: 140 }
            }
        ];
        
        console.log(`✅ Caricati ${shopData.tanks.length} carri armati (fallback)`);
    }
}

// Carica gli acquisti dell'utente
async function loadUserPurchases() {
    try {
        console.log('📦 Caricamento acquisti utente...');
        
        // Carica tank posseduti
        const tanksResponse = await fetch(`/maps/api.php?endpoint=tanks/owned&token=${userToken}`);
        if (!tanksResponse.ok) {
            throw new Error(`HTTP ${tanksResponse.status}: ${tanksResponse.statusText}`);
        }
        const tanksData = await tanksResponse.json();
        console.log('✅ Tank posseduti:', tanksData);
        
        // Carica armi possedute
        const weaponsResponse = await fetch(`../../user/src/tank-customization.php/user-weapons?token=${userToken}`);
        if (!weaponsResponse.ok) {
            throw new Error(`HTTP ${weaponsResponse.status}: ${weaponsResponse.statusText}`);
        }
        const weaponsData = await weaponsResponse.json();
        console.log('✅ Armi possedute:', weaponsData);
        
        // Aggiorna i tank posseduti
        if (shopData.tanks && Array.isArray(tanksData)) {
            const ownedTankIds = tanksData.map(tank => tank.id);
            shopData.tanks.forEach(tank => {
                tank.owned = ownedTankIds.includes(tank.id);
            });
            console.log(`✅ Tank posseduti aggiornati (${ownedTankIds.length} tank)`);
        }
        
        // Aggiorna le armi possedute
        if (shopData.weapons && weaponsData.success && Array.isArray(weaponsData.weapons)) {
            const ownedWeapons = weaponsData.weapons.filter(w => w.owned == 1);
            const ownedWeaponIds = ownedWeapons.map(weapon => weapon.id);
            shopData.weapons.forEach(weapon => {
                weapon.owned = ownedWeaponIds.includes(weapon.id);
            });
            console.log(`✅ Armi possedute aggiornate (${ownedWeaponIds.length} armi)`);
        }
        
        // Salva gli oggetti posseduti per la personalizzazione
        userPurchasedItems.weapons = shopData.weapons.filter(w => w.owned);
        
    } catch (error) {
        console.error('❌ Errore caricamento acquisti:', error);
        console.warn('⚠️ Usando possedimenti predefiniti (solo tank standard)');
        
        // Fallback: solo il tank standard è posseduto
        if (shopData.tanks) {
            shopData.tanks.forEach((tank, index) => {
                tank.owned = index === 0; // Solo il primo tank è posseduto
            });
        }
        
        // Fallback per armi
        userPurchasedItems.weapons = shopData.weapons.filter(w => w.owned);
        
        if (shopData.weapons) {
            shopData.weapons.forEach((weapon, index) => {
                weapon.owned = index === 0; // Solo la prima arma è posseduta
            });
        }
    }
}

// Carica le armi disponibili dal database
async function loadWeapons() {
    try {
        console.log('🔫 Caricamento arsenal dal database...');
        
        const response = await fetch('../../user/src/tank-customization.php/weapons');
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.weapons && data.weapons.length > 0) {
            shopData.weapons = data.weapons.map(weapon => ({
                id: weapon.id,
                name: weapon.name,
                description: weapon.description,
                price: weapon.price,
                damage: weapon.damage,
                range_distance: weapon.range_distance,
                reload_time: weapon.reload_time,
                type: weapon.type,
                typeDisplayName: getWeaponTypeDisplayName(weapon.type),
                rarityClass: getWeaponRarity(weapon.price),
                image: getWeaponImage(weapon.type, weapon.name),
                owned: false // Sarà aggiornato da loadUserPurchases
            }));
            
            console.log(`✅ Caricati ${shopData.weapons.length} armi dal database`);
        } else {
            throw new Error('Nessuna arma trovata nel database');
        }
    } catch (error) {
        console.error('❌ Errore caricamento armi dal database:', error);
        
        // Fallback con armi predefinite
        console.warn('⚠️ Database non disponibile, usando armi predefinite');
        shopData.weapons = [
            { 
                id: 1, 
                name: 'Cannone Base', 
                price: 0, 
                description: 'Armamento standard per tutti i tank', 
                damage: 25, 
                range_distance: 100, 
                reload_time: 2000,
                type: 'cannon',
                typeDisplayName: 'CANNONE',
                rarityClass: 'common',
                image: '../../images/Mitragliatrice_leggera.jpg', 
                owned: true
            },
            { 
                id: 2, 
                name: 'Mitragliatrice Pesante', 
                price: 300, 
                description: 'Arma automatica ad alta cadenza di fuoco', 
                damage: 15, 
                range_distance: 80, 
                reload_time: 1000,
                type: 'machine_gun',
                typeDisplayName: 'MITRAGLIATRICE',
                rarityClass: 'uncommon',
                image: '../../assets/enemies/postazione-mitragliatrice.png', 
                owned: false
            },
            { 
                id: 3, 
                name: 'Cannone Lungo', 
                price: 600, 
                description: 'Cannone ad alta precisione per il combattimento a distanza', 
                damage: 40, 
                range_distance: 150, 
                reload_time: 3000,
                type: 'long_cannon',
                typeDisplayName: 'CANNONE LUNGO',
                rarityClass: 'rare',
                image: '../../assets/enemies/artiglieria_pesante.jpg', 
                owned: false
            }
        ];
        
        console.log(`✅ Caricati ${shopData.weapons.length} armi (fallback)`);
    }
}

// Renderizza i tank posseduti per la personalizzazione
function renderMyTanks() {
    const container = document.getElementById('my-tanks-list');
    if (!container) return;
    
    container.innerHTML = '';
    
    if (userOwnedTanks.length === 0) {
        container.innerHTML = `
            <div class="no-items" style="grid-column: 1 / -1; text-align: center; padding: 40px;">
                <h3>🚗 Nessun tank posseduto</h3>
                <p style="margin: 10px 0; color: #9c9c9c;">Acquista dei tank per iniziare a personalizzarli!</p>
                <button onclick="scrollToSection('tanks-grid')" class="btn-customize">🛒 Vai ai Tank</button>
            </div>
        `;
        return;
    }
    
    userOwnedTanks.forEach(tank => {
        const customization = tankCustomizations[tank.id] || {};
        const equippedWeapons = customization.weapons || [];
        const isTargetTank = targetTankId && tank.id === targetTankId;
        
        const tankCard = document.createElement('div');
        tankCard.className = `tank-customization-card owned ${isTargetTank ? 'target-tank' : ''}`;
        tankCard.innerHTML = `
            <div class="tank-customize-header">
                <div class="tank-customize-name">${tank.name} ${isTargetTank ? '🎯' : ''}</div>
                <span class="tank-status-owned">${isTargetTank ? 'DA PERSONALIZZARE' : 'POSSEDUTO'}</span>
            </div>
            
            <div class="tank-customize-preview">
                <div class="tank-customize-image">
                    <img src="${tank.cover_path || `../../assets/tanks/${tank.id}.png`}" 
                         alt="${tank.name}" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div style="display: none;">🚗</div>
                </div>
                <div style="font-size: 12px; color: #9c9c9c;">ID: ${tank.id}</div>
            </div>
            
            <div class="tank-weapons-slots">
                ${[1,2,3].map(slot => {
                    const weapon = equippedWeapons.find(w => w.slot === slot);
                    return `
                        <div class="weapon-slot ${weapon ? 'filled' : ''}">
                            ${weapon ? weapon.name : 'Vuoto'}
                        </div>
                    `;
                }).join('')}
            </div>
            
            <div class="customize-actions">
                <button class="btn-customize" onclick="customizeTankModal(${tank.id})">
                    🎨 Personalizza
                </button>
                <button class="btn-reset" onclick="resetTankCustomization(${tank.id})" title="Reset configurazione">
                    🔄
                </button>
            </div>
        `;
        
        container.appendChild(tankCard);
    });
}

// Renderizza i carri armati
function renderTanks() {
    const container = document.getElementById('tanks-grid');
    if (!container) return;
    
    container.innerHTML = '';
    
    if (shopData.tanks.length === 0) {
        container.innerHTML = '<div class="no-items">🚗 Nessun tank disponibile</div>';
        return;
    }
    
    shopData.tanks.forEach(tank => {
        const tankCard = createTankCard(tank);
        container.appendChild(tankCard);
    });
}

// Renderizza le armi
function renderWeapons() {
    const container = document.getElementById('weapons-grid');
    if (!container) return;
    
    container.innerHTML = '';
    
    if (shopData.weapons.length === 0) {
        container.innerHTML = '<div class="no-items">🔫 Nessuna arma disponibile</div>';
        return;
    }
    
    shopData.weapons.forEach(weapon => {
        const weaponCard = createWeaponCard(weapon);
        container.appendChild(weaponCard);
    });
}

// Crea una card per un carro armato
function createTankCard(tank) {
    const card = document.createElement('div');
    card.className = `item-card tank-card ${tank.owned ? 'owned' : ''}`;
    
    const statusText = tank.owned ? 'POSSEDUTO' : `${tank.price} Crediti`;
    const buttonText = tank.owned ? 'Posseduto' : 'Acquista';
    const buttonClass = tank.owned ? 'btn-owned' : (userCredits >= tank.price ? 'btn-buy' : 'btn-disabled');
    
    card.innerHTML = `
        <div class="item-image tank-image">
            <img src="${tank.image}" alt="${tank.name}" onerror="this.src='../../images/carro_armato_1.png'">
            ${tank.price === 0 ? '<div class="free-badge">INCLUSO</div>' : ''}
        </div>
        <div class="item-info">
            <h3 class="item-title">${tank.name}</h3>
            <p class="item-description">${tank.description}</p>
            <div class="tank-stats">
                <div class="stat-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Velocità: ${tank.stats?.speed || 50}</span>
                </div>
                <div class="stat-item">
                    <i class="fas fa-shield-alt"></i>
                    <span>Corazza: ${tank.stats?.armor || 100}</span>
                </div>
                <div class="stat-item">
                    <i class="fas fa-fire"></i>
                    <span>Potenza: ${tank.stats?.firepower || 75}</span>
                </div>
            </div>
            <div class="item-footer">
                <div class="item-price">${statusText}</div>
                <button class="btn ${buttonClass}" 
                        onclick="purchaseItem('tank', ${tank.id})"
                        ${tank.owned || userCredits < tank.price ? 'disabled' : ''}>
                    <i class="fas ${tank.owned ? 'fa-check' : 'fa-shopping-cart'}"></i>
                    ${buttonText}
                </button>
            </div>
        </div>
    `;
    
    return card;
}

// Crea una card per un'arma (versione migliorata)
function createWeaponCard(weapon) {
    const card = document.createElement('div');
    card.className = `item-card weapon-card ${weapon.rarityClass || 'common'} ${weapon.owned ? 'owned' : ''}`;
    
    const statusText = weapon.owned ? 'POSSEDUTO' : `${weapon.price || 0} Crediti`;
    const buttonText = weapon.owned ? 'Posseduto' : 'Acquista';
    const buttonClass = weapon.owned ? 'btn-owned' : (userCredits >= (weapon.price || 0) ? 'btn-buy' : 'btn-disabled');
    
    card.innerHTML = `
        <div class="item-image weapon-image">
            <img src="${weapon.image}" alt="${weapon.name}" onerror="this.src='../../images/plus-icon.svg'">
            <div class="weapon-type-badge ${weapon.type}">${weapon.typeDisplayName || weapon.type.toUpperCase()}</div>
            ${weapon.price === 0 ? '<div class="free-badge">INCLUSO</div>' : ''}
        </div>
        <div class="item-info">
            <h3 class="item-title">${weapon.name}</h3>
            <p class="item-description">${weapon.description}</p>
            <div class="weapon-stats">
                <div class="stat-row">
                    <i class="fas fa-fist-raised"></i>
                    <span>Danno: <strong>${weapon.damage}</strong></span>
                </div>
                <div class="stat-row">
                    <i class="fas fa-bullseye"></i>
                    <span>Gittata: <strong>${weapon.range_distance}m</strong></span>
                </div>
                <div class="stat-row">
                    <i class="fas fa-clock"></i>
                    <span>Ricarica: <strong>${(weapon.reload_time/1000).toFixed(1)}s</strong></span>
                </div>
            </div>
            <div class="item-footer">
                <div class="item-price">${statusText}</div>
                <button class="btn ${buttonClass}" 
                        onclick="purchaseItem('weapon', ${weapon.id})"
                        ${weapon.owned || userCredits < (weapon.price || 0) ? 'disabled' : ''}>
                    <i class="fas ${weapon.owned ? 'fa-check' : 'fa-shopping-cart'}"></i>
                    ${buttonText}
                </button>
            </div>
        </div>
    `;
    
    return card;
}

// Acquista un oggetto
async function purchaseItem(type, itemId) {
    if (isLoading) return;
    
    let item;
    if (type === 'tank') item = shopData.tanks.find(t => t.id == itemId);
    else if (type === 'weapon') item = shopData.weapons.find(w => w.id == itemId);
    
    if (!item || item.owned) return;
    
    if (userCredits < item.price) {
        alert('💰 Crediti insufficienti!\nHai bisogno di più crediti per questo acquisto.');
        return;
    }
    
    if (!confirm(`🛒 Conferma acquisto\n\nVuoi acquistare "${item.name}" per ${item.price} crediti?\n\nCrediti attuali: ${userCredits}\nCrediti dopo l'acquisto: ${userCredits - item.price}`)) {
        return;
    }
    
    showLoading(true);
    
    try {
        const response = await fetch('../../user/src/tank-customization.php/purchase', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                token: userToken,
                type: type,
                id: itemId
            })
        });
        
        const data = await response.json();
        if (data.success) {
            item.owned = true;
            userCredits = data.newCredits || (userCredits - item.price);
            updateCreditsDisplay();
            
            // Re-render solo la sezione specifica
            if (type === 'tank') renderTanks();
            else if (type === 'weapon') renderWeapons();
            
            // Notifica di successo
            showPurchaseSuccess(item.name, item.price);
        } else {
            alert('❌ Errore durante l\'acquisto\n' + (data.error || data.message || 'Errore sconosciuto'));
        }
    } catch (error) {
        console.error('❌ Errore acquisto oggetto:', error);
        alert('🔌 Errore di connessione\nControlla la tua connessione e riprova.');
    } finally {
        showLoading(false);
    }
}

// Mostra notifica di acquisto riuscito
function showPurchaseSuccess(itemName, price) {
    const notification = document.createElement('div');
    notification.className = 'purchase-notification';
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas fa-check-circle"></i>
            <h4>Acquisto completato!</h4>
            <p>${itemName} è ora nel tuo arsenale</p>
            <small>-${price} crediti</small>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// Aggiorna la visualizzazione dei crediti
function updateCreditsDisplay() {
    const creditsElement = document.getElementById('user-credits');
    if (creditsElement) {
        creditsElement.textContent = userCredits.toLocaleString();
    }
}

// Mostra/nasconde il loading
function showLoading(show) {
    isLoading = show;
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.style.display = show ? 'flex' : 'none';
    }
}

// Debug: Test connessione API
function testAPIConnectivity() {
    console.log('🔧 TEST API CONNECTIVITY');
    console.log('Token:', userToken ? 'Present' : 'Missing');
    
    const endpoints = [
        '../../user/src/tank-customization.php/weapons',
        `../../user/src/tank-customization.php/user-stats?token=${userToken}`,
        '../../user/src/tank-customization.php/tanks'
    ];
    
    endpoints.forEach(async (endpoint) => {
        try {
            const response = await fetch(endpoint);
            console.log(`${endpoint}: ${response.status} ${response.statusText}`);
            const text = await response.text();
            console.log(`Response preview: ${text.substring(0, 200)}...`);
        } catch (error) {
            console.error(`${endpoint}: ERROR -`, error.message);
        }
    });
}

// Funzioni di utilità
function getTankPrice(index) {
    const prices = [0, 600, 800, 1200, 1500, 2000];
    return prices[index] || (index * 400 + 200);
}

function getTankDescription(name) {
    const descriptions = {
        'Tank Standard': 'Carro armato base per iniziare la tua carriera militare',
        'Tank Pesante': 'Corazzato massiccia resistenza e potenza di fuoco superiore',
        'Tank Veloce': 'Veicolo leggero ad alta mobilità per tattiche hit-and-run',
        'Tank d\'Assalto': 'Perfetto equilibrio tra velocità, corazza e potenza di fuoco',
        'Tank Sniper': 'Specializzato in combattimenti a lunga distanza con precisione letale'
    };
    return descriptions[name] || 'Potente veicolo da combattimento per dominare il campo di battaglia';
}

function getTankStats(name) {
    const stats = {
        'Tank Standard': { speed: 50, armor: 100, firepower: 75 },
        'Tank Pesante': { speed: 30, armor: 150, firepower: 120 },
        'Tank Veloce': { speed: 80, armor: 80, firepower: 85 },
        'Tank d\'Assalto': { speed: 60, armor: 120, firepower: 110 },
        'Tank Sniper': { speed: 40, armor: 90, firepower: 140 }
    };
    return stats[name] || { speed: 50, armor: 100, firepower: 75 };
}

function getWeaponTypeDisplayName(type) {
    const displayNames = {
        'cannon': 'CANNONE',
        'machine_gun': 'MITRAGLIATRICE', 
        'long_cannon': 'CANNONE LUNGO',
        'rocket': 'RAZZI',
        'laser': 'LASER'
    };
    return displayNames[type] || type.toUpperCase();
}

function getWeaponRarity(price) {
    if (price === 0) return 'free';
    if (price <= 200) return 'common';
    if (price <= 500) return 'uncommon';
    if (price <= 800) return 'rare';
    return 'legendary';
}

function getWeaponImage(type, name) {
    // Usa immagini specifiche per tipo se disponibili
    const typeImages = {
        'cannon': '../../images/Mitragliatrice_leggera.jpg',
        'machine_gun': '../../assets/enemies/postazione-mitragliatrice.png',
        'long_cannon': '../../assets/enemies/artiglieria_pesante.jpg'
    };
    return typeImages[type] || '../../images/plus-icon.svg';
}

// Gestione tasti di debug
document.addEventListener('keydown', function(event) {
    if (event.ctrlKey && event.shiftKey && event.key === 'D') {
        event.preventDefault();
        testAPIConnectivity();
    }
});

// === FUNZIONI PERSONALIZZAZIONE TANK === //

// Apri modal personalizzazione tank
function customizeTankModal(tankId) {
    const tank = userOwnedTanks.find(t => t.id === tankId);
    if (!tank) {
        alert('❌ Tank non trovato!');
        return;
    }
    
    console.log('🎨 Apertura personalizzazione per tank:', tank.name);
    
    // Crea modal personalizzazione
    const modal = document.createElement('div');
    modal.className = 'customization-modal';
    modal.innerHTML = `
        <div class="modal-overlay" onclick="closeCustomizationModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2>🎨 Personalizza ${tank.name}</h2>
                <button onclick="closeCustomizationModal()" class="close-btn">×</button>
            </div>
            
            <div class="modal-body">
                <div class="customization-info">
                    <p style="background: rgba(255, 102, 0, 0.1); padding: 10px; border-radius: 5px; border-left: 4px solid #ff6600; margin-bottom: 20px;">
                        ℹ️ <strong>Personalizzazione Limitata:</strong> Puoi equipaggiare solo armi e potenziamenti che hai già acquistato. 
                        Gli oggetti non posseduti non saranno disponibili per l'equipaggiamento.
                    </p>
                </div>
                
                <div class="customization-tabs">
                    <button class="tab-btn active" onclick="showCustomizationTab('weapons')">⚔️ Armi</button>
                    <button class="tab-btn" onclick="showCustomizationTab('appearance')">🎨 Aspetto</button>
                </div>
                
                <div id="customization-content">
                    <!-- Il contenuto verrà caricato dinamicamente -->
                </div>
            </div>
            
            <div class="modal-footer">
                <button onclick="saveCustomization(${tankId})" class="btn-save">💾 Salva Configurazione</button>
                <button onclick="closeCustomizationModal()" class="btn-cancel">❌ Annulla</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    showCustomizationTab('weapons');
}

// Chiudi modal personalizzazione
function closeCustomizationModal() {
    const modal = document.querySelector('.customization-modal');
    if (modal) {
        modal.remove();
    }
}

// Mostra tab personalizzazione
function showCustomizationTab(tabName) {
    // Aggiorna tab attivi
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelector(`[onclick="showCustomizationTab('${tabName}')"]`).classList.add('active');
    
    const content = document.getElementById('customization-content');
    
    switch(tabName) {
        case 'weapons':
            content.innerHTML = generateWeaponsTab();
            break;
        case 'appearance':
            content.innerHTML = generateAppearanceTab();
            break;
    }
}

// Genera contenuto tab armi
function generateWeaponsTab() {
    const ownedWeapons = userPurchasedItems.weapons || shopData.weapons.filter(w => w.owned);
    
    if (ownedWeapons.length === 0) {
        return `
            <div class="weapons-customization">
                <h3>⚔️ Configura Armamenti</h3>
                <div class="no-weapons-message">
                    <p>❌ Non possiedi ancora nessuna arma!</p>
                    <p style="margin: 10px 0; color: #9c9c9c;">Acquista delle armi dal negozio per poterle equipaggiare sui tuoi tank.</p>
                    <button onclick="scrollToSection('weapons-grid')" class="btn-customize">🛒 Vai alle Armi</button>
                </div>
            </div>
        `;
    }
    
    return `
        <div class="weapons-customization">
            <h3>⚔️ Configura Armamenti</h3>
            <p>Seleziona fino a 3 armi per il tuo tank (solo armi acquistate):</p>
            
            <div class="weapon-slots-config">
                ${[1,2,3].map(slot => `
                    <div class="weapon-slot-config">
                        <label>Slot ${slot}:</label>
                        <select id="weapon-slot-${slot}" onchange="updateWeaponPreview()">
                            <option value="">Nessuna arma</option>
                            ${ownedWeapons.map(weapon => 
                                `<option value="${weapon.id}">${weapon.name} ${weapon.owned ? '✅' : '🔒'}</option>`
                            ).join('')}
                        </select>
                    </div>
                `).join('')}
            </div>
            
            <div class="weapon-preview">
                <h4>Anteprima configurazione:</h4>
                <div id="weapon-preview-display">
                    <div class="weapon-stats">
                        <p><strong>Armi equipaggiate:</strong> <span id="equipped-count">0</span>/3</p>
                        <p><strong>Danno totale:</strong> <span id="total-damage">0</span></p>
                    </div>
                </div>
            </div>
            
            <div class="weapons-owned-info">
                <h4>🏪 Le tue armi:</h4>
                <div class="owned-weapons-list">
                    ${ownedWeapons.map(weapon => `
                        <div class="owned-weapon-item">
                            <span class="weapon-name">${weapon.name}</span>
                            <span class="weapon-damage">Danno: ${weapon.damage || 100}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
}

// Genera contenuto tab aspetto
function generateAppearanceTab() {
    return `
        <div class="appearance-customization">
            <h3>🎨 Personalizzazione Aspetto</h3>
            
            <div class="color-section">
                <label>Colore primario:</label>
                <input type="color" id="primary-color" value="#0066ff">
            </div>
            
            <div class="color-section">
                <label>Colore secondario:</label>
                <input type="color" id="secondary-color" value="#ff6600">
            </div>
            
            <div class="pattern-section">
                <label>Pattern:</label>
                <select id="tank-pattern">
                    <option value="solid">Colore uniforme</option>
                    <option value="stripes">Strisce</option>
                    <option value="camo">Camouflage</option>
                    <option value="spots">Macchie</option>
                </select>
            </div>
        </div>
    `;
}

// Salva personalizzazione tank
async function saveCustomization(tankId) {
    try {
        const customizationData = {
            tank_id: tankId,
            weapons: getSelectedWeapons(),
            appearance: getAppearanceSettings()
        };
        
        const response = await fetch('../../user/src/tank-customization.php/save-customization', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                token: userToken,
                customization: customizationData
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Personalizzazione salvata con successo!');
            await loadTankCustomizations();
            renderMyTanks();
            closeCustomizationModal();
        } else {
            alert('❌ Errore salvataggio: ' + (data.error || 'Errore sconosciuto'));
        }
    } catch (error) {
        console.error('Errore salvataggio personalizzazione:', error);
        alert('❌ Errore di connessione durante il salvataggio');
    }
}

// Ottieni armi selezionate
function getSelectedWeapons() {
    const weapons = [];
    for (let i = 1; i <= 3; i++) {
        const select = document.getElementById(`weapon-slot-${i}`);
        if (select && select.value) {
            weapons.push({
                slot: i,
                weapon_id: parseInt(select.value),
                name: select.options[select.selectedIndex].text
            });
        }
    }
    return weapons;
}

// Ottieni impostazioni aspetto
function getAppearanceSettings() {
    return {
        primary_color: document.getElementById('primary-color')?.value || '#0066ff',
        secondary_color: document.getElementById('secondary-color')?.value || '#ff6600',
        pattern: document.getElementById('tank-pattern')?.value || 'solid'
    };
}

// Reset personalizzazione tank
async function resetTankCustomization(tankId) {
    if (!confirm('🔄 Sei sicuro di voler resettare la personalizzazione di questo tank?')) {
        return;
    }
    
    try {
        const response = await fetch('../../user/src/tank-customization.php/reset-customization', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                token: userToken,
                tank_id: tankId
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('✅ Personalizzazione resettata!');
            await loadTankCustomizations();
            renderMyTanks();
        } else {
            alert('❌ Errore reset: ' + (data.error || 'Errore sconosciuto'));
        }
    } catch (error) {
        console.error('Errore reset personalizzazione:', error);
        alert('❌ Errore di connessione durante il reset');
    }
}

// Scorri a sezione specifica
function scrollToSection(sectionId) {
    const element = document.getElementById(sectionId);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
    }
}

// Aggiorna anteprima armi
function updateWeaponPreview() {
    const equippedWeapons = getSelectedWeapons();
    const equippedCount = equippedWeapons.length;
    
    // Calcola danno totale (assumendo danno base 100 per arma)
    const totalDamage = equippedCount * 100;
    
    // Aggiorna contatori
    const countEl = document.getElementById('equipped-count');
    const damageEl = document.getElementById('total-damage');
    
    if (countEl) countEl.textContent = equippedCount;
    if (damageEl) damageEl.textContent = totalDamage;
    
    // Mostra dettagli armi equipaggiate
    const previewEl = document.getElementById('weapon-preview-display');
    if (previewEl && equippedWeapons.length > 0) {
        const weaponsList = equippedWeapons.map(w => 
            `<div class="equipped-weapon">Slot ${w.slot}: ${w.name}</div>`
        ).join('');
        
        previewEl.innerHTML = `
            <div class="weapon-stats">
                <p><strong>Armi equipaggiate:</strong> ${equippedCount}/3</p>
                <p><strong>Danno totale:</strong> ${totalDamage}</p>
            </div>
            <div class="equipped-weapons-list">
                ${weaponsList}
            </div>
        `;
    }
}

// Inizializzazione all'avvio
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎮 Tank Game Shop - Inizializzazione...');
    initShop();
});
