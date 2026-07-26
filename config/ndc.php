<?php

return [
    'admin_key' => env('NDC_ADMIN_KEY', 'ndc-admin-change-me'),

    'categories' => [
        ['id' => 'all', 'label' => 'All', 'query' => null],
        ['id' => 'economy', 'label' => 'Economy & Fiscal', 'query' => 'Economy & Fiscal'],
        ['id' => 'jobs', 'label' => 'Jobs & 24-Hour Economy', 'query' => 'Jobs & 24-Hour Economy'],
        ['id' => 'infra', 'label' => 'Infrastructure', 'query' => 'Infrastructure'],
        ['id' => 'energy', 'label' => 'Energy & Oil/Gas', 'query' => 'Energy & Oil/Gas'],
        ['id' => 'health', 'label' => 'Health', 'query' => 'Health'],
        ['id' => 'education', 'label' => 'Education', 'query' => 'Education'],
        ['id' => 'debt', 'label' => 'Debt & IMF', 'query' => 'Debt & IMF'],
        ['id' => 'governance', 'label' => 'Governance & Reforms', 'query' => 'Governance & Reforms'],
    ],

    'outing_types' => [
        'tv' => 'TV Interview',
        'radio' => 'Radio Call-in',
        'press_conference' => 'Press Conference',
        'town_hall' => 'Town Hall',
        'social_ambush' => 'Social Clip Ambush',
    ],

    'difficulties' => [
        'soft' => 'Soft',
        'standard' => 'Standard',
        'hostile' => 'Hostile',
    ],
];
