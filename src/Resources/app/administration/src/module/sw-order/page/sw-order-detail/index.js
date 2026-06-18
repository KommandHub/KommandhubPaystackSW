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
            paystackOrder: null,
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
            if (!this.paystackOrder?.transactions?.length) {
                return false;
            }

            return this.paystackOrder.transactions.some(
                (transaction) =>
                    transaction.paymentMethod?.handlerIdentifier ===
                    'Kommandhub\\PaystackSW\\Payment\\Infrastructure\\Shopware\\Handler\\PaystackPaymentHandler'
            );
        },
    },

    watch: {
        orderId() {
            void this.fetchOrder();
        },
    },

    created() {
        void this.fetchOrder();
    },

    methods: {
        /**
         * Load the order including transaction and payment method associations.
         *
         * @returns {Promise<void>}
         */
        async fetchOrder() {
            if (!this.orderId) {
                this.isLoading = false;
                return;
            }

            this.isLoading = true;

            try {
                this.paystackOrder = await this.orderRepository.get(
                    this.orderId,
                    Shopware.Context.api,
                    this.orderCriteria
                );
            } catch (error) {
                if (this.isAbortError(error)) {
                    return;
                }

                console.error(
                    '[Paystack] Failed to load order details.',
                    error
                );
            } finally {
                this.isLoading = false;
            }
        },

        isAbortError(error) {
            return error?.code === 'ECONNABORTED'
                || error?.name === 'AbortError'
                || error?.message === 'Request aborted';
        },
    },
});
