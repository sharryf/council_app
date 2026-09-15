<?php

/**
 * Overrides just the table-chrome strings actually visible on the
 * Bureau panel's list pages (filters bar, search box, sort controls,
 * bulk-selection bar) — Laravel merges this over Filament's own English
 * defaults (vendor/filament/tables/resources/lang/en/table.php), so any
 * key left out here still falls back to English rather than breaking.
 * Same narrowly-scoped-override approach as
 * lang/vendor/filament-panels/dv/layout.php.
 */
return [

    'fields' => [

        'search' => [
            'label' => 'ހޯއްދަވާ',
            'placeholder' => 'ހޯއްދަވާ',
            'indicator' => 'ހޯއްދަވާ',
        ],

    ],

    'actions' => [

        'filter' => [
            'label' => 'ފިލްޓަރު',
        ],

        'open_bulk_actions' => [
            'label' => 'ބަލްކް އެކްޝަންސް',
        ],

    ],

    'filters' => [

        'actions' => [

            'apply' => [
                'label' => 'ފިލްޓަރކުރައްވާ',
            ],

            'remove' => [
                'label' => 'ފިލްޓަރ ނަގާލައްވާ',
            ],

            'remove_all' => [
                'label' => 'ހުރިހާ ފިލްޓަރު ނަގާލައްވާ',
                'tooltip' => 'ހުރިހާ ފިލްޓަރު ނަގާލައްވާ',
            ],

            'reset' => [
                'label' => 'ސާފުކުރައްވާ',
            ],

        ],

        'heading' => 'ފިލްޓަރުތައް',

        'indicator' => 'ހިނގަމުންދާ ފިލްޓަރު',

        'multi_select' => [
            'placeholder' => 'ހުރިހާ',
        ],

        'select' => [

            'placeholder' => 'ހުރިހާ',

            'relationship' => [
                'empty_option_label' => 'ނެތް',
            ],

        ],

    ],

    'selection_indicator' => [

        'selected_count' => ':count ރެކޯޑް ހޮވިއްޖެ',

        'actions' => [

            'select_all' => [
                'label' => 'ހުރިހާ :count ހޮއްވަވާ',
            ],

            'deselect_all' => [
                'label' => 'ހޮވުންތައް ކަނޑުވާލައްވާ',
            ],

        ],

    ],

    'sorting' => [

        'fields' => [

            'column' => [
                'label' => 'ތަރުތީބު',
            ],

            'direction' => [

                'label' => 'ތަރުތީބުވާ ގޮތް',

                'options' => [
                    'asc' => 'ކުޑައިން ބޮޑަށް',
                    'desc' => 'ބޮޑުން ކުޑައަށް',
                ],

            ],

        ],

    ],

    'result_count' => '{0} ނަތީޖާއެއް ނުފެނުނު|[1,*] :count ނަތީޖާ',

];
