<?php

namespace Antlion\StoreLocation\Pages;

use PageController;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\SiteConfig\SiteConfig;
use Antlion\StoreLocation\Model\StoreDepartment;
use Antlion\StoreLocation\Model\StaffMember;
use SilverStripe\ORM\ValidationResult;
use App\Models\FormSubmission;
use App\Service\CrmIntegrationService;

class StoreLocationPageController extends PageController
{
    private static array $allowed_actions = [
        'ContactForm',
        'doContactForm',
        'StaffContactForm',
        'doStaffContactForm',
    ];

    protected function init()
    {
        parent::init();
    }

    /* ---------- Helpers ---------- */

    private function isValidEmail(string $addr): bool
    {
        return method_exists(Email::class, 'is_valid_address')
            ? Email::is_valid_address($addr)
            : (bool) filter_var($addr, FILTER_VALIDATE_EMAIL);
    }

    /** Parse a string/array of emails into a unique, validated array */
    private function parseEmails(string|array|null $input): array
    {
        $seen = [];
        $chunks = is_array($input) ? $input : preg_split('/[,\s;]+/', (string) $input);
        foreach ($chunks as $raw) {
            $addr = trim((string) $raw);
            if ($addr !== '' && $this->isValidEmail($addr)) {
                $seen[strtolower($addr)] = $addr;
            }
        }
        return array_values($seen);
    }

    /** Department options for THIS location */
    private function departmentSource(): array
    {
        $list = $this->dataRecord->Departments()->sort('Title ASC');
        return $list->exists() ? $list->map('ID', 'Title')->toArray() : [];
    }

    /** Staff emails in a department AND tied to THIS location */
    private function staffEmailsForDepartmentAtThisLocation(StoreDepartment $dept): array
    {
        return $this->parseEmails(
            $dept->StaffMembers()
                ->filter('Locations.ID', $this->ID)
                ->column('Email')
        );
    }

    /** Recipient resolution: dept staff → dept email → page Mailto → SiteConfig Email */
    private function resolveRecipients(?StoreDepartment $dept): array
    {
        if ($dept) {
            $staff = $this->staffEmailsForDepartmentAtThisLocation($dept);
            if (!empty($staff)) {
                return $staff;
            }

            $deptEmail = $this->parseEmails($dept->Email ?? '');
            if (!empty($deptEmail)) {
                return $deptEmail;
            }
        }

        $pageEmail = $this->parseEmails($this->Mailto ?? '');
        if (!empty($pageEmail)) {
            return $pageEmail;
        }

        $cfg = SiteConfig::current_site_config();
        return $this->parseEmails($cfg->Email ?? '');
    }

    /* ---------- Contact form ---------- */

    public function ContactForm(): Form
    {
        $deptSource = $this->departmentSource();
        $hasDepts   = !empty($deptSource);

        $fields = FieldList::create(
            TextField::create('Name', 'Full Name*')
                ->setAttribute('autocomplete', 'name'),
            EmailField::create('Email', 'Email*')
                ->setAttribute('autocomplete', 'email'),
            TextField::create('Phone', 'Phone (optional)')
                ->setAttribute('autocomplete', 'tel'),
            TextareaField::create('Message', 'Question or Comment*')
                ->setRows(6),

            // Existing context (was PageUrl)
            HiddenField::create('PageUrl', '', $this->AbsoluteLink()),

            // 🔹 CRM-friendly helpers
            HiddenField::create('CRMSource', '', 'location-contact'),
            HiddenField::create('PageURL', '', $this->AbsoluteLink()),
            HiddenField::create('LocationID', '', (string)$this->ID),
            HiddenField::create('LocationTitle', '', $this->Title)
        );

        if ($hasDepts) {
            $fields->insertAfter(
                'Phone',
                DropdownField::create('DepartmentID', 'Department')
                    ->setSource($deptSource)
                    ->setEmptyString('— Select a department (optional) —')
            );

            // Pre-select via ?DepartmentID=123 or ?dept=Title
            $req = $this->getRequest();
            if ($id = (int) $req->getVar('DepartmentID')) {
                if (isset($deptSource[$id])) {
                    $fields->dataFieldByName('DepartmentID')?->setValue($id);
                }
            } elseif ($title = (string) $req->getVar('dept')) {
                $match = $this->dataRecord->Departments()->filter('Title', $title)->first();
                if ($match) {
                    $fields->dataFieldByName('DepartmentID')?->setValue($match->ID);
                }
            }
        } else {
            $fields->push(HiddenField::create('DepartmentID', '', ''));
        }

        $actions = FieldList::create(
            FormAction::create('doContactForm', 'Send message')
                ->addExtraClass('button primary')
        );

        $required = RequiredFields::create(['Name', 'Email', 'Message']);
        $form     = Form::create($this, 'ContactForm', $fields, $actions, $required);

        if (method_exists($form, 'enableSpamProtection')) {
            $form->enableSpamProtection();
        }

        return $form;
    }


