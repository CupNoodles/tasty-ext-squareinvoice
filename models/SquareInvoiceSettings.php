<?php

namespace CupNoodles\SquareInvoice\Models;

use Model;

/**
 * @method static instance()
 */
class SquareInvoiceSettings extends Model
{
    public $implement = ['System\Actions\SettingsModel'];

    // A unique code
    public $settingsCode = 'cupnoodles_square_invoice_settings';

    // Reference to field configuration
    public $settingsFieldsConfig = 'squareinvoicesettings';

    //
    //
    //
}
