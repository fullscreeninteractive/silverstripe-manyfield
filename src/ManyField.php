<?php

namespace FullscreenInteractive\ManyField;

use SilverStripe\Forms\CompositeField;
use SilverStripe\View\Requirements;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\FormField;
use SilverStripe\Security\SecurityToken;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\HTTPResponse;
use Exception;
use SilverStripe\ORM\RelationList;

class ManyField extends CompositeField
{
    private static $allowed_actions = [
        'createNewRecord',
        'recordForm',
        'saveRecord',
        'deleteRecord',
        'updatedRecords'
    ];

    protected int $minRecords = 0;

    protected int|null $maxRecords = null;

    protected bool $canAdd = true;

    protected bool $canRemove = true;

    protected bool $canSort = true;

    protected bool $inlineSave = false;

    protected $template = 'ManyField';

    protected string $addLabel = 'Add';

    protected bool|string $ajaxUrl = false;

    protected string|null $manyFieldDataClass = null;

    protected bool $callWriteOnNewRow = false;

    protected array $fieldCallbacks = [];

    protected FieldList|null $manyChildren = null;


    public function __construct(string $name, FieldList|array|null $children = null, string|null $title = null)
    {
        Requirements::javascript('fullscreeninteractive/silverstripe-manyfield:client/js/ManyField.src.js');
        Requirements::css('fullscreeninteractive/silverstripe-manyfield:client/css/ManyField.css');

        if ($children instanceof FieldList) {
            $this->manyChildren = $children;
        } else if (is_array($children)) {
            $this->manyChildren = FieldList::create(...$children);
        }

        $this->children = FieldList::create();
        $this->brokenOnConstruct = false;

        FormField::__construct($name, $title);
    }

    /**
     * A callback to customise a given form field instance. Must take 4
     * arguments `$field, $index, $manyField, $value`.
     */
    public function addFieldCallback(string $field, callable $callback): self
    {
        if (!isset($this->fieldCallbacks[$field])) {
            $this->fieldCallbacks[$field] = [];
        }

        $this->fieldCallbacks[$field][] = $callback;

        return $this;
    }


    /**
     * Set the minimum number of records required (e.g. 1).
     */
    public function setMinRecords(int $minRecords): self
    {
        $this->minRecords = $minRecords;

        return $this;
    }


    /**
     * Get the minimum number of records required (e.g. 1).
     */
    public function getMinRecords(): int
    {
        return $this->minRecords;
    }


    /**
     * Set the maximum number of records allowed (e.g. 10).
     */
    public function setMaxRecords(int $maxRecords): self
    {
        $this->maxRecords = $maxRecords;

        return $this;
    }


    /**
     * Get the maximum number of records allowed (e.g. 10).
     */
    public function getMaxRecords(): int|null
    {
        return $this->maxRecords;
    }


    /**
     * If true, the records will be saved inline when the user blurs a field.
     */
    public function setInlineSave(bool $inlineSave): self
    {
        $this->inlineSave = $inlineSave;

        return $this;
    }

    /**
     * Get the inline save flag.
     */
    public function getInlineSave(): bool
    {
        return $this->inlineSave;
    }

    /**
     * Set the can sort flag.
     */
    public function setCanSort(bool $bool): self
    {
        $this->canSort = $bool;

        return $this;
    }

    /**
     * Get the can sort flag.
     */
    public function getCanSort(): bool
    {
        return $this->canSort;
    }

    /**
     * Set the can remove flag.
     */
    public function setCanRemove(bool $bool): self
    {
        $this->canRemove = $bool;

        return $this;
    }

    /**
     * Set the can add flag.
     */
    public function setCanAdd(bool $bool): self
    {
        $this->canAdd = $bool;

        return $this;
    }

    /**
     * Get the can add flag.
     */
    public function canAdd(): bool
    {
        if ($this->readonly) {
            return false;
        }

        return $this->canAdd;
    }

    /**
     * Get the can remove flag.
     */
    public function canRemove(): bool
    {
        if ($this->readonly) {
            return false;
        }

        return $this->canRemove;
    }

    /**
     * Get the can sort flag.
     */
    public function canSort(): bool
    {
        if ($this->readonly) {
            return false;
        }

        return $this->canSort;
    }

    /**
     * Get the add label.
     */
    public function getAddLabel(): string
    {
        return ($this->addLabel ?? 'Add');
    }

    /**
     * Set the add label.
     */
    public function setAddLabel(string $label): self
    {
        $this->addLabel = $label;

        return $this;
    }

    /**
     * Check if the field has data.
     */
    public function hasData(): bool
    {
        return true;
    }

    /**
     * Set the call write on new row flag.
     */
    public function setCallWriteOnNewRow(bool $bool): self
    {
        $this->callWriteOnNewRow = $bool;

        return $this;
    }

    /**
     * Set the load from ajax flag.
     */
    public function setLoadFromAjax(string|bool $url): self
    {
        $this->ajaxUrl = $url;

        return $this;
    }

