<?php

namespace Antlion\StoreLocation\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\ListboxField;
use Antlion\StoreLocation\Model\StaffMember;
use Antlion\StoreLocation\Pages\StoreLocationPage;


class StoreDepartment extends DataObject
{
    private static string $table_name = 'StoreDepartment';

    private static array $db = [
        'Title' => 'Varchar(100)',
        'Email' => 'Varchar(255)',
        'Phone' => 'Varchar(50)',
    ];

    private static array $many_many = [
        'Locations' => StoreLocationPage::class,
    ];

    private static array $belongs_many_many = [
        'StaffMembers' => StaffMember::class,
    ];

    private static array $summary_fields = [
        'Title' => 'Department Name',
        'Email' => 'Email',
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName(['Locations', 'StaffMembers']);
        $fields->addFieldsToTab('Root.Main', [
            TextField::create('Title', 'Department Name'),
            TextField::create('Email', 'Department Email'),
            TextField::create('Phone', 'Department Phone'),
            ListboxField::create('Locations', 'Locations', StoreLocationPage::get()->map('ID', 'Title'))
            ->setDescription('Select locations that have this department'),
            ListboxField::create('StaffMembers', 'Department Email Recipients', StaffMember::get()->map('ID', 'getFullName'))
            ->setDescription('Select staff members to receive emails for this department'),
        ]);

        return $fields;
    }
}
