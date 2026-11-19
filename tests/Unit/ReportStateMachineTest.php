<?php
namespace Tests\Unit;

use App\Support\ReportStateMachine;
use PHPUnit\Framework\TestCase;

final class ReportStateMachineTest extends TestCase
{
    public function test_expected_report_lifecycle(): void
    {
        $this->assertTrue(ReportStateMachine::canTransition('Submitted','Under Review'));
        $this->assertTrue(ReportStateMachine::canTransition('Under Review','Verified'));
        $this->assertTrue(ReportStateMachine::canTransition('Verified','Assigned'));
        $this->assertTrue(ReportStateMachine::canTransition('Assigned','In Progress'));
        $this->assertTrue(ReportStateMachine::canTransition('In Progress','Exception'));
        $this->assertTrue(ReportStateMachine::canTransition('Exception','Closed'));
    }
    public function test_illegal_transitions_are_blocked(): void
    {
        foreach([['Under Review','Resolved'],['Rejected','Assigned'],['Resolved','Under Review'],['Closed','Assigned']] as [$from,$to]) {
            $this->assertFalse(ReportStateMachine::canTransition($from,$to));
        }
    }
}
