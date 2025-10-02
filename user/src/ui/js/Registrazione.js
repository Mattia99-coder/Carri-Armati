// Funzioni per la gestione della UI
$(document).ready(function() {
    $('#registrationForm').on('submit', function(e) {
        e.preventDefault();
        
        // Verifica che le password coincidano
        if($('#password').val() !== $('#confirmPassword').val()) {
            alert("Le password non coincidono!");
            return;
        }

        $.ajax({
            url: '/user/src/register.php',
            type: 'POST',
            data: JSON.stringify({
                name: $('#username').val(),
                password: $('#password').val()
            }),
            contentType: 'application/json',
            success: function(response) {
                console.log('Registrazione riuscita:', response);
                alert("Registrazione completata con successo!");
                // Salva il token se presente
                if (response.token) {
                    localStorage.setItem('userToken', response.token);
                }
                // Aspetta un momento prima del redirect
                setTimeout(function() {
                    window.location.href = '/lobby/index.html';
                }, 1000);
            },
            error: function(xhr) {
                let errorMessage = "Errore durante la registrazione";
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

