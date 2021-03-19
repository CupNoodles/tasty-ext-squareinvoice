<?php

/**
 * Model configuration options for settings model.
 */

return [
    'form' => [
        'toolbar' => [
            'buttons' => [
                'save' => ['label' => 'lang:admin::lang.button_save', 'class' => 'btn btn-primary', 'data-request' => 'onSave'],
                'saveClose' => [
                    'label' => 'lang:admin::lang.button_save_close',
                    'class' => 'btn btn-default',
                    'data-request' => 'onSave',
                    'data-request-data' => 'close:1',
                ],
            ],
        ],
        'fields' => [
            'enable_square_invoices_on_pickup' => [
                'label' => 'lang:cupnoodles.squareinvoice::default.enable_pickup',
                'type' => 'switch',
                'span' => 'left',
                'default' => FALSE,
            ],
            'enable_square_invoices_on_delivery' => [
                'label' => 'lang:cupnoodles.squareinvoice::default.enable_delivery',
                'type' => 'switch',
                'span' => 'left',
                'default' => FALSE,
            ],
            'production_mode' => [
                'label' => 'lang:cupnoodles.squareinvoice::default.production_mode',
                'type' => 'switch',
                'span' => 'left',
                'default' => FALSE,
                'on' => 'lang:cupnoodles.squareinvoice::default.label_production',
                'off' => 'lang:cupnoodles.squareinvoice::default.label_sandbox',
            ],
            'sandbox_application_id' => [
                'label' => 'lang:cupnoodles.squareinvoice::default.sandbox_application_id',
                'type' => 'text',
                'span' => 'left',
                'default' => FALSE,
            ],
            'sandbox_access_token' => [
                'label' => 'lang:cupnoodles.squareinvoice::default.sandbox_access_token',
                'type' => 'text',
                'span' => 'left',
                'default' => FALSE,
            ],
            'sandbox_location_id' => [
                'label' => 'lang:cupnoodles.squareinvoice::default.sandbox_location_id_label',
                'type' => 'text',
                'span' => 'left',
                'default' => FALSE
            ],
            'production_application_id' => [
                'label' => 'lang:cupnoodles.squareinvoice::default.production_application_id',
                'type' => 'text',
                'span' => 'left',
                'default' => FALSE,
            ],
            'production_access_token' => [
                'label' => 'lang:cupnoodles.squareinvoice::default.production_access_token',
                'type' => 'text',
                'span' => 'left',
                'default' => FALSE,
            ],
            'invoice_title' => [
                'label' => 'lang:cupnoodles.squareinvoice::default.invoice_title',
                'type' => 'text',
                'span' => 'left',
                'default' => FALSE,
            ],
            'invoice_description' => [
                'label' => 'lang:cupnoodles.squareinvoice::default.invoice_description',
                'type' => 'text',
                'span' => 'left',
                'default' => FALSE,
            ],
            'invoice_due_date_days' => [
                'label' => 'lang:cupnoodles.squareinvoice::default.invoice_due_date_days',
                'type' => 'number',
                'span' => 'left',
                'default' => '1',
            ]
        
        ],
        'rules' => [
        ],
    ],
];
