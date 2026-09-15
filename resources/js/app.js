import './dashboard-assistant';
import './sidebar-navigation-state';
import './pos-payment-calculator';
import './offline-pos';
import './keyboard-product-lines';

if ('serviceWorker' in navigator && window.location.protocol !== 'file:') {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}


window.navigationSearch = (groups) => ({
    open: false,
    query: '',
    items: groups.flatMap((group) => group.items.map((item) => ({ ...item, group: group.label }))),
    get results() {
        const needle = this.query.trim().toLocaleLowerCase();
        return this.items.filter((item) => !needle || `${item.label} ${item.group}`.toLocaleLowerCase().includes(needle)).slice(0, 12);
    },
});
