<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issuing To is only meaningful when goods go to someone other than
 * the requester (e.g. a stock admin requesting on a non-login
 * resident's behalf). Making a requester pick a Recipient for their
 * own everyday supply requests would be redundant — IssueGoods falls
 * back to the requester's own name at issue time when this is blank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_issue_requests', function (Blueprint $table) {
            $table->foreignId('recipient_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_issue_requests', function (Blueprint $table) {
            $table->foreignId('recipient_id')->nullable(false)->change();
        });
    }
};
