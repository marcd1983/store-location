<?php

namespace Antlion\StoreLocation\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\View\ArrayData;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;

class StoreSchedule extends DataObject
{
    private static string $table_name = 'StoreSchedule';

    private static array $db = [
        'Title' => 'Varchar(255)',
        'Notes' => 'Text',
    ];

    private static array $has_many = [
        'Hours'        => StoreHour::class,         // 7 rows (Mon–Sun)
        'HolidayHours' => StoreHolidayHour::class,  // per-date overrides
    ];

    private static array $owns = ['Hours', 'HolidayHours'];

    private static array $summary_fields = [
        'Title'               => 'Title',
        'Hours.Count'         => 'Weekdays',
        'HolidayHours.Count'  => 'Holidays',
    ];

    public function getCMSFields(): FieldList
    {
        
        $config = GridFieldConfig_RecordEditor::create();
        $config->removeComponentsByType(GridFieldAddNewButton::class);
        $config->removeComponentsByType(GridFieldDeleteAction::class);

        $fields = parent::getCMSFields();
        $fields->removeByName(['Hours', 'HolidayHours']);
        $fields->addFieldToTab(
            'Root.Weekly Hours',
                GridField::create(
                    'Hours',
                    'Weekly Hours (Mon–Sun)',
                    $this->Hours()->sort('Sort'),
                    $config
            )
        );

        $fields->addFieldToTab(
            'Root.Holiday Hours',
            GridField::create(
                'HolidayHours',
                'Holiday / Special Hours',
                $this->HolidayHours()->sort('Date'),
                GridFieldConfig_RecordEditor::create()
            )
        );

        return $fields;
    }

    public function onAfterWrite(): void
    {
        parent::onAfterWrite();

        // Seed 7 days (08:00–17:00) once
        if ($this->Hours()->count() === 0) {
            $defaults = [
                ['dow' => 1, 'label' => 'Monday',    'sort' => 1],
                ['dow' => 2, 'label' => 'Tuesday',   'sort' => 2],
                ['dow' => 3, 'label' => 'Wednesday', 'sort' => 3],
                ['dow' => 4, 'label' => 'Thursday',  'sort' => 4],
                ['dow' => 5, 'label' => 'Friday',    'sort' => 5],
                ['dow' => 6, 'label' => 'Saturday',  'sort' => 6],
                ['dow' => 7, 'label' => 'Sunday',    'sort' => 7],
            ];
            foreach ($defaults as $d) {
                StoreHour::create([
                    'StoreScheduleID' => $this->ID,
                    'DayOfWeek'       => $d['dow'],
                    'Label'           => $d['label'],
                    'OpenTime'        => '08:00:00',
                    'CloseTime'       => '17:00:00',
                    'IsClosed'        => false,
                    'Sort'            => $d['sort'],
                ])->write();
            }
        }
    }

    /**
     * Effective hours for a given Y-m-d (holiday overrides weekly).
     * @return array{source:string,isClosed:bool,openTime:?string,closeTime:?string,note:?string}
     */
     public function hoursForDate(string $ymd): ArrayData
    {
        $ts = strtotime($ymd);
        $md = date('m-d', $ts);

        // Exact holiday
        $holiday = $this->HolidayHours()->filter('Date', $ymd)->first();

        // Recurring (same month-day any year)
        if (!$holiday) {
            $holiday = $this->HolidayHours()
                ->filter(['IsRecurring' => true])
                ->filterAny(['Date:EndsWith' => $md]) // Y-m-d ends with m-d
                ->first();
        }

        if ($holiday) {
            return ArrayData::create([
                'Source'    => 'holiday',
                'IsClosed'  => (bool)$holiday->IsClosed,
                'OpenTime'  => $holiday->OpenTime ?: null,
                'CloseTime' => $holiday->CloseTime ?: null,
                'OpenTimeNice'  => $holiday->OpenTime ? date('g:ia', strtotime($holiday->OpenTime)) : null,
                'CloseTimeNice' => $holiday->CloseTime ? date('g:ia', strtotime($holiday->CloseTime)) : null,
                'Note'      => $holiday->Note,
            ]);
        }

        $dow = (int)date('N', $ts); // 1..7 (Mon..Sun)
        $day = $this->Hours()->filter('DayOfWeek', $dow)->first();

        if ($day) {
            return ArrayData::create([
                'Source'    => 'weekly',
                'IsClosed'  => (bool)$day->IsClosed,
                'OpenTime'  => $day->OpenTime ?: null,
                'CloseTime' => $day->CloseTime ?: null,
                'OpenTimeNice'  => $day->OpenTime ? date('g:ia', strtotime($day->OpenTime)) : null,
                'CloseTimeNice' => $day->CloseTime ? date('g:ia', strtotime($day->CloseTime)) : null,
                'Note'      => null,
            ]);
        }

        return ArrayData::create([
            'Source'    => 'none',
            'IsClosed'  => true,
            'OpenTime'  => null,
            'CloseTime' => null,
            'Note'      => null,
        ]);
    }
}
