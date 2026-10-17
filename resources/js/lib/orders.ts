import * as purchaseOrders from '@/routes/purchase-orders';
import * as salesOrders from '@/routes/sales-orders';
import type { OrderType } from '@/types';

/** Route helpers for the sales or purchase order resource. */
export const orderRoutes = (type: OrderType) =>
    type === 'sale' ? salesOrders : purchaseOrders;

/** Permission prefix for the order resource, e.g. `sales-orders.update`. */
export const orderResource = (type: OrderType) =>
    type === 'sale' ? 'sales-orders' : 'purchase-orders';

/** Singular display title, e.g. "Sales Order". */
export const orderTitle = (type: OrderType) =>
    type === 'sale' ? 'Sales Order' : 'Purchase Order';

/** Turn a snake_case status into words, e.g. "partially fulfilled". */
export const orderStatusLabel = (status: string) => status.replaceAll('_', ' ');

/** Badge variant for an order status. */
export const orderStatusVariant = (status: string) => {
    switch (status) {
        case 'fulfilled':
            return 'default' as const;
        case 'cancelled':
            return 'destructive' as const;
        case 'confirmed':
            return 'outline' as const;
        default:
            return 'secondary' as const;
    }
};

export const formatMoney = (value: string | number) =>
    `₹${Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
