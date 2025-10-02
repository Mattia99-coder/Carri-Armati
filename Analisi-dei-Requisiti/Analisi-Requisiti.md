# Analisi Funzionale

---

# 1. Introduzione

### **Obiettivo del documento**

Il presente documento definisce in modo esaustivo i **requisiti funzionali** e **non funzionali** del gioco sulla base delle direttive fornite dal cliente. Costituisce la base per tutte le fasi successive di progettazione, sviluppo e collaudo.

### **Descrizione generale del sistema**

Il gioco permette a più giocatori di guidare carri armati in una mappa 2D priva di direzione obbligata, in cui raccogliere punti, personalizzare i propri veicoli e affrontare postazioni nemiche statiche (soldati, artiglieria, mortai, mitragliatrici). Sono previste meccaniche di copertura dietro ostacoli statici.

Progetto di gioco tank sviluppato con architettura a micro servizi utilizzando solo **PHP** e **JavaScript vanilla/jQuery**.

### Tecnologie utilizzate

- **Frontend**: HTML, CSS3, JavaScript (ES5 compatibile) + jQuery 3.6.0 (per compatibilità con browser)
- **Backend**: PHP 8.1 con Apache HTTP Server
- **Database**: MySQL 8.0
- **Containerizzazione**: Docker & Docker Compose
- **Architettura**: REST API con microservizi

**Glossario**

- **Giocatore (Player)**: utente finale che accede al gioco.
- **Canna**: armamento principale del carro armato (cannone, mitragliatrice).
- **Postazione nemica**: entità statica con parametri di danno, raggio d’azione e tempo di ricarica.
- **Docker Server**: container Linux per eseguire backend e database.
- **Mappa**: area di gioco composta da mattonelle 10000×10000.

---

# 2. Obiettivi del Sistema

**Per obiettivi del sistema si intente cosa deve ottenere il sistema una volta realizzato.**

Gli obiettivi del sistema sono:

1. **Multiplayer**: fornire un’esperienza multiplayer (con un minimo di 2 player su singolo tank fino ad un massimo di 4; il numero massimo di tank per lobby è di 4, quindi con un totale di 16 player nello stesso game) 2D open world con movimenti e combattimento real-time.
2. **Personalizzazione**: permettere la personalizzazione e l’evoluzione dei carri armati basata sui punti guadagnati (EXP). Inoltre si deve offrire la possibilità di creazione e utilizzo di mappe personalizzate di grande dimensioni (oltre a quelle 10 predefinite).
3. **Progressione**: sistema di livelli, punti e classifiche.
4. **Meccaniche social**: implementazione di inviti ad amici per partite online e classifica globale.
5. **Scalabilità**: architettura a micro servizi containerizzata. In particolare è necessario garantire l’infrastruttura a container per il server, mentre è opzionale per il client.

---

# 3. Attori

**Gli attori sono le entità esterne che interagiscono con il sistema in termini di funzionalità (tipicamente gli utenti o sistemi esterni) e il modo in cui lo fanno.**

- **Giocatore Registrato (player)**: si tratta dell’utente finale del prodotto che accede, personalizza carri, gioca partite, visualizza statistiche e invita amici.
- **Amministratore di Sistema**: la persona che gestisce l’infrastruttura, in questo caso tutto il nostro team di sviluppo (gestisce i container Docker, aggiorna il server).

---

# 4. Requisiti Funzionali (RF)

**Qui viene descritto che cosa deve fare il sistema. Si adopera una suddivisione per aree funzionali (quindi tutti i micro-servizi che compongono il sistema: login, gameplay, shop, hangar, ecc.).**

## RF01: Sistema di Gestione Utenti

### RF01.01: Registrazione Utente

**Descrizione: Il sistema deve permettere la registrazione di nuovi utenti**. 

- **Input**:
    - Username
    - Password
    - Conferma password
- **Output**:
    - Token di autenticazione JWT
    - Profilo utente creato
- **Regole di Business**:
    - Username (con limitazioni su numero caratteri) deve essere univoco nel sistema
    - Password (con limitazioni su numero caratteri)
    - Creazione automatica di statistiche utente iniziali

