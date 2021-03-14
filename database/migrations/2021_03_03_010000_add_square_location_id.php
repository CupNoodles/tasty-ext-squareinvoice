<?php

namespace CupNoodles\SquareInvoice\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Schema;

/**
 * 
 */
class AddSquareLocationId extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('locations', 'square_location_id')) {
            Schema::table('locations', function (Blueprint $table) {
                $table->string('square_location_id');
            });
        }

        if (!Schema::hasColumn('orders', 'square_order_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('square_order_id');
                $table->string('square_invoice_id');
            });
        }
        
    }

    public function down()
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['square_location_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['square_order_id']);
            $table->dropColumn(['square_invoice_id']);
        });
    }

}
