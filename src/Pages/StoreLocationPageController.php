<?php

namespace Antlion\StoreLocation\Pages;

use Antlion\StoreLocation\Model\StaffMember;
use Antlion\StoreLocation\Model\StoreDepartment;
use PageController;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Location contact forms. Messages are routed by department:
 * department staff at this location → department email → the page's
 * "Location Recipients" → SiteConfig email.
 *
 * Every enquiry fires onAfterLocationEnquiry(array $enquiry) after the send
 * attempt, so a project can log it (e.g. to its own submissions model)
 * without this module depending on that model.
 */
class StoreLocationPageController extends PageController
{
    private static array $allowed_actions = [
        'ContactForm',
        'StaffContactForm',
    ];

    /** CSS classes for the submit buttons; themes differ (e.g. Foundation: "button primary") */
    private static string $submit_button_class = 'btn btn-primary';

    /* ---------- Contact form ---------- */

    public function ContactForm(): Form
    {
        $fields = FieldList::create(
            TextField::create('Name', _t(__CLASS__ . '.Name', 'Full name'))
                ->setAttribute('autocomplete', 'name'),
            EmailField::create('Email', _t(__CLASS__ . '.Email', 'Email'))
                ->setAttribute('autocomplete', 'email'),
            TextField::create('Phone', _t(__CLASS__ . '.Phone', 'Phone (optional)'))
                ->setAttribute('autocomplete', 'tel')
                ->setAttribute('type', 'tel'),
            TextareaField::create('Message', _t(__CLASS__ . '.Message', 'Question or comment'))
                ->setRows(6)
        );

        $departments = $this->departmentSource();
        if ($departments) {
            $dropdown = DropdownField::create('DepartmentID', _t(__CLASS__ . '.Department', 'Department'), $departments)
                ->setEmptyString(_t(__CLASS__ . '.AnyDepartment', 'Any department'));
            $fields->insertAfter('Phone', $dropdown);

            // Pre-select via ?DepartmentID=123 or ?dept=Title
            $request = $this->getRequest();
            if (($id = (int) $request->getVar('DepartmentID')) && isset($departments[$id])) {
                $dropdown->setValue($id);
            } elseif ($title = (string) $request->getVar('dept')) {
                $match = $this->dataRecord->Departments()->filter('Title', $title)->first();
                if ($match) {
                    $dropdown->setValue($match->ID);
                }
            }
        }

        $actions = FieldList::create(
            FormAction::create('doContactForm', _t(__CLASS__ . '.Send', 'Send message'))
                ->addExtraClass(static::config()->get('submit_button_class'))
        );

        $form = Form::create($this, 'ContactForm', $fields, $actions, RequiredFieldsValidator::create(['Name', 'Email', 'Message']));
        $this->protect($form);

        return $form;
    }

    public function doContactForm(array $data, Form $form): HTTPResponse
    {
        $dept = !empty($data['DepartmentID'])
            ? $this->dataRecord->Departments()->byID((int) $data['DepartmentID'])
            : null;

        $recipients = $this->resolveRecipients($dept);
        if (!$recipients) {
            $form->sessionMessage(
                _t(__CLASS__ . '.NoRecipient', 'Sorry, we couldn\'t send your message. Please call this location instead.'),
                ValidationResult::TYPE_ERROR
            );
            return $this->redirectBack();
        }

        $templateData = [
            'Name' => $data['Name'] ?? '',
            'Email' => $data['Email'] ?? '',
            'Phone' => $data['Phone'] ?? '',
            'Message' => $data['Message'] ?? '',
            'DepartmentTitle' => $dept?->Title,
            'LocationTitle' => $this->dataRecord->getDisplayName(),
            // Server-side, never from the submission
            'PageUrl' => $this->AbsoluteLink(),
            'SubmittedAt' => DBDatetime::now()->Nice(),
        ];

        $email = Email::create()
            ->setTo($recipients)
            ->setFrom($this->resolveFromEmail(), $this->resolveFromName())
            ->setSubject(_t(__CLASS__ . '.Subject', 'Website enquiry – {location}', ['location' => $templateData['LocationTitle']]))
            ->setHTMLTemplate('Antlion/StoreLocation/Email/ContactEmailLocation')
            ->setData($templateData);

        return $this->sendEnquiry($email, $form, $data, [
            'FormName' => 'Location contact',
            'FormAction' => 'ContactForm',
            'Context' => $dept?->Title ?: '',
            'SubmitterName' => $templateData['Name'],
            'SubmitterEmail' => $templateData['Email'],
            'SubmitterPhone' => $templateData['Phone'],
            'Message' => $templateData['Message'],
            'SentTo' => implode(', ', $recipients),
        ]);
    }

    /* ---------- Per-staff contact (keeps staff emails off the page) ---------- */

    public function StaffContactForm(): Form
    {
        $fields = FieldList::create(
            TextField::create('Name', _t(__CLASS__ . '.Name', 'Full name'))->setAttribute('autocomplete', 'name'),
            EmailField::create('Email', _t(__CLASS__ . '.Email', 'Email'))->setAttribute('autocomplete', 'email'),
            TextareaField::create('Message', _t(__CLASS__ . '.Message', 'Question or comment'))->setRows(6),
            HiddenField::create('StaffID', '')
        );

        $actions = FieldList::create(
            FormAction::create('doStaffContactForm', _t(__CLASS__ . '.Send', 'Send message'))
                ->addExtraClass(static::config()->get('submit_button_class'))
        );

        $form = Form::create($this, 'StaffContactForm', $fields, $actions, RequiredFieldsValidator::create(['Name', 'Email', 'Message', 'StaffID']));
        $this->protect($form);

        return $form;
    }

