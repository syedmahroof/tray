<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateDocument;
use App\Mcp\Tools\ListRecords;
use App\Support\McpListings;
use Laravel\Mcp\Server;

class TrayServer extends Server
{
    protected string $name = 'Tray CRM';

    protected string $version = '1.0.0';

    protected string $instructions = 'Search listings to resolve IDs before creating documents. All access uses the signed-in user permissions and branch/brand scope. Creation tools create draft documents by default; they do not send email or place orders with external suppliers. Check returned errors before retrying. Never retry a successful creation as it creates another document.';

    protected function boot(): void
    {
        foreach (array_keys(McpListings::definitions()) as $module) {
            $this->tools[] = new ListRecords($module);
        }
        foreach (['quotation', 'sales_order', 'purchase_order'] as $document) {
            $this->tools[] = new CreateDocument($document);
        }
    }
}
