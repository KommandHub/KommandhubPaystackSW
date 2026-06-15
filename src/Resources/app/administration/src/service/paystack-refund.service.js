const { Classes } = Shopware;

class PaystackRefundService extends Classes.ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'paystack') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'paystackRefundService';
    }

    refund(payload) {
        return this.httpClient.post(
            '_action/paystack/refund',
            payload,
            {
                headers: this.getBasicHeaders(),
            }
        ).then((response) => {
            return Classes.ApiService.handleResponse(response);
        });
    }
}

export default PaystackRefundService;
