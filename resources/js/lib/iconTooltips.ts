const CONTROL_SELECTOR = 'button, a[href], [role="button"]';
const SHOW_DELAY_MS = 250;
const GAP_PX = 6;
const EDGE_PX = 8;

/** Text a person can see in an element, leaving out screen-reader-only text. */
const visibleText = (element: Element): string => {
    let text = '';

    element.childNodes.forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) {
            text += node.textContent ?? '';
        } else if (
            node instanceof Element &&
            !node.classList.contains('sr-only')
        ) {
            text += visibleText(node);
        }
    });

    return text.trim();
};

/**
 * The tooltip text for an icon-only control: its aria-label, title or
 * screen-reader text. Controls that show text, or that already carry their
 * own tooltip, get none.
 */
const tooltipFor = (control: HTMLElement): string | null => {
    if (
        control.closest('[data-no-tooltip]') ||
        control.dataset.slot === 'tooltip-trigger' ||
        control.dataset.state === 'delayed-open' ||
        visibleText(control) !== '' ||
        !control.querySelector('svg')
    ) {
        return null;
    }

    // Keep the browser's own title tooltip from appearing alongside ours.
    if (control.title) {
        control.dataset.tooltip = control.title;
        control.removeAttribute('title');
    }

    const screenReaderText = control.querySelector('.sr-only')?.textContent;

    return (
        (
            control.dataset.tooltip ??
            control.getAttribute('aria-label') ??
            screenReaderText ??
            null
        )?.trim() || null
    );
};

/**
 * Show a tooltip over every icon-only button and link, named by its
 * aria-label (or title / screen-reader text), on hover and keyboard focus.
 * One delegated listener covers the whole app, so no button needs wiring.
 */
export function initializeIconTooltips(): void {
    // Server-side rendering has no DOM to decorate.
    if (typeof document === 'undefined') {
        return;
    }

    const tooltip = document.createElement('div');

    tooltip.setAttribute('role', 'tooltip');
    tooltip.className =
        'pointer-events-none fixed z-[100] hidden w-max max-w-64 rounded-md bg-foreground px-2.5 py-1 text-xs font-medium text-background shadow-md animate-in fade-in-0 zoom-in-95';
    document.body.appendChild(tooltip);

    let activeControl: HTMLElement | null = null;
    let showTimer: ReturnType<typeof setTimeout> | null = null;

    const hide = () => {
        if (showTimer) {
            clearTimeout(showTimer);
            showTimer = null;
        }

        activeControl = null;
        tooltip.classList.add('hidden');
    };

    const show = (control: HTMLElement, text: string) => {
        tooltip.textContent = text;
        tooltip.classList.remove('hidden');

        const target = control.getBoundingClientRect();
        const box = tooltip.getBoundingClientRect();
        const above = target.top - box.height - GAP_PX;
        const top = above >= EDGE_PX ? above : target.bottom + GAP_PX;
        const left = Math.min(
            Math.max(target.left + target.width / 2 - box.width / 2, EDGE_PX),
            window.innerWidth - box.width - EDGE_PX,
        );

        tooltip.style.top = `${top}px`;
        tooltip.style.left = `${left}px`;
    };

    const activate = (event: Event, delay: number) => {
        const control =
            event.target instanceof Element
                ? event.target.closest<HTMLElement>(CONTROL_SELECTOR)
                : null;

        if (control === activeControl) {
            return;
        }

        hide();

        const text = control ? tooltipFor(control) : null;

        if (!control || !text) {
            return;
        }

        activeControl = control;
        showTimer = setTimeout(() => {
            if (activeControl === control && control.isConnected) {
                show(control, text);
            }
        }, delay);
    };

    document.addEventListener('pointerover', (event) => {
        if (event.pointerType !== 'touch') {
            activate(event, SHOW_DELAY_MS);
        }
    });

    document.addEventListener('pointerout', (event) => {
        const next = event.relatedTarget;

        if (
            activeControl &&
            !(next instanceof Node && activeControl.contains(next))
        ) {
            hide();
        }
    });

    document.addEventListener('focusin', (event) => {
        if (
            event.target instanceof Element &&
            event.target.matches(':focus-visible')
        ) {
            activate(event, 0);
        }
    });

    document.addEventListener('focusout', hide);
    document.addEventListener('pointerdown', hide, true);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hide();
        }
    });
    window.addEventListener('scroll', hide, true);
    document.addEventListener('inertia:start', hide);
}
