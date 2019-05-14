# SilverStripe Many Field

[![Version](http://img.shields.io/packagist/v/fullscreeninteractive/silverstripe-manyfield.svg)](https://packagist.org/packages/fullscreeninteractive/silverstripe-manyfield)
[![License](http://img.shields.io/packagist/l/fullscreeninteractive/silverstripe-manyfield.svg)](license.md)

A reusable approach to a form field which allows you to create and delete rows 
in Forms.

This is designed to work on the front-end with limited javascript (i.e it does 
not require `GridField` or entwine).

Each row can relate to a DataObject subclass or simply to be used to capture the
data as an array.

## Installation

```
composer require fullscreeninteractive/silverstripe-manyfield
```

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

Data will either be saved as `setSwabList($data)`, `SwabList` database field or 
in the `SwabList` relation. If you are saving into a relation such as `HasMany`
or `ManyMany` list then make sure you include a hidden field in your field list.

```
    $many = ManyField::create('SwabList', [
        HiddenField::create('ID', ''),
        TextField::create('Swab'),
        TextField::create('TestSite'),
        TextField::create('Description'),
        TextField::create('Material')
    ]);
```

## Sorting

Include a Hidden field `Sort` and make sure sorting is enabled.

```
    $many = ManyField::create('SwabList', [
        HiddenField::create('ID', ''),
        HiddenField::create('Sort', ''),
        TextField::create('TestSite')
    ])->setCanSort(true);
```

## Required Fields

    $many = ManyField::create('SwabList', [
        TextField::create('TestSite')->setRequired(true)
    ])->setCanSort(true);

## Javascript Events

If you have UI handlers that need to run when fields are added or removed 
(such as Date Pickers) create a handler on your `<body>` element and listen for
either:

* `manyFieldAdded`
* `manyFieldRemoved`

## Licence

BSD 3-Clause License