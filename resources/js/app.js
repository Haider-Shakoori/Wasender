import './bootstrap';
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.store('modal', {
    name: null,
    open(name) { this.name = name; },
    close() { this.name = null; },
});
Alpine.data('theme', () => ({
    mode: document.documentElement.dataset.theme || 'system',
    set(value) {
        if (!['light', 'dark', 'system'].includes(value)) value = 'system';
        this.mode = value;
        localStorage.setItem('relay-theme', value);
        const dark = value === 'dark' || (value === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.dataset.theme = value;
    },
}));
Alpine.start();
