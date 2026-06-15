import template from './kommandhub-paystack-detail.html.twig';
import './kommandhub-paystack-detail.scss';
import icon from './icon.png';

const { Store, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Shopware.Component.register('kommandhub-paystack-detail', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    /** @private */
    metaInfo() {
        return {
            title: this.$t('kommandhub-paystack-detail.title'),
        };
    },

    inject: [
        'repositoryFactory',
        'paystackRefundService',
    ],

    props: {
        /**
         * The ID of the order to display Paystack details for.
         */
        orderId: {
            type: String,
            required: false,
            default: null,
        },
    },

    /** @private */
    data() {
        return {
            showRefundModal: false,
            refundAmount: null,
            refundCurrency: null,
            customerNote: null,
            merchantNote: null,
            isRefundLoading: false,
            isRefundSuccess: false,
            activeTransaction: null,
            activeCapture: null,
            showRefundsListModal: false,

            captures: [],
            refunds: [],
            isLoading: false,
        };
    },

    computed: {
        /**
         * Returns the order object from the swOrderDetail store.
         *
         * @returns {Object}
         */
        order: () => Store.get('swOrderDetail').order,

        /**
         * Checks if the order has any unsaved changes.
         *
         * @returns {Boolean}
         */
        orderChanges() {
            if (!this.order) {
                return false;
            }

            return this.orderRepository.hasChanges(this.order);
        },

        /**
         * Returns the repository for order transaction captures.
         *
         * @returns {Object}
         */
        transactionCaptureRepository() {
            return this.repositoryFactory.create('order_transaction_capture');
        },

        /**
         * Finds the Paystack transaction within the order's transactions.
         *
         * @returns {Object|null}
         */
        paystackTransaction() {
            if (!this.order || !this.order.transactions) {
                return null;
            }

            return this.order.transactions.find((transaction) => {
                return transaction.customFields
                    && transaction.customFields.paystack_reference;
            });
        },

        /**
         * Transforms the Paystack transaction into a data grid compatible format.
         *
         * @returns {Array<Object>}
         */
        paystackTransactionData() {
            if (!this.paystackTransaction) {
                return [];
            }

            return [{
                id: this.paystackTransaction.id,
                amount: this.paystackTransaction.customFields.paystack_amount,
                currency: this.paystackTransaction.customFields.paystack_currency,
                channel: this.paystackTransaction.customFields.paystack_payment_type,
                reference: this.paystackTransaction.customFields.paystack_reference,
                transactionId: this.paystackTransaction.customFields.paystack_transaction_id,
                fee: this.paystackTransaction.customFields.paystack_transaction_fee,
                verifiedAt: this.paystackTransaction.customFields.paystack_verified_at,
                state: this.paystackTransaction.stateMachineState,
            }];
        },

        /**
         * Column configuration for the Paystack transaction grid.
         *
         * @returns {Array<Object>}
         */
        paystackTransactionColumns() {
            return [
                {
                    property: 'amount',
                    label: 'kommandhub-paystack-detail.summary.chargedAmount',
                    primary: true,
                },
                {
                    property: 'reference',
                    label: 'kommandhub-paystack-detail.grid.reference',
                },
                {
                    property: 'transactionId',
                    label: 'kommandhub-paystack-detail.grid.transactionId',
                },
                {
                    property: 'fee',
                    label: 'kommandhub-paystack-detail.grid.processingFee',
                },
                {
                    property: 'state',
                    label: 'kommandhub-paystack-detail.grid.state',
                },
                {
                    property: 'verifiedAt',
                    label: 'kommandhub-paystack-detail.grid.verifiedAt',
                },
            ];
        },

        /**
         * Column configuration for the captures grid.
         *
         * @returns {Array<Object>}
         */
        paystackCaptureColumns() {
            return [
                {
                    property: 'amount',
                    label: 'kommandhub-paystack-detail.captures.grid.amount',
                    primary: true,
                },
                {
                    property: 'state',
                    label: 'kommandhub-paystack-detail.captures.grid.state',
                },
                {
                    property: 'externalReference',
                    label: 'kommandhub-paystack-detail.captures.grid.externalReference',
                },
                {
                    property: 'createdAt',
                    label: 'kommandhub-paystack-detail.captures.grid.createdAt',
                },
            ];
        },

        /**
         * Column configuration for the refunds grid.
         *
         * @returns {Array<Object>}
         */
        paystackRefundColumns() {
            return [
                {
                    property: 'amount',
                    label: 'kommandhub-paystack-detail.refunds.grid.amount',
                    primary: true,
                },
                {
                    property: 'state',
                    label: 'kommandhub-paystack-detail.refunds.grid.state',
                },
                {
                    property: 'externalReference',
                    label: 'kommandhub-paystack-detail.refunds.grid.externalReference',
                },
                {
                    property: 'reason',
                    label: 'kommandhub-paystack-detail.refunds.grid.reason',
                },
                {
                    property: 'createdAt',
                    label: 'kommandhub-paystack-detail.refunds.grid.createdAt',
                },
            ];
        },

        /**
         * Returns the list of captures.
         *
         * @returns {Array<Object>}
         */
        paystackCaptures() {
            return this.captures;
        },

        /**
         * Returns the filtered list of refunds based on the active capture.
         *
         * @returns {Array<Object>}
         */
        paystackRefunds() {
            if (this.activeCapture) {
                return this.refunds.filter(refund => refund.captureId === this.activeCapture.id);
            }

            return this.refunds;
        },

        /**
         * Returns the currency filter.
         *
         * @returns {Function}
         */
        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },

        /**
         * Returns the Paystack icon.
         *
         * @returns {String}
         */
        paystackIcon() {
            return icon;
        },
    },

    /** @private */
    watch: {
        paystackTransaction: {
            immediate: true,
            handler(transaction) {
                if (transaction?.id) {
                    this.loadCapturesAndRefunds();
                }
            },
        },
    },

    methods: {
        /**
         * Creates search criteria for fetching captures associated with a transaction.
         *
         * @param {String} transactionId
         * @returns {Criteria}
         */
        transactionCaptureCriteria(transactionId) {
            const criteria = new Criteria();

            criteria.addFilter(
                Criteria.equals('orderTransactionId', transactionId)
            );

            criteria.addAssociation('stateMachineState');
            criteria.addAssociation('refunds');
            criteria.addAssociation('refunds.stateMachineState');

            return criteria;
        },

        /**
         * Fetches captures and their associated refunds for the current Paystack transaction.
         *
         * @async
         * @returns {Promise<void>}
         */
        async loadCapturesAndRefunds() {
            if (!this.paystackTransaction?.id) {
                return;
            }

            this.isLoading = true;

            try {
                const captureResult = await this.transactionCaptureRepository.search(
                    this.transactionCaptureCriteria(this.paystackTransaction.id),
                    Shopware.Context.api
                );

                const captures = captureResult.map((capture) => ({
                    ...capture,
                    state: capture.stateMachineState?.translated?.name
                        || capture.stateMachineState?.name
                        || '',
                }));

                const refunds = [];

                captures.forEach((capture) => {
                    if (!capture.refunds?.length) {
                        return;
                    }

                    capture.refunds.forEach((refund) => {
                        refunds.push({
                            ...refund,
                            state: refund.stateMachineState?.translated?.name
                                || refund.stateMachineState?.name
                                || '',
                        });
                    });
                });

                this.captures = captures;
                this.refunds = refunds;
            } catch (error) {
                this.createNotificationError({
                    message: error.message,
                });
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Opens the refund modal for the selected item.
         *
         * @param {Object} item
         */
        onOpenRefundModal(item) {
            this.activeTransaction = item;
            this.refundAmount = item.amount;
            this.refundCurrency = item.currency;
            this.showRefundModal = true;
        },

        /**
         * Opens the refunds list modal for the selected capture.
         *
         * @param {Object} item
         */
        onOpenRefundsListModal(item) {
            this.activeCapture = item;
            this.showRefundsListModal = true;
        },

        /**
         * Closes the refunds list modal and resets active capture.
         */
        onCloseRefundsListModal() {
            this.activeCapture = null;
            this.showRefundsListModal = false;
        },

        /**
         * Closes the refund modal and resets refund-related data.
         */
        onCloseRefundModal() {
            this.showRefundModal = false;
            this.activeTransaction = null;
            this.refundAmount = null;
            this.refundCurrency = null;
            this.customerNote = null;
            this.merchantNote = null;
        },

        /**
         * Initiates the refund process through the Paystack refund service.
         */
        onConfirmRefund() {
            this.isRefundLoading = true;

            const payload = {
                transaction: this.activeTransaction.reference,
                orderTransactionId: this.activeTransaction.id,
                amount: Math.round(this.refundAmount * 100),
                currency: this.refundCurrency,
                customer_note: this.customerNote,
                merchant_note: this.merchantNote,
            };

            this.paystackRefundService.refund(payload)
                .then(async () => {
                    this.isRefundSuccess = true;
                    await this.loadCapturesAndRefunds();
                })
                .catch((error) => {
                    const errorData = error.response?.data;
                    let message = errorData?.error || error.message;

                    if (errorData?.meta?.nextStep) {
                        message += ` ${errorData.meta.nextStep}`;
                    }

                    this.createNotificationError({
                        message,
                    });
                })
                .finally(() => {
                    this.isRefundLoading = false;
                });
        },

        /**
         * Handles the completion of the refund process.
         */
        onRefundFinished() {
            this.isRefundSuccess = false;
            this.onCloseRefundModal();
        },
    },

    /** @private */
    created() {
        if (this.paystackTransaction?.id) {
            this.loadCapturesAndRefunds();
        }
    },
});