<?php

namespace Antlion\StoreLocation\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\DropdownField;
use Antlion\StoreLocation\Model\StoreSchedule;
class StoreLocationPageExtension extends Extension
{
    private static array $has_one = [
        'StoreSchedule' => StoreSchedule::class,
    ];

    private static array $owns = ['StoreSchedule'];

    public function onAfterWrite(): void
    {
        // parent::onAfterWrite();

        $owner = $this->owner; // StoreLocationPage (whatever its FQCN is)
        if (!$owner->StoreScheduleID) {
            $schedule = StoreSchedule::create(['Title' => "{$owner->Title} Hours"]);
            $schedule->write(); // seeds 7 rows
            $owner->StoreScheduleID = $schedule->ID;
            $owner->write();
        }
    }

    public function updateCMSFields(FieldList $fields): void
    {
        // Optional: allow swapping schedules
        $fields->addFieldToTab(
            'Root.Hours',
            DropdownField::create(
                'StoreScheduleID',
                'Hours Set',
                StoreSchedule::get()->map('ID', 'Title')
            )->setEmptyString('-- Select hours set --')
        );
    }
}
