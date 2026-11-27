<?php
namespace Database\Seeders;

use App\Models\{
    Zone, User, WasteCategory, Household, PickupInspection, MrfWeighIn,
    Report, Assignment, StatusHistory, Notice, Schedule
};
use Database\Seeders\Concerns\AllowsDemoSeeding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoOperationsSeeder extends Seeder
{
    use AllowsDemoSeeding;

    public function run(): void
    {
        $this->assertDemoSeedingAllowed();

        $zones = [
            'pine' => Zone::where('demo_key','LINIS-DEMO-ZONE-pine')->firstOrFail(),
            'ridge' => Zone::where('demo_key','LINIS-DEMO-ZONE-ridge')->firstOrFail(),
            'creek' => Zone::where('demo_key','LINIS-DEMO-ZONE-creek')->firstOrFail(),
        ];
        $categories = [
            'residual' => WasteCategory::where('national_type','residual')->firstOrFail(),
            'recyclable' => WasteCategory::where('national_type','recyclable')->firstOrFail(),
            'biodegradable' => WasteCategory::where('national_type','biodegradable')->firstOrFail(),
        ];
        $staff = [];
        foreach (['pine','ridge','creek'] as $key) {
            $staff[$key] = [
                'crew' => User::where('email','crew.'.$key.'@linis.test')->firstOrFail(),
                'eco' => User::where('email','ecoaide.'.$key.'@linis.test')->firstOrFail(),
            ];
        }
        $coordinators = [
            'pine' => User::where('email','coordinator.pine@linis.test')->firstOrFail(),
            'ridge' => User::where('email','coordinator.ridge@linis.test')->firstOrFail(),
            'creek' => User::where('email','admin@linis.test')->firstOrFail(),
        ];

        // 45 observations across the two completed weeks and current week.
        // Ridge deliberately scores 40% to demonstrate two-week red-purok alerts.
        // Sample week rates: Pine 80%, Ridge 40%, Creek 60%, never official claims.
        $passes = ['pine'=>4,'ridge'=>2,'creek'=>3];
        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);

        foreach (['pine','ridge','creek'] as $key) {
            $households = Household::where('zone_id',(string)$zones[$key]->id)
                ->where('demo_key','regex',new \MongoDB\BSON\Regex('^LINIS-DEMO-HOUSEHOLD-', 'i'))
                ->orderBy('reference')->get();
            if ($households->count() < 5) {
                throw new \RuntimeException('Run DemoConfigurationSeeder first: expected 5+ demo household references in '.$key);
            }
            foreach ([2,1,0] as $weeksAgo) {
                $start = $weekStart->copy()->subWeeks($weeksAgo);
                for ($i=0; $i<5; $i++) {
                    $day = 1 + ($i % 3);
                    // Current week: never create future inspection observations.
                    if ($weeksAgo === 0) $day = min($day, max(0,Carbon::today()->dayOfWeekIso - 1));
                    $at = $start->copy()->addDays($day)->setTime(8 + $i, 10);
                    if ($at->isFuture()) $at = now()->subMinutes($i + 1);
                    $segregated = $i < $passes[$key];
                    $inspection=PickupInspection::firstOrCreate(
                        ['demo_key'=>'LINIS-DEMO-INSPECTION-'.$key.'-'.$start->format('Ymd').'-'.$i],
                        [
                            'zone_id'=>(string)$zones[$key]->id,
                            'household_id'=>(string)$households[$i]->id,
                            'inspector_user_id'=>(string)$staff[$key]['eco']->id,
                            'inspected_at'=>$at,
                            'segregated'=>$segregated,
                            'note'=>$segregated
                                ? 'Waste was properly sorted.'
                                : 'Mixed waste observed during collection.',
                            'photo_path'=>null,
                            'change_history'=>[],
                        ]
                    );
                    if (str_starts_with((string)$inspection->note,'Demo observation:')) {
                        $inspection->note=$segregated?'Waste was properly sorted.':'Mixed waste observed during collection.';
                        $inspection->save();
                    }
                }
            }
        }

        // 18 weigh-in records: three categories x three zones x two month periods.
        // Diverted kg is never greater than recorded kg.
        $weightRows = [
            'residual' => [24.0,1.5],
            'recyclable' => [18.0,15.5],
            'biodegradable' => [16.0,12.0],
        ];
        foreach ([1,0] as $monthsAgo) {
            $month = Carbon::today()->startOfMonth()->subMonths($monthsAgo);
            foreach (['pine','ridge','creek'] as $zoneIndex=>$key) {
                foreach ($weightRows as $categoryKey => [$weight,$diverted]) {
                    $at = $month->copy()->addDays($monthsAgo===0?min(5 + $zoneIndex,Carbon::today()->day - 1):5+$zoneIndex)->setTime(10,20);
                    if ($at->isFuture()) $at=now()->subMinutes(10);
                    $weighIn=MrfWeighIn::firstOrCreate(
                        ['demo_key'=>'LINIS-DEMO-MRF-'.$month->format('Ym').'-'.$key.'-'.$categoryKey],
                        [
                            'zone_id'=>(string)$zones[$key]->id,
                            'category_id'=>(string)$categories[$categoryKey]->id,
                            'measured_at'=>$at,
                            'weight_kg'=>$weight + $zoneIndex,
                            'diverted_kg'=>$diverted + ($categoryKey==='residual'?0:($zoneIndex*.5)),
                            'recorded_by'=>(string)$staff[$key]['eco']->id,
                            'note'=>'MRF waste category weigh-in.',
                            'change_history'=>[],
                        ]
                    );
                    if ($weighIn->note==='Fictional MRF weigh-in data for MIT 103/104 demonstration only.') {
                        $weighIn->note='MRF waste category weigh-in.';
                        $weighIn->save();
                    }
                }
            }
        }

        // Each case has a matching status history; cases with assignments point to demo crews.
        // Reruns never overwrite changed case status, staff assignment, or notes.
        $caseRows = [
            // key, zone, reporter email, workflow endpoint, type, age hours
            ['pine-open','pine','resident.pine1@linis.test','Under Review','missed_collection',72],
            ['ridge-assigned','ridge','resident.ridge1@linis.test','Assigned','missed_collection',64],
            ['pine-progress','pine','resident.pine2@linis.test','In Progress','missed_collection',24],
            ['creek-resolved','creek','resident.creek1@linis.test','Resolved','missed_collection',96],
            ['ridge-exception','ridge','resident.ridge2@linis.test','Exception','missed_collection',55],
            ['creek-closed','creek','resident.creek2@linis.test','Closed','missed_collection',110],
            ['pine-rejected','pine','resident.pine1@linis.test','Rejected','missed_collection',80],
            ['ridge-dumping','ridge','resident.ridge1@linis.test','Under Review','illegal_dumping',4],
        ];
        $historyMap = [
            'Under Review' => ['Submitted','Under Review'],
            'Assigned' => ['Submitted','Under Review','Verified','Assigned'],
            'In Progress' => ['Submitted','Under Review','Verified','Assigned','In Progress'],
            'Resolved' => ['Submitted','Under Review','Verified','Assigned','In Progress','Resolved'],
            'Exception' => ['Submitted','Under Review','Verified','Assigned','In Progress','Exception'],
            'Closed' => ['Submitted','Under Review','Verified','Assigned','In Progress','Exception','Closed'],
            'Rejected' => ['Submitted','Under Review','Rejected'],
        ];
        $lastStatusPerCase = [];
        foreach ($caseRows as [$key,$zoneKey,$email,$status,$type,$age]) {
            $resident = User::where('email',$email)->firstOrFail();
            $crew = $staff[$zoneKey]['crew'];
            $hasAssignment = in_array($status,['Assigned','In Progress','Resolved','Exception','Closed'],true);
            $submittedAt = now()->subHours($age);
            $schedule = Schedule::where('zone_id',(string)$zones[$zoneKey]->id)
                ->where('category_id',(string)$categories['residual']->id)->first();
            $report = Report::firstOrCreate(
                ['demo_key'=>'LINIS-DEMO-CASE-'.$key],
                [
                    'tracking_id'=>'SWM-'.now()->format('Y').'-'.strtoupper(substr(sha1('LINIS-'. $key),0,10)),
                    'resident_id'=>(string)$resident->id,
                    'zone_id'=>(string)$zones[$zoneKey]->id,
                    'category_id'=>(string)$categories['residual']->id,
                    'schedule_id'=>$type==='missed_collection' && $schedule?(string)$schedule->id:null,
                    'incident_type'=>$type,
                    'incident_date'=>$submittedAt->toDateString(),
                    'location'=>$zones[$zoneKey]->name.' collection area',
                    'latitude'=>$type==='illegal_dumping'?16.4001:null,
                    'longitude'=>$type==='illegal_dumping'?120.6001:null,
                    'description'=>'Collection concern reported for review by the barangay office.',
                    'current_status'=>$status,
                    'submitted_at'=>$submittedAt,
                    'assigned_crew_id'=>$hasAssignment?(string)$crew->id:null,
                    'photo_path'=>null,
                ]
            );

            if (str_starts_with((string)$report->tracking_id,'SWM-DEMO-')) {
                $report->tracking_id='SWM-'.now()->format('Y').'-'.strtoupper(substr(sha1('LINIS-'.$key),0,10));
            }
            if (str_starts_with((string)$report->location,'FICTIONAL ')) {
                $report->location=$zones[$zoneKey]->name.' collection area';
            }
            if ($report->description==='Fictional MIT 103/104 demonstration record; this is not an actual allegation or pickup report.') {
                $report->description='Collection concern reported for review by the barangay office.';
            }
            if ($report->isDirty()) $report->save();

            if ($hasAssignment) {
                Assignment::firstOrCreate(
                    ['demo_key'=>'LINIS-DEMO-ASSIGNMENT-'.$key],
                    [
                        'report_id'=>(string)$report->id,
                        'crew_user_id'=>(string)$crew->id,
                        'assigned_by'=>(string)$coordinators[$zoneKey]->id,
                        'assigned_at'=>$submittedAt->copy()->addMinutes(45),
                        'outcome'=>in_array($status,['Resolved','Exception','Closed'],true)?$status:null,
                        'outcome_note'=>'Fictional crew action.',
                        'completed_at'=>in_array($status,['Resolved','Exception','Closed'],true)?$submittedAt->copy()->addHours(3):null,
                    ]
                );
            }

            $prior = null;
            foreach ($historyMap[$status] as $order=>$next) {
                StatusHistory::firstOrCreate(
                    ['demo_key'=>'LINIS-DEMO-HISTORY-'.$key.'-'.$order],
                    [
                        'report_id'=>(string)$report->id,
                        'changed_by'=>$order===0?null:(string)$coordinators[$zoneKey]->id,
                        'actor_type'=>$order===0?'system':'user',
                        'from_status'=>$prior,
                        'to_status'=>$next,
                        'reason'=>'Demonstration: status '.$next.'.',
                        'changed_at'=>$submittedAt->copy()->addMinutes($order*30),
                    ]
                );
                $prior=$next;
            }

            $notice=Notice::firstOrCreate(
                ['demo_key'=>'LINIS-DEMO-NOTICE-'.$key],
                [
                    'recipient_user_id'=>(string)$resident->id,
                    'report_id'=>(string)$report->id,
                    'title'=>'Demo report '.$status,
                    'message'=>'Your report '.$report->tracking_id.' is '.$status.'.',
                    'is_read'=>false,
                    'created_at'=>now()->subHours(min($age,12)),
                ]
            );
            if (str_contains((string)$notice->message,'fictional report')) {
                $notice->message='Your report '.$report->tracking_id.' is '.$status.'.';
                $notice->save();
            }
            $lastStatusPerCase[$key]=$status;
        }
        $this->command?->info('LINIS operations seeded or retained: 45 inspections, 18 weigh-ins and 8 case workflows with history, assignments and notifications.');
    }
}
