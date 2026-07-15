/**
 * Fully-qualified class name of the Shopware payment handler. Must stay in sync
 * with src/Checkout/Payment/Handler/PaystackPaymentHandler.php. Kept in one
 * place so a namespace change is a single-line fix rather than a silent breakage
 * across multiple components.
 */
export const PAYSTACK_HANDLER_IDENTIFIER =
    'Kommandhub\\PaystackSW\\Checkout\\Payment\\Handler\\PaystackPaymentHandler';

/**
 * Whether an error represents an aborted/cancelled HTTP request, which should be
 * swallowed silently rather than surfaced to the user.
 *
 * @param {*} error
 * @returns {boolean}
 */
export function isAbortError(error) {
    return error?.code === 'ECONNABORTED'
        || error?.name === 'AbortError'
        || error?.message === 'Request aborted';
}
