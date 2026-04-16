<?php

namespace FullscreenInteractive\ManyField;

use SilverStripe\Model\List\ArrayList;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\UserForms\Model\Submission\SubmittedFormField;
use SilverStripe\Model\ArrayData;

/**
 * Custom model for saving the table data into.
 */
class SubmittedManyFormField extends SubmittedFormField
{
    /** @var string */
    private static $table_name = 'SubmittedManyFormField';

    /** @var array<string, string> */
    private static $db = [
        'Processed' => 'Boolean'
    ];

    /** @var array<string, class-string> */
    private static $many_many = [
        'Children' => SubmittedFormField::class
    ];

    /** @var array<string, array<string, string>> */
    private static $many_many_extraFields = [
        'Children' => [
            'Row' => 'Int'
        ]
    ];

    /** @var array<int, string> */
    private static $cascade_deletes = [
        'Children'
    ];


    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('Processed');

        return $fields;
    }


    public function onAfterWrite(): void
    {
        parent::onAfterWrite();

        if (!$this->Processed) {
            if ($rows = json_decode($this->Value, true)) {
                foreach ($rows as $i => $ids) {
                    foreach ($ids as $fieldName => $id) {
                        $this->Children()->add($id, [
                            'Row' => $i
                        ]);
                    }
                }
            }

            DB::query('UPDATE SubmittedManyFormField SET Processed = 1 WHERE ID = '. $this->ID);
        }
    }


    public function getFormattedValue()
    {
        return $this->renderWith('Includes/SubmittedManyFormField');
    }


    /**
     * Return the value of this submitted form field suitable for inclusion
     * into the CSV
     *
     * @return DBField
     */
    public function getExportValue()
    {
        return $this->renderWith('Includes/SubmittedManyFormFieldExport');
    }


    /**
     * Returns the rows of the many field. Optionally includes a header row
     * if it is needed. As the order of the fields may change submission to
     * submission, it first sorts by the latest row (the newest configuration)
     *
     */
    /**
     * @return ArrayList<ArrayData>
     */
    public function getRows(): ArrayList
    {
        $rows = [];
        $columns = [];

        foreach ($this->Children() as $child) {
            if (!isset($rows[$child->Row])) {
                $rows[$child->Row] = [];
            }

            $rows[$child->Row][$child->Name] = $child;

            $columns[$child->Name] = $child->Title;
        }

        $output = ArrayList::create();
        $lastHeader = null;

        foreach ($rows as $row => $data) {
            if (!$lastHeader) {
                $header = ArrayList::create();

                foreach ($columns as $name => $title) {
                    $header->push(ArrayData::create([
                        'Title' => $title
                    ]));
                }

                $lastHeader = $header;
            } else {
                $header = null;
            }

            $columnData = ArrayList::create();

            foreach ($columns as $name => $column) {
                $value = SubmittedFormField::create();

                foreach ($data as $field) {
                    if ($field->Name === $name) {
                        $value = $field;

                        break;
                    }
                }

                $columnData->push($value);
            }

            $output->push(ArrayData::create([
                'HeaderRow' => $header,
                'Columns' => $columnData
            ]));
        }

        return $output;
    }
}
