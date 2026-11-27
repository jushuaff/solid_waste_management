<?php
namespace App\Support;

use App\Models\FeeProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Starting selection of common Philippine receiving institutions, not a claim
 * of exhaustive/current BSP authorization. Superadmins may add institutions.
 */
final class PaymentProviders
{
    public const DEFAULTS=[
        'wallet'=>[
            'GCash','Maya Wallet','GrabPay','ShopeePay','Coins.ph',
            'PalawanPay','Lazada Wallet','Bayad',
        ],
        'bank'=>[
            'BDO Unibank','Bank of the Philippine Islands (BPI)',
            'Metrobank','Land Bank of the Philippines (LANDBANK)',
            'Philippine National Bank (PNB)','Rizal Commercial Banking Corporation (RCBC)',
            'UnionBank','Security Bank','China Banking Corporation (Chinabank)',
            'EastWest Bank','Asia United Bank (AUB)','Development Bank of the Philippines (DBP)',
            'Philippine Savings Bank (PSBank)','Bank of Commerce','Maybank Philippines',
            'HSBC Philippines','CTBC Bank Philippines','Philippine Veterans Bank',
            'Philippine Business Bank','Sterling Bank of Asia','Bank of Makati',
            'CIMB Bank Philippines','GoTyme Bank','Maya Bank',
            'Tonik Digital Bank','UnionDigital Bank','UNO Digital Bank',
            'Overseas Filipino Bank (OFBank)','MariBank Philippines','OwnBank',
        ],
    ];

    public static function all():Collection
    {
        $options=collect();
        foreach(self::DEFAULTS as $type=>$names){
            foreach($names as $name)$options->push(['name'=>$name,'type'=>$type,'builtin'=>true]);
        }
        foreach(FeeProvider::where('is_active',true)->get() as $custom){
            $name=(string)$custom->name;
            if($options->contains(fn($option)=>Str::lower($option['name'])===Str::lower($name)))continue;
            $options->push(['name'=>$name,'type'=>(string)$custom->type,'builtin'=>false]);
        }
        return $options->sortBy(fn($option)=>$option['type'].'-'.Str::lower($option['name']))->values();
    }

    public static function typeOf(string $name):?string
    {
        foreach(self::all() as $provider){
            if(Str::lower($provider['name'])===Str::lower(trim($name)))return $provider['type'];
        }
        return null;
    }

    public static function isKnown(string $name):bool
    {
        return self::typeOf($name)!==null;
    }
}
