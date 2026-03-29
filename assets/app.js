import './bootstrap.js';

/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import "bootstrap/dist/css/bootstrap.min.css";
import "bootstrap-icons/font/bootstrap-icons.css"

import './js/script.js';

import * as bootstrap from "bootstrap";
document.addEventListener('turbo:load', function() {
    const toastElList = document.querySelectorAll('.toast');
    toastElList.forEach(toastEl => {
        const toastInstance = new bootstrap.Toast(toastEl, { delay: 5000 }); // Fermeture auto en 5s
        toastInstance.show();


    });
});

