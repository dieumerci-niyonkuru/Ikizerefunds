<?php
// Photography used on the public pages.
//
// These are hotlinked from the Unsplash CDN under the Unsplash License, which
// permits free commercial use and hotlinking. Every entry is one place to edit:
// swap `id` for a different Unsplash photo id, or replace `id` with a local
// path under assets/images/ and adjust siteImage() below.
//
// Chosen to show Black African students and savings groups, matching the club's
// actual membership at Tumba College, Rulindo.

function siteImages(): array
{
    return [
        'hero' => [
            'id'  => 'photo-1655720348590-c739c860beed',
            'alt' => 'Students working together on laptops outdoors',
        ],
        'ledger' => [
            'id'  => 'photo-1761370981247-1dfd749ec96b',
            'alt' => 'Three members reviewing savings records together at a table',
        ],
        'cta' => [
            'id'  => 'photo-1648301033733-44554c74ec50',
            'alt' => 'A group of students gathered outside a college building',
        ],
        // One photo per service card, in the same order as the services grid.
        'services' => [
            ['id' => 'photo-1655720360377-b97f6715e1ae', 'alt' => 'A member writing up a savings record'],
            ['id' => 'photo-1687422809654-579d81c29d32', 'alt' => 'A trader at her market stall checking her phone'],
            ['id' => 'photo-1573165706511-3ffde6ef1fe3', 'alt' => 'Members meeting around a wooden table'],
            ['id' => 'photo-1620829813573-7c9e1877706f', 'alt' => 'A student reviewing figures on a laptop'],
        ],

        'community' => [
            ['id' => 'photo-1594750852563-5ed8e0421d40', 'alt' => 'A graduate in cap and gown'],
            ['id' => 'photo-1778824717987-7a135889e928', 'alt' => 'Members celebrating around a table of paperwork'],
            ['id' => 'photo-1643785559446-26031ad01aa6', 'alt' => 'A member checking her account on her phone'],
            ['id' => 'photo-1529832588601-c01e066263a8', 'alt' => 'Members seated together at a meeting table'],
        ],
    ];
}

// Builds a sized Unsplash CDN URL. `auto=format` serves WebP/AVIF to browsers
// that accept it, so these stay light without a build step.
function siteImage(string $id, int $width, int $quality = 72): string
{
    return 'https://images.unsplash.com/' . $id
        . '?auto=format&fit=crop&w=' . $width . '&q=' . $quality;
}

// Matching srcset so phones never download a desktop-sized file.
function siteImageSrcset(string $id, array $widths = [640, 1024, 1600]): string
{
    $parts = [];
    foreach ($widths as $w) {
        $parts[] = siteImage($id, $w) . ' ' . $w . 'w';
    }

    return implode(', ', $parts);
}
