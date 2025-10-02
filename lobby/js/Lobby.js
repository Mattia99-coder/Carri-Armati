// Utility functions for DOM manipulation
function mostraSezione(elemento, nome) {
    if (!elemento) {
        console.error(`Elemento ${nome} non trovato`);
        return;
    }
    console.log(`Mostrando sezione ${nome}`);
    elemento.style.display = "flex";
}

function nascondiSezione(elemento, nome) {
    if (!elemento) {
        console.error(`Elemento ${nome} non trovato`);
        return;
    }
    console.log(`Nascondendo sezione ${nome}`);
    elemento.style.display = "none";
}

// Main initialization
document.addEventListener("DOMContentLoaded", () => {
    console.log("DOM Loaded, initializing...");
    
    // Get DOM elements
    const elements = {
        partita: document.getElementById("partita"),
        amici: document.getElementById("lista_amici"),
        hero: document.getElementById("hero"),
        uniscitiBtn: document.getElementById("unisciti_button"),
        chiudiBtn: document.getElementById("chiudi_button"),
        chiudiAmiciBtn: document.getElementById("chiudi_amici_button"),
        invitaBtns: Array.from(document.getElementsByClassName("invita_button")),
        mapBtn: document.getElementById("map_button"),
        mappe: document.getElementById("lista-mappe"),
        chiudiMappeBtn: document.getElementById("chiudi_mappe_button"),
        myMaps: document.getElementById("mie-mappe"),
        myMapsBtn: document.getElementById("my-maps-button"),
        chiudiMyMapsBtn: document.getElementById("chiudi_mie_mappe_button"),
        nomeMappa: document.getElementById("nome-mappa"),
        mapImages: document.querySelectorAll('.map-image'),
        backBtn: document.getElementById("back_button"),
    };

    // Log found elements
    Object.entries(elements).forEach(([key, value]) => {
        console.log(`${key}: ${value ? "trovato" : "non trovato"}`);
    });

    // Validate essential elements
    const missingElements = Object.entries(elements)
        .filter(([key, value]) => key !== 'invitaBtns' && !value)
        .map(([key]) => key);

    if (missingElements.length > 0) {
        console.error(`Elementi mancanti: ${missingElements.join(', ')}`);
        return;
    }

    // Event listeners
    elements.uniscitiBtn?.addEventListener("click", () => {
        console.log("Click su unisciti");
        if (elements.amici.style.display === "flex" || elements.mappe.style.display === "flex" || elements.myMaps.style.display === "flex") {
            console.error("Non è possibile unirsi a una partita mentre la lista amici è aperta.");
            return;
        } else {
            mostraSezione(elements.partita, "partita");
            nascondiSezione(elements.hero, "hero");
        }
    });

    elements.chiudiBtn?.addEventListener("click", () => {
        console.log("Click su chiudi partita");
        nascondiSezione(elements.partita, "partita");
        mostraSezione(elements.hero, "hero");
    });

    elements.chiudiAmiciBtn?.addEventListener("click", () => {
        console.log("Click su chiudi amici");
        nascondiSezione(elements.amici, "amici");
        mostraSezione(elements.hero, "hero");
    });

    elements.invitaBtns.forEach((btn, index) => {
        btn.addEventListener("click", () => {
            console.log(`Click su invita button ${index}`);
            mostraSezione(elements.amici, "amici");
            nascondiSezione(elements.hero, "hero");
        });
    });

    elements.mapBtn?.addEventListener("click", () => {
        console.log("Click su unisciti");
        if (elements.partita.style.display === "flex" || elements.myMaps.style.display === "flex" || elements.amici.style.display === "flex") {
            console.error("Non è possibile unirsi a una partita mentre la lista delle partite è aperta.");
            return;
        } else {
            mostraSezione(elements.mappe, "mappe");
            nascondiSezione(elements.hero, "hero");
        }
    });

    elements.chiudiMappeBtn?.addEventListener("click", () => {
        console.log("Click su chiudi amici");
        nascondiSezione(elements.mappe, "mappe");
        mostraSezione(elements.hero, "hero");
    });

    elements.myMapsBtn?.addEventListener("click", () => {
        console.log("Click su unisciti");
        if (elements.myMaps.style.display === "flex") {
            console.error("Non è possibile unirsi a una partita mentre la lista delle partite è aperta.");
            return;
        } else {
            mostraSezione(elements.myMaps, "le mie mappe");
            nascondiSezione(elements.hero, "hero");
            nascondiSezione(elements.mappe, "mappe");

        }
    });

    elements.backBtn?.addEventListener("click", () => {
        console.log("Click su indietro");
        if (elements.myMaps.style.display === "flex") {
            nascondiSezione(elements.myMaps, "le mie mappe");
            mostraSezione(elements.mappe, "mappe");

        }
    });

    elements.chiudiMyMapsBtn?.addEventListener("click", () => {
        console.log("Click su chiudi amici");
        nascondiSezione(elements.myMaps, "le mie mappe");
        mostraSezione(elements.hero, "hero");
    });

    // Gestione click sulle immagini delle mappe
    elements.mapImages.forEach(img => {
        img.addEventListener('mouseover', () => {
            if (elements.nomeMappa) {
                elements.nomeMappa.textContent = img.alt;
            }
            
            // Se questa immagine non ha il bordo verde, metti il bordo bianco
            if (img.style.border !== "4px solid green") {
                img.style.border = "2px solid white";
            }
        });

        img.addEventListener('mouseout', () => {
            // Rimuovi il bordo bianco solo se l'immagine non ha il bordo verde
            if (img.style.border !== "4px solid green") {
                img.style.border = "none";
            }
        });

        img.addEventListener('click', () => {
            const hasGreenBorder = img.style.border === "4px solid green";
            elements.mapImages.forEach(i => i.style.border = "none");
            if (!hasGreenBorder) {
                img.style.border = "4px solid green";
            }
            console.log(`Immagine della mappa cliccata: ${img.src}`);
        });
    });

    // Set initial state
    nascondiSezione(elements.partita, "partita");
    nascondiSezione(elements.amici, "amici");
    nascondiSezione(elements.mappe, "mappe");
    nascondiSezione(elements.myMaps, "le mie mappe");


});