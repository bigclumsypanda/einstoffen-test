<?php

declare(strict_types=1);

namespace App\Tests\Services;

use App\Exceptions\FakeUpsException;
use App\Services\FakeUps;
use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockResponse;
use Config\App;
use Config\FakeUps as FakeUpsConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

final class FakeUpsTest extends CIUnitTestCase
{
    public function testUsesSeparateAddressLookupAndCreationRequests(): void
    {
        $client = $this->createMock(CURLRequest::class);
        $calls = [];

        $responses = [
            [200, '{"valid":true}'],
            [404, '{}'],
            [201, '{"id":"stable-id"}']
        ];

        $client->expects($this->exactly(3))->method('request')->willReturnCallback(
            function ($method, $path, $options) use (&$calls, &$responses) {
                $calls[] = [$method, $path, $options];
                [$status, $body] = array_shift($responses);
                $mockResponse = new MockResponse(new App());

                return $mockResponse->setStatusCode($status)->setBody($body);
            },
        );

        $provider = new FakeUps(null, $client);
        $address = ['street' => 'A', 'city' => 'Bern', 'postalCode' => '3000', 'country' => 'CH'];

        $this->assertTrue($provider->validateAddress($address)['valid']);
        $this->assertNull($provider->findShipment('stable-id'));
        $this->assertSame('stable-id', $provider->createShipment(['id' => 'stable-id'])['id']);
        $this->assertSame(['POST', '/address-validation', ['http_errors' => false, 'json' => $address]], $calls[0]);
        $this->assertSame(['GET', '/shipments/stable-id', ['http_errors' => false]], $calls[1]);
        $this->assertSame(['POST', '/shipments', ['http_errors' => false, 'json' => ['id' => 'stable-id']]], $calls[2]);
    }

    public function testTimeoutConfigurationAndClientIsolation(): void
    {
        $config = new FakeUpsConfig();
        $config->timeout = 10;
        $provider = new FakeUps($config);
        $client = $this->getPrivateProperty($provider, 'client');
        $options = $this->getPrivateProperty($client, 'config');

        $this->assertSame(10, $options['timeout']);
        $this->assertSame(10, $options['connect_timeout']);
        $this->assertNotSame($client, $this->getPrivateProperty(new FakeUps($config), 'client'));
    }

    public static function badResponses(): array
    {
        return [
            [500, '{"error":"bad"}'],
            [302, '{}'],
            [200, ''],
            [200, 'not json'],
            [200, '[]']
        ];
    }

    #[DataProvider('badResponses')]
    public function testRejectsBadResponse(int $status, string $body): void
    {
        $client = $this->createMock(CURLRequest::class);
        $client->method('request')->willReturn((new MockResponse(new App()))->setStatusCode($status)->setBody($body));

        $this->expectException(FakeUpsException::class);

        (new FakeUps(null, $client))->createShipment([]);
    }

    public function testNormalizesTransportException(): void
    {
        $client = $this->createMock(CURLRequest::class);

        $client->method('request')->willThrowException(new RuntimeException('Connection refused'));

        $this->expectException(FakeUpsException::class);
        $this->expectExceptionMessage('Connection refused');

        (new FakeUps(null, $client))->createShipment([]);
    }
}
