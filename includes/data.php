<?php

$services = [
    ['Classic haircut', 'scissors', 'A timeless cut tailored to you. Includes a wash and a styled finish.', 30, 30, 'haircut', 1],
    ['Skin fade', 'spark', 'A seamless fade with crisp edges and a clean, modern finish.', 35, 45, 'haircut', 0],
    ['Beard trim', 'user', 'Shape, line up, and tame your beard. Finished with nourishing oil.', 20, 20, 'beard', 0],
    ['Cut & beard', 'check', 'The full refresh. A tailored haircut paired with a precise beard trim.', 45, 60, 'package', 1],
    ['Hot towel shave', 'drop', 'A traditional straight-razor shave with a warm towel and aftercare.', 25, 30, 'beard', 0],
    ['Kids\' haircut', 'smile', 'A fresh look for our younger neighbors. For children 12 and under.', 25, 30, 'haircut', 0],
];

$barbers = [
    ['Marco Rossi', 'Classic cuts & precision fades', 'marco'],
    ['Alex Johnson', 'Fades & beard styling', 'alex'],
    ['Sam Lee', 'Modern & textured cuts', 'sam']
];

function ic($n, $c = '') {
    static $p = [
        'scissors' => '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4 8.1 15.9M14.5 14.5 20 20M8.1 8.1 12 12"/>',
        'spark'    => '<path d="M12 3l2 6 6 2-6 2-2 6-2-6-6-2 6-2z"/>',
        'user'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'check'    => '<path d="M20 6 9 17l-5-5"/>',
        'drop'     => '<path d="M12 2s7 7 7 12a7 7 0 0 1-14 0c0-5 7-12 7-12z"/>',
        'smile'    => '<circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01"/>',
        'calendar' => '<path d="M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/>',
        'grid'     => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/>',
        'pin'      => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
        'arrow'    => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'bell'     => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M10 21h4"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'menu'     => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'eye'      => '<path d="M2 12s4-8 10-8 10 8 10 8-4 8-10 8S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'clock'    => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'chat'     => '<path d="M21 12a8 8 0 0 1-11.6 7.1L3 21l1.9-6.4A8 8 0 1 1 21 12z"/>',
        'heart'    => '<path d="M20.8 5.6a5.5 5.5 0 0 0-7.8 0L12 6.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 22l8.8-8.6a5.5 5.5 0 0 0 0-7.8z"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>'
    ];
    
    $svgPath = $p[$n] ?? '';
    return '<svg class="ico ' . $c . '" viewBox="0 0 24 24">' . $svgPath . '</svg>';
}

function logo() {
    return '<a href="index.php" class="logo">' . 
               '<i>' . ic('scissors') . '</i>' . 
               '<span>oak &amp; blade<small>NEIGHBORHOOD BARBERS</small></span>' . 
           '</a>';
}

function av($k, $c = '') {
    return '<span class="av ' . $c . '" style="background-image:url(assets/img/' . $k . '.jpg)"></span>';
}