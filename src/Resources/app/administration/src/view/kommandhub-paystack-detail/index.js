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
         * Order repository.
         *
         * @returns {Repository}
         */
        orderRepository() {
            return this.repositoryFactory.create('order');
        },

        /**
         * Returns the order object from the swOrderDetail store.
         *
         * @returns {Object}
         */
        order() {
            return Store.get('swOrderDetail')?.order;
        },

        /**
         * Checks if the order has any unsaved changes.
         *
         * @returns {Boolean}
         */
        orderChanges() {
            if (!this.order?.id || !this.orderRepository) {
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
            if (!this.order?.transactions) {
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
            if (!this.paystackTransaction?.id) {
                return [];
            }

            return [{
                id: this.paystackTransaction.id,
                amount: this.paystackTransaction.customFields?.paystack_amount,
                currency: this.paystackTransaction.customFields?.paystack_currency,
                channel: this.paystackTransaction.customFields?.paystack_payment_type,
                reference: this.paystackTransaction.customFields?.paystack_reference,
                transactionId: this.paystackTransaction.customFields?.paystack_transaction_id,
                fee: this.paystackTransaction.customFields?.paystack_transaction_fee,
                verifiedAt: this.paystackTransaction.customFields?.paystack_verified_at,
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
         * Column configuration for the capture grid.
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
         * Calculates the maximum refundable amount.
         *
         * @returns {Number}
         */
        maxRefundableAmount() {
            if (!this.paystackTransaction) {
                return 0;
            }

            const totalRefundedAmount = this.refunds.reduce((total, refund) => {
                const technicalState = refund.stateMachineState?.technicalName;

                if (
                    technicalState === 'completed'
                    || technicalState === 'in_progress'
                ) {
                    return total + Number(refund.amount?.totalPrice ?? 0);
                }

                return total;
            }, 0);

            // No captures yet => refund against original transaction amount
            if (this.captures.length === 0) {
                const transactionAmount = Number(
                    this.paystackTransaction.customFields?.paystack_amount ?? 0
                );

                return Number(
                    Math.max(
                        0,
                        transactionAmount - totalRefundedAmount
                    ).toFixed(2)
                );
            }

            const activeCapturesAmount = this.captures.reduce((total, capture) => {
                const technicalState = capture.stateMachineState?.technicalName;

                if (technicalState !== 'failed') {
                    return total + Number(capture.amount?.totalPrice ?? 0);
                }

                return total;
            }, 0);

            return Number(
                Math.max(
                    0,
                    activeCapturesAmount - totalRefundedAmount
                ).toFixed(2)
            );
        },

        /**
         * Returns the filtered list of refunds based on the active capture.
         *
         * @returns {Array<Object>}
         */
        paystackRefunds() {
            if (this.activeCapture?.id) {
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
            this.refundCurrency = item.currency;

            const maxAmount = Number(this.maxRefundableAmount);

            this.refundAmount = maxAmount > 0 ? maxAmount : 0.01;

            this.showRefundModal = true;
        },

        /**
         * Opens the refund list modal for the selected capture.
         *
         * @param {Object} item
         */
        onOpenRefundsListModal(item) {
            this.activeCapture = item;
            this.showRefundsListModal = true;
        },

        /**
         * Closes the refund list modal and resets active capture.
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

        debugRefundCalculation() {
            return {
                captures: this.captures.map(c => ({
                    id: c.id,
                    state: c.stateMachineState?.technicalName,
                    amount: c.amount,
                    totalPrice: c.amount?.totalPrice,
                })),
                refunds: this.refunds.map(r => ({
                    id: r.id,
                    state: r.stateMachineState?.technicalName,
                    amount: r.amount,
                    totalPrice: r.amount?.totalPrice,
                })),
            };
        },

        /**
         * Initiates the refund process through the Paystack refund service.
         */
        onConfirmRefund() {
            if (this.refundAmount > this.maxRefundableAmount) {
                this.createNotificationError({
                    message: this.$t('kommandhub-paystack-detail.refund.errorAmountTooHigh', {
                        maxAmount: this.currencyFilter(this.maxRefundableAmount, this.refundCurrency),
                    }),
                });
                return;
            }

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