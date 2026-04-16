<?php

namespace FullscreenInteractive\ManyField;

use Exception;
use SilverStripe\Assets\File;
use Psr\Log\LoggerInterface;
use SilverStripe\Assets\Upload;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\LiteralField;
use SilverStripe\UserForms\Extension\UserFormFileExtension;
use SilverStripe\UserForms\Form\GridFieldAddClassesButton;
use SilverStripe\UserForms\Model\EditableFormField;
use SilverStripe\UserForms\Model\EditableFormField\EditableFieldGroup;
use SilverStripe\UserForms\Model\EditableFormField\EditableFieldGroupEnd;
use SilverStripe\UserForms\Model\EditableFormField\EditableFileField;
use SilverStripe\UserForms\Model\EditableFormField\EditableFormStep;
use SilverStripe\UserForms\Model\EditableFormField\EditableTextField;
use SilverStripe\UserForms\Model\Submission\SubmittedFormField;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

if (!class_exists(EditableFormField::class)) {
    return;
}

class EditableManyField extends EditableFormField
{
    /** @var string */
    private static $singular_name = 'Repeater Field';

    /** @var string */
    private static $plural_name = 'Repeater Fields';

    /** @var array<string, mixed> */
    private static $db = [];

    /** @var string */
    private static $table_name = 'EditableManyField';

    /** @var array<string, class-string> */
    private static $many_many = [
        'Children' => EditableFormField::class
    ];

    /** @var array<int, string> */
    private static $owns = [
        'Children'
    ];

    /** @var array<int, string> */
    private static $cascade_deletes = [
        'Children'
    ];

    /** @var array<int, string> */
    private static $cascade_duplicates = [
        'Children'
    ];

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $fields->removeByName('Children');
            $fields->removeByName('Default');

            $editableColumns = GridFieldEditableColumns::create();
            $fieldClasses = singleton(EditableFormField::class)->getEditableFieldClasses();
            $editableColumns->setDisplayFields([
                'ClassName' => function ($record, $column, $grid) use ($fieldClasses) {
                    if ($record instanceof EditableFormField) {
                        $field = $record->getInlineClassnameField($column, $fieldClasses);
                        if ($record instanceof EditableFileField) {
                            $field->setAttribute('data-folderconfirmed', $record->FolderConfirmed ? 1 : 0);
                        }
                        return $field;
                    }
                },
                'Title' => function ($record, $column, $grid) {
                    if ($record instanceof EditableFormField) {
                        return $record->getInlineTitleField($column);
                    }
                }
            ]);

            $config = GridFieldConfig::create()
                ->addComponents(
                    $editableColumns,
                    GridFieldButtonRow::create(),
                    (new GridFieldAddClassesButton([EditableTextField::class]))
                        ->setButtonName(_t(__CLASS__ . '.ADD_FIELD', 'Add Field'))
                        ->setButtonClass('btn-primary'),
                    (new GridFieldAddClassesButton([EditableFormStep::class]))
                        ->setButtonName(_t(__CLASS__ . '.ADD_PAGE_BREAK', 'Add Page Break'))
                        ->setButtonClass('btn-secondary'),
                    (new GridFieldAddClassesButton([EditableFieldGroup::class, EditableFieldGroupEnd::class]))
                        ->setButtonName(_t(__CLASS__ . '.ADD_FIELD_GROUP', 'Add Field Group'))
                        ->setButtonClass('btn-secondary'),
                    GridFieldEditButton::create(),
                    GridFieldDeleteAction::create(),
                    GridFieldToolbarHeader::create(),
                    GridFieldOrderableRows::create('Sort'),
                    GridFieldDetailForm::create(),
                    // Betterbuttons prev and next is enabled by adding a GridFieldPaginator component
                    GridFieldPaginator::create(999)
                );

