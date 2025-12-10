<?php

namespace Antlion\StoreLocation\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\TimeField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ValidationException;

/**
 * Per-date override for a StoreSchedule.
 * Can represent a closed day or custom open/close hours.
 */
class StoreHolidayHour extends DataObject
{
    private static string $table_name = 'StoreHolidayHour';

    private static array $db = [
        'Date'        => 'Date',     // e.g. 2025-12-25 (Y-m-d)
        'IsRecurring' => 'Boolean',  // repeats every year (same month-day)
        'IsClosed'    => 'Boolean',  // closed all day
        'OpenTime'    => 'Time',     // required if not closed
        'CloseTime'   => 'Time',     // required if not closed
        'Note'        => 'Varchar(255)', // free-text label e.g. "Independence Day (Observed)"
    ];

    private static array $has_one = [
        'StoreSchedule' => StoreSchedule::class,
    ];

    private static array $summary_fields = [
        'Date'             => 'Date',
        'HolidayName'      => 'Name',
        'IsRecurring.Nice' => 'Recurring?',
        'IsClosed.Nice'    => 'Closed?',
        'OpenTimeNice'         => 'Opens',
        'CloseTimeNice'        => 'Closes',
    ];

    private static string $default_sort = '"Date" ASC';

    /** Show a friendly name in the GridField */
    public function getHolidayName(): string
    {
        // Prefer Note set by generator/editor; otherwise show a generic label
        return $this->Note ?: 'Holiday';
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

        $fields->replaceField('Date',        DateField::create('Date', 'Date')->setHTML5(true));
        $fields->replaceField('IsRecurring', CheckboxField::create('IsRecurring', 'Repeats every year'));
        $fields->replaceField('IsClosed',    CheckboxField::create('IsClosed', 'Closed all day'));
        $fields->replaceField('OpenTime',    TimeField::create('OpenTime', 'Opens at'));
        $fields->replaceField('CloseTime',   TimeField::create('CloseTime', 'Closes at'));
        $fields->replaceField('Note',        TextField::create('Note', 'Name / Note (optional)'));

        return $fields;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!$this->IsClosed) {
            if (!$this->OpenTime || !$this->CloseTime) {
                throw ValidationException::create('Provide both Open and Close times, or tick "Closed all day".');
            }
            if (strtotime($this->CloseTime) <= strtotime($this->OpenTime)) {
                throw ValidationException::create('Close time must be after Open time.');
            }
        } else {
            // Closed all day: clear times to avoid confusion
            $this->OpenTime = null;
            $this->CloseTime = null;
        }
    }

    // ------------------------------------------------------------
    // U.S. FEDERAL HOLIDAY GENERATION (actual + observed support)
    // ------------------------------------------------------------

    /**
     * Generate U.S. federal holidays for a given year into the provided schedule.
     *
     * @param StoreSchedule $schedule        Target schedule
     * @param int           $year            Year to generate (e.g. 2026)
     * @param bool          $includeObserved Include observed dates (Fri/Mon when fixed date on Sat/Sun)
     * @param bool          $markClosed      Mark generated entries as closed by default
     * @return int                           Number of rows created (duplicates are skipped)
     */
    public static function generateUSFederalFor(
        StoreSchedule $schedule,
        int $year,
        bool $includeObserved = true,
        bool $markClosed = true
    ): int {
        $map = self::federalHolidayMap($year, $includeObserved);
        $created = 0;

        foreach ($map as $ymd => $name) {
            // Skip duplicate date for this schedule
            if ($schedule->HolidayHours()->filter('Date', $ymd)->exists()) {
                continue;
            }

            /** @var self $rec */
            $rec = self::create([
                'StoreScheduleID' => $schedule->ID,
                'Date'            => $ymd,
                'IsRecurring'     => false,
                'IsClosed'        => $markClosed,
                'OpenTime'        => null,
                'CloseTime'       => null,
                'Note'            => $name,
            ]);
            $rec->write();
            $created++;
        }

        return $created;
    }

    /**
     * Internal: return ['Y-m-d' => 'Name'] for US federal holidays of a given year.
     * Includes "(Observed)" entries if $includeObserved is true.
     */
    protected static function federalHolidayMap(int $year, bool $includeObserved = true): array
    {
        $out = [];

        // helpers
        $date = static fn (int $y, int $m, int $d) => (new \DateTimeImmutable("$y-$m-$d"))->format('Y-m-d');
        $nthWeekday = static function (int $y, int $m, int $isoWeekday, int $n): \DateTimeImmutable {
            // ISO weekday: 1 = Mon .. 7 = Sun
            $first = new \DateTimeImmutable("$y-$m-01");
            $shift = ($isoWeekday - (int)$first->format('N') + 7) % 7;
            return $first->modify("+{$shift} days")->modify('+' . ($n - 1) . ' weeks');
        };
        $lastWeekday = static function (int $y, int $m, int $isoWeekday): \DateTimeImmutable {
            $last = (new \DateTimeImmutable("$y-$m-01"))->modify('last day of this month');
            $shiftBack = ((int)$last->format('N') - $isoWeekday + 7) % 7;
            return $last->modify("-{$shiftBack} days");
        };
        $observed = static function (\DateTimeImmutable $d): \DateTimeImmutable {
            // If the actual date falls on Sat → observed Fri; on Sun → observed Mon
            $w = (int)$d->format('N'); // 6=Sat, 7=Sun
            return $w === 6 ? $d->modify('-1 day') : ($w === 7 ? $d->modify('+1 day') : $d);
        };

        $add = static function (&$arr, \DateTimeImmutable $d, string $name) {
            $arr[$d->format('Y-m-d')] = $name;
        };

        // Fixed-date holidays
        $fixed = [
            [$date($year, 1, 1),   "New Year's Day"],
            [$date($year, 6, 19),  'Juneteenth National Independence Day'],
            [$date($year, 7, 4),   'Independence Day'],
            [$date($year, 11, 11), 'Veterans Day'],
            [$date($year, 12, 25), 'Christmas Day'],
        ];
        foreach ($fixed as [$ymd, $name]) {
            $actual = new \DateTimeImmutable($ymd);
            $add($out, $actual, $name);

            if ($includeObserved) {
                $obs = $observed($actual);
                if ($obs->format('Y-m-d') !== $actual->format('Y-m-d')) {
                    $add($out, $obs, "$name (Observed)");
                }
            }
        }

        // Weekday-based holidays
        $mlk          = $nthWeekday($year, 1,  1, 3); // 3rd Monday in Jan
        $washington   = $nthWeekday($year, 2,  1, 3); // 3rd Monday in Feb
        $memorial     = $lastWeekday($year, 5, 1);    // last Monday in May
        $labor        = $nthWeekday($year, 9,  1, 1); // 1st Monday in Sep
        $columbus     = $nthWeekday($year, 10, 1, 2); // 2nd Monday in Oct
        $thanksgiving = $nthWeekday($year, 11, 4, 4); // 4th Thursday in Nov

        foreach ([
            [$mlk,          'Birthday of Martin Luther King, Jr.'],
            [$washington,   "Washington's Birthday"],
            [$memorial,     'Memorial Day'],
            [$labor,        'Labor Day'],
            [$columbus,     'Columbus Day'],
            [$thanksgiving, 'Thanksgiving Day'],
        ] as [$dt, $name]) {
            $add($out, $dt, $name);
        }

        ksort($out);
        return $out;
    }
    

}
