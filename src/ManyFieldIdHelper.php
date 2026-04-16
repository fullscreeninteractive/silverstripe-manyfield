<?php

namespace FullscreenInteractive\ManyField;

use SilverStripe\Forms\FormTemplateHelper;
use SilverStripe\Core\Convert;
use SilverStripe\Forms\FormField;

class ManyFieldIdHelper extends FormTemplateHelper
{
    /** @var array<string, int> */
    protected $names = [];

     /**
     * Generate the field ID value
     *
     * @return string
     */
    /**
     * @param FormField $field
     */
    /**
     * @param FormField $field
     */
    public function generateFieldID($field): string
    {
        $name = sprintf(
            '%s_%s',
            $this->generateFormID($field->getForm()),
            Convert::raw2htmlid($field->getName())
        );

        if (isset($this->names[$name])) {
            $this->names[$name]++;

            $name .= $this->names[$name];
        } else {
            $this->names[$name] = 1;
        }

        return $name;
    }
}
