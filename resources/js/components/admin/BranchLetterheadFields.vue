<script setup lang="ts">
import ImageInput from '@/components/ImageInput.vue';
import InputError from '@/components/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Branch, NamedOption } from '@/types';

/**
 * The letterhead a branch prints on its quotations, shared by the create and
 * edit forms. Anything left blank falls back to the company's own details.
 */
defineProps<{
    branch?: Branch;
    brands: NamedOption[];
    errors: Record<string, string | undefined>;
}>();
</script>

<template>
    <div class="space-y-4 rounded-lg border p-4">
        <div>
            <Label class="text-base">Quotation letterhead</Label>
            <p class="text-sm text-muted-foreground">
                Printed on this branch's quotations. Leave blank to use the
                company's own details.
            </p>
        </div>

        <ImageInput
            id="logo"
            name="logo"
            label="Logo"
            :current-url="branch?.logo_url"
            :error="errors.logo"
        />

        <div class="grid gap-2">
            <Label for="company_name">Company name</Label>
            <Input
                id="company_name"
                name="company_name"
                :default-value="branch?.company_name ?? undefined"
            />
            <InputError :message="errors.company_name" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="phone">Phone</Label>
                <Input
                    id="phone"
                    name="phone"
                    :default-value="branch?.phone ?? undefined"
                />
                <InputError :message="errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="mobile">Mobile</Label>
                <Input
                    id="mobile"
                    name="mobile"
                    :default-value="branch?.mobile ?? undefined"
                />
                <InputError :message="errors.mobile" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    :default-value="branch?.email ?? undefined"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="website">Website</Label>
                <Input
                    id="website"
                    name="website"
                    :default-value="branch?.website ?? undefined"
                />
                <InputError :message="errors.website" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="gstin">GSTIN</Label>
            <Input
                id="gstin"
                name="gstin"
                :default-value="branch?.gstin ?? undefined"
            />
            <InputError :message="errors.gstin" />
        </div>

        <div class="grid gap-2">
            <Label>Brands</Label>
            <p class="text-sm text-muted-foreground">
                Shown under the footer of this branch's quotations.
            </p>
            <div class="flex flex-wrap gap-4 rounded-lg border p-4">
                <Label
                    v-for="brand in brands"
                    :key="brand.id"
                    class="flex items-center space-x-2 font-normal"
                >
                    <Checkbox
                        name="brands[]"
                        :value="String(brand.id)"
                        :default-value="
                            branch?.brand_ids?.includes(brand.id) ?? false
                        "
                        :data-test="`brand-${brand.id}`"
                    />
                    <span>{{ brand.name }}</span>
                </Label>
                <p
                    v-if="brands.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No brands yet.
                </p>
            </div>
            <InputError :message="errors.brands" />
        </div>

        <div class="grid gap-2">
            <Label for="quotation_terms">Default terms and conditions</Label>
            <Textarea
                id="quotation_terms"
                name="quotation_terms"
                rows="6"
                placeholder="One term per line"
                :default-value="branch?.quotation_terms ?? undefined"
            />
            <p class="text-sm text-muted-foreground">
                Used when a quotation has no terms of its own.
            </p>
            <InputError :message="errors.quotation_terms" />
        </div>
    </div>
</template>
