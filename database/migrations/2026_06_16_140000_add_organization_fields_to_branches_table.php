<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('registration_number', 30)->nullable()->after('legal_name');
            $table->string('website', 200)->nullable()->after('email');
            $table->string('state', 100)->nullable()->after('city');
            $table->string('pincode', 12)->nullable()->after('state');
            $table->string('pan', 10)->nullable()->after('social_youtube');
            $table->string('gstin', 15)->nullable()->after('pan');
            $table->decimal('gst_rate', 5, 2)->default(18)->after('gstin');
            $table->decimal('cgst_rate', 5, 2)->default(9)->after('gst_rate');
            $table->decimal('sgst_rate', 5, 2)->default(9)->after('cgst_rate');
            $table->decimal('igst_rate', 5, 2)->default(18)->after('sgst_rate');
            $table->string('gst_registration_type', 20)->default('regular')->after('igst_rate');
            $table->string('gst_filing_frequency', 20)->default('monthly')->after('gst_registration_type');
            $table->boolean('gst_reverse_charge')->default(false)->after('gst_filing_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name',
                'registration_number',
                'website',
                'state',
                'pincode',
                'pan',
                'gstin',
                'gst_rate',
                'cgst_rate',
                'sgst_rate',
                'igst_rate',
                'gst_registration_type',
                'gst_filing_frequency',
                'gst_reverse_charge',
            ]);
        });
    }
};
