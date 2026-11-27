<?php
namespace Database\Seeders;

use App\Models\{Zone, WasteCategory, CollectionPoint, Schedule, ScheduleException, LocalRule, Household};
use Database\Seeders\Concerns\AllowsDemoSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoConfigurationSeeder extends Seeder
{
    use AllowsDemoSeeding;

    public function run(): void
    {
        $this->assertDemoSeedingAllowed();
        $this->call(ReferenceDataSeeder::class);

        // Fictional examples only. They are NOT official schedules of any real barangay.
        $zoneNames = [
            'pine' => 'Demo Purok Pine',
            'ridge' => 'Demo Purok Ridge',
            'creek' => 'Demo Purok Creek',
        ];
        $zones = [];
        foreach ($zoneNames as $key => $legacyName) {
            // Preserve original zone IDs when sample data have already been seeded.
            $marker='LINIS-DEMO-ZONE-'.$key;
            $name=preg_replace('/^Demo /','',$legacyName);
            $zone=Zone::where('demo_key',$marker)->first() ?? Zone::where('name',$legacyName)->first();
            if (! $zone) {
                if (Zone::where('name',$name)->exists()) {
                    throw new \RuntimeException('A collection zone named '.$name.' already exists. Review it before importing sample records.');
                }
                $zone=Zone::create(['demo_key'=>$marker,'name'=>$name,
                    'description'=>'Purok collection service area','is_active'=>true]);
            } elseif ($zone->name===$legacyName) {
                $zone->name=$name;
                $zone->demo_key=$marker;
                if ($zone->description==='Fictional LINIS test zone. Replace with authorized local data.') {
                    $zone->description='Purok collection service area';
                }
                $zone->save();
            }
            $zones[$key]=$zone;
        }

        $categories = [
            'residual' => WasteCategory::where('national_type','residual')->firstOrFail(),
            'recyclable' => WasteCategory::where('national_type','recyclable')->firstOrFail(),
            'biodegradable' => WasteCategory::where('national_type','biodegradable')->firstOrFail(),
        ];

        $points = [];
        foreach ($zones as $key => $zone) {
            $points[$key] = CollectionPoint::firstOrCreate(
                ['demo_key' => 'LINIS-DEMO-POINT-'.$key],
                [
                    'zone_id' => (string) $zone->id,
                    'name' => ucfirst($key).' Collection Station',
                    'landmark' => 'Community collection area',
                    'instructions' => 'Bring properly sorted waste only during the published collection window.',
                    'is_active' => true,
                ]
            );
            if ($points[$key]->name==='Demo '.$key.' collection station') {
                $points[$key]->name=ucfirst($key).' Collection Station';
                if ($points[$key]->landmark==='Fictional community collection point') {
                    $points[$key]->landmark='Community collection area';
                }
                $points[$key]->save();
            }
        }

        // key, zone, waste category, Sunday=0 ... Saturday=6, times, pickup method, point.
        $scheduleRows = [
            ['pine-regular','pine','residual',1,'07:00','09:00','designated_point','pine'],
            ['pine-recycle','pine','recyclable',3,'10:00','12:00','curbside',null],
            ['pine-bio','pine','biodegradable',6,'07:30','10:00','truck_handover',null],
            ['ridge-regular','ridge','residual',2,'06:30','08:30','truck_handover',null],
            ['ridge-recycle','ridge','recyclable',4,'13:00','15:00','designated_point','ridge'],
            ['ridge-bio','ridge','biodegradable',6,'08:00','10:00','curbside',null],
            ['creek-regular','creek','residual',1,'08:00','10:00','curbside',null],
            ['creek-recycle','creek','recyclable',4,'08:00','10:00','truck_handover',null],
            ['creek-bio','creek','biodegradable',6,'06:30','08:30','designated_point','creek'],
        ];

        $schedules = [];
        foreach ($scheduleRows as [$key,$zoneKey,$categoryKey,$weekday,$start,$end,$method,$pointKey]) {
            $schedules[$key] = Schedule::firstOrCreate(
                ['demo_key' => 'LINIS-DEMO-SCHEDULE-'.$key],
                [
                    'zone_id' => (string) $zones[$zoneKey]->id,
                    'category_id' => (string) $categories[$categoryKey]->id,
                    'collection_point_id' => $pointKey ? (string) $points[$pointKey]->id : null,
                    'weekday' => $weekday,
                    'start_time' => $start,
                    'end_time' => $end,
                    'collection_method' => $method,
                    'effective_from' => now()->subMonths(2)->startOfMonth()->toDateString(),
                    'effective_until' => null,
                    'instructions' => match ($method) {
                        'designated_point' => 'Residents bring waste to the posted barangay collection point.',
                        'truck_handover' => 'Wait for the truck announcement and hand waste directly to the crew.',
                        'curbside' => 'Prepare segregated containers at the house-front pickup area.',
                    },
                    'publication_status' => 'published',
                    'published_at' => now()->subWeeks(2),
                    'revision' => 1,
                ]
            );
        }

        $nextMonday = Carbon::today()->next(Carbon::MONDAY)->toDateString();
        ScheduleException::firstOrCreate(
            ['demo_key' => 'LINIS-DEMO-EXCEPTION-PINE-REGULAR'],
            [
                'schedule_id' => (string) $schedules['pine-regular']->id,
                'affected_date' => $nextMonday,
                'action' => 'time_change',
                'replacement_date' => null,
                'replacement_start_time' => '09:30',
                'replacement_end_time' => '11:00',
                'reason' => 'Fictional collection truck maintenance notice for demo purposes.',
                'publication_status' => 'published',
                'created_by' => null,
            ]
        );

        $rules = [
            ['all-sorting',null,null,'Sort waste before collection',
                'Separate regular/residual, reusable/recyclable and biodegradable waste. Follow the schedule for your zone.'],
            ['pine-collection','pine','residual','Pine collection station instructions',
                'Residual waste must be taken to the designated station only during the displayed time window.'],
            ['ridge-recycle','ridge','recyclable','Ridge recyclable collection rules',
                'Keep paper and reusable containers dry and clean when bringing them to the collection point.'],
            ['creek-bio','creek','biodegradable','Creek biodegradable collection rules',
                'Use a covered container for food and garden waste; do not mix with regular trash.'],
        ];

        foreach ($rules as [$key,$zoneKey,$categoryKey,$title,$body]) {
            LocalRule::firstOrCreate(
                ['demo_key'=>'LINIS-DEMO-RULE-'.$key],
                [
                    'zone_id' => $zoneKey ? (string) $zones[$zoneKey]->id : null,
                    'category_id' => $categoryKey ? (string) $categories[$categoryKey]->id : null,
                    'title' => $title,
                    'content' => $body,
                    'effective_from' => now()->subMonths(2)->startOfMonth()->toDateString(),
                    'effective_until' => null,
                    'publication_status' => 'published',
                    'version' => 1,
                    'published_at' => now()->subWeeks(2),
                ]
            );
        }

        foreach ($zones as $key => $zone) {
            foreach (range(1, 6) as $number) {
                $household=Household::firstOrCreate(
                    ['demo_key'=>'LINIS-DEMO-HOUSEHOLD-'.$key.'-'.$number],
                    [
                        'reference' => strtoupper('PUROK-'.$key).'-'.str_pad((string)$number,3,'0',STR_PAD_LEFT),
                        'zone_id' => (string) $zone->id,
                        'location_description' => 'Household '.$number.' — '.$zone->name,
                        'is_active' => true,
                        'change_history' => [],
                    ]
                );
                if (str_starts_with((string)$household->location_description,'Fictional household reference ')) {
                    $household->location_description='Household '.$number.' — '.$zone->name;
                    $household->save();
                }
                $oldReference=strtoupper('DEMO-'.$key).'-'.str_pad((string)$number,3,'0',STR_PAD_LEFT);
                if ($household->reference===$oldReference) {
                    $household->reference=strtoupper('PUROK-'.$key).'-'.str_pad((string)$number,3,'0',STR_PAD_LEFT);
                    $household->save();
                }
            }
        }

        $this->command?->info('LINIS demo configuration created or retained: 3 zones, 3 points, 9 weekly schedules, 1 exception, 4 rules and 18 household references.');
    }
}
