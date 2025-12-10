<?php

namespace Antlion\StoreLocation\Elements;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Antlion\StoreLocation\Model\StaffMember;
use Antlion\StoreLocation\Pages\StoreLocationPage;

class ElementStaff extends BaseElement
{
    private static string $table_name = 'ElementStaff';
    private static $icon = 'font-icon-block-user';
    private static $singular_name = 'Staff List (curated)';
    private static $plural_name   = 'Staff List (curated)';

    private static bool $inline_editable = false;


    private static array $db = [
        'ScopeToCurrentLocation' => 'Boolean',
        'Columns'                => 'Int',
    ];

    private static array $many_many = [
        'StaffMembers' => StaffMember::class,
    ];

    private static array $many_many_extraFields = [
        'StaffMembers' => ['SortOrder' => 'Int'],
    ];

    private static array $defaults = [
        'ScopeToCurrentLocation' => true,
        'Columns' => 4,
    ];

    public function getType(): string
    {
        return 'Staff List (curated)';
    }

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        $fields->addFieldsToTab('Root.Main', [
            DropdownField::create('Columns', 'Card columns', array_combine(range(2, 6), range(2, 6)))
                ->setEmptyString('Auto'),
        ]);

        $config = GridFieldConfig_RelationEditor::create();
        $config->addComponent(new GridFieldOrderableRows('SortOrder'));

        $fields->addFieldToTab(
            'Root.StaffMembers',
            GridField::create('StaffMembers', 'Selected staff (drag to reorder)', $this->StaffMembers(), $config)
        );

        return $fields;
    }

    /** Ordered curated staff, optionally scoped to the current location */
    public function OrderedStaff()
    {
        $list = $this->StaffMembers()
            ->sort('SortOrder ASC, LastName ASC, FirstName ASC')
            ->filter('HideStaff', 0);

        $page = $this->getPage();
        if ($this->ScopeToCurrentLocation && $page instanceof StoreLocationPage) {
            $list = $list->filter('Locations.ID', $page->ID);
        }

        return $list->distinct(true);
    }
}
