<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building,
    Check,
    ChevronDown,
    Copy,
    Download,
    History,
    Mail,
    MessageCircle,
    Pencil,
    Printer,
    Share2,
    User,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/utils';
import { show as showBuilder } from '@/routes/builders';
import { show as showContact } from '@/routes/contacts';
import { show as showEnquiry } from '@/routes/enquiries';
import { show as showProject } from '@/routes/projects';
import {
    edit,
    email as emailRoute,
    index,
    pdf,
    print,
    revise as reviseRoute,
    show as showQuotation,
    status as statusRoute,
} from '@/routes/quotations';
import type { QuotationDetail, QuotationVersion } from '@/types';

const props = defineProps<{
    quotation: QuotationDetail;
    shareUrl: string;
    statuses: string[];
    versions: QuotationVersion[];
}>();

defineOptions({
    layout: (props: { quotation: QuotationDetail }) => ({
        breadcrumbs: [
            { title: 'Quotations', href: index() },
            { title: props.quotation.number, href: index() },
        ],
    }),
});

const permissions = computed(() => usePage().props.auth.permissions);

const statusVariant = (status: string) => {
    if (status === 'accepted') {
        return 'default' as const;
    }

    if (status === 'rejected' || status === 'expired') {
        return 'destructive' as const;
    }

    if (status === 'sent') {
        return 'outline' as const;
    }

    return 'secondary' as const;
};

const money = (value: string | number) =>
    `₹${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const lineTotal = (item: { quantity: string; unit_price: string }) =>
    Number(item.quantity) * Number(item.unit_price);

const shareMessage = computed(
    () =>
        `Quotation ${props.quotation.number} — Total ${money(props.quotation.total)}`,
);

const canNativeShare = typeof navigator !== 'undefined' && !!navigator.share;

const shareNatively = async () => {
    try {
        await navigator.share({
            title: props.quotation.number,
            text: shareMessage.value,
            url: props.shareUrl,
        });
    } catch {
        // The user dismissed the native share sheet — nothing to do.
    }
};

const shareViaWhatsApp = () => {
    const phone = props.quotation.contact?.phone?.replace(/\D/g, '') ?? '';
    const text = encodeURIComponent(`${shareMessage.value}\n${props.shareUrl}`);
    const base = phone ? `https://wa.me/${phone}` : 'https://wa.me/';

    window.open(`${base}?text=${text}`, '_blank', 'noopener');
};

const copyShareLink = async () => {
    try {
        await navigator.clipboard.writeText(props.shareUrl);
        toast.success('Share link copied to clipboard.');
    } catch {
        toast.error('Could not copy the link.');
    }
};

const canUpdate = computed(() =>
    permissions.value.includes('quotations.update'),
);

const changeStatus = (value: string) => {
    if (value === props.quotation.status) {
        return;
    }

    router.patch(
        statusRoute.url(props.quotation.id),
        { status: value },
        { preserveScroll: true },
    );
};

const emailDialogOpen = ref(false);
const emailing = ref(false);

const sendEmail = () => {
    emailing.value = true;

    router.post(
        emailRoute.url(props.quotation.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                emailing.value = false;
                emailDialogOpen.value = false;
            },
        },
    );
};

const canCreate = computed(() =>
    permissions.value.includes('quotations.create'),
);
const canSend = computed(() => permissions.value.includes('quotations.send'));

const reviseDialogOpen = ref(false);
const revising = ref(false);

const createRevision = () => {
    revising.value = true;

    router.post(
        reviseRoute.url(props.quotation.id),
        {},
        {
            onFinish: () => {
                revising.value = false;
                reviseDialogOpen.value = false;
            },
        },
    );
};
</script>

