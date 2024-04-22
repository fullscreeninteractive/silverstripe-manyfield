<?php

namespace FullscreenInteractive\ManyField;

use Dotenv\Exception\ValidationException;
use Exception;
use SilverStripe\Forms\GridField\GridField;
use FullscreenInteractive\ManyField\ManyField;
use Psr\Log\LoggerInterface;
use SilverStripe\Assets\Upload;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\UserForms\Extension\UserFormFileExtension;
use SilverStripe\UserForms\Form\GridFieldAddClassesButton;
use SilverStripe\UserForms\Model\EditableFormField;
use SilverStripe\UserForms\Model\EditableFormField\EditableFileField;
use SilverStripe\UserForms\Model\EditableFormField\EditableTextField;
use Symbiote\GridFieldExtensions\GridFieldEditableColumns;
use Symbiote\GridFieldExtensions\GridFieldOrderableRows;

if (class_exists(EditableFormField::class)) {
    return;
}

class EditableManyField extends EditableFormField
{
    private static $singular_name = 'Repeater Field';

    private static $plural_name = 'Repeater Fields';

    private static $db = [];

    private static $table_name = 'EditableManyField';

    private static $many_many = [
        'Children' => EditableFormField::class
    ];

    private static $owns = [
        'Children'
    ];

    private static $cascade_deletes = [
        'Children'
    ];

    private static $cascade_duplicates = [
        'Children'
    ];

    /**
     * @return FieldList
     */
    public function getCMSFields()
    {
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $editableColumns = new GridFieldEditableColumns();
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
                    new GridFieldButtonRow(),
                    (new GridFieldAddClassesButton(EditableTextField::class))
                        ->setButtonName(_t(__CLASS__ . '.ADD_FIELD', 'Add Field'))
                        ->setButtonClass('btn-primary'),
                    (new GridFieldAddClassesButton(EditableFormStep::class))
                        ->setButtonName(_t(__CLASS__ . '.ADD_PAGE_BREAK', 'Add Page Break'))
                        ->setButtonClass('btn-secondary'),
                    (new GridFieldAddClassesButton([EditableFieldGroup::class, EditableFieldGroupEnd::class]))
                        ->setButtonName(_t(__CLASS__ . '.ADD_FIELD_GROUP', 'Add Field Group'))
                        ->setButtonClass('btn-secondary'),
                    $editButton = new GridFieldEditButton(),
                    new GridFieldDeleteAction(),
                    new GridFieldToolbarHeader(),
                    new GridFieldOrderableRows('Sort'),
                    new GridFieldDetailForm(),
                    // Betterbuttons prev and next is enabled by adding a GridFieldPaginator component
                    new GridFieldPaginator(999)
                );

            $fields->addFieldsToTab('Root.Main', [
                GridField::create(
                    'Children',
                    'Children',
                    $this->Children(),
                    $config
                )
            ]);
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
    public function getValueFromData($data)
    {
        $incoming = isset($data[$this->Name]) ? $data[$this->Name] : false;

        if (!$incoming) {
            return json_encode([]);
        }

        // unset any rows which don't have any values at all
        $rowHasValue = [];

        foreach ($this->Children() as $field)
        {
            if (isset($incoming[$field->Name])) {
                foreach ($incoming[$field->Name] as $i => $value) {
                    if ($value && !empty($value)) {
                        $rowHasValue[$i] = true;
                    }
                }
            } elseif (isset($incoming['name']) && isset($incoming['name'][$field->Name])) {
                // handle multi-part
                foreach ($incoming['name'][$field->Name] as $i => $value) {
                    if ($value && !empty($value)) {
                        $rowHasValue[$i] = true;
                    }
                }
            }
        }

        $rows = [];

        foreach ($this->Children() as $field)
        {
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

        return json_encode($rows);
    }


    public function createNestedSubmittedFormField(EditableFormField $field, $data)
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
            $foldername = $field->getFormField()->getFolderName();
            $upload = Upload::create();

            try {
                $result = $upload->loadIntoFile($file, null, $foldername);

                /** @var AssetContainer|File $fileObj */
                $fileObj = $upload->getFile();

                if ($result && $fileObj) {
                    $fileObj->ShowInSearch = 0;
                    $fileObj->UserFormUpload = UserFormFileExtension::USER_FORM_UPLOAD_TRUE;
                    $fileObj->write();

                    $submittedField->UploadedFileID = $fileObj->ID;
                } else {
                    throw new Exception('Could not upload files: %s', implode($upload->getErrors()));
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
