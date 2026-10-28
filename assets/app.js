/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

// Confirmation dialogs: <button data-dialog-open="id"> opens <dialog id="id">
document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-dialog-open]');
    if (opener) {
        document.getElementById(opener.dataset.dialogOpen)?.showModal();

        return;
    }

    if (event.target.closest('[data-dialog-close]')) {
        event.target.closest('dialog')?.close();

        return;
    }

    // A click on the backdrop targets the dialog element itself
    if (event.target instanceof HTMLDialogElement) {
        event.target.close();
    }
});
