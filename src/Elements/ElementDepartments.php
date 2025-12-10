<?php

namespace Antlion\StoreLocation\Elements;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;
use Antlion\StoreLocation\Model\StoreDepartment;
use Antlion\StoreLocation\Model\StaffMember;
use Antlion\StoreLocation\Pages\StoreLocationPage;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;

class ElementDepartments extends BaseElement
{
    private static string $table_name = 'ElementDepartments';

    private static $icon = 'font-icon-block-users';
    private static $singular_name = 'Department Staff';
    private static $plural_name   = 'Department Staff Blocks';

    private static bool $inline_editable = false;


    private static array $db = [
        'ScopeToCurrentLocation' => 'Boolean',
        'ShowOtherStaff'         => 'Boolean',
        'Columns'                => 'Int',
    ];

    private static array $many_many = [
        'Departments' => StoreDepartment::class,
    ];

    // enable manual ordering on the join
    private static array $many_many_extraFields = [
        'Departments' => [
            'SortOrder' => 'Int',
        ],
    ];

    private static array $defaults = [
        'ScopeToCurrentLocation' => true,
        'Columns'                => 4,
    ];

    public function getType(): string
    {
        return 'Department Staff';
    }

    public function getCMSFields(): FieldList
    {
        $fields = parent::getCMSFields();

        // Controls
        $fields->addFieldsToTab('Root.Main', [
            CheckboxField::create('ScopeToCurrentLocation', 'Only show staff linked to this location'),
            CheckboxField::create('ShowOtherStaff', 'Add an "General Staff" section'),
            DropdownField::create('Columns', 'Card columns', array_combine(range(2, 6), range(2, 6)))
                ->setEmptyString('Auto'),
        ]);

        // Orderable department picker
        $config = GridFieldConfig_RelationEditor::create();
        $config->addComponent(new GridFieldOrderableRows('SortOrder'));

        // Bind to the relation
        $fields->addFieldToTab(
            'Root.Departments',
            GridField::create('Departments', 'Departments in this block', $this->Departments(), $config)
        );

        return $fields;
    }

    /** Convenience: departments in editor-defined order */
    public function OrderedDepartments()
    {
        // Sort by the join table’s SortOrder
        return $this->Departments()
        ->sort(['SortOrder' => 'ASC', 'Title' => 'ASC']);
    }

    /** Sections for the template (per-dept + optional “Other Staff”) */
    public function Sections(): ArrayList
    {
        $sections = ArrayList::create();
        $page     = $this->getPage();

        $departments = $this->Departments()->exists()
            ? $this->OrderedDepartments()
            : StoreDepartment::get()->sort('Title');

        foreach ($departments as $dept) {
            $staff = $this->staffFor($dept, $page);
            if ($staff->exists()) {
                $sections->push(ArrayData::create([
                    'Title'      => $dept->Title,
                    'Department' => $dept,
                    'Staff'      => $staff,
                ]));
            }
        }

        if ($this->ShowOtherStaff) {
            $general = $this->otherStaff($departments, $page);
            if ($general->exists()) {
                $sections->push(ArrayData::create([
                    'Title' => 'Other Staff',
                    'Staff' => $general,
                ]));
            }
        }

        return $sections;
    }

    protected function staffFor(StoreDepartment $dept, $page)
    {
        if ($page instanceof StoreLocationPage && $this->ScopeToCurrentLocation && $page->hasMethod('StaffInDepartment')) {
            return $page->StaffInDepartment($dept)->sort(['LastName' => 'ASC', 'FirstName' => 'ASC'])->distinct(true);
        }

        $list = StaffMember::get()->filter(['Departments.ID' => $dept->ID, 'HideStaff' => 0]);
        if ($this->ScopeToCurrentLocation && $page instanceof StoreLocationPage) {
            $list = $list->filter('Locations.ID', $page->ID);
        }
        return $list->sort(['LastName' => 'ASC', 'FirstName' => 'ASC'])->distinct(true);
    }

    protected function otherStaff($departments, $page)
    {
        $base = StaffMember::get()->filter('HideStaff', 0);
        if ($this->ScopeToCurrentLocation && $page instanceof StoreLocationPage) {
            $base = $base->filter('Locations.ID', $page->ID);
        }

        $deptIDs = $departments->column('ID') ?: [];
        if ($deptIDs) {
            $inSomeDeptIDs = StaffMember::get()
                ->filter(['HideStaff' => 0, 'Departments.ID' => $deptIDs]);
            if ($this->ScopeToCurrentLocation && $page instanceof StoreLocationPage) {
                $inSomeDeptIDs = $inSomeDeptIDs->filter('Locations.ID', $page->ID);
            }
            $inSomeDeptIDs = $inSomeDeptIDs->column('ID');
            if ($inSomeDeptIDs) {
                $base = $base->exclude('ID', $inSomeDeptIDs);
            }
        }
        return $base->sort(['LastName' => 'ASC', 'FirstName' => 'ASC'])->distinct(true);
    }
}
