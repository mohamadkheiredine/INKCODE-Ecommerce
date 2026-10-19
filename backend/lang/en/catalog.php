<?php

// Catalog rules (Categories and Brands): validation details and refusals.
return [
    'slug_format' => 'Use lowercase letters, digits and single dashes only, for example "summer-shirts".',

    'categories' => [
        'parent' => 'parent Category',
        'parent_missing' => 'The selected parent Category does not exist.',
        'parent_is_child' => ':name is already a child Category. Choose a top-level Category as the parent.',
        'own_parent' => 'A Category cannot be its own parent.',
        'has_children_cannot_move' => 'This Category has children of its own, so it must stay top-level.',
        'has_children' => ':name still has child Categories. Move or delete them first.',
        'in_use' => ':name is still used by Products. Move those Products to another Category first.',
    ],

    'brands' => [
        'in_use' => ':name is still used by Products. Move those Products to another Brand first.',
        'logo' => 'logo',
    ],
];
