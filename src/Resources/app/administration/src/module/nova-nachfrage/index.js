import './page/nova-nachfrage-index';

/**
 * Einstellungen → Erweiterungen → Nova Nachfrage: Übersicht (zuletzt verarbeitet, demnächst fällig),
 * Widersprüche (nur gekürzter Hash) und manuelles Eintragen eines Widerspruchs.
 */
Shopware.Module.register('nova-nachfrage', {
    type: 'plugin',
    name: 'nova-nachfrage',
    title: 'nova-nachfrage.general.title',
    description: 'nova-nachfrage.general.description',
    color: '#6c4ceb',
    icon: 'regular-envelope',

    routes: {
        index: {
            component: 'nova-nachfrage-index',
            path: 'index',
            meta: { parentPath: 'sw.settings.index.plugins', privilege: 'system_config:read' },
        },
    },

    settingsItem: [{
        group: 'plugins',
        to: 'nova.nachfrage.index',
        icon: 'regular-envelope',
        label: 'nova-nachfrage.general.title',
        privilege: 'system_config:read',
    }],
});
