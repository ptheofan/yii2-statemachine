<?php
/**
 * User: Paris Theofanidis
 * Date: 02/07/16
 * Time: 11:46
 */

return [
    'states' => [
        'default' => [
            'fillcolor' => '#4D7E8F',
            'style' => 'filled',
            'shape' => 'box',
            'fontcolor' => 'white',
            'tableTypeIndicatorColor' => '#dddddd',
            'tableTypeValueColor' => '#F2FF00',
        ],
        'initial' => [
            'fillcolor' => '#21AE1F',
        ],
        'final' => [
            'fillcolor' => '#A12C2C',
        ],
        'intermediate' => [
            'shape' => 'box',
            'fontcolor' => '#717171',
            'style' => 'filled, rounded, dashed',
            'fillcolor' => '#F2F2F2',
            'tableTypeIndicatorColor' => '#777777',
            'tableTypeValueColor' => '#707200',
        ],
    ],
    'events' => [
        'default' => [
            'fontcolor' => 'black',
            'fillcolor' => 'black',
            'color' => 'black',
            'style' => 'filled',
        ],
        'refresh' => [
            'fontcolor' => '#777777',
            'fillcolor' => '#aaaaaa',
            'color' => '#aaaaaa',
            'style' => 'dashed',
        ],
        'timeout' => [
            'style' => 'dashed',
        ],
        'exclusiveRoles' => [
            'system' => [
                'fontcolor' => '#BE6900',
                'fillcolor' => '#FF8D00',
                'color' => '#FF8D00',
                'style' => 'dashed',
            ],
            'admin' => [
                'fontcolor' => '#5087a7',
                'fillcolor' => '#5087a7',
                'color' => '#5087a7',
            ],
        ],
    ],
];