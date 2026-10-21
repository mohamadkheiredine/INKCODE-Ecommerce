<?php

namespace App\Enums;

/**
 * The six reports; the value is the report's URL segment and file name.
 */
enum ReportType: string
{
    case Sales = 'sales';
    case Products = 'products';
    case Categories = 'categories';
    case Customers = 'customers';
    case Returns = 'returns';
    case Inventory = 'inventory';
}
