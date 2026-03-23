<?php

namespace App;

enum ProjectStatusEnum
{
    case ACTIVE;
    case ABORTED;
    case POSTPONED;
    case COMPLETED;
    case PRESENTATION;

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active', //The project is currently active and ongoing.
            self::ABORTED => 'Aborted',     //The project has been terminated before completion, probably due to customer request or unforeseen circumstances.
            self::POSTPONED => 'Postponed',     //The project is on hold
            self::PRESENTATION => 'Presentation',     //The project is in the presentation phase, where the results are being showcased to customers.
            self::COMPLETED => 'Completed',     //The project has been successfully completed.
        };
    }
}
