<?php

namespace Config;

use App\Validation\InvoiceRules;
use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Validation\StrictRules\CreditCardRules;
use CodeIgniter\Validation\StrictRules\FileRules;
use CodeIgniter\Validation\StrictRules\FormatRules;
use CodeIgniter\Validation\StrictRules\Rules;

class Validation extends BaseConfig
{
    // --------------------------------------------------------------------
    // Setup
    // --------------------------------------------------------------------

    /**
     * Stores the classes that contain the
     * rules that are available.
     *
     * @var list<string>
     */
    public array $ruleSets = [
        Rules::class,
        InvoiceRules::class,
        FormatRules::class,
        FileRules::class,
        CreditCardRules::class,
    ];

    /**
     * Specifies the views that are used to display the
     * errors.
     *
     * @var array<string, string>
     */
    public array $templates = [
        'list' => 'CodeIgniter\Validation\Views\list',
        'single' => 'CodeIgniter\Validation\Views\single',
    ];

    // --------------------------------------------------------------------
    // Rules
    // --------------------------------------------------------------------

    /**
     * @var array<string, string>
     */
    public array $invoiceCreated = [
        'event' => 'required|string|in_list[invoice.created]',
        'invoice_id' => 'required|string|max_length[64]',
        'customer.id' => 'required|string|max_length[64]',
        'customer.name' => 'required|string|max_length[255]',
        'customer.email' => 'required|string|valid_email|max_length[255]',
        'amount' => 'required|invoiceAmount',
        'currency' => 'required|string|regex_match[/^[A-Z]{3}$/D]',
        'status' => 'required|string|max_length[32]',
        'due_date' => 'required|string|valid_date[Y-m-d]',
        'created_at' => 'required|string|valid_date[Y-m-d\TH:i:s\Z]',
    ];

    /**
     * @var array<string, string>
     */
    public array $invoiceDeleted = [
        'event' => 'required|string|max_length[32]|in_list[invoice.deleted]',
        'invoice_id' => 'required|string|max_length[64]',
    ];
}
