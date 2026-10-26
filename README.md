# omnibus/canada-post

Canada Post for [omnisense/omnibus](https://github.com/glitchr-omnisense/omnibus): Get Rates,
non-contract shipments (paid by the card on file) or contract shipments with their labels,
tracking and the nearest post offices - the XML REST web services with basic auth.

```php
$gateway = (new CanadaPostGatewayFactory($http))->create($options);   // $http: the application's HTTP client - none given, the factory makes its own; the options below
```

No framework needed: the package requires `omnisense/omnibus` and `symfony/http-client`. In a
Symfony application, the same through the bundle's configuration:

```yaml
omnibus:
    gateways:
        canada_post:
            factory: canada_post
            options:
                username: '%env(CANADA_POST_USERNAME)%'    # the API key's username and password
                password: '%env(CANADA_POST_PASSWORD)%'
                customer_number: '%env(CANADA_POST_CUSTOMER)%'
                contract_id: '%env(CANADA_POST_CONTRACT)%' # optional: commercial contract
                sandbox: true
                rates: [...]                               # optional: configured prices instead of Get Rates
```

The service is the service code (DOM.RP Regular, DOM.EP Expedited, DOM.XP Xpresspost, DOM.PC
Priority, USA.EP, USA.XP, INT.XP...). Shipment options: `sender_province` and `recipient_province`
(two letters, Canadian addresses need them), `signature`, `label_format` (PDF, ZPL), `description`
(for customs).

Credentials: a [Canada Post Developer Program](https://www.canadapost-postescanada.ca/information/app/drc/home)
account gives development and production API keys and your customer number; a contract id comes
with a commercial agreement.

Built from Canada Post's published API documentation and tested on recorded answers; not yet run
against the development environment: that needs the credentials above.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by Canada Post Corporation. It reaches their service on behalf of whoever runs it, and it
does not redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT
licence covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with Canada Post's terms
([Developer Program
terms](https://www.canadapost-postescanada.ca/cpc/en/support/kb/company-policies/terms-conditions/developer-portal-terms-and-conditions.page)),
for obtaining whatever they require (an account, a key, a licence or subscription, an attribution or
a citation, a rate limit to respect), and for any use made of what it returns. The authors publish
it free of charge, make no commercial use of it, and accept no liability for how it is used (see
[LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