    public function doContactForm(array $data, Form $form): HTTPResponse
    {
        if (empty($data['Name']) || empty($data['Email']) || empty($data['Message'])) {
            $form->sessionMessage('Please complete the required fields.', ValidationResult::TYPE_ERROR);
            return $this->redirectBack();
        }
        
        $dept = null;
        if (!empty($data['DepartmentID'])) {
            // Safe: only finds departments linked to THIS page
            $dept = $this->dataRecord->Departments()->byID((int) $data['DepartmentID']);
        }

        $recipients = $this->resolveRecipients($dept);
        if (empty($recipients)) {
            $form->sessionMessage(
                'Sorry, we could not route your message (no valid recipient configured). Please call this location.',
                'bad'
            );
            return $this->redirectBack();
        }

        $fromHost = (string) parse_url(Director::absoluteBaseURL(), PHP_URL_HOST);
        $from     = trim($this->MailFrom ?? '') ?: 'noreply@' . $fromHost;
        $replyTo  = trim($data['Email'] ?? '');

        $subject = sprintf('Website enquiry – %s', $this->Title);
        $templateData = [
            'Name'            => $data['Name'] ?? '',
            'Email'           => $data['Email'] ?? '',
            'Phone'           => $data['Phone'] ?? '',
            'Message'         => $data['Message'] ?? '',
            'DepartmentTitle' => $dept?->Title,
            'LocationTitle'   => $this->Title,
            'PageUrl'         => $data['PageUrl'] ?? $this->AbsoluteLink(),
            'SubmittedAt'     => DBDatetime::now()->Nice(),
        ];

        $sentTo = implode(', ', $recipients);

        // Log to FormSubmission
        $submission = FormSubmission::create([
            'FormName'       => 'Location contact',
            'FormAction'     => 'ContactForm',
            'PageID'         => $this->ID,
            'Context'        => $dept?->Title ?: '',
            'ContextLink'    => $templateData['PageUrl'],
            'SubmitterName'  => $templateData['Name'],
            'SubmitterEmail' => $templateData['Email'],
            'SubmitterPhone' => $templateData['Phone'],
            'Message'        => $templateData['Message'],
            'SentTo'         => $sentTo,
            'RawData'        => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
        $submission->write();

        // 🔗 CRM integration hook
        $crm = CrmIntegrationService::singleton();
        $crm->captureLead($data, $this->getRequest(), [
            'source'           => $data['CRMSource'] ?? 'location-contact',
            'page_url'         => $data['PageURL'] ?? ($data['PageUrl'] ?? $this->AbsoluteLink()),
            'location_id'      => $this->ID,
            'location_title'   => $this->Title,
            'department_id'    => $dept?->ID,
            'department_title' => $dept?->Title,
        ]);

        $email = Email::create()
            ->setTo($recipients)
            ->setFrom($from)
            ->setSubject($subject)
            ->setHTMLTemplate('Antlion/StoreLocation/Email/ContactEmailLocation')
            ->setData($templateData);

        if ($replyTo) {
            $email->addReplyTo($replyTo, $data['Email'] ?? null);
        }

        try {
            $email->send();
            $submission->Status = 'Emailed';
            $submission->write();

            $form->sessionMessage('Thanks! Your message has been sent.', 'good');
        } catch (\Throwable $e) {
            $submission->Status       = 'Error';
            $submission->ErrorMessage = $e->getMessage();
            $submission->write();

            $form->sessionMessage('Sorry, something went wrong sending your message.', 'bad');
        }

        return $this->redirectBack();
    }



    /* ---------- OPTIONAL: per-staff contact (hide staff emails) ---------- */

    public function StaffContactForm(): Form
    {
        $fields = FieldList::create(
            TextField::create('Name', 'Full Name*'),
            EmailField::create('Email', 'Email*'),
            TextareaField::create('Message', 'Question or Comment*')->setRows(6),
            HiddenField::create('StaffID', ''),
            HiddenField::create('PageUrl', '', $this->AbsoluteLink()),

            // 🔹 CRM helpers
            HiddenField::create('CRMSource', '', 'staff-contact'),
            HiddenField::create('PageURL', '', $this->AbsoluteLink()),
            HiddenField::create('LocationID', '', (string)$this->ID),
            HiddenField::create('LocationTitle', '', $this->Title)
        );

        $actions  = FieldList::create(
            FormAction::create('doStaffContactForm', 'Send Email')
                ->addExtraClass('button')
        );
        $required = RequiredFields::create(['Name', 'Email', 'Message']);

        $form = Form::create($this, 'StaffContactForm', $fields, $actions, $required);
        if (method_exists($form, 'enableSpamProtection')) {
            $form->enableSpamProtection();
        }
        return $form;
    }


    public function doStaffContactForm(array $data, Form $form): HTTPResponse
    {
        $staff = null;
        if (!empty($data['StaffID'])) {
            $staff = StaffMember::get()->byID((int)$data['StaffID']);
        }

        // Default to page/mailto if staff has no email
        $fallbackEmail = ($this->Mailto ?? '') ?: (SiteConfig::current_site_config()->Email ?? '');
        $recipients = $this->parseEmails($staff?->Email ?: $fallbackEmail);
        if (empty($recipients)) {
            $form->sessionMessage('No valid recipient configured for that staff member.', 'bad');
            return $this->redirectBack();
        }

        $from    = trim($this->MailFrom ?? '') ?: 'noreply@' . parse_url(Director::absoluteBaseURL(), PHP_URL_HOST);
        $replyTo = trim($data['Email'] ?? '');

        $sentTo = implode(', ', $recipients);
        $staffName = $staff?->Name ?: $staff?->Title ?: '';

        // Log to FormSubmission
        $submission = FormSubmission::create([
            'FormName'       => 'Staff contact',
            'FormAction'     => 'StaffContactForm',
            'PageID'         => $this->ID,
            'Context'        => $staffName,
            'ContextLink'    => $data['PageUrl'] ?? $this->AbsoluteLink(),
            'SubmitterName'  => $data['Name'] ?? '',
            'SubmitterEmail' => $data['Email'] ?? '',
            'SubmitterPhone' => '',
            'Message'        => $data['Message'] ?? '',
            'SentTo'         => $sentTo,
            'RawData'        => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ]);
        $submission->write();

        // 🔗 CRM integration hook
        $crm = CrmIntegrationService::singleton();
        $crm->captureLead($data, $this->getRequest(), [
            'source'          => $data['CRMSource'] ?? 'staff-contact',
            'page_url'        => $data['PageURL'] ?? ($data['PageUrl'] ?? $this->AbsoluteLink()),
            'location_id'     => $this->ID,
            'location_title'  => $this->Title,
            'staff_id'        => $staff?->ID,
            'staff_name'      => $staffName,
        ]);

        $email = Email::create()
            ->setTo($recipients)
            ->setFrom($from)
            ->setSubject('Website enquiry')
            ->setHTMLTemplate('Email/StaffContactFormEmail')
            ->setData($data);

        if ($replyTo) {
            $email->addReplyTo($replyTo, $data['Name'] ?? null);
        }

        try {
            $email->send();
            $submission->Status = 'Emailed';
            $submission->write();

            $form->sessionMessage('Thanks! Your message has been sent.', 'good');
        } catch (\Throwable $e) {
            $submission->Status       = 'Error';
            $submission->ErrorMessage = $e->getMessage();
            $submission->write();

            $form->sessionMessage('Sorry, something went wrong sending your message.', 'bad');
        }

        return $this->redirectBack();
    }

}