            if ($this->isInDB()) {
                $fields->addFieldsToTab('Root.Main', [
                    GridField::create(
                        'Children',
                        '',
                        $this->Children(),
                        $config
                    )
                ]);
            } else {
                $fields->addFieldsToTab('Root.Main', [
                    LiteralField::create(
                        'Children',
                        '<p class="alert alert-info">' . _t(__CLASS__ . '.NO_DATA', 'Save this form to see the fields') . '</p>'
                    )
                ]);
            }
        });

        return parent::getCMSFields();
    }

    /**
     * Return the form field
     *
     */
    public function getFormField()
    {
        $children = [];
        foreach ($this->Children() as $editableFormField) {
            $children[] = $editableFormField->getFormField();
        }

        $field = ManyField::create($this->Name, FieldList::create($children), $this->Title)
            ->setCanSort(false)
            ->setMinRecords(1);

        $this->doUpdateFormField($field);

        return $field;
    }


    public function getSubmittedFormField(): SubmittedManyFormField
    {
        return SubmittedManyFormField::create();
    }


    /**
     * When saving this data from the front end, extract the array and
     * create the children records
     */
    /**
     * @param array<string, mixed> $data
     */
    public function getValueFromData(array $data): string
    {
        $incoming = isset($data[$this->Name]) ? $data[$this->Name] : false;

        if (!$incoming) {
            return '[]';
        }

        // unset any rows which don't have any values at all
        $rowHasValue = [];

        foreach ($this->Children() as $field) {
            if (isset($incoming[$field->Name])) {
                foreach ($incoming[$field->Name] as $i => $value) {
                    if ($value !== null && $value !== '') {
                        $rowHasValue[$i] = true;
                    }
                }
            } elseif (isset($incoming['name']) && isset($incoming['name'][$field->Name])) {
                // handle multi-part
                foreach ($incoming['name'][$field->Name] as $i => $value) {
                    if ($value !== null && $value !== '') {
                        $rowHasValue[$i] = true;
                    }
                }
            }
        }

        $rows = [];

        foreach ($this->Children() as $field) {
            if (isset($incoming[$field->Name])) {
                foreach ($incoming[$field->Name] as $i => $value) {
                    if (!isset($rowHasValue[$i])) {
                        // empty row;
                        continue;
                    }

                    if (!isset($rows[$i])) {
                        $rows[$i] = [];
                    }

                    $submittedField = $this->createNestedSubmittedFormField($field, [
                        $field->Name => $value
                    ]);

                    $rows[$i][$field->Name] = $submittedField->ID;
                }
            } elseif (isset($incoming['name']) && isset($incoming['name'][$field->Name])) {
                // handle multi-part
                foreach ($incoming['name'][$field->Name] as $i => $value) {
                    if (!isset($rowHasValue[$i])) {
                        // empty row;
                        continue;
                    }

                    $submittedField = $this->createNestedSubmittedFormField($field, [
                        $field->Name => $value
                    ]);

                    $rows[$i][$field->Name] = $submittedField->ID;
                }
            }
        }

        $json = json_encode($rows);
        return is_string($json) ? $json : '[]';
    }


    /**
     * @param array<string, mixed> $data
     */
    public function createNestedSubmittedFormField(EditableFormField $field, array $data): SubmittedFormField
    {
        $submittedField = $field->getSubmittedFormField();
        $submittedField->Name = $field->Name;
        $submittedField->Title = $field->getField('Title');

        // save the value from the data
        if ($field->hasMethod('getValueFromData')) {
            $submittedField->Value = $field->getValueFromData($data);
        } else {
            if (isset($data[$field->Name])) {
                $submittedField->Value = $data[$field->Name];
            }
        }

        $file = null;
        if (!empty($_FILES[$field->Name]['name'])) {
            $file = $_FILES[$field->Name];
        } else if (!empty($_FILES[$this->Name]['name'])) {
            if (!empty($_FILES[$this->Name]['name'][$field->Name])) {
                $file = [
                    'tmp_name' => $_FILES[$this->Name]['tmp_name'][$field->Name][0],
                    'type' => $_FILES[$this->Name]['type'][$field->Name][0],
                    'name' => $_FILES[$this->Name]['name'][$field->Name][0],
                    'error' => $_FILES[$this->Name]['error'][$field->Name][0],
                    'size' => $_FILES[$this->Name]['size'][$field->Name][0]
                ];
            }
        }

        if ($file) {
            $foldername = false;
            $fieldFormField = $field->getFormField();
            if (method_exists($fieldFormField, 'getFolderName')) {
                $foldername = (string) $fieldFormField->getFolderName();
            }
            $upload = Upload::create();

            try {
                $result = $upload->loadIntoFile($file, null, $foldername);

                $fileObj = $upload->getFile();

                if ($result && $fileObj instanceof File) {
                    $fileObj->setField('ShowInSearch', 0);
                    $fileObj->setField('UserFormUpload', UserFormFileExtension::USER_FORM_UPLOAD_TRUE);
                    $fileObj->write();

                    $submittedField->UploadedFileID = $fileObj->ID;
                } else {
                    throw new Exception(sprintf('Could not upload files: %s', implode(', ', $upload->getErrors())));
                }
            } catch (ValidationException $e) {
                Injector::inst()->get(LoggerInterface::class)->error($e);
            }
        }

        $submittedField->extend('onPopulationFromField', $field);
        $submittedField->write();

        return $submittedField;
    }
}
