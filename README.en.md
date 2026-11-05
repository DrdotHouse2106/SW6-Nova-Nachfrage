# Nova Follow-up (Nova Nachfrage)

[![Buy me a coffee](https://img.shields.io/badge/☕_Buy_me_a_coffee-PayPal-0070ba?style=for-the-badge&logo=paypal&logoColor=white)](https://paypal.me/DrdotHouse)

*[Deutsche Version](README.md)*

Shopware 6.7 plugin for a **follow-up after shipping**: a few days after an order has been shipped, the
customer receives **one** e-mail asking them to review the purchased items, optionally a link to a Google
review and **similar products of your own**. Objections (opt-outs) are stored permanently (as a hash only),
so that this address never receives such an e-mail again – not after a new order and not as a guest.

> **Legal note:** The plugin is built for existing-customer advertising without consent under German
> § 7 (3) UWG (address obtained from a sale, own similar goods only, no objection, notice at collection and in
> every e-mail). Whether your texts, your range and your process meet these requirements cannot be judged by
> the plugin. **Please have it reviewed legally before use.** Neither the plugin nor this description is
> legal advice.

## Features

- **One e-mail per order**, X days (default 10) after **all deliveries** of the order are "Shipped". Sending
  log in a separate table – no order is mailed twice.
- **Content:** purchased items with image, product link and **"Write a review"** button (opens the review
  form directly), optional **Google review** block, then **similar products** (cross-sellings of the
  purchased items, then best sellers of the same categories), an **additional text per sales channel** and the
  **objection notice with unsubscribe link** in the footer.
- **Own e-mail template type** "Nova Nachfrage" (`nova_nachfrage`) with default template de/en (HTML and
  text), editable under *Settings → Email templates*. Sent via Shopware's mail service with the sales
  channel's sender.
- **Opt-out:** link in every e-mail (HMAC-signed), confirmation page in the shop (opt-out only via button/POST),
  plus the headers `List-Unsubscribe` and `List-Unsubscribe-Post` (one-click). Optionally respects Shopware
  newsletter unsubscriptions.
- **Notice at collection:** short text on the checkout confirmation page below the terms, and a variable for
  the order confirmation e-mail.
- **Safeguards:** no e-mail for cancelled orders, refunded/cancelled payments, returned deliveries and
  orders without products; minimum interval per customer (default 30 days); orders shipped more than 60 days
  ago are never caught up.
- **Test mode:** all e-mails go to a test address.
- **Administration overview** under *Settings → Extensions → Nova Follow-up*: key figures, orders due soon and
  recently processed, opt-outs (shortened hash only), manual opt-out entry.
- **Console:** `nova-nachfrage:send` (with `--dry-run`) and `nova-nachfrage:optouts`.

## Setup

1. Scheduled tasks must run (`scheduled-task:run` + `messenger:consume`, or the admin worker); task
   `nova_nachfrage.send` runs hourly.
2. Check the e-mail template "Nova Nachfrage" – keep the footer with the objection notice and
   `{{ unsubscribeUrl }}`.
3. Configure the plugin per sales channel: first **test mode** with a test address, then **Active**.
4. Dry run: `bin/console nova-nachfrage:send --dry-run --pending`.
5. Add the notice to the order confirmation e-mail (variable `novaNachfrageNotice`, see below).
6. Update the privacy policy (Nova Rechtstexte 1.8.7+: block `bestandskundenwerbung` is inserted
   automatically when Nova Nachfrage is installed and active).
7. Switch test mode off.

## Decisions

- **Only "Shipped", not "Shipped partially"**: with partial shipments the customer does not have everything
  yet. The period starts when all (non-cancelled) deliveries are "Shipped".
- **Shipping time** = time of the transition to "Shipped" from `state_machine_history`; latest one for
  several deliveries; fallback: last change of the delivery.
- **Review form via URL:** Shopware 6.7 cannot open the "Reviews" tab via URL. The plugin appends
  `#nova-review` and ships a small script on product pages that opens the tab (off-canvas on mobile), expands
  the form and scrolls to it. **Guests cannot review in Shopware** (customer account required) – for guest
  orders the template shows the product link and asks for a reply by e-mail.
- **Similar products:** active cross-sellings of the purchased items (including e.g. "Customers also bought"),
  then best sellers of the same categories; only active, visible, available products, no purchased items or
  their variants, one per main product.

## Variables of the template "Nova Nachfrage"

`order`, `customer` (= `order.orderCustomer`), `salesChannel`, `products` (`name`, `productNumber`,
`quantity`, `url`, `reviewUrl`, `imageUrl`), `reviewsEnabled`, `canReview`, `googleReviewUrl`,
`googleReviewText`, `recommendations` (`name`, `productNumber`, `url`, `imageUrl`, `price`), `extraText`,
`unsubscribeUrl` (mandatory in every e-mail), `shopUrl`, `currencyIsoCode`, `optOutAllChannels`.

Order confirmation (HTML part):

```twig
{% if novaNachfrageNotice is defined and novaNachfrageNotice %}
    <p style="font-size:12px; color:#777;">{{ novaNachfrageNotice }}</p>
{% endif %}
```

## Privacy

| Table | Content | Retention |
|---|---|---|
| `nova_nachfrage_optout` | SHA-256 hash of the normalised address (fixed prefix), time, source, optional sales channel | permanent – otherwise the objection could not be respected |
| `nova_nachfrage_log` | `order_id`, sales channel, address hash, status, reason, attempts, times | 400 days, then deleted automatically |

No plain-text e-mail addresses in the plugin's tables. The hash is pseudonymous, not anonymous. Uninstalling
**without** "keep data" deletes both tables – **including the opt-outs**.

## Notes

- **Google review:** the block is the same for **all** recipients. Do not add "review gating" (sending only
  happy customers to Google) and do not offer incentives or vouchers for reviews – Google's policies forbid
  both.
- The e-mail is advertising: keep it to your own, **similar** products.

## How this plugin was made

This plugin was developed with the help of AI (Claude by Anthropic). The requirements come from running several Shopware shops day to day; the code was written and revised with AI. It is checked with automated tests and static analysis (where present in the repository), regular security reviews of the code and in production use in the author's own shops. Still: use at your own risk and without warranty (see license). Please report bugs and suggestions as an issue. More background: [SW6-Nova-Plugins](https://github.com/DrdotHouse2106/SW6-Nova-Plugins).

## License

MIT – see [LICENSE](LICENSE).
