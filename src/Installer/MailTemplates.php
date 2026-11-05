<?php declare(strict_types=1);

namespace Nova\Nachfrage\Installer;

/**
 * Standardvorlagen der Nachfrage-Mail (de/en, HTML und Text).
 *
 * Variablen:
 * - order (Bestellung), customer (= order.orderCustomer, mit salutation), salesChannel, shopUrl, currencyIsoCode
 * - products: gekaufte Artikel – name, productNumber, quantity, url (Produktseite, null wenn nicht mehr
 *   sichtbar), reviewUrl (Produktseite mit Anker #nova-review, öffnet das Bewertungsformular), imageUrl
 * - reviewsEnabled (Bewertungen im Kanal eingeschaltet), canReview (Kunde hat ein Kundenkonto – Gäste können
 *   in Shopware nicht bewerten)
 * - googleReviewUrl, googleReviewText (leer = Block entfällt)
 * - recommendations: ähnliche Produkte – name, productNumber, url, imageUrl, price
 * - extraText (Zusatztext des Verkaufskanals, HTML), unsubscribeUrl (Abmeldelink), optOutAllChannels
 *
 * Der Fuß mit dem Hinweis auf das Widerspruchsrecht gehört in jede dieser Mails – beim Anpassen bitte stehen lassen.
 */
final class MailTemplates
{
    public const SUBJECT_DE = 'Wie zufrieden sind Sie mit Ihrer Bestellung {{ order.orderNumber }}?';
    public const SUBJECT_EN = 'How satisfied are you with your order {{ order.orderNumber }}?';

    public const HTML_DE = <<<'TWIG'
<div style="font-family:arial,helvetica,sans-serif; font-size:14px; line-height:1.5; color:#222; max-width:640px;">
    <p>{% if customer.salutation and customer.salutation.translated.letterName %}{{ customer.salutation.translated.letterName }} {{ customer.firstName }} {{ customer.lastName }}{% else %}Hallo {{ customer.firstName }} {{ customer.lastName }}{% endif %},</p>
    <p>vor einigen Tagen haben wir Ihre Bestellung {{ order.orderNumber }} verschickt. Wir hoffen, alles ist gut bei Ihnen angekommen und erfüllt seinen Zweck.</p>

    {% if products|length > 0 %}
    <p><strong>Wie zufrieden sind Sie mit Ihrem Einkauf?</strong> Ihre Meinung hilft anderen Kunden bei der Auswahl – und uns, besser zu werden.</p>
    <table cellpadding="6" cellspacing="0" style="border-collapse:collapse; width:100%; font-size:14px;">
        {% for product in products %}
        <tr style="border-top:1px solid #e5e5e5;">
            <td style="width:90px; vertical-align:top;">{% if product.imageUrl %}<img src="{{ product.imageUrl }}" alt="" width="80" style="display:block; max-width:80px; height:auto;">{% endif %}</td>
            <td style="vertical-align:top;">
                {{ product.name }}{% if product.productNumber %} <span style="color:#777;">({{ product.productNumber }})</span>{% endif %}<br>
                {% if canReview and product.reviewUrl %}<a href="{{ product.reviewUrl }}" style="display:inline-block; margin-top:4px; padding:6px 14px; background:#2b6cb0; color:#fff; text-decoration:none; border-radius:4px;">Jetzt bewerten</a>
                {% elseif product.url %}<a href="{{ product.url }}">Zum Artikel</a>{% endif %}
            </td>
        </tr>
        {% endfor %}
    </table>
    {% if reviewsEnabled and not canReview %}
    <p style="color:#555;">Bewertungen können im Shop mit einem Kundenkonto abgegeben werden. Sie haben ohne Kundenkonto bestellt? Dann antworten Sie gern einfach auf diese E-Mail – wir freuen uns über jede Rückmeldung.</p>
    {% elseif not reviewsEnabled %}
    <p>Antworten Sie gern einfach auf diese E-Mail – wir freuen uns über jede Rückmeldung.</p>
    {% endif %}
    {% endif %}

    {% if googleReviewUrl %}
    <p style="margin-top:20px;">{{ googleReviewText ?: 'Sie möchten uns auch bei Google bewerten? Darüber freuen wir uns sehr.' }}<br>
        <a href="{{ googleReviewUrl }}">Bei Google bewerten</a></p>
    {% endif %}

    {% if recommendations|length > 0 %}
    <p style="margin-top:24px;"><strong>Das könnte Sie auch interessieren</strong></p>
    <table cellpadding="6" cellspacing="0" style="border-collapse:collapse; width:100%; font-size:14px;">
        {% for product in recommendations %}
        <tr style="border-top:1px solid #e5e5e5;">
            <td style="width:90px; vertical-align:top;">{% if product.imageUrl %}<a href="{{ product.url }}"><img src="{{ product.imageUrl }}" alt="" width="80" style="display:block; max-width:80px; height:auto; border:0;"></a>{% endif %}</td>
            <td style="vertical-align:top;"><a href="{{ product.url }}">{{ product.name }}</a>{% if product.price is not null %}<br><span style="color:#555;">{{ product.price|currency(currencyIsoCode) }}</span>{% endif %}</td>
        </tr>
        {% endfor %}
    </table>
    {% endif %}

