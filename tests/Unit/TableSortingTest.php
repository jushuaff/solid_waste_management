<?php
namespace Tests\Unit;

use App\Support\TableSorting;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class TableSortingTest extends TestCase
{
    public function test_rejects_arbitrary_sort_columns_and_directions(): void
    {
        $request=Request::create('/reports','GET',[
            'sort'=>'role_id.$where',
            'direction'=>'malicious',
        ]);
        [$sort,$direction]=TableSorting::resolve(
            $request,
            ['reference'=>'tracking_id','incident_date'=>'incident_date'],
            'submitted_at'
        );
        $this->assertSame('submitted_at',$sort);
        $this->assertSame('desc',$direction);
    }

    public function test_valid_header_sort_uses_ascending_by_default(): void
    {
        $request=Request::create('/reports','GET',['sort'=>'reference']);
        [$sort,$direction]=TableSorting::resolve(
            $request,['reference'=>'tracking_id'],'created_at'
        );
        $this->assertSame('reference',$sort);
        $this->assertSame('asc',$direction);
    }

    public function test_dashboard_rows_sort_numerically_and_keep_all_rows(): void
    {
        $rows=[
            ['zone'=>(object)['name'=>'Pine'],'compliance'=>80.00],
            ['zone'=>(object)['name'=>'Ridge'],'compliance'=>40.00],
            ['zone'=>(object)['name'=>'Creek'],'compliance'=>null],
        ];
        $request=Request::create('/linis/dashboard','GET',[
            'sort'=>'compliance','direction'=>'asc',
        ]);
        $fields=[
            'zone'=>static fn($row)=>$row['zone']->name,
            'compliance'=>static fn($row)=>$row['compliance'],
        ];
        $sorted=TableSorting::rows($rows,$request,$fields);
        $this->assertCount(3,$sorted);
        $this->assertSame(['Ridge','Pine','Creek'],array_map(
            static fn($row)=>$row['zone']->name,$sorted
        ));
    }

    public function test_missing_metrics_are_last_even_when_sorted_descending(): void
    {
        $rows=[
            ['zone'=>(object)['name'=>'Pine'],'compliance'=>80.00],
            ['zone'=>(object)['name'=>'Ridge'],'compliance'=>40.00],
            ['zone'=>(object)['name'=>'Creek'],'compliance'=>null],
        ];
        $request=Request::create('/linis/dashboard','GET',[
            'sort'=>'compliance','direction'=>'desc',
        ]);
        $sorted=TableSorting::rows($rows,$request,[
            'zone'=>static fn($row)=>$row['zone']->name,
            'compliance'=>static fn($row)=>$row['compliance'],
        ]);
        $this->assertSame(['Pine','Ridge','Creek'],array_map(
            static fn($row)=>$row['zone']->name,$sorted
        ));
    }

    public function test_dashboard_rows_sort_by_zone_descending(): void
    {
        $rows=[
            ['zone'=>(object)['name'=>'Pine'],'inspections'=>4],
            ['zone'=>(object)['name'=>'Ridge'],'inspections'=>10],
            ['zone'=>(object)['name'=>'Creek'],'inspections'=>2],
        ];
        $request=Request::create('/linis/dashboard','GET',[
            'sort'=>'zone','direction'=>'desc',
        ]);
        $sorted=TableSorting::rows($rows,$request,[
            'zone'=>static fn($row)=>$row['zone']->name,
            'inspections'=>static fn($row)=>$row['inspections'],
        ]);
        $this->assertSame(['Ridge','Pine','Creek'],array_map(
            static fn($row)=>$row['zone']->name,$sorted
        ));
    }
}
