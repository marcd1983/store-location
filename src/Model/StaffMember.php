<?php

namespace Antlion\StoreLocation\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\ListboxField;
use Antlion\StoreLocation\Model\StoreDepartment;
use Antlion\StoreLocation\Pages\StoreLocationPage;
use SilverStripe\Assets\Image;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\CheckboxField;
class StaffMember extends DataObject
{
    private static string $table_name = 'StaffMember';

    private static array $db = [
        'FirstName' => 'Varchar(100)',
        'LastName' => 'Varchar(100)',
        'Title' => 'Varchar(100)',
        'Email' => 'Varchar(255)',
        'Phone' => 'Varchar(50)',
        'Bio' => 'Text',
        'HideStaff' => 'Boolean'
    ];

    private static array $has_one = [
        'Image' => Image::class
    ];

    private static array $many_many = [
        'Locations' => StoreLocationPage::class,
        'Departments' => StoreDepartment::class,
    ];

    private static $owns = [
        'Image',
    ];

    private static array $summary_fields = [
        'Image.CMSThumbnail' => 'Image',
        'FullName' => 'Name',
        'Title' => 'Title',
        'Email' => 'Email',
    ];

    private static $default_sort = '"LastName" ASC, "FirstName" ASC';

    public function getFullName(): string
    {
        return trim("{$this->FirstName} {$this->LastName}");
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['Main','Departments', 'Locations']);
        $fields->addFieldsToTab('Root.Main', [
            TextField::create('FirstName'),
            TextField::create('LastName'),
            TextField::create('Title'),
            TextField::create('Email'),
            TextField::create('Phone'),
            TextareaField::create('Bio'),
            UploadField::create('Image', 'Staff Photo')->setFolderName('Uploads/Staff'),
            CheckboxField::create('HideStaff', 'Hide Staff Member'),
            ListboxField::create('Departments', 'Departments', StoreDepartment::get()->map('ID', 'Title')),
            ListboxField::create('Locations', 'Locations', StoreLocationPage::get()->map('ID', 'Title')),
        ]);

        return $fields;
    }
}
