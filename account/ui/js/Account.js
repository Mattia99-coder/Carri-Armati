function mostraPartite(hero, partita) {
    if (hero && partita) {
        hero.style.display = "none";
        partita.style.display = "block";
    } else {
        console.error("Elementi 'hero' o 'partita' non trovati.");
    }
}

function chiudiPartita(partita, hero) {
    if (partita && hero) {
        partita.style.display = "none";
        hero.style.display = "block";
    } else {
        console.error("Elementi 'partita' o 'hero' non trovati.");
    }
}

function mostraModifica(hero, modifica) {
    if (hero && modifica) {
        hero.style.display = "none";
        modifica.style.display = "flex";
    } else {
        console.error("Elementi 'hero' o 'modifica' non trovati.");
    }
}

function chiudiModifica(modifica, hero) {
    if (modifica && hero) {
        modifica.style.display = "none";
        hero.style.display = "block";
    } else {
        console.error("Elementi 'modifica' o 'hero' non trovati.");
    }
}

document.addEventListener("DOMContentLoaded", function () {
    const hero = document.getElementById("hero");
    const chiudi = document.getElementById("chiudi_button");
    const modificaAccount = document.getElementById("modifica_account");
    const modifica = document.getElementById("modifica");
    const chiudiModificaBtn = document.getElementById("chiudi_modifica");

    

    if (modificaAccount) {
        modificaAccount.addEventListener("click", () => mostraModifica(hero, modifica));
    } else {
        console.error("Elemento 'modifica_account' non trovato.");
    }

    if (chiudiModificaBtn) {
        chiudiModificaBtn.addEventListener("click", () => chiudiModifica(modifica, hero));
    } else {
        console.error("Elemento 'chiudi_modifica' non trovato.");
    }
});