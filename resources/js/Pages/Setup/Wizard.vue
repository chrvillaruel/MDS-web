<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    defaults: {
        registered_name?: string | null;
        branch_code?: string;
        vat_status?: string;
    };
}>();

const step = ref(1);

const form = useForm({
    registered_name: '',
    business_style: '',
    tin: '',
    branch_code: '00000',
    address: '',
    vat_status: 'vat_registered',
    bir_rdo_code: '',
    first_branch_code: 'HQ',
    first_branch_name: 'Head Office',
    first_branch_address: '',
});

function next() {
    if (step.value < 3) step.value += 1;
}
function prev() {
    if (step.value > 1) step.value -= 1;
}
function submit() {
    form.post('/setup');
}
</script>

<template>
    <Head title="Set up your business" />
    <div class="min-h-screen bg-slate-50 px-4 py-10">
        <div class="max-w-2xl mx-auto">
            <h1 class="text-2xl font-semibold text-slate-900">Set up your MDS account</h1>
            <p class="text-sm text-slate-600 mt-1">
                You need to provide your BIR-required taxpayer details before you can issue invoices.
            </p>

            <ol class="flex items-center gap-2 mt-6 mb-6 text-xs text-slate-600">
                <li :class="step >= 1 ? 'text-slate-900 font-medium' : ''">1. Taxpayer</li>
                <li>·</li>
                <li :class="step >= 2 ? 'text-slate-900 font-medium' : ''">2. VAT + RDO</li>
                <li>·</li>
                <li :class="step >= 3 ? 'text-slate-900 font-medium' : ''">3. First branch</li>
            </ol>

            <form class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm space-y-4"
                @submit.prevent="submit">
                <template v-if="step === 1">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Registered name</label>
                        <input v-model="form.registered_name" required
                            class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                        <p v-if="form.errors.registered_name" class="text-xs text-rose-600 mt-1">
                            {{ form.errors.registered_name }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Business style (DBA, optional)</label>
                        <input v-model="form.business_style"
                            class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">TIN (9 digits)</label>
                            <input v-model="form.tin" required maxlength="9" pattern="\d{9}"
                                class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                            <p v-if="form.errors.tin" class="text-xs text-rose-600 mt-1">{{ form.errors.tin }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Branch code (5 digits)</label>
                            <input v-model="form.branch_code" required maxlength="5" pattern="\d{5}"
                                class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                            <p v-if="form.errors.branch_code" class="text-xs text-rose-600 mt-1">
                                {{ form.errors.branch_code }}
                            </p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Registered address</label>
                        <textarea v-model="form.address" required rows="3"
                            class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                        <p v-if="form.errors.address" class="text-xs text-rose-600 mt-1">{{ form.errors.address }}</p>
                    </div>
                </template>

                <template v-if="step === 2">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">VAT status</label>
                        <select v-model="form.vat_status" required
                            class="mt-1 w-full rounded border-slate-300 px-3 py-2 border">
                            <option value="vat_registered">VAT-registered</option>
                            <option value="non_vat_registered">Non-VAT-registered</option>
                        </select>
                        <p v-if="form.errors.vat_status" class="text-xs text-rose-600 mt-1">
                            {{ form.errors.vat_status }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">BIR RDO code (3 digits, optional)</label>
                        <input v-model="form.bir_rdo_code" maxlength="3" pattern="\d{3}"
                            class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                        <p v-if="form.errors.bir_rdo_code" class="text-xs text-rose-600 mt-1">
                            {{ form.errors.bir_rdo_code }}
                        </p>
                    </div>
                </template>

                <template v-if="step === 3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Branch code</label>
                        <input v-model="form.first_branch_code" required
                            class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                        <p v-if="form.errors.first_branch_code" class="text-xs text-rose-600 mt-1">
                            {{ form.errors.first_branch_code }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Branch name</label>
                        <input v-model="form.first_branch_name" required
                            class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                        <p v-if="form.errors.first_branch_name" class="text-xs text-rose-600 mt-1">
                            {{ form.errors.first_branch_name }}
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Branch address</label>
                        <textarea v-model="form.first_branch_address" required rows="3"
                            class="mt-1 w-full rounded border-slate-300 px-3 py-2 border" />
                        <p v-if="form.errors.first_branch_address" class="text-xs text-rose-600 mt-1">
                            {{ form.errors.first_branch_address }}
                        </p>
                    </div>
                </template>

                <div class="flex justify-between pt-2">
                    <button v-if="step > 1" type="button" @click="prev"
                        class="text-sm text-slate-700 hover:underline">Back</button>
                    <span v-else></span>
                    <button v-if="step < 3" type="button" @click="next"
                        class="rounded bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800">
                        Next
                    </button>
                    <button v-else type="submit" :disabled="form.processing"
                        class="rounded bg-slate-900 text-white px-4 py-2 text-sm font-medium hover:bg-slate-800 disabled:opacity-50">
                        Finish setup
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
