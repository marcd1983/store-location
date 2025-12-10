<?php

namespace Antlion\StoreLocation\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TimeField;

class StoreHour extends DataObject
{
    private static string $table_name = 'StoreHour';

    private static array $db = [
        'DayOfWeek' => 'Int',         // 1..7 (Mon..Sun) — fixed after seeding
        'Label'     => 'Varchar(20)', // editor-friendly label, editable
        'OpenTime'  => 'Time',        // e.g. 08:00:00
        'CloseTime' => 'Time',        // e.g. 17:00:00
        'IsClosed'  => 'Boolean',
        'Sort'      => 'Int',         // derived from DayOfWeek
    ];

    private static array $has_one = [
        'StoreSchedule' => StoreSchedule::class,
    ];

    // Sort by weekday order
    private static string $default_sort = 'Sort ASC';

    private static array $summary_fields = [
        'DayName'        => 'Day',
        'Label'          => 'Label',
        'OpenTimeNice'   => 'Opens',
        'CloseTimeNice'  => 'Closes',
        'IsClosed.Nice'  => 'Closed?',
    ];

    /** Human name for the weekday (not stored) */
    public function getDayName(): string
    {
        return self::days()[$this->DayOfWeek] ?? (string)$this->DayOfWeek;
    }

    public function getOpenTimeNice(): ?string
    {
        if (!$this->OpenTime) return null;
        return date('g:ia', strtotime((string)$this->OpenTime)); // e.g. 5:00pm
    }

    public function getCloseTimeNice(): ?string
    {
        if (!$this->CloseTime) return null;
        return date('g:ia', strtotime((string)$this->CloseTime));
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // Hide DayOfWeek (we don’t want editors changing the weekday)
        $fields->removeByName('DayOfWeek');

        // Show the weekday as read-only info
        $fields->insertBefore(
            'Label',
            ReadonlyField::create('DayName', 'Day', $this->DayName)
        );

        // Keep label editable (optional display label like “Sales Hours”)
        $fields->replaceField('Label', TextField::create('Label', 'Label (optional)'));

        // Time + Closed controls remain editable
        $fields->replaceField('OpenTime',  TimeField::create('OpenTime',  'Opens at'));
        $fields->replaceField('CloseTime', TimeField::create('CloseTime', 'Closes at'));
        $fields->replaceField('IsClosed',  CheckboxField::create('IsClosed', 'Closed all day'));

        // Hide Sort from editors (we’ll maintain it automatically)
        $fields->removeByName('Sort');

        return $fields;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // Keep Sort in sync with DayOfWeek so ordering is always Mon..Sun
        if ($this->DayOfWeek) {
            $this->Sort = (int)$this->DayOfWeek;
        }

        // If no label set, default to the weekday name
        if (!$this->Label) {
            $this->Label = $this->DayName;
        }

        // (Optional) enforce sane times when not closed
        if (!$this->IsClosed && $this->OpenTime && $this->CloseTime) {
            if (strtotime($this->CloseTime) <= strtotime($this->OpenTime)) {
                // Swap or normalize (or throw a validation error if you prefer)
                $tmp = $this->OpenTime;
                $this->OpenTime = $this->CloseTime;
                $this->CloseTime = $tmp;
            }
        }
        // If closed, you could clear times to avoid confusion:
        // if ($this->IsClosed) { $this->OpenTime = null; $this->CloseTime = null; }
    }

    /** Keep for seeding / helpers */
    public static function days(): array
    {
        return [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];
    }
}
