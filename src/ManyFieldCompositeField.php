<?php

namespace FullscreenInteractive\ManyField;

use SilverStripe\Forms\CompositeField;

class ManyFieldCompositeField extends CompositeField
{
    /**
     * Returns the HTML ID of the field.
     *
     * The ID is generated as FormName_FieldName. All Field functions should ensure that this ID is
     * included in the field.
     *
     * @return string
     */
    public function ID()
    {
        return null;
    }

    /**
     * Returns the HTML ID for the form field holder element.
     *
     * @return string
     */
    public function HolderID()
    {
        return null;
    }
}