// Keep dashboard carousel navigation consistent with the available content.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.learner-dashboard .stream-carousel-wrap').forEach(wrap => {
        const carousel = wrap.querySelector('.stream-carousel');
        const previous = wrap.querySelector('.prev');
        const next = wrap.querySelector('.next');
        if (!carousel || !previous || !next) return;
        const update = () => {
            const maximum = carousel.scrollWidth - carousel.clientWidth;
            previous.hidden = next.hidden = maximum <= 2;
            previous.disabled = carousel.scrollLeft <= 2;
            next.disabled = carousel.scrollLeft >= maximum - 2;
        };
        carousel.addEventListener('scroll', update, { passive: true });
        if ('ResizeObserver' in window) {
            new ResizeObserver(update).observe(carousel);
        } else {
            window.addEventListener('resize', update);
        }
        update();
    });
});