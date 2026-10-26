<script setup>
// The success, information and error message a controller sent with its redirect (`->with('success', ...)`), shown at
// the top of the page by each layout.
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Alert from '@/Components/Ui/Alert.vue';

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
</script>

<template>
    <div v-if="flash.success || flash.info || flash.error" class="space-y-3">
        <Alert v-if="flash.success" variant="success">{{ flash.success }}</Alert>
        <Alert v-if="flash.info" variant="info">{{ flash.info }}</Alert>
        <Alert v-if="flash.error" variant="danger">{{ flash.error }}</Alert>
    </div>
</template>
