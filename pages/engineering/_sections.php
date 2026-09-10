<?php
/**
 * Engineering sections — one source of truth for:
 *   - the hub grid on index.php
 *   - each sub-page's title
 *   - slug validation in _section_top.php
 *
 * Key = URL slug / filename (without .php).  Value = display label.
 * To add a section: add a line here and create <slug>.php (copy an existing one).
 */
$ENGINEERING_SECTIONS = [
    'catwalk'                => 'Catwalk',
    'dust_collector'         => 'Dust Collector',
    'fender'                 => 'Fender',
    'fill_pipe'              => 'Fill Pipe',
    'screw_conveyor_box_240' => 'Full Load Screw Conveyor Box 240',
    'hydraulic_systems'      => 'Hydraulic Systems',
    'hydraulic_tank'         => 'Hydraulic Tank',
    'main_auger'             => 'Main Auger',
    'misc_items'             => 'Misc Items',
    'rear_auger'             => 'Rear Auger',
    'slidegate'              => 'Slidegate',
    'trough'                 => 'Trough',
    'weight_deflectors'      => 'Weight Deflectors',
];
