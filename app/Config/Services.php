<?php

namespace Config;

use App\Models\AddressModel;
use App\Models\CustomerModel;
use App\Models\InvoiceModel;
use App\Services\AdminNotifier;
use App\Services\FakeUps;
use App\Services\InvoiceWebhookService;
use App\Services\MailDispatcher;
use App\Services\WebhookValidator;
use App\Services\Outbox;
use App\Services\ShipmentService;
use App\Models\OutboxModel;
use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 */
class Services extends BaseService
{
    public static function fakeups($getShared = true): FakeUps
    {
        if ($getShared) {
            return static::getSharedInstance('fakeups');
        }

        return new FakeUps();
    }

    public static function mailer($getShared = true): MailDispatcher
    {
        if ($getShared) {
            return static::getSharedInstance('mailer');
        }

        return new MailDispatcher(static::outbox());
    }

    public static function adminNotifier($getShared = true): AdminNotifier
    {
        if ($getShared) {
            return static::getSharedInstance('adminNotifier');
        }

        return new AdminNotifier(static::mailer(), service('logger'));
    }

    public static function webhookValidator($getShared = true): WebhookValidator
    {
        if ($getShared) {
            return static::getSharedInstance('webhookValidator');
        }

        return new WebhookValidator(service('validation'));
    }

    public static function invoiceWebhookService($getShared = true): InvoiceWebhookService
    {
        if ($getShared) {
            return static::getSharedInstance('invoiceWebhookService');
        }

        return new InvoiceWebhookService(
            model(CustomerModel::class),
            model(InvoiceModel::class),
            model(AddressModel::class),
            static::outbox(),
        );
    }
    public static function outbox($getShared = true): Outbox
    {
        if ($getShared) {
            return static::getSharedInstance('outbox');
        }
        return new Outbox(model(OutboxModel::class));
    }

    public static function shipmentService($getShared = true): ShipmentService
    {
        if ($getShared) {
            return static::getSharedInstance('shipmentService');
        }
        return new ShipmentService(static::fakeups(), static::mailer());
    }
}