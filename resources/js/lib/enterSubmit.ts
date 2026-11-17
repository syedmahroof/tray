/**
 * Stop the Enter key in a text field from saving the form it sits in, on every
 * form in the app, so a form is only ever saved by its button.
 *
 * Textareas keep their new lines, and anything that already handled Enter
 * itself (a picker choosing an option) is left alone. A form that should
 * still submit on Enter opts back in with `data-enter-submits`.
 */
export function initializeEnterSubmitGuard(): void {
    // Server-side rendering has no DOM to guard.
    if (typeof document === 'undefined') {
        return;
    }

    document.addEventListener('keydown', (event) => {
        if (
            event.key !== 'Enter' ||
            event.defaultPrevented ||
            event.isComposing
        ) {
            return;
        }

        const field = event.target;

        if (
            field instanceof HTMLInputElement &&
            field.form &&
            field.type !== 'search' &&
            !field.form.hasAttribute('data-enter-submits')
        ) {
            event.preventDefault();
        }
    });
}
