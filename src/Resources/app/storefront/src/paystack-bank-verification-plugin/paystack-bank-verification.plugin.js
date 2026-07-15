const { PluginBaseClass } = window;

/**
 * Paystack Bank Verification Plugin
 *
 * Responsibilities:
 * - Load available banks from Paystack.
 * - Verify account number against a selected bank.
 * - Populate verified account name automatically.
 * - Attach bank name and bank code to the submitted form.
 */
export default class PaystackBankVerificationPlugin extends PluginBaseClass {
    static options = {
        banksUrl: '',
        verifyUrl: '',

        bankSelectSelector: '#bankName',
        accountNumberSelector: '#accountNumber',
        bvnSelector: '#bvn',
        accountNameSelector: '#accountName',

        submitButtonSelector: 'button[type="submit"]',

        initialBankCode: '',
    };

    /**
     * Nigerian bank account numbers are always 10 digits.
     */
    static ACCOUNT_NUMBER_LENGTH = 10;

    /**
     * BVN is always 11 digits.
     */
    static BVN_LENGTH = 11;

    init() {
        this._bankSelect = this.el.querySelector(this.options.bankSelectSelector);
        this._accountNumberInput = this.el.querySelector(this.options.accountNumberSelector);
        this._bvnInput = this.el.querySelector(this.options.bvnSelector);
        this._accountNameInput = this.el.querySelector(this.options.accountNameSelector);
        this._submitButton = this.el.querySelector(this.options.submitButtonSelector);

        if (!this._bankSelect || !this._accountNumberInput) {
            return;
        }

        this._registerEvents();
        this._loadBanks();
    }

    /**
     * Register DOM event listeners.
     *
     * @private
     */
    _registerEvents() {
        this._accountNumberInput.addEventListener(
            'blur',
            this._onAccountNumberBlur.bind(this)
        );

        this._bankSelect.addEventListener(
            'change',
            this._onBankChange.bind(this)
        );

        this.el.addEventListener(
            'submit',
            this._onFormSubmit.bind(this)
        );
    }

    /**
     * Load available banks from backend endpoint.
     *
     * @private
     */
    _loadBanks() {
        if (!this.options.banksUrl) {
            return;
        }

        fetch(this.options.banksUrl, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.json())
            .then((result) => {
                if (result.status && Array.isArray(result.data)) {
                    this._populateBankSelect(result.data);
                }
            })
            .catch((error) => {
                console.error('Failed to load banks.', error);
            });
    }

    /**
     * Populate bank select dropdown.
     *
     * @param {Array} banks
     * @private
     */
    _populateBankSelect(banks) {
        while (this._bankSelect.options.length > 1) {
            this._bankSelect.remove(1);
        }

        banks.forEach((bank) => {
            const option = document.createElement('option');

            option.value = bank.code;
            option.text = bank.name;
            option.dataset.bankName = bank.name;

            if (bank.code === this.options.initialBankCode) {
                option.selected = true;
            }

            this._bankSelect.add(option);
        });

        // Re-run verification when editing existing profile data.
        if (
            this._bankSelect.value &&
            this._accountNumberInput.value &&
            !this._accountNameInput.value
        ) {
            this._verifyAccount();
        }
    }

    /**
     * Handle account number blur.
     *
     * @private
     */
    _onAccountNumberBlur() {
        this._verifyAccount();
    }

    /**
     * Handle bank selection changes.
     *
     * @private
     */
    _onBankChange() {
        this._verifyAccount();
    }


    /**
     * Verify account number against selected bank.
     *
     * Uses Paystack's account resolution endpoint through
     * the plugin backend controller.
     *
     * @private
     */
    _verifyAccount() {
        if (!this.options.verifyUrl) {
            return;
        }

        const accountNumber = this._accountNumberInput.value.trim();
        const bankCode = this._bankSelect.value;

        if (
            accountNumber.length < PaystackBankVerificationPlugin.ACCOUNT_NUMBER_LENGTH ||
            !bankCode
        ) {
            if (
                accountNumber.length > 0 &&
                accountNumber.length < PaystackBankVerificationPlugin.ACCOUNT_NUMBER_LENGTH
            ) {
                this._markValidationState(
                    this._accountNumberInput,
                    false,
                    'Account number must be 10 digits'
                );
            } else {
                this._markValidationState(this._accountNumberInput, null);
            }

            return;
        }

        this._setLoading(true, this._accountNumberInput);

        const formData = new FormData();
        formData.append('account_number', accountNumber);
        formData.append('bank_code', bankCode);

        fetch(this.options.verifyUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => response.json())
            .then((result) => {
                this._setLoading(false, this._accountNumberInput);

                if (result.status && result.data) {
                    this._accountNameInput.value = result.data.account_name;

                    this._markValidationState(
                        this._accountNumberInput,
                        true,
                        `Account verified: ${result.data.account_name}`
                    );
                } else {
                    this._markValidationState(
                        this._accountNumberInput,
                        false,
                        result.message || 'Account verification failed'
                    );
                }
            })
            .catch((error) => {
                this._setLoading(false, this._accountNumberInput);
                console.error('Failed to verify account.', error);

                this._markValidationState(
                    this._accountNumberInput,
                    false,
                    'Verification service unavailable'
                );
            });
    }


    /**
     * Toggle loading state.
     *
     * @param {boolean} loading
     * @param {HTMLElement} input
     * @private
     */
    _setLoading(loading, input) {
        input.classList.toggle('is-loading', loading);
    }

    /**
     * Update validation UI.
     *
     * valid:
     * - true  => success state
     * - false => error state
     * - null  => reset state
     *
     * @param {HTMLElement} input
     * @param {boolean|null} valid
     * @param {string} message
     * @private
     */
    _markValidationState(input, valid, message = '') {
        const container = input.closest('.form-group');

        const invalidFeedback =
            container?.querySelector('.invalid-feedback');

        const validFeedback =
            container?.querySelector('.valid-feedback');

        if (valid === null) {
            input.classList.remove('is-valid', 'is-invalid');

            if (invalidFeedback) {
                invalidFeedback.textContent = '';
            }

            if (validFeedback) {
                validFeedback.textContent = '';
            }

            return;
        }

        input.classList.toggle('is-valid', valid);
        input.classList.toggle('is-invalid', !valid);

        if (validFeedback) {
            validFeedback.textContent = valid ? message : '';
        }

        if (invalidFeedback) {
            invalidFeedback.textContent = valid ? '' : message;
        }
    }

    /**
     * Add hidden bank fields before form submission.
     *
     * We store both the selected bank name and code
     * so they can be persisted on the customer record.
     *
     * @private
     */
    _onFormSubmit() {
        const selectedOption =
            this._bankSelect.options[this._bankSelect.selectedIndex];

        if (!selectedOption) {
            return;
        }

        this._createOrUpdateHiddenField(
            'hiddenBankName',
            'bankName',
            selectedOption.dataset.bankName || ''
        );

        this._createOrUpdateHiddenField(
            'hiddenBankCode',
            'bankCode',
            this._bankSelect.value
        );
    }

    /**
     * Create or update hidden form fields.
     *
     * @param {string} id
     * @param {string} name
     * @param {string} value
     * @private
     */
    _createOrUpdateHiddenField(id, name, value) {
        let field = this.el.querySelector(`#${id}`);

        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.id = id;
            field.name = name;

            this.el.appendChild(field);
        }

        field.value = value;
    }
}