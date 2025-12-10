<?php

namespace Antlion\StoreLocation\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use Antlion\StoreLocation\Model\StoreSchedule;
use Antlion\StoreLocation\Model\StoreHolidayHour;

class StoreHoursAdmin extends ModelAdmin
{
    // IMPORTANT: untyped statics so SilverStripe config picks them up
    private static $menu_title      = 'Store Hours';
    private static $url_segment     = 'store-hours';
    private static $menu_icon_class = 'font-icon-clock';

    private static $managed_models = [
        StoreSchedule::class,
    ];

    // If you added the "Generate U.S. Federal Holidays" action, keep these:
    private static $url_handlers = [
        'item/$ID/doGenerateUSFederalHolidays' => 'doGenerateUSFederalHolidays',
    ];

    private static $allowed_actions = [
        'doGenerateUSFederalHolidays' => 'ADMIN',
    ];

    public function getEditForm($id = null, $fields = null): Form
    {
        $form = parent::getEditForm($id, $fields);

        $record = $this->currentRecord();
        if (!$record || !$record instanceof StoreSchedule) {
            return $form;
        }

        // Action panel (Generate Federal Holidays)
        $panel = FieldGroup::create(
            LiteralField::create('GenHelp', '<strong>Holiday Tools:</strong>&nbsp;'),
            HiddenField::create('RecordID', '', $record->ID),
            NumericField::create('GenYear', 'Year', (int)date('Y')),
            CheckboxField::create('GenObserved', 'Include observed dates (Fri/Mon when a fixed date falls on weekend)')->setValue(1),
            CheckboxField::create('GenClosed', 'Mark generated holidays as Closed')->setValue(1),
            FormAction::create('doGenerateUSFederalHolidays', 'Generate U.S. Federal Holidays')
                ->addExtraClass('btn btn-primary')
        );

        $form->Fields()->insertBefore('Root', $panel);

        return $form;
    }

    public function doGenerateUSFederalHolidays(array $data, Form $form)
    {
        $id = $data['RecordID'] ?? $this->getRequest()->param('ID');
        /** @var StoreSchedule|null $schedule */
        $schedule = StoreSchedule::get()->byID((int)$id);
        if (!$schedule) {
            $form->sessionMessage('Could not find schedule record.', 'bad');
            return $this->redirectBack();
        }

        $year       = isset($data['GenYear']) ? (int)$data['GenYear'] : (int)date('Y');
        $includeObs = !empty($data['GenObserved']);
        $markClosed = !empty($data['GenClosed']);

        $created = StoreHolidayHour::generateUSFederalFor($schedule, $year, $includeObs, $markClosed);

        if ($created > 0) {
            $form->sessionMessage("Created {$created} holiday row(s) for {$year}.", 'good');
        } else {
            $form->sessionMessage("No holidays created for {$year} (possibly already present).", 'notice');
        }

        return $this->redirectBack();
    }
}
