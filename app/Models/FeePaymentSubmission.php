<?php
namespace App\Models;

class FeePaymentSubmission extends Document
{
    protected $table='fee_payment_submissions';
    protected $casts=[
        'amount_centavos'=>'integer',
        'paid_on'=>'date',
        'reviewed_at'=>'datetime',
    ];

    public function charge(){return $this->belongsTo(GarbageFeeCharge::class,'charge_id');}
    public function household(){return $this->belongsTo(Household::class,'household_id');}
    public function receivingAccount(){return $this->belongsTo(FeeReceivingAccount::class,'receiving_account_id');}
    public function resident(){return $this->belongsTo(User::class,'resident_user_id');}
}
