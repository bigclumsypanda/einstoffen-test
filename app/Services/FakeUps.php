<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\FakeUpsException;
use CodeIgniter\HTTP\CURLRequest;
use Config\FakeUps as FakeUpsConfig;
use Config\Services;
use JsonException;
use Throwable;

class FakeUps
{
    private CURLRequest $client;

    public function __construct(?FakeUpsConfig $config = null, ?CURLRequest $client = null)
    {
        $config ??= config(FakeUpsConfig::class);

        $options = [
            'baseURI' => $config->baseUrl,
            'timeout' => $config->timeout,
            'connect_timeout' => $config->timeout,
            'http_errors' => false,
        ];

        $this->client = $client ?? Services::curlrequest($options, null, null, false);
    }

    public function createShipment(array $payload): array
    {
        return $this->request('POST', '/shipments', $payload);
    }

    public function validateAddress(array $payload): array
    {
        return $this->request('POST', '/address-validation', $payload);
    }

    public function findShipment(string $id): ?array
    {
        return $this->request('GET', '/shipments/' . rawurlencode($id), null, true);
    }

    private function request(string $method, string $path, ?array $payload, bool $allowMissing = false): ?array
    {
        try {
            $options = ['http_errors' => false];

            if ($payload !== null) {
                $options['json'] = $payload;
            }

            $response = $this->client->request($method, $path, $options);
            $status = $response->getStatusCode();

            if ($allowMissing && $status === 404) {
                return null;
            }

            if ($status < 200 || $status >= 300) {
                throw new FakeUpsException('FakeUPS HTTP ' . $status . ' for ' . $path, $status);
            }

            $body = (string) $response->getBody();

            if (!$body) {
                throw FakeUpsException::forEmptyResponse($status);
            }

            $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

            if (!is_array($data) || array_is_list($data)) {
                throw FakeUpsException::forInvalidResponse($status);
            }

            return $data;
        } catch (FakeUpsException $e) {
            throw $e;
        } catch (JsonException $e) {
            throw new FakeUpsException('FakeUPS returned invalid JSON.', 0, $e);
        } catch (Throwable $e) {
            throw new FakeUpsException('FakeUPS request failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
