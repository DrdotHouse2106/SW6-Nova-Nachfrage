# 1.1.0

- **More relevant similar products:** manual cross-selling lists first; dynamic cross-sellings and best sellers only if they share a specific category with the purchase (directly assigned, not the main navigation or parent categories), most shared categories first. Previously any shared category, including very broad ones, counted and only sales were used for sorting – leading to parts for other vehicles or unrelated items

# 1.0.0

- First release: **follow-up e-mail after shipping** – at most one e-mail per order, X days (default 10) after all deliveries are "Shipped" (time from the state history); hourly scheduled task and console command `nova-nachfrage:send` (with `--dry-run`, `--pending`, `--order`)
- Content: purchased items with a "Write a review" link (anchor `#nova-review` opens the review form), optional **Google review** block (link per sales channel, the same for all recipients), similar products (cross-sellings, then best sellers of the same categories; only active, visible, available, not purchased), additional text per sales channel, mandatory footer with objection notice
- Own e-mail template type "Nova Nachfrage" (`nova_nachfrage`) with default template de/en
- Opt-out: signed unsubscribe link (opt-out only via button), one-click unsubscribe via `List-Unsubscribe`/`List-Unsubscribe-Post`, stored permanently as a hash only, optionally respects newsletter unsubscriptions, valid for all or one sales channel
- Safeguards: no e-mail for cancellations, refunds, returns or orders without products; minimum interval per customer (default 30 days); shipments older than 60 days are never caught up; at most 100 e-mails per run
- Notice at collection on the checkout confirmation page and as variable `novaNachfrageNotice` for all e-mail templates (e.g. order confirmation)
- Test mode (all e-mails to a test address)
- Administration: overview under Settings → Extensions (key figures, due soon, recently processed, opt-outs, record opt-out); console command `nova-nachfrage:optouts` (list, add, check, remove)
