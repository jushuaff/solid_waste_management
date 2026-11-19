<?php
namespace Database\Seeders;
use App\Models\{Zone,WasteCategory};
use Illuminate\Database\Seeder;
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        if(Zone::count()===0){
            Zone::create(['name'=>'Sample Zone A','description'=>'Replace with approved zone name','is_active'=>true]);
        }
        foreach ([['Residual / Regular','residual','City / Municipality'],
                  ['Reusable / Recyclable','recyclable','Barangay / local partner'],
                  ['Biodegradable / Compostable','biodegradable','Barangay']] as $category){
            WasteCategory::firstOrCreate(['local_label'=>$category[0]],[
                'national_type'=>$category[1],'responsible_office'=>$category[2],'is_active'=>true]);
        }
    }
}
