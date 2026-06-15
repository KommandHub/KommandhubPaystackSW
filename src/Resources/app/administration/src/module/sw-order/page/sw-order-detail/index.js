import template from './sw-order-detail.html.twig';

const { Criteria } = Shopware.Data;

Shopware.Component.override('sw-order-detail', {
    template,

    inject: [
        'repositoryFactory',
    ],

    props: {
        orderId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            isLoading: true,
            order: null,
        };
    },

    computed: {
        /**
         * Extend the default order criteria and load
         * payment method associations for transaction checks.
         *
         * @returns {Criteria}
         */
        orderCriteria() {
            const criteria = this.$super('orderCriteria');

            criteria.addAssociation('transactions.paymentMethod');
            criteria.addAssociation('transactions.stateMachineState');
            criteria.addAssociation('transactions.captures.refunds.stateMachineState');
            criteria.addAssociation('transactions.captures.stateMachineState');

            return criteria;
        },

        /**
         * Order repository.
         *
         * @returns {Repository}
         */
        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        /**
         * Determines whether the order contains
         * a Paystack transaction.
         *
         * @returns {boolean}
         */
        isPaystackPayment() {
            if (!this.order?.transactions?.length) {
                return false;
            }

            return this.order.transactions.some(
                (transaction) =>
                    transaction.paymentMethod?.handlerIdentifier ===
                    'Kommandhub\\PaystackSW\\Checkout\\Payment\\PaystackPaymentHandler'
            );
        },
    },

    watch: {
        orderId() {
            this.fetchOrder();
        },
    },

    created() {
        this.fetchOrder();
    },

    methods: {
        /**
         * Load the order including transaction and payment method associations.
         *
         * @returns {Promise<void>}
         */
        async fetchOrder() {
            if (!this.orderId) {
                return;
            }

            this.isLoading = true;

            try {
                this.order = await this.orderRepository.get(
                    this.orderId,
                    Shopware.Context.api,
                    this.orderCriteria
                );
            } catch (error) {
                console.error(
                    '[Paystack] Failed to load order details.',
                    error
                );
            } finally {
                this.isLoading = false;
            }
        },
    },
});