### RF01.2: Autenticazione Utente

**Descrizione: Il sistema deve permettere l'accesso sicuro agli utenti registrati.** 

- **Input**:
    - Username
    - Password
- **Output**:
    - Token di sessione con scadenza
- **Regole di Business**:
    - Verifica hash della password
    - Generazione token univoco a 32 caratteri
    - Invalidazione token precedenti per sicurezza

### RF01.3: Gestione Sessioni

**Descrizione: Il sistema deve gestire le sessioni utente tramite token.**

- **Funzionalità**:
    - Verifica validità token per ogni richiesta autenticata
    - Scadenza automatica token
    - Logout con invalidazione token

---

## RF02: Sistema di Gioco

### RF02.1: Motore di Gioco

**Descrizione: Motore di gioco per la gestione delle partite in real-time.**

- **Componenti**:
    - Rendering canvas HTML5 con pixel art
    - Sistema di collisioni per tank e proiettili
    - Gestione input keyboard per controlli
    - Sistema di fisica per movimento e balistica

### RF02.2: Meccaniche di Tank

**Descrizione: Gestione dei tank e delle loro caratteristiche.**

- **Attributi Tank**:
    - Modello (Standard, Pesante, Veloce, d'Assalto, Sniper)
    - Punti vita variabili per tipo
    - Velocità di movimento
    - Slot armi (fino a 4 armi simultanee)

### RF02.3: Sistema Armi

**Descrizione: Gestione delle armi e del combattimento.**

- **Tipologie di armi**:
    - **Cannon**: Cannoni con danni elevati (80-200 danni)
    - **Machine Gun**: Mitragliatrici a fuoco rapido (25-40 danni)
    - **Heavy Artillery**: Artiglieria pesante a lunga gittata (200 danni)

**Attributi Armi**:

- **Danno base**
- **Gittata massima (150-450 unità)**
- **Tempo di ricarica (500-4000ms)**
- **Prezzo di acquisto (0-3500 crediti)**

---

## RF03: Sistema Mappe

### RF03.1: Mappe Predefinite

**Descrizione**: **Collezione di 10 mappe diverse con biomi unici.**

- **Mappe disponibili**:
    - Selezione di 10 mappe con nomi diversi e caratteristici in base al bioma

### RF03.2: Generazione Procedurale

**Descrizione**: **Sistema di generazione automatica mappe utilizzando Simplex Noise.**

- **Parametri**:
    - Seed pseudorandomico che permette riproducibilità
    - Biomi con texture e colori specifici
    - Distribuzione automatica di ostacoli e coperture
    - Spawn point bilanciati per i giocatori

### RF03.3: Editor Mappe Utente

**Descrizione**: **Interfaccia per creazione mappe personalizzate.**

- **Funzionalità**:
    - Creazione mappe custom con editor visuale
    - Salvataggio mappe private o pubbliche
    - Condivisione mappe tra utenti
    - Gestione libreria mappe personali

---

## RF04: Sistema Multiplayer

### RF04.1: Gestione Partite

**Descrizione: Creazione e gestione partite multiplayer.**

- **Stati della partita**:
    - **Waiting**: In attesa di giocatori
    - **In Progress**: Partita in corso
    - **Completed**: Partita terminata
    - **Cancelled**: Partita annullata
- **Parametri**:
    - **Numero massimo giocatori**: ****2-4
    - **Selezione mappa obbligatoria**
    - **Timeout per partite inattive**

### RF04.2: Sistema Inviti

**Descrizione: Invio e gestione inviti tra amici.**

- **Funzionalità**:
    - Invito diretto tramite username
    - Notifiche inviti in tempo reale
- **Stati invito**: pending, accepted
- Scadenza automatica inviti dopo tempo limite

### RF04.3: Multiplayer Locale

**Descrizione: Sistema di creazione partite multiplayer locale (stessa rete)**

- **Funzionalità**:
    - Creazione di partita multiplayer con un limite massimo al numero di giocatori.
    - Visibilità
    - Lista partite pubbliche disponibili
    - Filtri per mappa e numero giocatori
    - Join automatico a partite compatibili
    - Bilanciamento skill level per partite equilibrate

---

## RF05: Sistema Progressione

### RF05.1: Statistiche Utente

**Descrizione**: **Tracciamento prestazioni e progressi giocatore.**

- **Metriche**:
    - Livello giocatore (basato su exp)
    - Punti totali accumulati
    - Kill/Death ratio
    - Partite giocate e vinte
    - Tempo di gioco totale
    - Crediti di gioco disponibili

### RF05.2: Sistema Classifiche

**Descrizione**: **Leaderboard globali e personalizzate.**

- **Classifiche**:
    - Top 10 giocatori per punti totali
    - Classifiche per kill/death ratio
    - Statistiche personali comparative

### RF05.3: Sistema Ricompense

**Descrizione**: **Assegnazione di ricompense basate su prestazioni.** 

- **Ricompense**:
    - Esperienza per kill e vittorie
    - Crediti per acquisti shop

---

## RF06: Sistema Shop

### RF06.1: Negozio Armi

**Descrizione**: **Acquisto e gestione armi con crediti di gioco.**

- **Funzionalità**:
    - Catalogo armi con prezzi (800-3500 crediti)
    - Preview statistiche armi
    - Sistema di acquisto con verifica crediti
    - Inventario armi personale
    - Costo arma proporzionale a qualità

### RF06.2: Negozio Tank

**Descrizione**: **Acquisto e gestione tank con crediti di gioco.**

- **Funzionalità**:
    - Catalogo tank con prezzi (800-3500 crediti)
    - Preview statistiche tank
    - Sistema di acquisto con verifica crediti
    - Inventario tank personale
    - Costo tank proporzionale a qualità

### RF06.3: Personalizzazione Tank

**Descrizione**: Personalizzazione su configurazione per tank.

- **Opzioni**:
    - Equipaggiamento max 4 slot armi per tank
    - Configurazioni salvate per riutilizzo rapido
    - Preview modifiche in tempo reale

---

## RF07: Sistema di Personalizzazione dei Tank

---

# 5. Requisiti Non-Funzionali (RNF)

**Qui viene descritto come il sistema deve comportarsi mentre svolge le funzioni descritte nei requisiti funzionali. Nello specifico si analizzano quelli che sono la qualità, i vincoli e le proprietà del sistema (prestazioni, sicurezza, usabilità, scalabilità, ecc.).**

## RNF01: Performance

### RNF01.1: Tempi di risposta

**Descrizione**: **Tempi di risposta accettabili per l’utente.**

- **Login/Registrazione**: < 2 secondi
- **Caricamento mappe**: < 5 secondi
- **Latenza multiplayer**: < 100ms per azioni critiche
- **Query database**: < 500ms per operazioni standard

### RNF01.2: Throughput

**Descrizione**: **Carico che il sistema deve essere in grado di sopportare.**

- **Utenti concorrenti**: Supporto minimo 100 utenti simultanei
- **Partite simultanee**: Supporto 25 partite multiplayer contemporanee
- **Operazioni DB**: 1000 query/secondo su hardware standard

### **RNF01.3: Scalabilità**

**Descrizione**: **Possibilità di adattamento a qualsiasi sistema e situazione di riutilizzo.**

- **Scaling orizzontale**: Architettura container per scalabilità automatica
- **Load balancing**: Distribuzione carico tra istanze multiple
- **Database sharding**: Preparazione per partizionamento dati

---

## RNF02: Affidabilità

### **RNF02.1: Disponibilità**

**Descrizione**: **Il sistema dovrebbe garantire affidabilità per quanto riguarda la disponibilità dell’erogazione del servizio.**

- **Uptime target**: 99.5% (4.38 ore downtime/mese)
- **Recovery time**: < 15 minuti per restart servizi
- **Backup automatici**: Database backup ogni 6 ore

### **RNF02.2: Fault Tolerance**

**Descrizione**: **Il sistema deve essere in grado di gestire gli errori in maniera efficiente.**

- **Gestione errori**: Graceful degradation per failure parziali
- **Retry logic**: Tentativo automatico operazioni fallite (max 3 retry)
- **Circuit breaker**: Protezione sovraccarico servizi

### **RNF02.3: Consistenza Dati**

**Descrizione**: **Il sistema deve garantire la consistenza dei dati per evitare problemi.**

- **Transazioni ACID**: Garanzia consistenza operazioni critiche
- **Isolamento**: Prevent race conditions su operazioni concorrenti
- **Rollback**: Ripristino stato precedente in caso di errori

---

## RNF03: Sicurezza

### **RNF03.1: Autenticazione**

**Descrizione**: **Criteri di autenticazione.**

- **Token JWT**: Sicurezza basata su token con scadenza
- **Password hashing**: Algoritmo `bcrypt` per hash password
- **Session management**: Token univoci a 32 caratteri

### **RNF03.2: Autorizzazione**

**Descrizione**: **Criteri per le autorizzazione per l’utente e l’amministratore sulle varie operazioni.**

- **Access control**: Verifica permessi per ogni operazione
- **Resource protection**: Accesso dati limitato al proprietario
- **API security**: Validazione input e sanificazione dati

### **RNF03.3: Privacy**

**Descrizione**: **Politiche di sicurezza e normative nazionali.**

- **Data encryption**: Comunicazione HTTPS per dati sensibili
- **GDPR compliance**: Gestione dati personali conforme normative
- **Audit log**: Tracciamento operazioni per sicurezza

---

## RFN04: Usabilità

### **RNF04.1: Interfaccia Utente**

**Descrizione**: **I criteri che devono essere rispettati dall’implementazione dell’interfaccia utente.**

- **Cross-browser**: Supporto Chrome, Firefox, Safari, Edge
- **Responsive design**: Adattamento schermi diversi
- **Accessibilità**: Conformità standard WCAG 2.1

### **RNF04.2: Esperienza Utente**

**Descrizione**: **Implementazioni che favoriscono l’esperienza dell’utente.**

- **Learning curve**: Tempo apprendimento < 30 minuti
- **Error handling**: Messaggi errore chiari e actionable
- **Feedback**: Notifiche immediate per azioni utente

### **RNF04.3: Localizzazione**

**Descrizione**: **Tipizzazione dei formati ora, lingua e data.**

- **Lingua**: Interfaccia in italiano
- **Timezone**: Gestione automatica fuso orario
- **Formati**: Date e numeri in formato locale

---

## RNF05: Maintainability

### **RNF05.1: Modularità**

**Descrizione**: **L’implementazione dell’infrastruttura è suddivisa in microservizi per consentire la modularità e l’indipendenza.**

- **Microservizi**: Separazione logica in servizi indipendenti
- **API REST**: Interfacce standardizzate tra componenti
- **Dependency injection**: Riduzione accoppiamento tra moduli

### **RNF05.2: Documentazione**

**Descrizione**: **Necessaria per l’esame.**

- **Code documentation**: Commenti inline per logica complessa
- **API specs**: Documentazione OpenAPI/Swagger completa
- **Deployment guide**: Istruzioni setup e configurazione

### **RNF05.3: Testing**

**Descrizione**: **Tutte le tipologie di test adottate durante lo sviluppo del sistema.**

- **Unit tests**: Copertura > 80% per funzioni critiche
- **Integration tests**: Test API end-to-end
- **Load testing**: Verifica performance sotto carico

---

## RNF06: Portabilità

### **RNF06.1: Platform Independence**

**Descrizione**: **Lo scopo è quello di rendere il più indipendente possibili l’implementazione dal sistema su cui viene eseguita.**

- **Containerizzazione**: Docker per consistenza ambienti
- **Database portability**: SQL standard per compatibilità
- **Browser compatibility**: JavaScript ES5 per supporto esteso

### **RNF06.2: Deployment**

**Descrizione**: **Non ho capito.**

- **Environment parity**: Consistenza dev/staging/production
- **Configuration management**: Variabili ambiente per settings
- **Rollback capability**: Possibilità ripristino versioni precedenti

---

---

# 6. Architettura del sistema

## Architettura Microservizi

### User Service (`/user/`)

- Autenticazione e autorizzazione
- Gestione profili utente
- Statistiche e progressione
- API multiplayer e inviti

### **Game Service** (`/game/`)

- Engine di gioco real-time
- Meccaniche di gameplay
- Rendering e physics

### **Maps Service** (`/maps/`)

- Gestione mappe predefinite
- Generazione procedurale
- API tank e configurazioni

### **Shop Service** (`/shop/`)

- Catalogo armi e personalizzazioni
- Sistema di acquisto
- Gestione inventario

### **Leaderboard Service** (`/leaderboard/`)

- Classifiche globali
- Statistiche comparative
- Sistema ranking

## Infrastruttura

### **Containerizzazione (Docker Compose)**

```yaml
services:
 web:      # Apache PHP 8.1 per servizi web
 db:       # MySQL 8.0 per persistenza dati
 volumes:  # Persistenza database
 network:  # Comunicazione inter-container

```

### **Database Design**

- **Schema relazionale** con 12 tabelle principali
- **Indici ottimizzati** per query frequenti
- **Foreign keys** per integrità referenziale
- **Stored procedures** per operazioni complesse

## **Pattern Architetturali**

### **MVP (Model-View-Presenter)**

- **Model**: Entità database e business logic
- **View**: Interfacce HTML/JavaScript
- **Presenter**: Controller PHP per gestione richieste

### **Repository Pattern**

- **Data Access Layer**: Isolamento logica database
- **Business Logic Layer**: Regole di business
- **Presentation Layer**: API REST endpoints

## 5. Requisiti Non Funzionali

- **RNF1 (Performance)**: <200ms latenza round-trip server-client.
- **RNF2 (Scalabilità)**: Supporto a 1000 partite concorrenti.
- **RNF3 (Affidabilità)**: Uptime ≥ 99.5%.
- **RNF4 (Usabilità)**: Interfaccia intuitiva per la creazione mappe.
- **RNF5 (Sicurezza)**: Crittografia dati sensibili in transito (TLS), protezione da SQL injection.
- **RNF6 (Portabilità)**: Backend containerizzato, client web cross-browser.
- **RNF7 (Manutenibilità)**: Codebase modulare, API documentata.

---

# 7. Specifica delle API

## **Authentication APIs**

- **`POST /user/src/login.php`**
    
    **Scopo**: **Autenticazione per utente esistente.** 
    
    **Input**:
    
    ```php
    {
     "name": "string",
     "password": "string"
    }
    ```
    
    **Output**:
    
    ```php
    {
     "success": true,
     "token": "string"
    }
    ```
    
- **`POST /user/src/register.php`**
    
    **Scopo**: **Registrazione nuovo utente.**
    
    **Input**:
    
    ```php
    {
     "username": "string",
     "password": "string",
     "confirmPassword": "string"
    }
    ```
    
    **Output**:
    
    ```php
    {
     "success": true,
     "user_id": "integer",
     "token": "string"
    }
    ```
    
- **`GET /user/src/whois.php?token={token}`**
    
    **Scopo**: **Verifica identità da token di sessione.**
    
    **Output**:
    
    ```php
    {
     "user_id": "integer",
     "username": "string",
     "level": "integer",
     "credits": "integer"
    }
    ```
    

---

## Game APIs

- **`GET /maps/api.php/maps/slots`**
    
    **Scopo**: **Lista delle mappe disponibili.**
    
    **Output**:
    
    ```php
    [
     {
       "id": "integer",
       "name": "string",
       "cover_path": "string"
     }
    ]
    ```
    
- **`GET /maps/api.php/tanks/slots`**
    
    **Scopo**: **Lista tank disponibili.**
    
    **Output**:
    
    ```php
    [
     {
       "id": "integer",
       "name": "string",
       "cover_path": "string"
     }
    ]
    ```
    
- **`GET /maps/api.php/maps/generate?id={mapId}`**
    
    **Scopo**: **Generazione procedurale mappa.**
    
    **Output**:
    
    ```php
    {
     "mapData": "string",
     "biome": "string",
     "seed": "integer"
    }
    ```
    

---

## Multiplayer APIs

- **`POST /user/src/multiplayer.php/multiplayer/create`**
    
    **Scopo**: **Creazione nuova partita.**
    
    **Input**:
    
    ```php
    {
     "token": "string",
     "map_id": "integer",
     "max_players": "integer"
    }
    ```
    
    **Output**:
    
    ```php
    {
     "match_id": "integer",
     "status": "waiting"
    }
    ```
    
- **`GET /user/src/multiplayer.php/multiplayer/list`**
    
    **Scopo**: **Lista partite disponibili.**
    
    **Output**:
    
    ```php
    [
     {
       "match_id": "integer",
       "map_id": "integer",
       "current_players": "integer",
       "max_players": "integer",
       "status": "string"
     }
    ]
    ```
    
- **`POST /user/src/multiplayer.php/multiplayer/join`**
    
    **Scopo**: **Partecipazione a partita esistente.**
    
    **Input**:
    
    ```php
    {
     "token": "string",
     "match_id": "integer"
    }
    ```
    
- **`POST /user/src/multiplayer.php/multiplayer/invite`**
    
    **Scopo**: **Invito amico a partita.**
    
    **Input**:
    
    ```php
    {
     "token": "string",
     "username": "string",
     "match_id": "integer"
    }
    ```
    

---

## Statistics APIs

- **`GET /user/src/records.php/records/leaderboard`**
    
    **Scopo**: **Classifica top 10 giocatori.**
    
    **Output**:
    
    ```php
    [
     {
       "username": "string",
       "total_points": "integer",
       "total_kills": "integer",
       "total_deaths": "integer",
       "matches_played": "integer"
     }
    ]
    ```
    
- **`GET /user/src/records.php/records/user?token={token}`**
    
    **Scopo**: **Statistiche utente specifico.**
    
    **Output**:
    
    ```php
    {
     "level": "integer",
     "total_points": "integer",
     "experience": "integer",
     "total_kills": "integer",
     "total_deaths": "integer",
     "matches_played": "integer",
     "credits": "integer"
    }
    ```
    
- **`POST /user/src/records.php/records/save`**
    
    **Scopo**: **Salvataggio risultato partita.**
    
    **Input**:
    
    ```
    {
     "token": "string",
     "score": "integer",
     "kills": "integer",
     "deaths": "integer",
     "duration": "integer",
     "map_id": "integer",
     "tank_id": "integer"
    }
    ```
    

---

## 6. Vincoli

- **Tecnologici**:
    - Backend: Node.js o Python (Django/Flask).
    - Database: PostgreSQL o MongoDB.
    - Container: Docker (minimo v20).
- **Dimensionali**:
    - Mappa: massimo 10000×10000 mattonelle.
- **Organizzativi**:
    - Budget definito: NOME_PROGETTO_FASE1.
    - Timeline: 6 mesi sviluppo + 1 mese test.
- **Normativi**:
    - GDPR per gestione utenti UE.

---

# 8. Modello dei Dati

## Entità Principali

### Users

**Descrizione**: **Anagrafica utenti del sistema**

```sql
Users (
 id: INT PRIMARY KEY AUTO_INCREMENT,
 username: VARCHAR(255) UNIQUE NOT NULL,
 password: VARCHAR(255) NOT NULL
)
```

### Tokens

**Descrizione**: Gestione sessioni e autenticazione

```sql
Tokens (
 token: VARCHAR(255) PRIMARY KEY,
 user_id: INT FOREIGN KEY → Users.id,
 expiration: TIMESTAMP NOT NULL
)
```

### **UserStats**

**Descrizione**: Statistiche e progressione utenti

```sql
UserStats (
 user_id: INT PRIMARY KEY FOREIGN KEY → Users.id,
 level: INT DEFAULT 1,
 total_points: INT DEFAULT 0,
 experience: INT DEFAULT 0,
 total_kills: INT DEFAULT 0,
 total_deaths: INT DEFAULT 0,
 matches_played: INT DEFAULT 0,
 total_playtime: INT DEFAULT 0,
 credits: INT DEFAULT 500
)
```

### **GameMaps**

**Descrizione**: Definizione mappe di gioco

```sql
game_maps (
 id: INT PRIMARY KEY AUTO_INCREMENT,
 name: VARCHAR(255) NOT NULL,
 description: TEXT,
 biome: VARCHAR(100) DEFAULT 'mixed',
 seed: INT,
 cover_path: TEXT
)
```

### **TankWeapons**

**Descrizione**: Catalogo armi disponibili

```sql
TankWeapons (
 id: INT PRIMARY KEY AUTO_INCREMENT,
 name: VARCHAR(255) NOT NULL,
 type: ENUM('cannon', 'machine_gun', 'heavy_artillery'),
 damage: INTEGER NOT NULL,
 range_distance: INTEGER NOT NULL,
 reload_time: INTEGER NOT NULL,
 price: INTEGER DEFAULT 0,
 description: TEXT
)
```

### **GameMatches**

**Descrizione**: Gestione partite multiplayer

```sql
GameMatches (
 id: INT PRIMARY KEY AUTO_INCREMENT,
 created_by_user_id: INT FOREIGN KEY → Users.id,
 map_id: INT FOREIGN KEY → game_maps.id,
 max_players: INT DEFAULT 2,
 status: ENUM('waiting', 'in_progress', 'completed', 'cancelled'),
 created_at: TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

## Relazioni Principali

### **User ↔ Statistics (1:1)**

Ogni utente ha esattamente un record di statistiche

### **User ↔ Tokens (1:N)**

Un utente può avere token multipli (gestione sessioni multiple)

### **User ↔ GameRecords (1:N)**

Un utente può avere molti record di partite giocate

### **GameMatches ↔ GameMatchPlayers (1:N)**

Una partita può avere 2-4 giocatori partecipanti

### **User ↔ UserTankCustomizations (1:N)**

Un utente può avere multiple configurazioni tank salvate

![image.png](image.png)

## 7. Casi d'Uso Principali (Testuali)

### Caso d'Uso 1: Registrazione e Login

**Attore primario**: Giocatore

**Descrizione**: Il giocatore crea un account o effettua il login, quindi accede alla dashboard.

### Caso d'Uso 2: Creazione Partita\ n**Attore primario**: Giocatore

**Descrizione**: Il giocatore invita amici, seleziona la mappa e avvia la partita.

### Caso d'Uso 3: Personalizzazione Carro

**Attore primario**: Giocatore

**Descrizione**: Il giocatore spende punti per acquistare e montare nuovi cannoni o mitragliatrici.

### Caso d'Uso 4: Upload Mappa Manuale

**Attore primario**: Giocatore/Admin

**Descrizione**: Dalla GUI di creazione mappe, il contenuto viene caricato, validato e reso disponibile.

### Caso d'Uso 5 (Premiale): Generazione Mappa Automatica

**Attore primario**: Giocatore

**Descrizione**: Il sistema genera in modo randomico una mappa con ostacoli e postazioni.

---

*Fine del documento di analisi dei requisiti.*

---

---

---

Analisi generata da Github Copilot:

# Requisiti implementati

## Requisiti minimi

1. **Docker per il lato server** - Docker Compose con MySQL + Apache PHP
2. **Gestione degli utenti** - Login/registrazione con token-based auth
3. **Tabella dei record** - Sistema di punteggi, livelli e classifiche
4. **Invito ad amici** - API per inviti multiplayer
5. **Collezione di 10 mappe** - 10 mappe predefinite + sistema di selezione
6. **Interfaccia creazione mappe** - API per mappe create dagli utenti
7. **Sistema punti/potenziamenti** - Livelli, esperienza e statistiche utente
8. **Multiplayer 2+ giocatori** - Sistema di partite con min 2, max 4 giocatori