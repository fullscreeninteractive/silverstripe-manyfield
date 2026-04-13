<?php

namespace FullscreenInteractive\ManyField\Tests;

use FullscreenInteractive\ManyField\ManyFieldIdHelper;
use PHPUnit\Framework\TestCase;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormField;

class ManyFieldIdHelperTest extends TestCase
{
    public function testGenerateFieldIdAppendsSuffixForDuplicateName(): void
    {
        $reflection = new \ReflectionClass(ManyFieldIdHelper::class);
        /** @var ManyFieldIdHelper $helper */
        $helper = $reflection->newInstanceWithoutConstructor();
        $form = $this->createMock(Form::class);
        $form->method('FormName')->willReturn('MockForm');
        $field = $this->createMock(FormField::class);
        $field->method('getForm')->willReturn($form);
        $field->method('getName')->willReturn('DuplicateName');

        $first = $helper->generateFieldID($field);
        $second = $helper->generateFieldID($field);

        $this->assertStringEndsWith('_DuplicateName', $first);
        $this->assertSame($first . '2', $second);
    }
}
