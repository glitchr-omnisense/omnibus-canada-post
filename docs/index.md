# omnibus/canada-post

Canada Post for [omnisense/omnibus](https://github.com/glitchr-omnisense/omnibus): rates,
non-contract shipments (paid by the card on file) or contract shipments with their labels,
tracking and the nearest post offices, through Canada Post's XML REST web services with HTTP
basic authentication. The gateway calls:

| Operation | Web service | Request | Media type |
|---|---|---|---|
| rate | Get Rates | `POST /rs/ship/price` | `application/vnd.cpc.ship.rate-v4+xml` |
| ship, no contract | Create Non-Contract Shipment | `POST /rs/{customer}/ncshipment` | `application/vnd.cpc.ncshipment-v4+xml` |
| ship, contract | Create Shipment | `POST /rs/{customer}/{customer}/shipment` | `application/vnd.cpc.shipment-v8+xml` |
| ship (label) | the shipment's `label` link | `GET` the link | the link's media type |
| track | Get Tracking Detail | `GET /vis/track/pin/{pin}/detail` | `application/vnd.cpc.track-v2+xml` |
| pickup points | Get Nearest Post Office | `GET /rs/postoffice` | `application/vnd.cpc.postoffice+xml` |

on `https://soa-gw.canadapost.ca` (production) or `https://ct.soa-gw.canadapost.ca` (the
development environment, `sandbox: true`). Every call has a 20-second timeout.

## Installation

```sh
composer require omnibus/canada-post
```

Requires PHP 8.2, `omnisense/omnibus` (2.x) and `symfony/http-client` (6.4, 7 or 8); given no
HTTP client, the factory makes one with `HttpClient::create()`. In a Symfony application with
`Omnibus\Bridge\Symfony\OmnibusBundle`, the factory is registered as soon as the package is
installed.

## Credentials

- A [Canada Post Developer Program](https://www.canadapost-postescanada.ca/information/app/drc/home)
  account: it gives the development and production API keys (a **username** and a **password**
  each) and the **customer number**.
- A **contract id**, with a commercial agreement: shipments then go through the contract
  service and are paid on account, and rates are the contract's.

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `username` | string | - | yes | the API key's username |
| `password` | string | - | yes | the API key's password |
| `customer_number` | string | - | yes | the customer number, in the shipment paths |
| `contract_id` | ?string | `null` | no | a commercial contract: contract shipments and contract rates |
| `sandbox` | bool | `false` | no | `true`: the development environment |
| `rates` | list | - | no | prices from configuration instead of Get Rates ([the rates option](https://github.com/glitchr-omnisense/omnibus/blob/2.x/docs/configuration.md#the-rates-option)): with it, Get Rates is not called |

Shipment options read when booking (`Shipment::$options`):

| Option | Default | |
|---|---|---|
| `sender_province` | - | the sender's two-letter province or state; Canadian addresses need it |
| `recipient_province` | - | the recipient's two-letter province or state |
| `signature` | `false` | `true`: the signature option (`SO`) |
| `label_format` | `PDF` | `PDF` (an 8.5 x 11 inch sheet) or `ZPL` (4 x 6 inch) |
| `description` | `Merchandise` | the customs description, for a shipment leaving Canada |

The address model has no province: it comes from the two options.

## Capabilities

| Request | Supported |
|---|---|
| `Rating` | yes (Get Rates), or configured rates with `rates` |
| `Shipping` | yes |
| `Tracking` | yes |
| `Pickup` | yes (post offices) |
| `GetSlip` | no |
| `Cancel` | no |
| `Paperless` | no |

## Behaviour

**Units.** Postcodes are sent in capitals without spaces; the weight is the shipment's total in
kilograms with three decimals (at least 0.001); dimensions are the first parcel's, in
centimetres, when all three are given.

**Services.** The service is Canada Post's service code; labels come from
`Omnibus\CanadaPost\Mapping::SERVICES` when Canada Post gives no name:

| Code | Service |
|---|---|
| `DOM.RP` | Regular Parcel |
| `DOM.EP` | Expedited Parcel (the default in Canada) |
| `DOM.XP` | Xpresspost |
| `DOM.PC` | Priority |
| `USA.EP` | Expedited Parcel USA |
| `USA.XP` | Xpresspost USA |
| `USA.TP` | Tracked Packet USA |
| `USA.SP.AIR` | Small Packet USA Air |
| `INT.XP` | Xpresspost International (the default outside Canada) |
| `INT.IP.AIR` | International Parcel Air |
| `INT.TP` | Tracked Packet International |
| `INT.SP.AIR` | Small Packet International Air |

**Rate.** A `mailing-scenario` with the customer number, the contract id when there is one, the
weight and dimensions, the sender's postcode, and the destination: a Canadian postcode, a US ZIP
code, or another country's code. Every quote is a rate: its service code, its `service-name` (else
the table), the amount `price-details/due` in `CAD`, `days` the `expected-transit-time`.
`Shipment::$service` does not filter them. Rates come cheapest first; `toPickupPoint` is `false`.

**Ship.** Without a contract, a non-contract shipment, paid by the card on file; with one, a
contract shipment in a group named after today's date (`Ymd`), paid on account
(`intended-method-of-payment Account`). The request carries the sender's postcode as shipping
point, the service `Shipment::$service` (else `DOM.EP` in Canada, `INT.XP` elsewhere), sender and
destination (name, company, phone, two address lines, city, province option, country, postcode),
the signature option, the weight and dimensions, the print preferences (`label_format`), the
reference (35 characters) as `customer-ref-1`. A shipment leaving Canada adds customs: the first
parcel's currency, reason `SOG` (sale of goods), one line with the `description`, the total weight,
the first parcel's `value` (1.00 when it has none) and one unit. One shipment is made for all the
parcels, with the total weight.

The tracking number is the `tracking-pin`; then the `label` link is fetched with the API
credentials: its document becomes the label's `content`, the link its `url`. When the label is not
ready yet, the booking succeeds without content (a comment in `ShippingAction` says `GetSlip`
fetches it later; `GetSlip` is not supported, so the link in `url` is the way to it, with the same
basic authentication). The format is `application/zpl` for `ZPL`, `application/pdf` otherwise;
`trackingUrl` is
`https://www.canadapost-postescanada.ca/track-reperage/en#/search?searchFor=<number>`. A response
without a PIN is a `CarrierException` (`Canada Post booked no shipment.`). `pickupPoint` is not
sent.

**Track.** `Accept-language: fr-CA` when the locale starts with `fr`, else `en-CA`. The
significant events: date and time (in PHP's default time zone), description, `site province` as
location, the event identifier as `code`, sorted oldest first. The status is the latest event's,
or `DELIVERED` when the answer has an `actual-delivery-date`. An event's status, read in this
order:

| Event identifier | Or the description contains | `TrackingStatus` |
|---|---|---|
| `1408`, `1409`, `1421` to `1437`, `1441`, `1442`, `1497`, `1498` | `delivered` | `DELIVERED` |
| `0500` | `out for delivery` | `OUT_FOR_DELIVERY` |
| `1701` to `1707` | `available for pickup` | `AVAILABLE_FOR_PICKUP` |
| | `return` | `RETURNED` |
| `2600` to `2604` | `attempted`, `unable` | `EXCEPTION` |
| `3000`, `3001`, `3002` | `electronic information submitted`, `shipment information received` | `PENDING` |
| any other | | `IN_TRANSIT` |

**Pickup points.** Post offices that take Deliver to Post Office parcels (`d2po=true`) near the address's
postcode and city, at most `min(50, limit)`; the parcel is not used. Each office: its `office-id`,
name, address line, postcode and city, country `CA`, coordinates, the distance converted from
kilometres to metres. Opening hours are not read: `openingHours` is always empty. The offices are
kept in Canada Post's order and cut to `limit`.

> [!WARNING]
> Known defect: opening hours are never read, so `openingHours` is always empty, where Canada Post's answer carries them.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnibus\CanadaPost\CanadaPostGatewayFactory;
use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Symfony\Component\HttpClient\HttpClient;

$canadaPost = (new CanadaPostGatewayFactory(HttpClient::create()))->create([
    'username' => getenv('CANADA_POST_USERNAME'),
    'password' => getenv('CANADA_POST_PASSWORD'),
    'customer_number' => getenv('CANADA_POST_CUSTOMER'),
    'sandbox' => true,
]);

$shipment = new Shipment(
    new Address('Glitch Art', ['100 King St W'], 'M5X 1A9', 'Toronto', 'CA', phone: '416-555-0100'),
    new Address('Ada Lovelace', ['1 Wellington St'], 'K1A 0A9', 'Ottawa', 'CA', email: 'ada@example.org'),
    [new Parcel(1500, 30, 20, 10)],
    service: 'DOM.EP',
    reference: 'CMD-1042',
    options: ['sender_province' => 'ON', 'recipient_province' => 'ON', 'label_format' => 'PDF'],
);

foreach ($canadaPost->rate($shipment) as $rate) {
    printf("%-7s %-18s %6.2f %s, %s days\n", $rate->service, $rate->label, $rate->amount / 100, $rate->currency, $rate->days ?? '?');
}

$label = $canadaPost->ship($shipment);
if (null !== $label->content) {
    file_put_contents($label->trackingNumber.'.pdf', $label->content);
}
echo $label->trackingNumber, ' ', $label->trackingUrl, "\n";

$tracking = $canadaPost->track($label->trackingNumber, 'en');
echo $tracking->status->value, ' ', $tracking->latest()?->location, "\n";

foreach ($canadaPost->pickupPoints($shipment->recipient, 3) as $point) {
    printf("%s %s %d m\n", $point->id, $point->name, $point->distance);
}
```

In Symfony:

```yaml
omnibus:
    gateways:
        canada_post:
            factory: canada_post
            options:
                username: '%env(CANADA_POST_USERNAME)%'
                password: '%env(CANADA_POST_PASSWORD)%'
                customer_number: '%env(CANADA_POST_CUSTOMER)%'
                contract_id: '%env(default::CANADA_POST_CONTRACT)%'   # empty: non-contract shipments
                sandbox: '%env(bool:CANADA_POST_SANDBOX)%'
```

## Errors

Every failure is an `Omnibus\Exception\CarrierException` whose `carrier` is `canada_post`:

- an HTTP status of 400 or more, or a `messages` answer: the first message's `description` (else
  `HTTP <status>`), its `code` in `carrierCode`;
- a body that is not XML, or an HTML page: `Canada Post answered with a body that is not XML.`;
- a network error: `Canada Post request failed: ...`;
- `Canada Post booked no shipment.` when no PIN comes back.

A failed label download after the shipment is created is not raised (above). A missing
`username`, `password` or `customer_number` is an `InvalidConfigException` when the gateway is
built; `GetSlip`, `Cancel` and `Paperless` raise `RequestNotSupportedException` before any call.

## Verified, and not

Tested offline: `Tests/CanadaPostGatewayTest.php` (rates in `CAD` cheapest first with their
transit days, the weight in kilograms, the postcode without its space, the rate media type; a
non-contract shipment with its province and service, its label fetched from the artifact link; a
contract shipment on the contract path with its contract id; tracking with its location and the
delivered status; post offices with their distance in metres) and
`Tests/CanadaPostContractTest.php`, the family's conformance suite on recorded answers. The example
above was run in the core's harness on those recorded answers.

Not verified: the README says the package is built from Canada Post's published API documentation
and was not yet run against the development environment; that needs the credentials above.

## Responsibility

An independent, unofficial client, not affiliated with Canada Post Corporation. Whoever runs it
complies with Canada Post's terms ([Developer Program
terms](https://www.canadapost-postescanada.ca/cpc/en/support/kb/company-policies/terms-conditions/developer-portal-terms-and-conditions.page)):
see the README's Responsibility section.
