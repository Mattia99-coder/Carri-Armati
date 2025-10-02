// Funzioni per la gestione della UI

function login(username, password) {
    $.ajax({
        url: '/user/src/login.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            name: username,
            password: password
        }),
        success: function(response) {
            // Salva il token in localStorage
            if (response.token) {
                localStorage.setItem('userToken', response.token);
            } else {
                // Se la risposta è solo il token (per compatibilità)
                localStorage.setItem('userToken', response);
            }
            // Reindirizza l'utente alla pagina del gioco o dashboard
            window.location.href = '../../../../lobby/index.html';
        },
        error: function(xhr) {
            let errorMessage = "Errore durante il login";
            try {
                const errorData = JSON.parse(xhr.responseText);
                if (errorData.error) {
                    errorMessage = errorData.error;
                }
            } catch (e) {
                // Se non è JSON, usa il testo della risposta
                if (xhr.responseText) {
                    errorMessage = xhr.responseText;
                }
            }
            alert(errorMessage);
        }
    });
}

// Gestione del form di login
$(document).ready(function() {
    $('#loginForm').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: '/user/src/login.php',
            type: 'POST',
            data: JSON.stringify({
                name: $('#username').val(),
                password: $('#password').val()
            }),
            contentType: 'application/json',
            success: function(response) {
                // Salva il token ricevuto
                if (response.token) {
                    localStorage.setItem('userToken', response.token);
                } else {
                    // Se la risposta è solo il token (per compatibilità)
                    localStorage.setItem('userToken', response);
                }
                // Reindirizza alla pagina del gioco
                window.location.href = '../../../../lobby/index.html';
            },
            error: function(xhr) {
                let errorMessage = "Errore durante il login";
                try {
                    const errorData = JSON.parse(xhr.responseText);
                    if (errorData.error) {
                        errorMessage = errorData.error;
                    }
                } catch (e) {
                    // Se non è JSON, usa il testo della risposta
                    if (xhr.responseText) {
                        errorMessage = xhr.responseText;
                    }
                }
                alert(errorMessage);
            }
        });
    });
});

