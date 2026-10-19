<?php

// Catalog rules (Categories, Brands, Attributes, Products and Variants): validation details and refusals.
return [
    'slug_format' => 'Use lowercase letters, digits and single dashes only, for example "summer-shirts".',
    'code_format' => 'Use capital letters, digits and single dashes only, for example "RED" or "TSHIRT-RED-M".',

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

    'attributes' => [
        'value' => 'value',
        'value_name' => 'value name',
        'value_code' => 'value code',
        'value_repeated' => 'Each value of an Attribute needs its own name and code.',
        'value_missing' => 'This value does not belong to this Attribute.',
        'value_in_use' => ':name is used by Variants, so it cannot be removed.',
        'in_use' => ':name is used by Variants, so it cannot be deleted.',
    ],

    'products' => [
        'category' => 'Category',
        'brand' => 'Brand',
        'base_price' => 'base price',
        'tag' => 'tag',
        'in_use' => ':name appears on Orders, so it cannot be deleted. Archive it instead.',
    ],

    'images' => [
        'image' => 'image',
        'too_many' => 'A Product can have at most :max images.',
        'order_mismatch' => "List each of the Product's images exactly once.",
    ],

    'variants' => [
        'value' => 'Attribute value',
        'variant' => 'Variant',
        'price' => 'price',
        'average_cost' => 'Average cost',
        'low_stock_threshold' => 'Low-stock threshold',
        'sku_taken' => 'Another Variant already uses the SKU :sku.',
        'sku_repeated' => 'Each Variant needs its own SKU.',
        'in_use' => ':sku appears on Orders, so it cannot be deleted.',
        'other_attributes' => "This Product's Variants differ by :attributes. Choose values of exactly these Attributes, or delete its Variants first.",
        'simple_product' => 'This Product has one Variant without Attributes. Delete it first to generate Variants from Attributes.',
        'try_again' => 'Another change to these Variants was saved at the same moment. Try again.',
    ],
];
