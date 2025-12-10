<?php

namespace Antlion\StoreLocation\Pages;

use Page;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\ArrayData;
use Antlion\StoreLocation\Model\StoreDepartment;
use Antlion\StoreLocation\Model\StaffMember;
use Antlion\StoreLocation\Model\StoreSchedule;
use SilverStripe\ORM\ArrayList;
use stdClass;


class StoreLocationPage extends Page
{
    private static string $table_name = 'StoreLocationPage';
    private static $description = 'Page for location information';
     private static $icon_class = 'font-icon-flag';

    /** Address & contact details */
    private static array $db = [
        'Address'  => 'Varchar(255)',
        'Address2' => 'Varchar(255)',
        'City'     => 'Varchar(100)',
        'State'    => 'Varchar(50)',
        'Zip'      => 'Varchar(20)',
        'Phone'    => 'Varchar(50)',
        'Email'    => 'Varchar(255)',
        'MapEmbedURL' => 'Varchar(2048)',
        'MapLinkURL'  => 'Varchar(2048)',
        'Notes'    => 'Text',
        'Mailto'   => 'Varchar(255)',
    ];

    /** Relations to your other models */
    private static array $belongs_many_many = [
        'Departments'  => StoreDepartment::class,
        'StaffMembers' => StaffMember::class,
    ];

    /** One schedule per location (managed in StoreHoursAdmin) */
    private static array $has_one = [
        'DefaultSchedule' => StoreSchedule::class,
    ];

    /** Publish the related schedule when publishing the page */
    private static array $owns = ['DefaultSchedule'];

    private static array $summary_fields = [
        'Title'          => 'Location',
        'FullAddress'    => 'Address',
        'Phone'          => 'Phone',
        'Email'          => 'Email',
    ];

    public function getFullAddress(): string
    {
        $parts = array_filter([$this->Address, $this->Address2, $this->City, $this->State, $this->Zip]);
        return DBField::create_field('Varchar', implode(', ', $parts));
    }

    /**
     * Convenience: delegate effective hours lookup to the linked schedule.
     * Returns ['isClosed'=>bool,'openTime'=>?string,'closeTime'=>?string,'source'=>'weekly|holiday|none','note'=>?string]
     */
    public function effectiveHoursForDate(string $ymd): ArrayData
    {
        if ($this->DefaultScheduleID) {
            return $this->DefaultSchedule()->hoursForDate($ymd);
        }
        return ArrayData::create([
            'Source'    => 'none',
            'IsClosed'  => true,
            'OpenTime'  => null,
            'CloseTime' => null,
            'Note'      => null,
        ]);
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();

        foreach (['MapEmbedURL', 'MapLinkURL'] as $f) {
            if ($this->$f) {
                $url = trim($this->$f);
                if (!preg_match('~^https://(www\.)?google\.(com|[a-z.]+)/~i', $url)) {
                    // reject or clear; here we just keep it simple and clear
                    $this->$f = null;
                }
            }
        }
    }

    /**
     * Auto-create a schedule the first time this page is saved (so editors always have one to pick).
     * The StoreSchedule model will seed Mon–Sun to 08:00–17:00 on first write.
     */
    protected function onAfterWrite()
    {
        parent::onAfterWrite();

        if (!$this->DefaultScheduleID) {
            $schedule = StoreSchedule::create(['Title' => "{$this->Title} Schedule"]);
            $schedule->write(); // seeds 7 rows inside StoreSchedule::onAfterWrite()
            $this->DefaultScheduleID = $schedule->ID;
            // Write once more to persist the relationship
            // (avoid loops by not changing Title etc. here)
            $this->write();
        }
    }
    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        // Group address/contact into a tidy accordion
        $fields->removeByName(['Address','Address2','City','State','Zip','Phone','Email','Notes']);