    public function doStaffContactForm(array $data, Form $form): HTTPResponse
    {
        // Only visible staff at this location can be messaged through this page
        $staff = StaffMember::get()->filter([
            'ID' => (int) ($data['StaffID'] ?? 0),
            'Locations.ID' => $this->ID,
            'HideStaff' => false,
        ])->first();

        $recipients = $this->parseEmails($staff?->Email);
        if (!$staff || !$recipients) {
            $form->sessionMessage(_t(__CLASS__ . '.NoStaff', 'Sorry, we couldn\'t find that staff member.'), ValidationResult::TYPE_ERROR);
            return $this->redirectBack();
        }

        $templateData = [
            'Name' => $data['Name'] ?? '',
            'Email' => $data['Email'] ?? '',
            'Message' => $data['Message'] ?? '',
            'StaffName' => $staff->getFullName(),
            'LocationTitle' => $this->dataRecord->getDisplayName(),
            'PageUrl' => $this->AbsoluteLink(),
            'SubmittedAt' => DBDatetime::now()->Nice(),
        ];

        $email = Email::create()
            ->setTo($recipients)
            ->setFrom($this->resolveFromEmail(), $this->resolveFromName())
            ->setSubject(_t(__CLASS__ . '.StaffSubject', 'Website enquiry for {name}', ['name' => $templateData['StaffName']]))
            ->setHTMLTemplate('Antlion/StoreLocation/Email/StaffContactEmail')
            ->setData($templateData);

        return $this->sendEnquiry($email, $form, $data, [
            'FormName' => 'Staff contact',
            'FormAction' => 'StaffContactForm',
            'Context' => $templateData['StaffName'],
            'SubmitterName' => $templateData['Name'],
            'SubmitterEmail' => $templateData['Email'],
            'SubmitterPhone' => '',
            'Message' => $templateData['Message'],
            'SentTo' => implode(', ', $recipients),
        ]);
    }

    /* ---------- Helpers ---------- */

    /**
     * Sends, reports the result on the form, and fires the logging hook.
     */
    private function sendEnquiry(Email $email, Form $form, array $data, array $enquiry): HTTPResponse
    {
        $replyTo = trim((string) ($data['Email'] ?? ''));
        if ($replyTo && $this->isValidEmail($replyTo)) {
            $email->setReplyTo($replyTo); // Symfony mailer: address only
        }

        $enquiry += [
            'PageID' => $this->ID,
            'ContextLink' => $this->AbsoluteLink(),
            // Only the form's own fields: no security token, nothing injected
            'RawData' => array_diff_key(array_intersect_key($data, $form->Fields()->dataFields()), ['SecurityID' => true]),
        ];

        try {
            $email->send();
            $enquiry['Status'] = 'Emailed';
            $form->sessionMessage(_t(__CLASS__ . '.Sent', 'Thanks! Your message has been sent.'), ValidationResult::TYPE_GOOD);
        } catch (\Throwable $e) {
            $enquiry['Status'] = 'Error';
            $enquiry['ErrorMessage'] = $e->getMessage();
            $form->sessionMessage(_t(__CLASS__ . '.Failed', 'Sorry, something went wrong sending your message.'), ValidationResult::TYPE_ERROR);
        }

        $this->extend('onAfterLocationEnquiry', $enquiry);

        return $this->redirectBack();
    }

    /** reCAPTCHA etc. when silverstripe/spamprotection is installed and configured */
    private function protect(Form $form): void
    {
        if ($form->hasMethod('enableSpamProtection')) {
            $form->enableSpamProtection();
        }
    }

    private function isValidEmail(string $address): bool
    {
        return Email::is_valid_address($address);
    }

    /** Parse a string/array of emails into a unique, validated array */
    private function parseEmails(string|array|null $input): array
    {
        $seen = [];
        $chunks = is_array($input) ? $input : preg_split('/[,\s;]+/', (string) $input);
        foreach ($chunks as $raw) {
            $address = trim((string) $raw);
            if ($address !== '' && $this->isValidEmail($address)) {
                $seen[strtolower($address)] = $address;
            }
        }

        return array_values($seen);
    }

    /** From: SiteConfig DefaultFromEmail if set and valid, else noreply@<host> */
    private function resolveFromEmail(): string
    {
        $from = trim((string) (SiteConfig::current_site_config()->DefaultFromEmail ?? ''));
        if ($from && $this->isValidEmail($from)) {
            return $from;
        }

        $host = preg_replace('/^www\./i', '', (string) parse_url(Director::absoluteBaseURL(), PHP_URL_HOST));

        return 'noreply@' . $host;
    }

    private function resolveFromName(): string
    {
        return trim((string) (SiteConfig::current_site_config()->DefaultFromName ?? ''));
    }

    /** Department options for this location */
    private function departmentSource(): array
    {
        return $this->dataRecord->Departments()->sort('Title ASC')->map('ID', 'Title')->toArray();
    }

    /** Recipient resolution: dept staff here → dept email → page Mailto → SiteConfig Email */
    private function resolveRecipients(?StoreDepartment $dept): array
    {
        if ($dept) {
            $staff = $this->parseEmails(
                $dept->StaffMembers()->filter(['Locations.ID' => $this->ID, 'HideStaff' => false])->column('Email')
            );
            if ($staff) {
                return $staff;
            }
            if ($deptEmail = $this->parseEmails($dept->Email)) {
                return $deptEmail;
            }
        }

        return $this->parseEmails($this->dataRecord->Mailto)
            ?: $this->parseEmails(SiteConfig::current_site_config()->Email ?? '');
    }
}
