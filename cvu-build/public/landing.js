const header = document.querySelector('.site-header');
const links = [...document.querySelectorAll('[data-section-link]')];
const sections = links.map(link => document.getElementById(link.dataset.sectionLink));

function markSection() {
    const offset = header.getBoundingClientRect().height + 20;
    const atBottom = window.scrollY > 0 &&
        window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 2;
    const current = atBottom
        ? sections.at(-1)
        : sections.findLast(section => section.getBoundingClientRect().top <= offset) ?? sections[0];

    for (const link of links) {
        if (link.dataset.sectionLink === current.id) {
            link.setAttribute('aria-current', 'location');
        } else {
            link.removeAttribute('aria-current');
        }
    }
}

function updateHeader() {
    document.documentElement.style.setProperty('--landing-header-height', header.getBoundingClientRect().height + 'px');
    markSection();
}

new ResizeObserver(updateHeader).observe(header);
window.addEventListener('scroll', markSection, { passive: true });
window.addEventListener('resize', updateHeader);
updateHeader();
