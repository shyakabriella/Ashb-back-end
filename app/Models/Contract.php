<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'agreement_date',
        'effective_date',
        'client_name',
        'client_tin',
        'hotel_name',
        'website_name',
        'discount_percent',
        'standard_monthly_fee',
        'discounted_monthly_fee',
        'post_discount_monthly_fee',
        'provider_representative_name',
        'provider_signature_text',
        'provider_signed_date',
        'client_representative_name',
        'client_signature_text',
        'client_signed_date',
        'kpi_recipient',
        'billing_cycle',
        'invoice_day',
        'is_active',
        'pdf_path',
    ];

    protected $casts = [
        'agreement_date' => 'date:Y-m-d',
        'effective_date' => 'date:Y-m-d',
        'provider_signed_date' => 'date:Y-m-d',
        'client_signed_date' => 'date:Y-m-d',
        'discount_percent' => 'decimal:2',
        'standard_monthly_fee' => 'decimal:2',
        'discounted_monthly_fee' => 'decimal:2',
        'post_discount_monthly_fee' => 'decimal:2',
        'invoice_day' => 'integer',
        'is_active' => 'boolean',
    ];
}