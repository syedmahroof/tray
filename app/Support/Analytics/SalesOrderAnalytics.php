<?php

namespace App\Support\Analytics;

/**
 * Sales order value, fulfilment and the customers behind it.
 */
class SalesOrderAnalytics extends OrderAnalytics
{
    public function title(): string
    {
        return 'Sales Orders';
    }

    public function description(): string
    {
        return 'Ordered and fulfilled value by customer and salesperson. Cancelled orders are left out of values.';
    }

    protected function type(): string
    {
        return 'sale';
    }
}
