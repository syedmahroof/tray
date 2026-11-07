/**
 * Stop the mouse wheel from changing a focused number field. Scrolling over
 * one takes focus off it instead, so the value stays put and the page scrolls.
 */
export function initializeNumberInputWheelGuard(): void {
    // Server-side rendering has no DOM to guard.
    if (typeof document === 'undefined') {
        return;
    }

    document.addEventListener(
        'wheel',
        (event) => {
            const target = event.target;

            if (
                target instanceof HTMLInputElement &&
                target.type === 'number' &&
                target === document.activeElement
            ) {
                target.blur();
            }
        },
        { passive: true },
    );
}
