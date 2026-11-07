/**
 * Stop the mouse wheel from changing a focused number field. Scrolling over
 * one takes focus off it instead, so the value stays put and the page scrolls.
 */
export function initializeNumberInputWheelGuard(): void {
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
