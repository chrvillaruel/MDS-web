<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    seller?: {
        id: number;
        registered_name: string;
        tin: string;
        vat_status: string;
        setup_completed_at: string | null;
    } | null;
}>();

const page = usePage();
const flash = computed(() => (page.props.flash as Record<string, string> | undefined) ?? {});
</script>

<template>
    <Head title="Dashboard" />
    <div class="min-h-screen bg-slate-50">
        <header class="bg-white border-b border-slate-200">
            <div class="max-w-5xl mx-auto px-4 py-3 flex items-center justify-between">
                <h1 class="text-lg font-semibold text-slate-900">MDS</h1>
                <div class="flex items-center gap-3 text-sm">
                    <Link href="/setup" class="text-slate-700 hover:underline">Setup</Link>
                    <Link href="/logout" method="post" as="button" class="text-rose-600 hover:underline">
                        Sign out
                    </Link>
                </div>
            </div>
        </header>

        <main class="max-w-5xl mx-auto px-4 py-8">
            <div v-if="flash.success" class="mb-4 rounded bg-emerald-50 border border-emerald-200 text-emerald-900 px-4 py-2 text-sm">
                {{ flash.success }}
            </div>

            <div v-if="!seller" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-4 text-amber-900">
                <div class="font-medium">Finish your setup before issuing invoices.</div>
                <p class="text-sm mt-1">
                    BIR requires your taxpayer details on every invoice. It only takes a minute.
                </p>
                <Link href="/setup" class="mt-3 inline-block rounded bg-amber-600 text-white px-3 py-1.5 text-sm font-medium">
                    Start setup
                </Link>
            </div>

            <div v-else class="rounded-lg border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">{{ seller.registered_name }}</h2>
                <dl class="mt-3 grid grid-cols-2 gap-y-2 text-sm">
                    <dt class="text-slate-500">TIN</dt>
                    <dd class="text-slate-900">{{ seller.tin }}</dd>
                    <dt class="text-slate-500">VAT status</dt>
                    <dd class="text-slate-900">{{ seller.vat_status === 'vat_registered' ? 'VAT-registered' : 'Non-VAT' }}</dd>
                </dl>
            </div>
        </main>
    </div>
</template>
