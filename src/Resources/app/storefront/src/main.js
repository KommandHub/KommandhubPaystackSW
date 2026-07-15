const PluginManager = window.PluginManager;

PluginManager.register(
    'PaystackBankVerification',
    () => import('./paystack-bank-verification-plugin/paystack-bank-verification.plugin'),
    '[data-paystack-bank-verification]'
);