    {% if extraText %}
    <div style="margin-top:20px;">{{ extraText|raw }}</div>
    {% endif %}

    <p style="margin-top:24px;">Viele Grüße<br>{{ salesChannel.translated.name }}</p>

    <p style="margin-top:32px; padding-top:12px; border-top:1px solid #e5e5e5; font-size:12px; color:#777;">
        Sie erhalten diese E-Mail, weil Sie bei uns bestellt haben: einmalig nach dem Versand mit der Bitte um Ihre Meinung und Hinweisen auf ähnliche Produkte aus unserem eigenen Sortiment.
        Sie können der Verwendung Ihrer E-Mail-Adresse für solche E-Mails jederzeit widersprechen, ohne dass hierfür andere als die Übermittlungskosten nach den Basistarifen entstehen:
        <a href="{{ unsubscribeUrl }}" style="color:#777;">Keine solchen E-Mails mehr erhalten</a>.
    </p>
</div>
TWIG;

    public const PLAIN_DE = <<<'TWIG'
{% if customer.salutation and customer.salutation.translated.letterName %}{{ customer.salutation.translated.letterName }} {{ customer.firstName }} {{ customer.lastName }}{% else %}Hallo {{ customer.firstName }} {{ customer.lastName }}{% endif %},

vor einigen Tagen haben wir Ihre Bestellung {{ order.orderNumber }} verschickt. Wir hoffen, alles ist gut bei Ihnen angekommen und erfüllt seinen Zweck.
{% if products|length > 0 %}

Wie zufrieden sind Sie mit Ihrem Einkauf? Ihre Meinung hilft anderen Kunden bei der Auswahl – und uns, besser zu werden.

{% for product in products %}
- {{ product.name }}{% if product.productNumber %} ({{ product.productNumber }}){% endif %}{{ "\n" }}
{%- if canReview and product.reviewUrl %}  Jetzt bewerten: {{ product.reviewUrl }}{{ "\n" }}{% elseif product.url %}  {{ product.url }}{{ "\n" }}{% endif %}
{% endfor %}
{% if reviewsEnabled and not canReview %}

Bewertungen können im Shop mit einem Kundenkonto abgegeben werden. Sie haben ohne Kundenkonto bestellt? Dann antworten Sie gern einfach auf diese E-Mail – wir freuen uns über jede Rückmeldung.
{% elseif not reviewsEnabled %}

Antworten Sie gern einfach auf diese E-Mail – wir freuen uns über jede Rückmeldung.
{% endif %}
{% endif %}
{% if googleReviewUrl %}
{{ "\n" }}{{ googleReviewText ?: 'Sie möchten uns auch bei Google bewerten? Darüber freuen wir uns sehr.' }}
Bei Google bewerten: {{ googleReviewUrl }}
{% endif %}
{% if recommendations|length > 0 %}

Das könnte Sie auch interessieren:
{% for product in recommendations %}
- {{ product.name }}{% if product.price is not null %} – {{ product.price|currency(currencyIsoCode) }}{% endif %}

  {{ product.url }}
{% endfor %}
{% endif %}
{% if extraText %}

{{ extraText|striptags }}
{% endif %}

Viele Grüße
{{ salesChannel.translated.name }}

--
Sie erhalten diese E-Mail, weil Sie bei uns bestellt haben: einmalig nach dem Versand mit der Bitte um Ihre Meinung und Hinweisen auf ähnliche Produkte aus unserem eigenen Sortiment. Sie können der Verwendung Ihrer E-Mail-Adresse für solche E-Mails jederzeit widersprechen, ohne dass hierfür andere als die Übermittlungskosten nach den Basistarifen entstehen:
{{ unsubscribeUrl }}
TWIG;

    public const HTML_EN = <<<'TWIG'
<div style="font-family:arial,helvetica,sans-serif; font-size:14px; line-height:1.5; color:#222; max-width:640px;">
    <p>{% if customer.salutation and customer.salutation.translated.letterName %}{{ customer.salutation.translated.letterName }} {{ customer.firstName }} {{ customer.lastName }}{% else %}Hello {{ customer.firstName }} {{ customer.lastName }}{% endif %},</p>
    <p>a few days ago we shipped your order {{ order.orderNumber }}. We hope everything arrived safely and does its job.</p>

