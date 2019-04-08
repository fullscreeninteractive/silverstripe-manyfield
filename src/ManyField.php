<?php

namespace FullscreenInteractive\ManyField;

use SilverStripe\Forms\CompositeField;
use SilverStripe\View\Requirements;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\FormField;
use SilverStripe\Control\HTTPResponse;

class ManyField extends CompositeField
{
    private static $allowed_actions = [
        'createNewRecord'
    ];

    /**
     * @var int
     */
    protected $minRecords = 0;

    /**
     * @var int
     */
    protected $maxRecords = null;

    /**
     * @var boolean
     */
    protected $canAdd = true;

    /**
     * @var boolean
     */
    protected $canRemove = true;

    /**
     * @var boolean
     */
    protected $canSort = true;

    /**
     * @var string
     */
    protected $template = 'ManyField';

    /**
     * @var string
     */
    protected $addLabel = 'Add';

    /**
     * @var array
     */
    protected $fieldCallbacks = [];

    /**
     * 
     */
    protected $manyChildren = [];

    /**
     * @param string $name
     * @param array $children
     */
    public function __construct($name, $children = null) {
        Requirements::javascript('fullscreeninteractive/silverstripe-manyfield:client/js/ManyField.src.js');
        Requirements::css('fullscreeninteractive/silverstripe-manyfield:client/css/ManyField.css');

        if ($children instanceof FieldList) {
            $this->manyChildren = $children;
        } else if (is_array($children)) {
            $this->manyChildren = new FieldList($children);
        }
        
        $this->children = new FieldList();
        $this->brokenOnConstruct = false;

        FormField::__construct($name, null);
    }

    /**
     * A callback to customise a given form field instance. Must take 3
     * arguments `$field, $index, $manyField`.
     *
     * @param string $field
     * @param callable $callback
     */
    public function addFieldCallback($field, $callback) {
        $this->fieldCallbacks[$field] = $callback;
    }

    /**
     * @param int $minRecords
     *
     * @return $this
     */
    public function setMinRecords($minRecords) {
        $this->minRecords = $minRecords;

        return $this;
    }

    /**
     * @return int
     */
    public function getMinRecords() {
        return $this->minRecords;
    }

    /**
     * @param int $maxRecords
     *
     * @return $this
     */
    public function setMaxRecords($maxRecords) {
        $this->maxRecords = $maxRecords;

        return $this;
    }

    /**
     * @return int
     */
    public function getMaxRecords() {
        return $this->maxFields;
    }

    /**
     * @param boolean $bool
     *
     * @return $this
     */
    public function setCanSort($bool) {
        $this->canSort = $bool;

        return $this;
    }

    /**
     * @param boolean $bool
     *
     * @return $this
     */
    public function setCanRemove($bool) {
        $this->canRemove = $bool;

        return $this;
    }

    /**
     * @param boolean $bool
     *
     * @return $this
     */
    public function setCanAdd($bool) {
        $this->canAdd = $bool;

        return $this;
    }

    /**
     * @return boolean
     */
    public function canAdd() {
        return $this->canAdd;
    }

    /**
     * @return boolean
     */
    public function canRemove() {
        return $this->canRemove;
    }

    /**
     * @return boolean
     */
    public function canSort() {
        return $this->canSort;
    }

    /**
     * @return string
     */
    public function getAddLabel()
    {
        return $this->addLabel;
    }

    /**
     * @param string $label
     *
     * @return $this
     */
    public function setAddLabel($label)
    {
        $this->addLabel = $label;

        return $this;
    }

    /**
     * @return boolean
     */
    public function hasData()
    {
        return true;
    }

    /**
     * Set the field value.
     *
     * If a FormField requires specific behaviour for loading content from either the database
     * or a submitted form value they should override setSubmittedValue() instead.
     *
     * @param mixed $value Either the parent object, or array of source data being loaded
     * @param array|DataObject $data {@see Form::loadDataFrom}
     * @return $this
     */
    public function setSubmittedValue($value, $data = null)
    {
        parent::setSubmittedValue($value, $data);
    }

