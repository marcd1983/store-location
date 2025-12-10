<?php

namespace App\UserForms\Fields;

use SilverStripe\UserForms\Model\EditableFormField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\TextField;
use Antlion\StoreLocation\Pages\StoreLocationPage;

class LocationDropdownField extends EditableFormField
{
    private static string $table_name = 'LocationDropdownField';

    private static $singular_name = 'Location dropdown';
    private static $plural_name   = 'Location dropdowns';

    private static $db = [
        'EmptyString'        => 'Varchar(255)',
        'OnlyWithEmail'      => 'Boolean', // only show locations that have Email
        'OnlyWithDepartments'=> 'Boolean', // only show locations that are linked to ≥1 department
    ];

    public function getFormField()
    {
        $title = $this->Title ?: 'Location';

        $list = StoreLocationPage::get();

        if ($this->OnlyWithEmail) {
            $list = $list->filter('Email:not', null)->exclude('Email', '');
        }

        if ($this->OnlyWithDepartments) {
            // Locations that have at least one linked department
            $list = $list->filter('Departments.ID:GreaterThan', 0)->distinct(true);
        }

        $source = $list->sort('Title')->map('ID', 'Title')->toArray();

        return DropdownField::create($this->Name, $title, $source)
            ->setEmptyString($this->EmptyString ?: 'Select a location');
    }

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        $fields->replaceField(
            'Default',
            TextField::create('EmptyString', 'Empty option label')
                ->setDescription('Shown as the first placeholder option.')
        );

        $fields->insertAfter(
            'EmptyString',
            CheckboxField::create('OnlyWithEmail', 'Only show locations that have an Email')
        );

        $fields->insertAfter(
            'OnlyWithEmail',
            CheckboxField::create('OnlyWithDepartments', 'Only show locations that have departments linked')
        );

        return $fields;
    }

    /**
     * Store a friendly value in the submission.
     * The saved "Value" becomes the location Title instead of the numeric ID.
     */
    public function getSubmittedValue($data, $form = null)
    {
        $id = $data[$this->Name] ?? null;
        if (!$id) {
            return '';
        }

        $loc = StoreLocationPage::get()->byID((int)$id);
        if (!$loc) {
            return '';
        }

        // 1) Start with Mailto (can be comma/semicolon-separated)
        $recipients = [];
        if ($loc->Mailto) {
            $parts = preg_split('/[;,]/', $loc->Mailto);
            $recipients = array_map('trim', $parts ?: []);
        }

        // 2) If still empty, fall back to the page's Email
        if (!$recipients && $loc->Email) {
            $recipients = [trim($loc->Email)];
        }

        // 3) (Optional) also include staff emails at this location
        // uncomment if you want them added as well
        /*
        $staffEmails = $loc->StaffMembers()
            ->filter(['Email:not' => null, 'HideStaff' => 0])
            ->column('Email');
        $recipients = array_merge($recipients, $staffEmails);
        */

        // Clean + de-dupe
        $recipients = array_values(array_unique(array_filter($recipients)));

        return implode(',', $recipients);
    }
    
}
