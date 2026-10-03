<?php

/**
 * TwittPay - WISECP payment gateway
 * ---------------------------------------------------------------------------
 * area()      creates the payment and sends the customer to the hosted page
 * callback()  answers both the browser coming back and the gateway's webhook
 *
 * The callback verifies every transaction against the API before it reports a
 * payment. The return URL carries a status, but anybody can type a URL, so it is
 * never believed on its own.
 *
 * @version 1.0
 */
class twittpay extends PaymentGatewayModule
{
    public function __construct()
    {
        $this->name = __CLASS__;

        parent::__construct();
    }

    public function config_fields()
    {
        return [
            'base_url' => [
                'name'        => 'Endpoint URL',
                'description' => 'Your own gateway address, for example https://checkout.twittpay.com',
                'type'        => 'text',
                'value'       => $this->config['settings']['base_url'] ?? '',
            ],
            'api_key' => [
                'name'        => 'Brand Key',
                'description' => 'From your gateway dashboard, under Brands.',
                'type'        => 'text',
                'value'       => $this->config['settings']['api_key'] ?? '',
            ],
            'currency_rate' => [
                'name'        => 'USD to BDT Rate',
                'description' => 'Used only when the invoice is not in BDT. 1 USD = this many BDT.',
                'type'        => 'text',
                'value'       => $this->config['settings']['currency_rate'] ?? '120',
            ],
        ];
    }

    /**
     * The payment screen. Creates the payment, then hands the browser over.
     */
    public function area($params = [])
    {
        $invoiceId = $this->checkout_id;
        $amount    = (float) $params['amount'];
        $currency  = strtoupper(trim((string) ($params['currency'] ?? 'BDT')));

        $firstname = $this->clientInfo->name ?? '';
        $lastname  = $this->clientInfo->surname ?? '';
        $email     = $this->clientInfo->email ?? '';

        $successUrl = $this->links['successful'];
        $cancelUrl  = $this->links['failed'];
        $webhookUrl = $this->links['callback'];

        $data = [
            'cus_name'    => trim($firstname . ' ' . $lastname),
            'cus_email'   => ($email !== '' ? $email : 'default@gmail.com'),
            'amount'      => number_format($this->toBdt($amount, $currency), 2, '.', ''),
            'success_url' => $successUrl,
            'cancel_url'  => $cancelUrl,
            'webhook_url' => $webhookUrl,
            'metadata'    => [
                'invoiceid'        => (string) $invoiceId,
                'invoice_amount'   => number_format($amount, 2, '.', ''),
                'invoice_currency' => $currency,
                'source'           => 'wisecp',
            ],
        ];

        $response = $this->apiCall('/api/payment/create', $data);

        if (!empty($response['status']) && !empty($response['payment_url'])) {
            $url = $response['payment_url'];

            echo '<div style="text-align:center;padding:24px;font-size:15px;">'
                . htmlspecialchars($this->lang['redirecting'], ENT_QUOTES, 'UTF-8')
                . '<br><br><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($this->lang['pay-button'], ENT_QUOTES, 'UTF-8')
                . '</a></div>'
                . '<script>location.href=' . json_encode($url) . ';</script>';

            return;
        }

        // Never print the raw API response to a customer - it can carry the key
        // back out in an error string.
        echo '<div style="text-align:center;padding:24px;font-size:15px;color:#b91c1c;">'
            . htmlspecialchars($this->lang['start-failed'], ENT_QUOTES, 'UTF-8')
            . '</div>';
    }

