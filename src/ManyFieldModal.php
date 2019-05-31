<?php

namespace FullscreenInteractive\ManyField;

use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\FieldList;
use SilverStripe\View\ArrayData;
use SilverStripe\Security\SecurityToken;

/**
 * Rather than allowing the user to edit inline using a table-like layout - this
 * class displays the edit form in a Bootstrap modal HTML with a custom save
 * button and preview.
 */

 class ManyFieldModal extends ManyField
 {
    protected $summaryTemplate = '';

    /**
     * @var string
     */
    protected $template = 'ManyFieldModal';

    public function setSummaryTemplate($template)
    {
        $this->summaryTemplate = $template;

        return $this;
    }

    /**
     * Return the list of fields.
     * @return FieldList
     */
    public function FieldList() {
        $output = FieldList::create();
        $index = 0;

        if ($this->value) {
            foreach ($this->value as $record) {
                $output->push(LiteralField::create(
                    'Template'. $record->ID,
                    $record->customise(ArrayData::create([
                        'RemoveLink' => $this->Link(sprintf(
                            'deleteRecord?ClassName=%s&ID=%s&SecurityID=%s',
                            $record->ClassName,
                            $record->ID,
                            SecurityToken::inst()->getValue()
                        ))
                    ]))->renderWith($this->summaryTemplate)
                ));
            }
        }

        return $output;
    }

 }