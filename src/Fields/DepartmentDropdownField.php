<?php

namespace App\Fields;

use SilverStripe\UserForms\Model\EditableFormField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TreeDropdownField;
use Antlion\StoreLocation\Model\StoreDepartment;
use Antlion\StoreLocation\Pages\StoreLocationPage;

class DepartmentDropdownField extends EditableFormField
{
    private static string $table_name = 'DepartmentDropdownField';

    private static $singular_name = 'Department dropdown';
    private static $plural_name   = 'Department dropdowns';

    private static $db = [
        'EmptyString'     => 'Varchar(255)',
        'OnlyWithEmail'   => 'Boolean',     // show only departments with an Email set
        'LimitToLocation' => 'Int',         // optional StoreLocationPage ID
    ];

    public function getFormField()
    {
        $title = $this->Title ?: 'Department';

        $list = StoreDepartment::get();

        // Optional filter: must have Email
        if ($this->OnlyWithEmail) {
            $list = $list->filter('Email:not', null)->exclude('Email', '');
        }

        // Optional filter: limit to one StoreLocationPage
        if ($this->LimitToLocation) {
            // many_many filter
            $list = $list->filter('Locations.ID', (int)$this->LimitToLocation);
        }

        $source = $list->sort('Title')->map('ID', 'Title')->toArray();

        return DropdownField::create($this->Name, $title, $source)
            ->setEmptyString($this->EmptyString ?: 'Select a department');
    }

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        // Replace "Default" with a friendlier placeholder label
        $fields->replaceField(
            'Default',
            TextField::create('EmptyString', 'Empty option label')
                ->setDescription('Shown as the first placeholder option, e.g. “Select a department”.')
        );

        // Optional location limiter (TreeDropdown keeps it tidy if you have many)
        $fields->insertAfter(
            'EmptyString',
            TreeDropdownField::create(
                'LimitToLocation',
                'Limit to location (optional)',
                StoreLocationPage::class
            )->setDescription('If set, only departments linked to this location will appear.')
        );

        $fields->insertAfter(
            'LimitToLocation',
            CheckboxField::create('OnlyWithEmail', 'Only show departments that have an Email')
        );

        return $fields;
    }

    /**
     * Store a human-friendly value in the submission.
     * UserForms will save this string as the SubmittedFormField Value.
     */
    public function getSubmittedValue($data, $form = null)
    {
        $id = $data[$this->Name] ?? null;
        if (!$id) {
            return '';
        }

        $dept = StoreDepartment::get()->byID((int)$id);
        if (!$dept) {
            return '';
        }

        // 1) Gather staff emails linked to this department
        $staffEmails = $dept->StaffMembers()
            ->filter('Email:not', null)
            ->column('Email');

        // Clean up: trim, remove blanks, de-dupe
        $staffEmails = array_values(array_unique(array_filter(array_map('trim', $staffEmails))));

        // 2) Fallback to department Email if staff list is empty
        if (empty($staffEmails)) {
            if ($dept->Email) {
                $staffEmails = [trim($dept->Email)];
            }
        }

        // Return as comma-separated string (UserForms expects this format for "Send to field")
        return implode(',', $staffEmails);
    }
}