    /**
     * @param DataObjectInterface $record
     */
    public function saveInto(DataObjectInterface $record)
    {
        if ($record->hasMethod('set'. $this->name)) {
            $func = 'set'. $this->name;
            $record->$func($this);
        } else if ($record->hasField($this->name)) {
            $record->setCastedField($this->name, json_encode($this->dataValue()));
        } else if ($record->getRelationType($this->name)) {
            $this->updateRelation($record, true);
        }
    }

    /**
     * Creates a new row template and returns it onto the page. As this is a new
     * record we never will have to load anything into it.
     *
     * @return HTML;
     */
    public function createNewRecord()
    {
        $index = Controller::curr()->getRequest()->getVar('index');

        $response = new HTTPResponse();
        $response->setBody($this->generateRow($index++)->FieldHolder());

        return $response;
    }

    /**
     * Return the list of fields. We'll create a row for each of the values if
     * they exist otherwise we'll only return
     *
     * @return FieldList
     */
    public function FieldList() {
        $output = FieldList::create();
        $index = 0;

        if ($this->value) {
            foreach ($this->value as $record) {
                $output->push($this->generateRow($index++, $record));
            }
        }

        $output->push($this->generateRow($index++));

        return $output;
    }

    /**
     * Generates a unique row of form fields for this ManyField
     *
     * @param int $index
     * @param mixed value
     *
     * @return CompositeField
     */
    public function generateRow($index, $value = null)
    {
        $row = CompositeField::create();
        $row->addExtraClass("row manyfield__row");

        foreach ($this->manyChildren as $child) {
            $field = clone $child;
            $field->name = $this->name . '['.$child->name . ']['. $index . ']';

            if (isset($this->fieldCallbacks[$child->name])) {
                call_user_func($this->fieldCallbacks[$name], $field, $index, $this);
            }

            $row->push($field);
        }

        $this->extend('alterRow', $row);

        return $row;
    }

    public function AbsoluteLink($action = null)
    {
        return Director::absoluteURL($this->Link($action));
    }

    /**
     * Helper for going through all the values in this manymany field and 
     * delete or create new records. This method won't be perfect for every case
     * but it'll handle most cases as long as the Field name matches the 
     * relation name.
     */
    public function updateRelation(DataObjectInterface $record, $delete = true) 
    {
        $existing = $record->{$this->name}();
        $removed = [];

        // if no value then we should clear everything out
        if (!$this->value) {
            if ($delete) {
                foreach ($existing as $row) {
                    $row->delete();
                }
            } else {
                $existing->removeAll();
            }

            return $this;
        }

        foreach ($existing as $row) {
            if (!isset($this->value['ID'])) {
                throw new Exception('Missing ID field in ManyMany field list.');
            }

            if (!isset($this->value['ID'][$row->ID])) {
                // missing so delete or remove.
                if ($delete) {
                    $existing->find('ID', $row->ID)->delete();
                } else {
                    $existing->removeById($row->ID);
                }
            }

            foreach ($this->value['ID'] as $key => $id) {
                if ($id) {
                    $idKeyMap[$key] = $id;
                }
            }
            
            foreach ($this->value as $col => $values) {
                if ($col == 'ID') {
                    continue;
                }

                foreach ($values as $key => $value) {
                    if (!isset($updatedData[$key])) {
                        $updatedData[$key] = [];
                    }

                    $updatedData[$key][$col] = $value;
                }
            }

            foreach ($updatedData as $key => $data) {
                if (isset($idKeyMap[$key])) {
                    $existing->find('ID', $idKeyMap[$key])->update($row);
                } else {
                    $create = Injector::inst()->create($existing->dataClass());
                    $create->update($data);
                    $create->write();
                    $existing->add($create);
                }
            }
        }

        return $this;
    }
}
