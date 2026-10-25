<?php

namespace Omnibus\CanadaPost\Tests;

use Omnibus\CanadaPost\CanadaPostGatewayFactory;
use Omnibus\GatewayFactoryInterface;
use Omnibus\Model\Address;
use Omnibus\Model\Parcel;
use Omnibus\Model\Shipment;
use Omnibus\Testing\GatewayContractTests;
use Omnibus\Testing\RecordedHttpClient;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** The conformance suite on Canada Post's recorded answers (the development environment, a non-contract shipment). */
final class CanadaPostContractTest extends TestCase
{
    use GatewayContractTests;

    protected function gatewayFactory(HttpClientInterface $http): GatewayFactoryInterface
    {
        return new CanadaPostGatewayFactory($http);
    }

    protected function gatewayOptions(): array
    {
        return ['username' => 'user', 'password' => 'pass', 'customer_number' => '0001234567', 'sandbox' => true];
    }

    protected function contractShipment(): Shipment
    {
        return new Shipment(new Address('Glitch Art', ['123 Rue Sainte-Catherine'], 'H2X 1K4', 'Montréal', 'CA', phone: '5145551234'), new Address('Alex Martin', ['100 Queen St W'], 'M5H 2N2', 'Toronto', 'CA'), [new Parcel(1500, 30, 20, 10)], reference: 'ORDER-1042', options: ['sender_province' => 'QC', 'recipient_province' => 'ON']);
    }

    protected function contractTrackingNumber(): string
    {
        return '12345678901234';
    }

    protected function recordedAnswers(): array
    {
        $xml = static fn (string $body) => RecordedHttpClient::answer($body, 200, ['content-type' => 'application/xml']);

        return [
            '/rs/ship/price' => $xml('<price-quotes xmlns="http://www.canadapost.ca/ws/ship/rate-v4"><price-quote><service-code>DOM.EP</service-code><service-name>Expedited Parcel</service-name><price-details><due>14.52</due></price-details><service-standard><expected-transit-time>2</expected-transit-time></service-standard></price-quote><price-quote><service-code>DOM.RP</service-code><service-name>Regular Parcel</service-name><price-details><due>13.80</due></price-details><service-standard><expected-transit-time>4</expected-transit-time></service-standard></price-quote></price-quotes>'),
            '/rs/artifact/' => RecordedHttpClient::answer('%PDF-1.4 cp', 200, ['content-type' => 'application/pdf']),
            '/ncshipment' => $xml('<non-contract-shipment-info xmlns="http://www.canadapost.ca/ws/ncshipment-v4"><shipment-id>406951321983787352</shipment-id><tracking-pin>12345678901234</tracking-pin><links><link rel="self" href="https://ct.soa-gw.canadapost.ca/rs/0001234567/ncshipment/406951321983787352" media-type="application/vnd.cpc.ncshipment-v4+xml"/><link rel="label" href="https://ct.soa-gw.canadapost.ca/rs/artifact/76108cb5192002d5/10238/0" media-type="application/pdf"/></links></non-contract-shipment-info>'),
            '/vis/track/pin/' => $xml('<tracking-detail xmlns="http://www.canadapost.ca/ws/track-v2"><pin>12345678901234</pin><actual-delivery-date>2026-10-02</actual-delivery-date><significant-events><occurrence><event-identifier>1408</event-identifier><event-date>2026-10-02</event-date><event-time>10:15:00</event-time><event-description>Item successfully delivered</event-description><event-site>TORONTO</event-site><event-province>ON</event-province></occurrence><occurrence><event-identifier>0100</event-identifier><event-date>2026-10-01</event-date><event-time>08:00:00</event-time><event-description>Item processed</event-description><event-site>MONTREAL</event-site><event-province>QC</event-province></occurrence></significant-events></tracking-detail>'),
            '/rs/postoffice' => $xml('<post-office-list xmlns="http://www.canadapost.ca/ws/postoffice"><post-office><office-id>0000123</office-id><name>SHOPPERS DRUG MART #0862</name><distance>0.62</distance><address><office-address>700 BAY ST</office-address><city>TORONTO</city><postal-code>M5G1Z6</postal-code><latitude>43.6565</latitude><longitude>-79.3850</longitude></address></post-office><post-office><office-id>0000124</office-id><name>TORONTO STN A</name><distance>1.10</distance><address><office-address>25 THE ESPLANADE</office-address><city>TORONTO</city><postal-code>M5E1W5</postal-code></address></post-office><post-office><office-id>0000125</office-id><name>REXALL #7311</name><distance>1.60</distance><address><office-address>63 WELLESLEY ST E</office-address><city>TORONTO</city><postal-code>M4Y1G7</postal-code></address></post-office></post-office-list>'),
        ];
    }
}
