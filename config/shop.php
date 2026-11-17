<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Low Stock Threshold
    |--------------------------------------------------------------------------
    |
    | A product is considered low stock when its stock quantity is greater
    | than zero but less than or equal to this value. The catalog seed data
    | is calibrated to a threshold of two so the computed statuses match
    | the original storefront data.
    |
    */

    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 2),

];
