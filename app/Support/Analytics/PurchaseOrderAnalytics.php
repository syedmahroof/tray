<?php

namespace App\Support\Analytics;

/**
 * Purchase order spend, fulfilment and the suppliers behind it.
 */
class PurchaseOrderAnalytics extends OrderAnalytics
{
    public function title(): string
    {
        return 'Purchase Orders';
    }

    public function description(): string
    {
        return 'Ordered and received value by supplier. Cancelled orders are left out of values.';
    }

    protected function type(): string
    {
        return 'purchase';
    }
}