    /**
     * Get the load from ajax flag.
     */
    public function getLoadFromAjax(): string|bool
    {
        return $this->ajaxUrl;
    }

    /**
     * @param DataObjectInterface $record
     */
    public function saveInto(DataObjectInterface $record)
    {
        if (!$record instanceof DataObject) {
            return;
        }

        if ($record->hasMethod('set' . $this->name)) {
            $func = 'set' . $this->name;
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
     * @return HTTPResponse
     */
    public function createNewRecord()
    {
        $request = Controller::curr()->getRequest();

        if (!SecurityToken::inst()->checkRequest($request)) {
            return Controller::curr()->httpError(400);
        }

        $index = (int) $request->getVar('index');

        $response = HTTPResponse::create();
        $response->setBody($this->generateRow($index)->FieldHolder());

        return $response;
    }

    /**
     * Saves an individual line item
     * @return HTTPResponse|string
     */
    public function saveRecord()
    {
        $request = Controller::curr()->getRequest();

        if (!SecurityToken::inst()->checkRequest($request)) {
            return Controller::curr()->httpError(400);
        }

        if ($this->readonly) {
            return Controller::curr()->httpError(401);
        }

        $index = Controller::curr()->getRequest()->requestVar('ID');
        $class = Controller::curr()->getRequest()->requestVar('ClassName');

        if (!$class) {
            $class = $this->manyFieldDataClass;
        }

        if (!$class && $this->value) {
            $class = $this->value->dataClass();
        }

        if (!is_string($class) || $class === '') {
            throw new Exception('saveRecord() must be passed a ClassName');
        }

        if ($this->manyFieldDataClass && $class !== $this->manyFieldDataClass) {
            throw new Exception('Invalid ClassName passed');
        }

        if (!is_subclass_of($class, DataObject::class)) {
            throw new Exception('ClassName must be a DataObject subclass');
        }

        if (!$index) {
            $record = $class::create();
        } else {
            $record = $class::get()->byId($index);
        }

        if (!$record || !$record->canEdit()) {
            return Controller::curr()->httpError(400);
        }

        // update the record
        foreach ($this->manyChildren as $child) {
            $child->setValue($request->requestVar($child->Name));
            $child->saveInto($record);
        }

        $record->write();

        return $this->forTemplate();
    }

    /**
     * Displays a Form for a particular record.
     */
    public function recordForm()
    {
        $request = Controller::curr()->getRequest();

        if (!SecurityToken::inst()->checkRequest($request)) {
            return Controller::curr()->httpError(400, 'Missing security token');
        }

        $index = Controller::curr()->getRequest()->getVar('RecordID');
        $class = Controller::curr()->getRequest()->getVar('ClassName');

        if (!$class) {
            $class = $this->manyFieldDataClass;
        }

        if (!$index || !is_string($class) || $class === '') {
            throw new Exception('recordForm() must be passed an RecordID and ClassName');
        }

        if ($this->manyFieldDataClass && $class !== $this->manyFieldDataClass) {
            throw new Exception('Invalid ClassName passed');
        }

        if (!is_subclass_of($class, DataObject::class)) {
            throw new Exception('ClassName must be a DataObject subclass');
        }

        $record = $class::get()->byId($index);

        if (!$record || !$record->canView()) {
            return Controller::curr()->httpError(404);
        }

        $response = HTTPResponse::create();

        $edit = $this->generateRow(0, $record, false);
        $edit->removeExtraClass('row manyfield__row');
        $response->setBody($edit->FieldHolder());

        return $response;
    }

    /**
     * Deletes a record
     */
    public function deleteRecord()
    {
        $request = Controller::curr()->getRequest();

        if (!SecurityToken::inst()->checkRequest($request)) {
            return Controller::curr()->httpError(400, 'Bad security token');
        }

        if ($this->readonly) {
            return Controller::curr()->httpError(401);
        }

        $index = Controller::curr()->getRequest()->getVar('ID');
        $class = Controller::curr()->getRequest()->getVar('ClassName');

        if (!$class) {
            $class = $this->manyFieldDataClass;
        }

        if (!$index || !is_string($class) || $class === '') {
            throw new Exception('deleteRecord() must be passed an ID and ClassName');
        }

        if ($this->manyFieldDataClass && $class !== $this->manyFieldDataClass) {
            throw new Exception('Invalid ClassName passed');
        }

        if (!is_subclass_of($class, DataObject::class)) {
            throw new Exception('ClassName must be a DataObject subclass');
        }

        $record = $class::get()->byId($index);

        if (!$record || !$record->canDelete()) {
            return Controller::curr()->httpError(404, 'No record found with that ID');
        }

        $record->delete();

        return $this->forTemplate();
    }

    /**
     * @param string $class
     *
     * @return self
     */
    public function setDataClass($class)
    {
        $this->manyFieldDataClass = $class;

        return $this;
    }

    /**
     * Add link URL.
     */
    public function AddLink(): string
    {
        return Controller::join_links(
            $this->AbsoluteLink('createNewRecord'),
            '?SecurityID=' . SecurityToken::inst()->getValue()
        );
    }

    /**
     * Edit Record Form URL
     */
    public function EditLink(): string
    {
        return Controller::join_links(
            $this->AbsoluteLink('recordForm'),
            '?SecurityID=' . SecurityToken::inst()->getValue() . '&ClassName=' . $this->value->dataClass()
        );
    }

    /**
     * Save Record Form URL
     *
     * @return string
     */
    public function SaveLink(): string
    {
        return Controller::join_links(
            $this->AbsoluteLink('saveRecord'),
            '?SecurityID=' . SecurityToken::inst()->getValue() . '&ClassName=' . $this->value->dataClass()
        );
    }

    /**
     * Override set value.
     */
    public function setValue($value, $data = null)
    {
        if (!$value && $data) {
            if (is_array($data)) {
                if (isset($data[$this->name])) {
                    $value = $data[$this->name];
                }
            } else if ($data->hasMethod($this->name)) {
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
    public function FieldList()
    {
        $output = FieldList::create();
        $index = 0;

        if ($this->value) {
            foreach ($this->value as $record) {
                $row = $this->generateRow($index++, $record);
                $output->push($row);
            }
        } else {
            // display one if none exist.
            $output->push($this->generateRow($index++));
        }

        return $output;
    }

    protected function updateManyNestedField($field, $index, $value, $prefixName)
    {
        $name = $field->name;

        if ($field instanceof CompositeField) {
            foreach ($field->getChildren() as $c) {
                $c = $this->updateManyNestedField($c, $index, $value, false);

                if ($prefixName) {
                    $c->setName($this->name . '[' . $field->name . '][' . $index . '][' . $c->name . ']');
                }
            }
        } else {
            if ($value && is_object($value) && $value->hasMethod($name)) {
                $field->setValue($value->{$name}(), $value);
            } else if (is_object($value)) {
                $field->setValue($value->{$name}, $value);
            } else if (is_array($value)) {
                $field->setValue((isset($value[$name])) ? $value[$name] : null);
            } else {
                $field->setValue($value);
            }

            if ($prefixName) {
                $field->setName($this->name . '[' . $field->name . '][' . $index . ']');
            }
        }

        if (isset($this->fieldCallbacks[$name])) {
            foreach ($this->fieldCallbacks[$name] as $cb) {
                call_user_func($cb, $field, $index, $this, $value);
            }
        }

        return $field;
    }

    /**
     * Generates a unique row of form fields for this ManyField
     *
     * @param int $index
     * @param mixed $value
     * @param bool $prefixName
     *
     * @return CompositeField
     */
    public function generateRow($index, $value = null, $prefixName = true)
    {
        $row = ManyFieldCompositeField::create();
        $row->addExtraClass("row manyfield__row");

        if (!$value && $this->callWriteOnNewRow) {
            // create a new value
            $value = $this->createPhysicalRecord();
        }

        foreach ($this->manyChildren as $child) {
            $field = clone $child;
            $originalName = $field->name;
            $field = $this->updateManyNestedField($field, $index, $value, $prefixName);

            $field = $field->setReadonly($this->readonly);
            $field = $field->setDisabled($this->readonly);

            if ($value) {
                if (is_object($value) && $value->hasMethod('modifyManyRecordField')) {
                    $field = $value->modifyManyRecordField($field);
                } else {
                    $value = $this->value;

                    if (is_object($value)) {
                        $field = $field->setValue($value->{$field->name}, $value);
                    } else if (is_array($value)) {
                        if (isset($value[$originalName])) {
                            $field = $field->setValue((isset($value[$originalName][$index])) ? $value[$originalName][$index] : null);
                        } elseif (isset($value[$index][$originalName])) {
                            $field = $field->setValue((isset($value[$index][$originalName])) ? $value[$index][$originalName] : null);
                        }
                    }
                }
            }

            if ($field) {
                $row->push($field);
            }
        }


        if ($this->getInlineSave()) {
            $row
                ->addExtraClass('inline-save')
                ->setAttribute('data-inline-save', $this->Link('saveRecord'))
                ->setAttribute('data-inline-delete', $this->Link('deleteRecord'));
        }

        $this->extend('alterRow', $row);

        return $row;
    }


    public function createPhysicalRecord()
    {
        $class = $this->manyFieldDataClass ?: ($this->value ? $this->value->dataClass() : null);

        if (!is_string($class) || !is_subclass_of($class, DataObject::class)) {
            throw new Exception('Unable to determine DataObject class for ManyField record creation');
        }

        $create = Injector::inst()->create($class);
        $create->write();

        if ($this->value instanceof RelationList) {
            $this->value->add($create);
        }

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
        if ($this->inlineSave) {
            return $this;
        }

        $existing = $record->{$this->name}();

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

        $updatedData = [];

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
