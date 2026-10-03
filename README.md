# TwittPay for WISECP

WISECP payment module

Part of the [TwittPay](https://twittpay.com) addon family.

## Quick start

1. Download the latest zip from the **Releases** page of this repository.
2. Install it on your WISECP following the guide below.
3. Open the TwittPay settings and enter your **Brand Key**. You can get it from [your dashboard](https://twittpay.com/user/brands).
4. Make a small test payment to confirm everything works.

Payments are always re-verified on your server before an order or invoice is marked paid.

## Detailed installation guide

```text
===========================================================================
 TWITTPAY - WISECP payment gateway
===========================================================================

 WHERE IT GOES
   Extract this zip at your WISECP root - the folder that has coremio/ in it.
   Everything lands under:

     coremio/modules/Payment/twittpay/

   Nothing you already have is overwritten.

 INSTALL
   1. Admin panel -> Settings -> Payment Gateways.
   2. Find "TwittPay" and activate it.
   3. Fill in the three fields:

        Endpoint URL      your own gateway address, e.g.
                          https://checkout.twittpay.com
                          (the API host shown on your gateway's developer page)

        Brand Key           from your gateway dashboard, under Brands

        USD to BDT Rate   only used when the invoice is not already in BDT

   4. Save, then place a test order and pay it.

 HOW IT WORKS
   * The customer picks TwittPay on the invoice, WISECP calls area(), and
     the module creates the payment and hands the browser to the hosted page.
   * On the way back WISECP calls callback() with ?transactionId=... on the URL.
   * The gateway also posts to that same callback URL from its own server, with
     no browser involved.
   * Either way the module verifies the transaction against the API before it
     tells WISECP anything, so a hand-typed status does nothing.

 WHAT A PAYMENT DOES
   COMPLETED  -> the checkout is marked successful and the invoice is paid
   PENDING    -> nothing is marked yet. The customer has sent the money and your
                 merchant has not approved it; the gateway calls the callback
                 again with the answer. Do not ask the customer to pay again.
   ERROR      -> failed

 CURRENCY
   The gateway charges BDT.

   * Invoice already in BDT: the exact amount is sent.
   * Invoice in another currency: the amount is multiplied by the USD to BDT
     Rate, and the original amount and currency ride along in metadata.
   * Cleaner option: add BDT as a currency in WISECP, then put its currency id
     into 'force_convert_to' in config.php. WISECP converts the invoice itself
     and the module sends the amount untouched.

 WHAT TO WATCH
   * The Endpoint URL is your API host. Pasting the whole endpoint or a trailing
     /api is fine - only the scheme and host are used.
   * If your site answers on both www and non-www, make sure WISECP's own URL
     setting matches the one customers use, or the callback lands on the other
     host.
   * Refunds are not done through the API. Refund on the gateway side, then
     record it in WISECP by hand.
   * The customer never sees a raw API error - a failed create shows a plain
     message instead, because an error string can carry the key back out.

 FILES
   coremio/modules/Payment/twittpay/twittpay.php   the gateway
   coremio/modules/Payment/twittpay/config.php        defaults
   coremio/modules/Payment/twittpay/lang/en.php       English strings
   coremio/modules/Payment/twittpay/lang/tr.php       Turkish strings
   coremio/modules/Payment/twittpay/logo.png          the logo

 CHECKED
   The PHP was checked with a lexer that balances braces only inside real PHP
   code. PHP itself was NOT run - there is no PHP binary on the machine this was
   built on, so php -l was never executed. Test it on a staging install first.
```
