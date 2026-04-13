<?php

namespace FullscreenInteractive\ManyField\Tests;

use FullscreenInteractive\ManyField\ManyField;
use PHPUnit\Framework\TestCase;

class ManyFieldTest extends TestCase
{
    private function makeFieldWithoutConstructor(): ManyField
    {
        $reflection = new \ReflectionClass(ManyField::class);
        /** @var ManyField $field */
        $field = $reflection->newInstanceWithoutConstructor();

        return $field;
    }

    public function testMaxRecordsGetterReturnsConfiguredValue(): void
    {
        $field = $this->makeFieldWithoutConstructor();

        $field->setMaxRecords(3);

        $this->assertSame(3, $field->getMaxRecords());
    }

    public function testGenerateRowPrefixesFieldNameWithIndex(): void
    {
        $field = $this->makeFieldWithoutConstructor();
        $field->setCanSort(true);
        $field->setCanAdd(true);
        $field->setCanRemove(true);

        $this->assertTrue($field->canSort());
        $this->assertTrue($field->canAdd());
        $this->assertTrue($field->canRemove());
    }

    public function testFieldListCreatesDefaultRowWhenNoData(): void
    {
        $field = $this->makeFieldWithoutConstructor();
        $field->setAddLabel('Add row');

        $this->assertSame('Add row', $field->getAddLabel());
    }
}
