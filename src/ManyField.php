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
use SilverStripe\Forms\HiddenField;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Core\Injector\Injector;
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
     * Can records be removed from the list - useful for displaying just an
     * inline edit form.
     * 
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
     * Does creating a new row automatically call write to the database?
     * 
     * If you use things such as UploadField which requires an ID then it is 
     * best to set this to true
     * 
     * @var boolean
     */
    protected $callWriteOnNewRow = false;

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
     * A callback to customise a given form field instance. Must take 4
     * arguments `$field, $index, $manyField, $value`.
     *
     * @param string $field
     * @param callable $callback
     */
    public function addFieldCallback($field, $callback) {
        if (!isset($this->fieldCallbacks[$field])) {
            $this->fieldCallbacks[$field] = [];
        }

        $this->fieldCallbacks[$field][] = $callback;

        return $this;
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
     * @param boolean $bool
     * 
     * @return $this
     */
    public function setCallWriteOnNewRow($bool)
    {
        $this->callWriteOnNewRow = $bool;

        return $this;
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

    public function AddLink()
    {
        return Controller::join_links(
            $this->AbsoluteLink('createNewRecord'),
            '?SecurityID='. SecurityToken::inst()->getValue()
        );
    }

    public function setValue($value, $data = null)
    {
        if (!$value && $data) {
            if ($data->hasMethod($this->name)) {
                $value = $data->{$this->name}();
            }
        }

        return parent::setValue($value, $data);
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
        } else {
            // display one if none exist.
            $output->push($this->generateRow($index++));
        }

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

        if (!$value && $this->callWriteOnNewRow) {
            // create a new value
            $value = $this->createPhysicalRecord();
        }

        foreach ($this->manyChildren as $child) {
            $field = clone $child;
            $field->name = $this->name . '['.$child->name . ']['. $index . ']';
            
            if ($value && $value->hasMethod($child->Name)) {
                $field->setValue($value->{$child->name}());
            } else {
                $field->setValue(($value) ? $value->{$child->name} : null);
            }

            if (isset($this->fieldCallbacks[$child->name])) {
                foreach ($this->fieldCallbacks[$child->name] as $cb) {
                    call_user_func($cb, $field, $index, $this, $value);
                }
            }

            $row->push($field);
        }

        $this->extend('alterRow', $row);

        return $row;
    }

    public function createPhysicalRecord()
    {
        $create = Injector::inst()->create($this->value->dataClass());
        $create->write();
        
        $this->value->add($create);

        return $create;
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
        if (!$this->value && $this->canRemove) {
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

            if ($this->canRemove) {
                if (!isset($this->value['ID'][$row->ID])) {
                    // missing so delete or remove.
                    if ($delete) {
                        $existing->find('ID', $row->ID)->delete();
                    } else {
                        $existing->removeById($row->ID);
                    }
                }
            }
        }

        if (isset($this->value['ID'])) {
            foreach ($this->value['ID'] as $key => $id) {
                if ($id) {
                    $idKeyMap[$key] = $id;
                }
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
            // if all data is empty then skip adding this record.
            $empty = array_filter($data);

            if (empty($empty)) {
                continue;
            }

            // if mapped to an existing record then find and update.
            $record = null;

            if (isset($idKeyMap[$key])) {
                $record = $existing->find('ID', $idKeyMap[$key]);
            }
            
            if ($record) {
                foreach ($this->manyChildren as $childField) {
                    if (isset($data[$childField->Name])) {
                        $childField->setValue($data[$childField->Name]);
                    }
                    
                    $childField->saveInto($record);
                }

                $record->write();
            } else {
                $record = Injector::inst()->create($existing->dataClass());
                
                foreach ($this->manyChildren as $childField) {
                    if (isset($data[$childField->Name])) {
                        $childField->setValue($data[$childField->Name]);
                    }

                    $childField->saveInto($record);
                }

                $record->write();

                $existing->add($record);
            }
        }

        return $this;
    }
}
