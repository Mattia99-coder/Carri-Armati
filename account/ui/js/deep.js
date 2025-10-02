document.addEventListener('DOMContentLoaded', function() {
    // Dropdown amici
    const dropdownToggle = document.querySelector('.dropdown-toggle');
    const friendsList = document.querySelector('.friends-list');
    
    dropdownToggle.addEventListener('click', function() {
        friendsList.classList.toggle('show');
        this.classList.toggle('rotate');
    });

    // Caroselli
    const carousels = document.querySelectorAll('.carousel');
    
    carousels.forEach(carousel => {
        const prevBtn = carousel.querySelector('.prev');
        const nextBtn = carousel.querySelector('.next');
        const itemsContainer = carousel.querySelector('.items-container');
        const itemWidth = carousel.querySelector('.item-card').offsetWidth + 15; // Larghezza item + gap
        
        prevBtn.addEventListener('click', () => {
            itemsContainer.scrollBy({ left: -itemWidth, behavior: 'smooth' });
        });
        
        nextBtn.addEventListener('click', () => {
            itemsContainer.scrollBy({ left: itemWidth, behavior: 'smooth' });
        });
    });
});