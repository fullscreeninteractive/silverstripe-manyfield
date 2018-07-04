<?php

namespace FullscreenInteractive\ManyField;

use SilverStripe\Forms\FormTemplateHelper;
use SilverStripe\Core\Convert;

class ManyFieldIdHelper extends FormTemplateHelper
{
    protected $names = [];

     /**
     * Generate the field ID value
     *
     * @param FormField $field
     * @return string
     */
    public function generateFieldID($field)
    {
        if ($form = $field->getForm()) {
            $name = sprintf(
                "%s_%s",
                $this->generateFormID($form),
                Convert::raw2htmlid($field->getName())
            );
        } else {
            $name = Convert::raw2htmlid($field->getName());
        }


        if (isset($this->names[$name])) {
            $this->names[$name]++;

            $name .= $this->names[$name];
        } else {
            $this->names[$name] = 1;
        }

        return $name;
    }
}
