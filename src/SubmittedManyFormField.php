<?php

namespace FullscreenInteractive\ManyField;

use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DB;
use SilverStripe\UserForms\Model\Submission\SubmittedFormField;
use SilverStripe\View\ArrayData;

/**
 * Custom model for saving the table data into.
 */
class SubmittedManyFormField extends SubmittedFormField
{
    private static $table_name = 'SubmittedManyFormField';

    private static $db = [
        'Processed' => 'Boolean'
    ];

    private static $many_many = [
        'Children' => SubmittedFormField::class
    ];

    private static $many_many_extraFields = [
        'Children' => [
            'Row' => 'Int'
        ]
    ];

    private static $cascade_deletes = [
        'Children'
    ];


    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('Processed');

        return $fields;
    }


    public function onAfterWrite()
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
    public function getRows()
    {
        $rows = [];
        $i = 0;
        $max = null;
        $columns = [];

        foreach ($this->Children() as $child) {
            if (!isset($rows[$child->Row])) {
                $rows[$child->Row] = [];
            }

            $rows[$child->Row][$child->Name] = $child;

            if (!$max || $max < $child->Row) {
                $max = $child->Row;
            }

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