<template>
    <Head :title="quotation.number" />

    <div class="flex flex-col space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <Link
                    :href="index()"
                    class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:underline"
                >
                    <ArrowLeft class="h-4 w-4 text-slate-500" /> Back to List
                </Link>
                <div class="flex items-center gap-2">
                    <Heading
                        variant="small"
                        :title="quotation.number"
                        :description="`Created ${formatDate(quotation.created_at)}`"
                    />
                    <Badge
                        v-if="versions.length > 1"
                        variant="secondary"
                        class="tabular-nums"
                    >
                        v{{ quotation.version }}
                    </Badge>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <DropdownMenu v-if="canUpdate">
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline">
                            <Badge
                                :variant="statusVariant(quotation.status)"
                                class="capitalize"
                            >
                                {{ quotation.status }}
                            </Badge>
                            <ChevronDown
                                class="h-4 w-4 text-muted-foreground"
                            />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-44">
                        <DropdownMenuLabel>Change status</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            v-for="option in statuses"
                            :key="option"
                            class="capitalize"
                            @select="changeStatus(option)"
                        >
                            <Check
                                class="h-4 w-4"
                                :class="
                                    option === quotation.status
                                        ? 'opacity-100'
                                        : 'opacity-0'
                                "
                            />
                            {{ option }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <Button variant="outline" as-child>
                    <a
                        :href="print.url(quotation.id)"
                        target="_blank"
                        rel="noopener"
                    >
                        <Printer class="h-4 w-4" /> Print
                    </a>
                </Button>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline">
                            <Share2 class="h-4 w-4" /> Share
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-52">
                        <DropdownMenuItem
                            v-if="canNativeShare"
                            @select="shareNatively"
                        >
                            <Share2 class="h-4 w-4" /> Share via device
                        </DropdownMenuItem>
                        <DropdownMenuItem @select="shareViaWhatsApp">
                            <MessageCircle class="h-4 w-4" /> WhatsApp
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="canSend && quotation.contact?.email"
                            @select="emailDialogOpen = true"
                        >
                            <Mail class="h-4 w-4" /> Email to customer
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @select="copyShareLink">
                            <Copy class="h-4 w-4" /> Copy link
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <a :href="pdf.url(quotation.id)">
                                <Download class="h-4 w-4" /> Download PDF
                            </a>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <Button
                    v-if="canCreate"
                    variant="outline"
                    @click="reviseDialogOpen = true"
                >
                    <History class="h-4 w-4" /> Revise
                </Button>

                <Button v-if="canUpdate" as-child>
                    <Link :href="edit(quotation.id)"><Pencil /> Edit</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            <Card>
                <CardHeader
                    class="flex flex-row items-center gap-2 border-b pb-3"
                >
                    <User class="h-5 w-5 text-[#2563eb]" />
                    <CardTitle class="text-base font-semibold"
                        >Quotation For</CardTitle
                    >
                </CardHeader>
                <CardContent class="space-y-1 pt-6 text-sm">
                    <Link
                        v-if="quotation.contact"
                        :href="showContact(quotation.contact.id)"
                        class="font-medium hover:underline"
                    >
                        {{ quotation.contact.name }}
                    </Link>
                    <p v-else class="text-muted-foreground">—</p>
                    <p
                        v-if="quotation.contact?.phone"
                        class="text-muted-foreground"
                    >
                        {{ quotation.contact.phone }}
                    </p>
                    <p
                        v-if="quotation.contact?.email"
                        class="text-muted-foreground"
                    >
                        {{ quotation.contact.email }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center gap-2 border-b pb-3"
                >
                    <Building class="h-5 w-5 text-[#4f46e5]" />
                    <CardTitle class="text-base font-semibold"
                        >Details</CardTitle
                    >
                </CardHeader>
                <CardContent class="space-y-2 pt-6 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Status</span>
                        <Badge
                            :variant="statusVariant(quotation.status)"
                            class="capitalize"
                        >
                            {{ quotation.status }}
                        </Badge>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Date</span>
                        <span>{{ formatDate(quotation.quotation_date) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Valid until</span>
                        <span>{{ formatDate(quotation.valid_until) }}</span>
                    </div>
                    <div v-if="quotation.project" class="flex justify-between">
                        <span class="text-muted-foreground">Project</span>
                        <Link
                            :href="showProject(quotation.project.id)"
                            class="font-medium hover:underline"
                        >
                            {{ quotation.project.name }}
                        </Link>
                    </div>
                    <div v-if="quotation.enquiry" class="flex justify-between">
                        <span class="text-muted-foreground">Enquiry</span>
                        <Link
                            :href="showEnquiry(quotation.enquiry.id)"
                            class="font-medium hover:underline"
                        >
                            #{{ quotation.enquiry.id
                            }}{{
                                quotation.enquiry.contact
                                    ? ` — ${quotation.enquiry.contact.name}`
                                    : ''
                            }}
                        </Link>
                    </div>
                    <div v-if="quotation.builder" class="flex justify-between">
                        <span class="text-muted-foreground">Builder</span>
                        <Link
                            :href="showBuilder(quotation.builder.id)"
                            class="font-medium hover:underline"
                        >
                            {{ quotation.builder.name }}
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    class="flex flex-row items-center gap-2 border-b pb-3"
                >
                    <CardTitle class="text-base font-semibold">Total</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 pt-6 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Subtotal</span>
                        <span class="tabular-nums">{{
                            money(quotation.subtotal)
                        }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Discount</span>
                        <span class="tabular-nums"
                            >- {{ money(quotation.discount) }}</span
                        >
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground"
                            >Tax ({{ Number(quotation.tax_percent) }}%)</span
                        >
                        <span class="tabular-nums">{{
                            money(quotation.tax_amount)
                        }}</span>
                    </div>
                    <div
                        class="flex justify-between border-t pt-2 text-base font-semibold"
                    >
                        <span>Total</span>
                        <span class="tabular-nums">{{
                            money(quotation.total)
                        }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Items</CardTitle>
            </CardHeader>
            <CardContent>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12">#</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead class="text-right">Qty</TableHead>
                            <TableHead class="text-right">Unit Price</TableHead>
                            <TableHead class="text-right">Amount</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(item, i) in quotation.items"
                            :key="item.id"
                        >
                            <TableCell class="text-muted-foreground">{{
                                i + 1
                            }}</TableCell>
                            <TableCell>
                                <div class="font-medium">
                                    {{ item.description }}
                                </div>
                                <div
                                    v-if="item.product"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ item.product.name }}
                                </div>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                Number(item.quantity)
                            }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                money(item.unit_price)
                            }}</TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                money(lineTotal(item))
                            }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Card v-if="versions.length > 1">
            <CardHeader class="flex flex-row items-center gap-2 border-b pb-3">
                <History class="h-5 w-5 text-[#4f46e5]" />
                <CardTitle class="text-base font-semibold"
                    >Revision History</CardTitle
                >
            </CardHeader>
            <CardContent class="pt-4">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-16">Version</TableHead>
                            <TableHead>Number</TableHead>
                            <TableHead>Created</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="text-right">Total</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="version in versions"
                            :key="version.id"
                            :class="
                                version.id === quotation.id ? 'bg-muted/50' : ''
                            "
                        >
                            <TableCell class="tabular-nums"
                                >v{{ version.version }}</TableCell
                            >
                            <TableCell>
                                <Link
                                    v-if="version.id !== quotation.id"
                                    :href="showQuotation(version.id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ version.number }}
                                </Link>
                                <span v-else class="font-medium"
                                    >{{ version.number }} (current)</span
                                >
                            </TableCell>
                            <TableCell class="text-muted-foreground">{{
                                formatDate(version.created_at)
                            }}</TableCell>
                            <TableCell>
                                <Badge
                                    :variant="statusVariant(version.status)"
                                    class="capitalize"
                                >
                                    {{ version.status }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">{{
                                money(version.total)
                            }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <div
            v-if="quotation.notes || quotation.terms"
            class="grid gap-6 md:grid-cols-2"
        >
            <Card v-if="quotation.notes">
                <CardHeader><CardTitle>Notes</CardTitle></CardHeader>
                <CardContent
                    class="text-sm whitespace-pre-wrap text-muted-foreground"
                    >{{ quotation.notes }}</CardContent
                >
            </Card>
            <Card v-if="quotation.terms">
                <CardHeader
                    ><CardTitle>Terms &amp; Conditions</CardTitle></CardHeader
                >
                <CardContent
                    class="text-sm whitespace-pre-wrap text-muted-foreground"
                    >{{ quotation.terms }}</CardContent
                >
            </Card>
        </div>
    </div>

    <Dialog v-model:open="emailDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Email quotation</DialogTitle>
                <DialogDescription>
                    Send quotation {{ quotation.number }} with the PDF attached
                    to
                    <span class="font-medium text-foreground">{{
                        quotation.contact?.email
                    }}</span
                    >. Draft quotations will be marked as sent.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="emailing"
                    @click="emailDialogOpen = false"
                >
                    Cancel
                </Button>
                <Button :disabled="emailing" @click="sendEmail">
                    <Mail class="h-4 w-4" />
                    {{ emailing ? 'Sending…' : 'Send email' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="reviseDialogOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Revise quotation</DialogTitle>
                <DialogDescription>
                    This creates a new editable draft copy of
                    {{ quotation.number }} with all its items. The current
                    version will be marked as
                    <span class="font-medium text-foreground">Revised</span> and
                    kept for reference.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button
                    variant="outline"
                    :disabled="revising"
                    @click="reviseDialogOpen = false"
                >
                    Cancel
                </Button>
                <Button :disabled="revising" @click="createRevision">
                    <History class="h-4 w-4" />
                    {{ revising ? 'Creating…' : 'Create revision' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