    {% if products|length > 0 %}
    <p><strong>How satisfied are you with your purchase?</strong> Your opinion helps other customers choose – and helps us get better.</p>
    <table cellpadding="6" cellspacing="0" style="border-collapse:collapse; width:100%; font-size:14px;">
        {% for product in products %}
        <tr style="border-top:1px solid #e5e5e5;">
            <td style="width:90px; vertical-align:top;">{% if product.imageUrl %}<img src="{{ product.imageUrl }}" alt="" width="80" style="display:block; max-width:80px; height:auto;">{% endif %}</td>
            <td style="vertical-align:top;">
                {{ product.name }}{% if product.productNumber %} <span style="color:#777;">({{ product.productNumber }})</span>{% endif %}<br>
                {% if canReview and product.reviewUrl %}<a href="{{ product.reviewUrl }}" style="display:inline-block; margin-top:4px; padding:6px 14px; background:#2b6cb0; color:#fff; text-decoration:none; border-radius:4px;">Write a review</a>
                {% elseif product.url %}<a href="{{ product.url }}">View product</a>{% endif %}
            </td>
        </tr>
        {% endfor %}
    </table>
    {% if reviewsEnabled and not canReview %}
    <p style="color:#555;">Reviews can be written in our shop with a customer account. You ordered as a guest? Then simply reply to this e-mail – we appreciate any feedback.</p>
    {% elseif not reviewsEnabled %}
    <p>Simply reply to this e-mail – we appreciate any feedback.</p>
    {% endif %}
    {% endif %}

    {% if googleReviewUrl %}
    <p style="margin-top:20px;">{{ googleReviewText ?: 'Would you also like to review us on Google? We would be delighted.' }}<br>
        <a href="{{ googleReviewUrl }}">Review us on Google</a></p>
    {% endif %}

    {% if recommendations|length > 0 %}
    <p style="margin-top:24px;"><strong>You might also like</strong></p>
    <table cellpadding="6" cellspacing="0" style="border-collapse:collapse; width:100%; font-size:14px;">
        {% for product in recommendations %}
        <tr style="border-top:1px solid #e5e5e5;">
            <td style="width:90px; vertical-align:top;">{% if product.imageUrl %}<a href="{{ product.url }}"><img src="{{ product.imageUrl }}" alt="" width="80" style="display:block; max-width:80px; height:auto; border:0;"></a>{% endif %}</td>
            <td style="vertical-align:top;"><a href="{{ product.url }}">{{ product.name }}</a>{% if product.price is not null %}<br><span style="color:#555;">{{ product.price|currency(currencyIsoCode) }}</span>{% endif %}</td>
        </tr>
        {% endfor %}
    </table>
    {% endif %}

    {% if extraText %}
    <div style="margin-top:20px;">{{ extraText|raw }}</div>
    {% endif %}

    <p style="margin-top:24px;">Kind regards<br>{{ salesChannel.translated.name }}</p>

    <p style="margin-top:32px; padding-top:12px; border-top:1px solid #e5e5e5; font-size:12px; color:#777;">
        You are receiving this e-mail because you ordered from us: once after shipping, asking for your opinion and pointing out similar products from our own range.
        You can object to the use of your e-mail address for such e-mails at any time without incurring any costs other than the transmission costs at the basic rates:
        <a href="{{ unsubscribeUrl }}" style="color:#777;">Stop receiving these e-mails</a>.
    </p>
</div>
TWIG;

    public const PLAIN_EN = <<<'TWIG'
{% if customer.salutation and customer.salutation.translated.letterName %}{{ customer.salutation.translated.letterName }} {{ customer.firstName }} {{ customer.lastName }}{% else %}Hello {{ customer.firstName }} {{ customer.lastName }}{% endif %},

a few days ago we shipped your order {{ order.orderNumber }}. We hope everything arrived safely and does its job.
{% if products|length > 0 %}

How satisfied are you with your purchase? Your opinion helps other customers choose – and helps us get better.

{% for product in products %}
- {{ product.name }}{% if product.productNumber %} ({{ product.productNumber }}){% endif %}{{ "\n" }}
{%- if canReview and product.reviewUrl %}  Write a review: {{ product.reviewUrl }}{{ "\n" }}{% elseif product.url %}  {{ product.url }}{{ "\n" }}{% endif %}
{% endfor %}
{% if reviewsEnabled and not canReview %}

Reviews can be written in our shop with a customer account. You ordered as a guest? Then simply reply to this e-mail – we appreciate any feedback.
{% elseif not reviewsEnabled %}

Simply reply to this e-mail – we appreciate any feedback.
{% endif %}
{% endif %}
{% if googleReviewUrl %}
{{ "\n" }}{{ googleReviewText ?: 'Would you also like to review us on Google? We would be delighted.' }}
Review us on Google: {{ googleReviewUrl }}
{% endif %}
{% if recommendations|length > 0 %}

You might also like:
{% for product in recommendations %}
- {{ product.name }}{% if product.price is not null %} – {{ product.price|currency(currencyIsoCode) }}{% endif %}

  {{ product.url }}
{% endfor %}
{% endif %}
{% if extraText %}

{{ extraText|striptags }}
{% endif %}

Kind regards
{{ salesChannel.translated.name }}

--
You are receiving this e-mail because you ordered from us: once after shipping, asking for your opinion and pointing out similar products from our own range. You can object to the use of your e-mail address for such e-mails at any time without incurring any costs other than the transmission costs at the basic rates:
{{ unsubscribeUrl }}
TWIG;
}
