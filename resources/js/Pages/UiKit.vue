<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

const text = ref('');
const choice = ref('treasurer');

const roles = [
    { value: 'owner', label: 'Owner' },
    { value: 'admin', label: 'Admin' },
    { value: 'treasurer', label: 'Treasurer' },
];

const buttonVariants = ['primary', 'secondary', 'ghost', 'danger'];
const badgeVariants = ['neutral', 'info', 'success', 'warning', 'danger'];
const alertVariants = ['info', 'success', 'warning', 'danger'];
</script>

<template>
    <Head title="UI kit" />

    <div class="min-h-screen bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-white">
        <div class="max-w-5xl mx-auto p-6 md:p-10 space-y-10">

            <header class="flex items-start justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-5">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">UI kit</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                        Shared primitives. One blue accent; colour otherwise means status.
                    </p>
                </div>
                <ThemeToggle />
            </header>

            <section class="space-y-3">
                <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400">Buttons</h2>
                <Card>
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <Button v-for="v in buttonVariants" :key="v" :variant="v">{{ v }}</Button>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <Button size="sm">Small</Button>
                            <Button size="md">Medium</Button>
                            <Button size="lg">Large</Button>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <Button loading>Loading</Button>
                            <Button disabled>Disabled</Button>
                            <Button variant="secondary" disabled>Disabled</Button>
                        </div>
                    </div>
                </Card>
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400">Stat cards</h2>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <StatCard label="Clubs" :value="128" caption="Across 4 provinces" />
                    <StatCard label="Members" value="3,412" />
                    <StatCard label="Grand lodges" :value="70" caption="51 in the US" />
                    <StatCard label="Outstanding dues" value="£1,240" caption="9 members" />
                </div>
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400">Badges</h2>
                <Card>
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge v-for="v in badgeVariants" :key="v" :variant="v">{{ v }}</Badge>
                    </div>
                </Card>
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400">Form fields</h2>
                <Card>
                    <div class="grid sm:grid-cols-2 gap-5">
                        <Input v-model="text" label="Club name" placeholder="Apollo Lodge" hint="Shown on the public site." />
                        <Input label="Email" type="email" placeholder="secretary@lodge.org" error="That address is already in use." />
                        <Select v-model="choice" label="Role" :options="roles" hint="Controls which admin areas they reach." />
                        <Input label="Disabled" placeholder="Not editable" disabled />
                    </div>
                </Card>
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400">Alerts</h2>
                <div class="space-y-3">
                    <Alert v-for="v in alertVariants" :key="v" :variant="v" :title="v">
                        Short supporting sentence explaining what happened and what to do next.
                    </Alert>
                </div>
            </section>

            <section class="space-y-3">
                <h2 class="text-sm font-medium text-slate-500 dark:text-slate-400">Card with header and footer</h2>
                <Card>
                    <template #header>
                        <div class="flex items-center justify-between">
                            <h3 class="font-medium">Grand Lodge of Texas</h3>
                            <Badge variant="success">Seeded</Badge>
                        </div>
                    </template>

                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        Sovereign Grand Lodge for the State of Texas. Chartered 1837; headquartered in Waco.
                    </p>

                    <template #footer>
                        <div class="flex justify-end gap-2">
                            <Button variant="ghost" size="sm">Cancel</Button>
                            <Button size="sm">Save changes</Button>
                        </div>
                    </template>
                </Card>
            </section>

        </div>
    </div>
</template>
