import {
    currencyDecimals,
    roundToCurrency,
    minRefundableAmount,
    maxRefundableAmount,
} from './refund-calculator';

describe('refund-calculator', () => {
    it('resolves currency decimals with a 2-decimal default', () => {
        expect(currencyDecimals('NGN')).toBe(2);
        expect(currencyDecimals('JPY')).toBe(0);
        expect(currencyDecimals('KWD')).toBe(3);
        expect(currencyDecimals('ZZZ')).toBe(2);
    });

    it('rounds to currency precision', () => {
        expect(roundToCurrency(10.129, 'NGN')).toBe(10.13);
        expect(roundToCurrency(10.129, 'KWD')).toBe(10.129);
        expect(roundToCurrency(10.9, 'JPY')).toBe(11);
    });

    it('derives min refundable from minor units', () => {
        expect(minRefundableAmount(50, 'NGN')).toBe(0.5);
        expect(minRefundableAmount(50, 'JPY')).toBe(50);
        expect(minRefundableAmount(50, 'KWD')).toBe(0.05);
        expect(minRefundableAmount(undefined, 'NGN')).toBe(0.5);
    });

    it('uses the transaction amount when there are no captures', () => {
        expect(maxRefundableAmount({
            transactionAmount: 100, captures: [], refunds: [], currency: 'NGN',
        })).toBe(100);
    });

    it('subtracts active (completed/in_progress) refunds and ignores failed ones', () => {
        const refunds = [
            { stateMachineState: { technicalName: 'completed' }, amount: { totalPrice: 20 } },
            { stateMachineState: { technicalName: 'in_progress' }, amount: { totalPrice: 10 } },
            { stateMachineState: { technicalName: 'failed' }, amount: { totalPrice: 5 } },
        ];
        expect(maxRefundableAmount({
            transactionAmount: 100, captures: [], refunds, currency: 'NGN',
        })).toBe(70);
    });

    it('uses non-failed captures as the base when captures exist', () => {
        const captures = [
            { stateMachineState: { technicalName: 'completed' }, amount: { totalPrice: 60 } },
            { stateMachineState: { technicalName: 'failed' }, amount: { totalPrice: 40 } },
        ];
        const refunds = [
            { stateMachineState: { technicalName: 'completed' }, amount: { totalPrice: 10 } },
        ];
        expect(maxRefundableAmount({
            transactionAmount: 100, captures, refunds, currency: 'NGN',
        })).toBe(50);
    });

    it('never returns a negative balance', () => {
        const refunds = [
            { stateMachineState: { technicalName: 'completed' }, amount: { totalPrice: 200 } },
        ];
        expect(maxRefundableAmount({
            transactionAmount: 100, captures: [], refunds, currency: 'NGN',
        })).toBe(0);
    });
});
