export function searchableSelect(options, selected, emptyValue = null) {
    const normalize = value => String(value ?? '').toLocaleLowerCase('uz').normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '').replace(/[‘’ʻʼ`']/g, '').replace(/\s+/g, ' ').trim();
    return {
        options: [...options].sort((a, b) => a.label.localeCompare(b.label, 'uz', {numeric: true})),
        selected, query: '', open: false, active: 0,
        get currentLabel() { return this.options.find(item => String(item.value) === String(this.selected))?.label || ''; },
        get matches() {
            const terms = normalize(this.query).split(' ').filter(Boolean);
            return this.options.filter(item => {
                const text = normalize(item.search || item.label);
                return terms.every(term => text.includes(term) || (/^[\d+()-]+$/.test(term) && /\d/.test(term) && text.replace(/[\s()+-]/g, '').includes(term.replace(/[()+-]/g, ''))));
            });
        },
        get results() { return this.matches.slice(0, 20); },
        choose(item) { this.selected = String(item.value); this.query = ''; this.open = false; this.active = 0; },
        typeQuery(value) { this.query = value; this.selected = emptyValue; this.open = true; this.active = 0; },
        move(direction) {
            this.open = true; this.active = Math.max(0, Math.min(this.results.length - 1, this.active + direction));
            this.$nextTick?.(() => this.$root.querySelectorAll('[role="option"]')[this.active]?.scrollIntoView({block: 'nearest'}));
        },
        clear() { this.selected = emptyValue; this.query = ''; this.open = true; this.active = 0; },
    };
}
