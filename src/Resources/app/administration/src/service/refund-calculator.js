/**
 * Pure refund math for the Paystack admin UI.
 *
 * No Shopware dependencies, so it can be unit-tested in isolation (see
 * refund-calculator.spec.js). The currency-decimal table mirrors the backend
 * PaystackCurrencyHelper — keep the two in sync.
 */

const CURRENCY_DECIMALS = {
    JPY: 0, XOF: 0, XAF: 0, KMF: 0, GNF: 0, CLP: 0, RWF: 0, UGX: 0,
    BHD: 3, IQD: 3, JOD: 3, KWD: 3, LYD: 3, OMR: 3, TND: 3,
};

const ACTIVE_REFUND_STATES = ['completed', 'in_progress'];
const FAILED_CAPTURE_STATE = 'failed';

/**
 * Number of minor-unit decimals for a currency (defaults to 2).
 *
 * @param {string} currency
 * @returns {number}
 */
export function currencyDecimals(currency) {
    return CURRENCY_DECIMALS[currency] ?? 2;
}

/**
 * Rounds a major-unit amount to its currency's precision.
 *
 * @param {number} value
 * @param {string} currency
 * @returns {number}
 */
export function roundToCurrency(value, currency) {
    return Number(Number(value ?? 0).toFixed(currencyDecimals(currency)));
}

/**
 * Minimum refundable amount in major units, derived from a minor-unit config
 * value (Paystack's minimum refund is expressed in minor units).
 *
 * @param {number} minMinorUnits
 * @param {string} currency
 * @returns {number}
 */
export function minRefundableAmount(minMinorUnits, currency) {
    return Number(minMinorUnits ?? 50) / (10 ** currencyDecimals(currency));
}

function sumActiveRefunds(refunds) {
    return refunds.reduce((total, refund) => {
        return ACTIVE_REFUND_STATES.includes(refund.stateMachineState?.technicalName)
            ? total + Number(refund.amount?.totalPrice ?? 0)
            : total;
    }, 0);
}

/**
 * Maximum refundable amount in major units.
 *
 * Base is the sum of non-failed captures, or the original transaction amount
 * when no captures exist yet, minus refunds already completed or in progress.
 *
 * @param {Object} params
 * @param {number} params.transactionAmount original transaction amount (major units)
 * @param {Array}  [params.captures]
 * @param {Array}  [params.refunds]
 * @param {string} params.currency
 * @returns {number}
 */
export function maxRefundableAmount({ transactionAmount, captures = [], refunds = [], currency }) {
    const refunded = sumActiveRefunds(refunds);

    if (captures.length === 0) {
        return roundToCurrency(Math.max(0, Number(transactionAmount ?? 0) - refunded), currency);
    }

    const activeCaptures = captures.reduce((total, capture) => {
        return capture.stateMachineState?.technicalName !== FAILED_CAPTURE_STATE
            ? total + Number(capture.amount?.totalPrice ?? 0)
            : total;
    }, 0);

    return roundToCurrency(Math.max(0, activeCaptures - refunded), currency);
}
