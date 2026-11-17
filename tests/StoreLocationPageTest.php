<?php

namespace Antlion\StoreLocation\Tests;

use Antlion\StoreLocation\Model\StoreHolidayHour;
use Antlion\StoreLocation\Pages\StoreLocationPage;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;

/**
 * Hours logic: weekly hours, holiday overrides, recurring holidays, "open
 * now", and the LocalBusiness JSON-LD. Dates are mocked; 2026-06-15 is a
 * Monday.
 */
class StoreLocationPageTest extends SapphireTest
{
    protected $usesDatabase = true;

    private StoreLocationPage $page;

    protected function setUp(): void
    {
        parent::setUp();
        // No network: these tests don't need coordinates (see StoreLocatorTest)
        StoreLocationPage::config()->set('geocode_on_save', false);

        // onAfterWrite creates a schedule: Mon–Sun 08:00–17:00
        $this->page = StoreLocationPage::create([
            'Title' => 'Westbrook',
            'LocationName' => 'Westbrook Yard',
            'Address' => '14 Mill Lane',
            'City' => 'Westbrook',
            'State' => 'OR',
            'Zip' => '97001',
            'Phone' => '(555) 014-2290',
        ]);
        $this->page->write();

        // Closed on Sundays
        $sunday = $this->page->DefaultSchedule()->Hours()->filter('DayOfWeek', 7)->first();
        $sunday->IsClosed = true;
        $sunday->write();
    }

    public function testNewLocationsGetADefaultWeek(): void
    {
        $hours = $this->page->DefaultSchedule()->Hours();

        $this->assertCount(7, $hours);
        $this->assertSame('08:00:00', $hours->filter('DayOfWeek', 1)->first()->OpenTime);
    }

    public function testOpenNowDuringWeeklyHours(): void
    {
        DBDatetime::set_mock_now('2026-06-15 10:30:00');
        $today = $this->page->getTodaysHours();

        $this->assertSame('weekly', $today->Source);
        $this->assertTrue($today->IsOpenNow);

        DBDatetime::set_mock_now('2026-06-15 17:00:00');
        $this->assertFalse($this->page->getTodaysHours()->IsOpenNow, 'closing time is exclusive');
    }

    public function testClosedDay(): void
    {
        DBDatetime::set_mock_now('2026-06-21 11:00:00'); // Sunday
        $today = $this->page->getTodaysHours();

        $this->assertTrue($today->IsClosed);
        $this->assertFalse($today->IsOpenNow);
    }

    public function testHolidayOverridesWeeklyHours(): void
    {
        $this->holiday(['Date' => '2026-06-15', 'OpenTime' => '10:00:00', 'CloseTime' => '14:00:00', 'Note' => 'Stocktake']);

        DBDatetime::set_mock_now('2026-06-15 15:00:00');
        $today = $this->page->getTodaysHours();

        $this->assertSame('holiday', $today->Source);
        $this->assertSame('Stocktake', $today->Note);
        $this->assertFalse($today->IsOpenNow, 'closes at 2pm on the special day');
    }

    public function testRecurringHolidayAppliesEveryYear(): void
    {
        $this->holiday(['Date' => '2020-12-25', 'IsRecurring' => true, 'IsClosed' => true, 'Note' => 'Christmas Day']);

        $this->assertTrue($this->page->effectiveHoursForDate('2026-12-25')->IsClosed);
        $this->assertSame('holiday', $this->page->effectiveHoursForDate('2026-12-25')->Source);
    }

    public function testUpcomingHolidaysRollRecurringOnesForward(): void
    {
        DBDatetime::set_mock_now('2026-12-28 09:00:00');
        $this->holiday(['Date' => '2020-12-25', 'IsRecurring' => true, 'IsClosed' => true, 'Note' => 'Christmas Day']);
        $this->holiday(['Date' => '2027-01-01', 'IsClosed' => true, 'Note' => "New Year's Day"]);
        $this->holiday(['Date' => '2026-12-01', 'IsClosed' => true, 'Note' => 'Past']);

        $upcoming = $this->page->UpcomingHolidays();

        $this->assertSame(["New Year's Day", 'Christmas Day'], $upcoming->column('Name'));
        $this->assertSame('2027-12-25', $upcoming->last()->Date->getValue(), 'next occurrence, not this year\'s');
    }

    public function testWeeklyHoursFlagToday(): void
    {
        DBDatetime::set_mock_now('2026-06-17 09:00:00'); // Wednesday
        $rows = $this->page->getWeeklyHours();

        $this->assertCount(7, $rows);
        $this->assertSame(['Wednesday'], $rows->filter('IsToday', true)->column('Day'));
        $this->assertTrue($rows->find('Day', 'Sunday')->IsClosed);
    }

    public function testLocalBusinessJsonIsValidAndHasOpeningHours(): void
    {
        $this->page->Address = '14 "Mill" Lane </script>';
        $json = $this->page->getLocalBusinessSchemaJSON();
        $node = json_decode($json, true);

        $this->assertIsArray($node, 'always valid JSON, whatever the address contains');
        $this->assertStringNotContainsString('</script>', $json, 'safe inside a script tag');
        $this->assertSame('LocalBusiness', $node['@type']);
        $this->assertSame('Westbrook Yard', $node['name']);
        $this->assertCount(6, $node['openingHoursSpecification'], 'six open days');
        $this->assertSame('08:00', $node['openingHoursSpecification'][0]['opens']);
    }

    public function testMapUrlFallsBackToAnAddressSearch(): void
    {
        $this->assertStringStartsWith('https://www.google.com/maps/search/?api=1&query=14%20Mill%20Lane', $this->page->getMapURL());
        $this->assertSame('tel:5550142290', $this->page->getPhoneHref());
    }

    public function testOnlyGoogleMapUrlsAreKept(): void
    {
        $this->page->MapEmbedURL = 'https://evil.example/embed';
        $this->page->write();

        $this->assertNull($this->page->MapEmbedURL);
    }

    private function holiday(array $fields): void
    {
        StoreHolidayHour::create(['StoreScheduleID' => $this->page->DefaultScheduleID, ...$fields])->write();
    }
}
