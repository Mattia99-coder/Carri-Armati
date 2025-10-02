<?php
session_start();

// Funzione per verificare il token
function verifyToken($token) {
    if (empty($token)) return false;
    
    $db_host = getenv('DATABASE_HOST') ?: 'db';
    $db_name = 'tank-game';
    $db_user = getenv('DATABASE_USER') ?: 'game_user';
    $db_pass = getenv('DATABASE_PASSWORD') ?: 'secret';
    
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("
            SELECT u.id, u.username 
            FROM Tokens t 
            JOIN Users u ON t.user_id = u.id 
            WHERE t.token = ? AND t.expiration > NOW()
        ");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

// Controlla autenticazione: prima sessione PHP, poi token
$user = null;
$auth_method = '';

if (isset($_SESSION['user_id'])) {
    // Utente autenticato tramite sessione PHP
    $user = [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username']
    ];
    $auth_method = 'session';
} else {
    // Verifica se c'è un token nei parametri URL o cookie
    $token = $_GET['token'] ?? $_COOKIE['userToken'] ?? null;
    
    if ($token) {
        $tokenUser = verifyToken($token);
        if ($tokenUser) {
            $user = $tokenUser;
            $auth_method = 'token';
            
            // Opzionalmente, crea una sessione PHP per questo accesso
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
        }
    }
}

// Se nessuna autenticazione valida, reindirizza al login
if (!$user) {
    header('Location: ../user/src/login.php');
    exit;
}

// Include il file HTML del negozio
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Negozio - Tank Game</title>
    <link rel="stylesheet" href="css/negozio-style.css">
</head>
<body>
    <div id="negozio-container">
        <div class="header">
            <h1>🛒 Negozio Tank Game</h1>
            <div class="user-info">
                <span>Benvenuto, <?php echo htmlspecialchars($user['username']); ?>!</span>
                <span id="user-credits">Crediti: <span id="credits-amount">0</span></span>
                <small style="color: #666;">(Auth: <?php echo $auth_method; ?>)</small>
            </div>
        </div>
        
        <div class="shop-sections">
            <!-- Sezione Personalizzazione Tank -->
            <div class="shop-section" id="customization-section">
                <h2>🎨 Personalizza i tuoi Tank</h2>
                <p style="text-align: center; margin-bottom: 20px; color: #e0e0e0;">
                    Configura e personalizza i tuoi tank con armi, potenziamenti e livree personalizzate.
                </p>
                <div id="customization-area" class="customization-area">
                    <!-- Area di personalizzazione tank -->
                    <div id="my-tanks-list" class="items-grid">
                        <!-- I tank dell'utente saranno caricati via JavaScript -->
                    </div>
                </div>
            </div>
            
            <!-- Sezione Tank -->
            <div class="shop-section">
                <h2>🚗 Tank Disponibili</h2>
                <div id="tanks-grid" class="items-grid">
                    <!-- I tank saranno caricati via JavaScript -->
                </div>
            </div>
            
            <!-- Sezione Armi per Tank -->
            <div class="shop-section">
                <h2>⚔️ Armi per Tank</h2>
                <p style="text-align: center; margin-bottom: 20px; color: #e0e0e0;">
                    Personalizza i tuoi tank con cannoni e mitragliatrici. Ogni tank può montare fino a 3 armi.
                </p>
                <div id="weapons-grid" class="items-grid">
                    <!-- Le armi saranno caricate via JavaScript -->
                </div>
            </div>
        </div>
        
        <div class="navigation">
            <a href="../lobby/index.html" class="btn btn-secondary">← Torna alla Lobby</a>
            <a href="../user/src/logout.php" class="btn btn-logout">Logout</a>
        </div>
    </div>
    
    <script src="js/Negozio.js"></script>
    <script>
        // Inizializza il negozio
        document.addEventListener('DOMContentLoaded', function() {
            initShop();
        });
    </script>
</body>
</html>