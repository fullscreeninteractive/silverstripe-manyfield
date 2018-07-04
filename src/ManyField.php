<?php

namespace FullscreenInteractive\ManyField;

use SilverStripe\Forms\CompositeField;
use SilverStripe\View\Requirements;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\ORM\DataObjectInterface;

class ManyField extends CompositeField
{
    protected $requiredFieldGroups = 0;

    protected $maxFields = null;

    protected $canAdd = true;

    protected $canRemove = true;

    protected $template = 'ManyField';

    protected $addLabel = 'Add';

    protected $fieldCallbacks = [];

    public function __construct($name, $children = null) {
        Requirements::javascript('fullscreeninteractive/manyfield:client/js/ManyField.src.js');
        Requirements::css('fullscreeninteractive/manyfield:client/css/ManyField.css');

        if ($children instanceof FieldList) {
            $this->children = $children;
        } elseif (is_array($children)) {
            $this->children = new FieldList($children);
        } else {
            //filter out null/empty items
            $children = array_filter(func_get_args());
            $this->children = new FieldList($children);
        }

        $this->children->setContainerField($this);
        $this->setRequiredFieldGroups($requiredFieldGroups);
        $this->setMaxFields($maxFields);

        $this->addExtraClass(sprintf("%s_required", $this->getRequiredFieldGroups()));

        $this->brokenOnConstruct = false;

        $this->setName($name);
    }

    /**
     * A callback which takes
     *
     * @param string $field
     * @param callable $callback
     */
    public function addFieldCallback($field, $callback) {
        $this->fieldCallbacks[$field] = $callback;
    }

    public function setRequiredFieldGroups($requiredFieldGroups) {
        $this->requiredFieldGroups = $requiredFieldGroups;
    }

    public function getRequiredFieldGroups() {
        return $this->requiredFieldGroups;
    }

    public function setMaxFields($maxFields) {
        $this->maxFields = $maxFields;
    }

    public function getMaxFields() {
        return $this->maxFields;
    }

    public function setCanRemove($bool) {
        $this->canRemove = $bool;
    }

    public function setCanAdd($bool) {
        $this->canAdd = $bool;
    }

    public function canAdd() {
        return $this->canAdd;
    }

    public function canRemove() {
        return $this->canRemove;
    }

    public function getAddLabel()
    {
        return $this->addLabel;
    }

    public function setAddLabel($label)
    {
        $this->addLabel = $label;

        return $this;
    }

    public function getAttributes() {
        $attr = parent::getAttributes();
        return $attr;
    }

    public function saveInto(DataObjectInterface $record)
    {
        if ($this->name) {
            $record->setCastedField($this->name, json_encode($this->dataValue()));
        }
    }

    public function FieldList() {
        if ($this->maxFields) {
            $fieldList = new FieldList();
            $arrayOfFields = array();

            for ($i = 0; $i < $this->maxFields; $i++) {
                foreach ($this->children as $c) {
                    if ($c instanceof CompositeField) {
                        $cc2 = clone $c;
                        $nC = new CompositeField();
                        foreach ($cc2->children as $cc) {
                            $cc3 = clone $cc;
                            $cc3->name = $cc->name . '['. $i .']';
                            $nC->push($cc3);
                        }

                        $arrayOfFields[$i][] = $nC;
                    } else {
                        $cc2 = clone $c;
                        $cc2->name = $c->name . '['. $i .']';

                        if(isset($this->fieldCallbacks[$c->name])) {
                            $cc2 = call_user_func($this->fieldCallbacks[$c->name], $cc2, $i);
                        }

                        $arrayOfFields[$i][] = $cc2;
                    }
                }
            }

            for ($i = 0; $i < sizeof($arrayOfFields); $i++) {
                $comp = new CompositeField();
                $comp->addExtraClass("row many_field");

                if ($i > 0) {
                    if (!$this->value || $i >= $this->value->count()) {
                        $comp->addExtraClass('inactive');
                    }
                }

                $field = null;

                for ($j = 0; $j < sizeof($arrayOfFields[$i]); $j++) {
                    if ($arrayOfFields[$i][$j] instanceof CompositeField) {
                        foreach ($arrayOfFields[$i][$j]->children as $cc) {
                            if ($this->value) {
                                if ($record = $this->value->offsetGet($j)) {
                                    $cc->setValue($record->{$cc->Name()});
                                }
                            }

                            if(isset($this->fieldCallbacks[$cc->Name()])) {
                                $cc = call_user_func($this->fieldCallbacks[$cc->Name()], $cc, $j);
                            }

                            if($cc) {
                                $arrayOfFields[$i][$j]->push($cc);
                            }
                        }

                        $field = $arrayOfFields[$i][$j];
                    } else {
                        $field = $arrayOfFields[$i][$j];

                        if ($this->value) {
                            if ($record = $this->value->offsetGet($i)) {
                                $name = str_replace(array('[]'), '', $field->Name);

                                if ($record->{$name}) {
                                    $field->setValue($record->{$name});

                                    if(isset($this->fieldCallbacks[$name])) {
                                        call_user_func($this->fieldCallbacks[$name], false, $field, $record);
                                    }
                                }
                            }
                        }
                    }
                    $comp->push($field);
                }
                $fieldList->push($comp);
            }

            return $fieldList;
        } else if(!$this->value) {
            return $this->children;
        }
    }
}
