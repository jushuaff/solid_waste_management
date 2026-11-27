<?php
namespace App\Services;

use App\Models\{GarbageFeeCharge,GarbageFeePayment};

class FeeLedger
{
    /** Money is stored in integer centavos to avoid floating-point accounting errors. */
    public static function centavos(string $value):int
    {
        if(!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/',$value)){
            throw new \InvalidArgumentException('Enter a valid amount with up to two decimal places.');
        }
        $parts=explode('.',$value,2);
        return ((int)$parts[0])*100+(int)str_pad($parts[1]??'',2,'0');
    }

    public static function paid(GarbageFeeCharge $charge):int
    {
        $payments=$charge->relationLoaded('payments')
            ? $charge->payments
            : GarbageFeePayment::where('charge_id',(string)$charge->id)->get();
        return (int)$payments->filter(fn($row)=>$row->status==='recorded')
            ->sum(fn($row)=>(int)$row->amount_centavos);
    }

    public static function balance(GarbageFeeCharge $charge):int
    {
        return max(0,(int)$charge->amount_centavos-self::paid($charge));
    }

    public static function status(GarbageFeeCharge $charge):string
    {
        $paid=self::paid($charge);
        if($paid<=0)return 'Unpaid';
        return $paid>=(int)$charge->amount_centavos?'Paid':'Partial';
    }

    public static function money(int $centavos):string
    {
        return '₱'.number_format($centavos/100,2);
    }
}
