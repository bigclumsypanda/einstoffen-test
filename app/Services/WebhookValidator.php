<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InvalidWebhookException;
use CodeIgniter\Validation\ValidationInterface;
use Config\Validation;
use JsonException;
use stdClass;

final class WebhookValidator
{
    public function __construct(private ValidationInterface $validation)
    {
    }

    /**
     * @throws JsonException
     */
    public function parse(string $body): array
    {
        try {
            $object = json_decode($body, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidWebhookException(['json' => 'Expected a JSON object.']);
        }

        if (!$object instanceof stdClass) {
            throw new InvalidWebhookException(['json' => 'Expected a JSON object.']);
        }

        $payload = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

        if (!$this->validate($payload)) {
            throw new InvalidWebhookException($this->errors());
        }

        $result = array_intersect_key($payload, $this->rulesFor($payload['event']));

        if ($payload['event'] === 'invoice.created') {
            $result['customer'] = array_intersect_key($payload['customer'], array_flip(['id', 'name', 'email']));
        }

        return $result;
    }

    public function validate(array $payload): bool
    {
        $event = $payload['event'] ?? null;

        return $this->validation->reset()
            ->setRules($this->rulesFor(is_string($event) ? $event : ''))
            ->run($payload);
    }

    public function errors(): array
    {
        return $this->validation->getErrors();
    }

    private function rulesFor(string $event): array
    {
        $rules = config(Validation::class);

        return match ($event) {
            'invoice.created' => $rules->invoiceCreated,
            'invoice.deleted' => $rules->invoiceDeleted,
            default => ['event' => 'required|string|in_list[invoice.created,invoice.deleted]'],
        };
    }
}
