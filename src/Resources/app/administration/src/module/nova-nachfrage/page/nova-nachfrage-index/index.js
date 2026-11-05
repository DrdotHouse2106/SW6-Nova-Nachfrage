import template from './nova-nachfrage-index.html.twig';
import './nova-nachfrage-index.scss';

const { Component } = Shopware;

/**
 * Übersicht von Nova Nachfrage. Daten kommen aus /api/_action/nova-nachfrage/overview – ohne E-Mail-Adressen.
 */
Component.register('nova-nachfrage-index', {
    template,

    inject: ['loginService', 'acl'],

    data() {
        return {
            isLoading: false,
            failed: false,
            stats: null,
            recent: [],
            upcoming: [],
            optOuts: [],
            newEmail: '',
            saving: false,
            message: null,
        };
    },

    computed: {
        canAdd() {
            return this.acl.can('system_config:update');
        },
    },

    created() {
        this.load();
    },

    methods: {
        http() {
            return Shopware.Application.getContainer('init').httpClient;
        },

        headers() {
            return {
                Accept: 'application/json',
                Authorization: `Bearer ${this.loginService.getToken()}`,
            };
        },

        async load() {
            this.isLoading = true;
            this.failed = false;
            try {
                const { data } = await this.http().get('_action/nova-nachfrage/overview', { headers: this.headers() });
                this.stats = data.stats;
                this.recent = data.recent || [];
                this.upcoming = data.upcoming || [];
                this.optOuts = data.optOuts || [];
            } catch (e) {
                this.failed = true;
            } finally {
                this.isLoading = false;
            }
        },

        async addOptOut() {
            const email = (this.newEmail || '').trim();
            if (!email) {
                return;
            }
            this.saving = true;
            this.message = null;
            try {
                const { data } = await this.http().post('_action/nova-nachfrage/optout', { email }, { headers: this.headers() });
                this.message = {
                    variant: 'positive',
                    text: this.$tc(data.new ? 'nova-nachfrage.optOuts.added' : 'nova-nachfrage.optOuts.exists', 0, { hash: data.hash }),
                };
                this.newEmail = '';
                await this.load();
            } catch (e) {
                this.message = { variant: 'critical', text: this.$tc('nova-nachfrage.optOuts.invalid') };
            } finally {
                this.saving = false;
            }
        },

        date(value) {
            if (!value) {
                return '–';
            }
            // Datenbankwerte sind UTC ohne Zeitzone
            const iso = String(value).replace(' ', 'T');
            return Shopware.Utils.format.date(iso.endsWith('Z') ? iso : `${iso}Z`);
        },

        statusLabel(row) {
            const key = `nova-nachfrage.status.${row.action || row.status}`;
            const label = this.$tc(key);
            return label === key ? (row.action || row.status) : label;
        },

        reasonLabel(reason) {
            if (!reason) {
                return '';
            }
            const key = `nova-nachfrage.reason.${reason}`;
            const label = this.$tc(key);
            return label === key ? reason : label;
        },

        orderLink(orderId) {
            return { name: 'sw.order.detail', params: { id: orderId } };
        },
    },
});
