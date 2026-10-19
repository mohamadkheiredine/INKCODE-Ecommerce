<?php

namespace App\Support;

/**
 * What a slug a User types may look like: lowercase Latin letters and digits in groups
 * joined by single dashes ("summer-shirts").
 */
final class Slug
{
    public const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
}
