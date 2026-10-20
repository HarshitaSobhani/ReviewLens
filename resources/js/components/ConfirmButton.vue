<script setup lang="ts">
import { ref } from 'vue';
import Btn from './Btn.vue';

const props = withDefaults(defineProps<{
    label: string; title: string; message: string; confirmLabel?: string; size?: 'sm' | 'md';
}>(), { confirmLabel: 'Delete', size: 'sm' });
const emit = defineEmits<{ (e: 'confirm'): void }>();
const dialog = ref<HTMLDialogElement | null>(null);
function confirm() {
    dialog.value?.close();
    emit('confirm');
}
</script>

<template>
    <div class="inline-block">
        <Btn variant="danger" :size="props.size" @click="dialog?.showModal()">{{ label }}</Btn>
        <dialog ref="dialog" class="m-auto w-[min(92vw,26rem)] rounded-xl p-0 shadow-xl backdrop:bg-slate-900/40" aria-labelledby="confirm-title">
            <div class="p-5">
                <h2 id="confirm-title" class="text-base font-semibold text-slate-900">{{ title }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ message }}</p>
                <div class="mt-5 flex justify-end gap-2">
                    <Btn variant="secondary" @click="dialog?.close()">Cancel</Btn>
                    <Btn variant="danger" @click="confirm">{{ confirmLabel }}</Btn>
                </div>
            </div>
        </dialog>
    </div>
</template>
