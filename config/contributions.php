<?php

return [
    'leaderboard_limit' => 20,
    'reputation' => [
        'approved' => [
            'add' => 25,
            'update' => 15,
            'correct' => 12,
            'report_issue' => 8,
        ],
        'rejected' => [
            'add' => -3,
            'update' => -2,
            'correct' => -2,
            'report_issue' => 0,
        ],
    ],
    'levels' => [
        ['name' => 'Scout', 'points' => 0],
        ['name' => 'Verifier', 'points' => 50],
        ['name' => 'Curator', 'points' => 150],
        ['name' => 'Guardian', 'points' => 300],
        ['name' => 'Steward', 'points' => 600],
    ],
];
