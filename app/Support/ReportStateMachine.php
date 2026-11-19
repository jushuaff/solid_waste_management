<?php
namespace App\Support;

final class ReportStateMachine
{
    public const TRANSITIONS = [
        'Submitted' => ['Under Review'],
        'Under Review' => ['Verified', 'Rejected'],
        'Verified' => ['Assigned'],
        'Assigned' => ['In Progress'],
        'In Progress' => ['Resolved', 'Exception'],
        'Exception' => ['Assigned', 'Closed'],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