        $locationDetails = ToggleCompositeField::create(
            'LocationDetails',
            'Location details',
            [
                TextField::create('Address', 'Address'),
                TextField::create('Address2', 'Address line 2'),
                TextField::create('City', 'City'),
                TextField::create('State', 'State'),
                TextField::create('Zip', 'ZIP / Postcode'),
                TextField::create('Phone', 'Phone'),
                TextField::create('Email', 'Email'),
                TextField::create('MapLinkURL', 'Map link (optional)')
                ->setDescription('A normal Google Maps link for a “View larger map” or “Directions” button.'),
                TextField::create('MapEmbedURL', 'Google Maps embed URL')
                ->setDescription('Paste the URL from the Google Maps “Share → Embed a map → Copy HTML”, then strip everything except the src URL that starts with https://www.google.com/maps/embed?pb=...'),
                TextareaField::create('Notes', 'Notes')->setRows(3),
            ]
        )->setHeadingLevel(3)->setStartClosed(false);

        $fields->addFieldToTab('Root.Main', $locationDetails);

        // Hours tab: simple selector for the schedule (managed in StoreHoursAdmin)
        $fields->removeByName('DefaultScheduleID'); // ensure we control placement
        $fields->addFieldsToTab('Root.Hours', [
            DropdownField::create(
                'DefaultScheduleID',
                'Schedule',
                StoreSchedule::get()->map('ID', 'Title')
            )->setEmptyString('— Select schedule —')
        ]);

        // Departments tab
        $fields->removeByName('Departments');
        $fields->addFieldToTab(
            'Root.Departments',
            GridField::create(
                'Departments',
                'Departments',
                $this->Departments(),
                GridFieldConfig_RelationEditor::create()
            )
        );

        // Departments tab
        $fields->removeByName('LocationRecipients');
        $fields->addFieldToTab(
            'Root.LocationRecipients',
            TextField::create('Mailto', 'Location Recipients')
        );

        return $fields;
    }

    /**
     * Staff linked to BOTH the given department and this location.
     * Accepts a StoreDepartment or an ID.
     */
    public function StaffInDepartment($department)
    {
        $deptID = $department instanceof StoreDepartment ? (int)$department->ID : (int)$department;

        return StaffMember::get()
            ->filter([
                'Departments.ID' => $deptID,
                'Locations.ID'   => $this->ID,
                'HideStaff'      => 0,
            ])
            ->sort(['LastName' => 'ASC', 'FirstName' => 'ASC'])
            ->distinct(true);
    }

    /**
     * Sections to render:
     *  - One section per department (Title, Staff list)
     *  - Optional "Other Staff" section (any staff at this location not in a linked department)
     *
     * @return ArrayList of ArrayData{Title:string, Staff:DataList<StaffMember>}
     */
    public function AllStaffSections(?string $otherSectionTitle = null): ArrayList
    {
        $sections = ArrayList::create();

        // 1) Per-department sections
        $deptIDs = $this->Departments()->column('ID');
        foreach ($this->Departments()->sort('Title ASC') as $dept) {
            $staff = $this->StaffInDepartment($dept);
            if ($staff->exists()) {
                $sections->push(ArrayData::create([
                    'Title' => $dept->Title,
                    'Staff' => $staff,
                ]));
            }
        }

        // 2) "Other Staff" (at this location, not in any of the page's departments)
        $staffAtLocation = StaffMember::get()
            ->filter(['Locations.ID' => $this->ID, 'HideStaff' => 0])
            ->sort(['LastName' => 'ASC', 'FirstName' => 'ASC'])
            ->distinct(true);

        $general = $staffAtLocation;
        if (!empty($deptIDs)) {
            // IDs of staff that appear in ANY of this page's departments
            $withDeptIDs = StaffMember::get()
                ->filter([
                    'Locations.ID'   => $this->ID,
                    'Departments.ID' => $deptIDs,
                    'HideStaff'      => 0,
                ])
                ->column('ID');

            if (!empty($withDeptIDs)) {
                $general = $general->exclude('ID', $withDeptIDs);
            }
        }

        if ($general->exists()) {
            $title = $otherSectionTitle ?? ($sections->count() > 0 ? 'Staff Members' : 'Staff');
            $sections->push(ArrayData::create([
                'Title' => $title,
                'Staff' => $general,
            ]));
        }

        return $sections;
    }

}
