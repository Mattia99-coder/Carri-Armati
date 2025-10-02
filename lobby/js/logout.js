$(document).ready(function() {
    $('#logout-btn').click(function() {
        const token = localStorage.getItem('userToken');
        
        $.ajax({
            url: '../../user/php/logout.php',
            type: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token
            },
            success: function() {
                localStorage.removeItem('userToken');
                window.location.href = '../../user/ui/html/Login.html';
            },
            error: function() {
                alert('Errore durante il logout');
            }
        });
    });
});
