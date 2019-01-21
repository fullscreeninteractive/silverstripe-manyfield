# SilverStripe Many Field

[![Version](http://img.shields.io/packagist/v/ullscreeninteractive/silverstripe-manyfield.svg)](https://packagist.org/packages/fuilscreeninteractive/silverstripe-manyfield)
[![License](http://img.shields.io/packagist/l/fullscreeninteractive/silverstripe-manyfield.svg)](license.md)

A reusable approach to a form field which allows you to create and delete rows.

Each row can relate to a DataObject subclass or simply to be used to capture the
data

## Usage

![Image of Function](https://raw.githubusercontent.com/fullscreeninteractive/silverstripe-manyfield/master/client/img/demo.png)

```php
use FullscreenInteractive\ManyField\ManyField;

$fields = new FieldList(
    $many = ManyField::create('SwabList', [
        TextField::create('Swab'),
        TextField::create('TestSite'),
        TextField::create('Description'),
        TextField::create('Material')
    ])
);
```

Data will either be saved as `setSwabList($data)`, `SwabList` database field or in the `SwabList` relation.
