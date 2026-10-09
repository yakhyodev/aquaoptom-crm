<script>
    (() => {
        const key = 'aquaoptom-theme';
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        let preference;
        try { preference = localStorage.getItem(key); } catch (_) {}
        const valid = value => value === 'dark' || value === 'light';
        const syncControls = () => {
            const dark = document.documentElement.dataset.theme === 'dark';
            document.querySelectorAll('[data-theme-toggle]').forEach(button => {
                button.setAttribute('aria-pressed', String(dark));
                button.setAttribute('aria-label', dark ? 'Kunduzgi rejimga o‘tish' : 'Tungi rejimga o‘tish');
                button.title = dark ? 'Kunduzgi rejimga o‘tish' : 'Tungi rejimga o‘tish';
                button.querySelector('[data-theme-icon]').textContent = dark ? '☾' : '☀';
                button.querySelector('[data-theme-label]').textContent = dark ? 'Tungi' : 'Kunduzgi';
            });
        };
        const apply = theme => {
            document.documentElement.dataset.theme = theme;
            document.documentElement.style.colorScheme = theme;
            document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme === 'dark' ? '#0b1220' : '#f4f6fa');
            syncControls();
        };
        apply(valid(preference) ? preference : (media.matches ? 'dark' : 'light'));
        document.addEventListener('DOMContentLoaded', syncControls);
        document.addEventListener('livewire:navigated', syncControls);
        document.addEventListener('click', event => {
            if (!event.target.closest('[data-theme-toggle]')) { return; }
            preference = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            try { localStorage.setItem(key, preference); } catch (_) {}
            apply(preference);
        });
        window.addEventListener('storage', event => {
            if (event.key !== key) { return; }
            preference = event.newValue;
            apply(valid(preference) ? preference : (media.matches ? 'dark' : 'light'));
        });
        media.addEventListener('change', event => {
            if (!valid(preference)) { apply(event.matches ? 'dark' : 'light'); }
        });
    })();
</script>