    /**
     * Called two ways, and both are handled here:
     *   - the customer's browser lands on it with ?transactionId=... on the URL
     *   - the gateway posts to it, form encoded and unsigned, with no browser
     *
     * The webhook is not signed on purpose - it only tells you which transaction
     * to go and ask about, and the verify call below is what decides.
     */
    public function callback()
    {
        $transactionId = $this->transactionId();

        if ($transactionId === '') {
            $this->error = 'No transaction id received.';

            return false;
        }

        $verified = $this->apiCall('/api/payment/verify', ['transaction_id' => $transactionId]);

        // A miss answers status 0, a number. Only a real payment carries text.
        $status = (isset($verified['status']) && is_string($verified['status']))
            ? strtoupper(trim($verified['status']))
            : '';

        if ($status === '') {
            $this->error = 'The gateway does not know this transaction.';

            return false;
        }

        $meta      = $this->decodeMetadata($verified);
        $invoiceId = $meta['invoiceid'] ?? null;

        if (empty($invoiceId)) {
            $this->error = 'This payment carries no invoice reference.';

            return false;
        }

        $checkout = $this->get_checkout($invoiceId);

        if (!$checkout) {
            $this->error = 'Checkout ID unknown';

            return false;
        }

        $this->set_checkout($checkout);

        if ($status === 'COMPLETED') {
            return [
                'status'         => 'successful',
                'transaction_id' => $transactionId,
            ];
        }

        if ($status === 'PENDING') {
            // The money has been sent and the merchant has not approved it yet.
            // The gateway calls this URL again with the answer, so the invoice is
            // left alone rather than marked failed.
            $this->error = 'The payment is being checked. The invoice will be updated once it clears.';

            return false;
        }

        $this->error = 'Payment status failed';

        return false;
    }

    /**
     * The id can arrive on the URL, in the webhook's form body, or in a JSON body.
     */
    private function transactionId()
    {
        foreach (['transactionId', 'transaction_id'] as $key) {
            if (!empty($_REQUEST[$key])) {
                return trim((string) $_REQUEST[$key]);
            }
        }

        $raw = file_get_contents('php://input');

        if (!empty($raw)) {
            $body = json_decode($raw, true);

            if (is_array($body)) {
                foreach (['transactionId', 'transaction_id'] as $key) {
                    if (!empty($body[$key])) {
                        return trim((string) $body[$key]);
                    }
                }
            }
        }

        return '';
    }

    /** metadata comes back from verify as a JSON string. */
    private function decodeMetadata($verified)
    {
        if (!is_array($verified) || !isset($verified['metadata'])) {
            return [];
        }

        $meta = $verified['metadata'];

        if (is_array($meta)) {
            return $meta;
        }

        if (is_object($meta)) {
            return (array) $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /** The gateway charges BDT. Anything else is converted with the set rate. */
    private function toBdt($amount, $currency)
    {
        if ($currency === 'BDT' || !preg_match('/^[A-Z]{3}$/', $currency)) {
            return (float) $amount;
        }

        $rate = (float) ($this->config['settings']['currency_rate'] ?? 0);

        if ($rate <= 0) {
            $rate = 1;
        }

        return (float) $amount * $rate;
    }

    /**
     * Scheme and host of the configured endpoint. Pasting the whole endpoint or a
     * trailing /api still works.
     */
    private function baseUrl()
    {
        $raw    = rtrim(trim((string) ($this->config['settings']['base_url'] ?? '')), '/');
        $scheme = parse_url($raw, PHP_URL_SCHEME);
        $host   = parse_url($raw, PHP_URL_HOST);

        if (empty($host)) {
            $host = strtok(ltrim(preg_replace('#^[a-z]+://#i', '', $raw), '/'), '/');
        }

        if (empty($scheme)) {
            $scheme = 'https';
        }

        return $scheme . '://' . $host;
    }

    /** One POST to the API. JSON in, array out. */
    private function apiCall($endpoint, $payload)
    {
        // metadata has to arrive as a JSON object; a PHP list would encode as an
        // array and be rejected.
        if (isset($payload['metadata'])) {
            $payload['metadata'] = (object) $payload['metadata'];
        }

        $ch = curl_init($this->baseUrl() . $endpoint);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'API-KEY: ' . trim((string) ($this->config['settings']['api_key'] ?? '')),
            ],
        ]);

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $response === '') {
            return ['status' => 0, 'message' => ($error !== '' ? $error : 'No response from the gateway.')];
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return ['status' => 0, 'message' => 'The gateway sent back something that is not JSON.'];
        }

        return $decoded;
    }
}
