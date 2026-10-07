<script setup>
defineProps({
  open: Boolean,
  title: String,
})

defineEmits(['close'])
</script>

<template>
  <div
    v-if="open"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    @click.self="$emit('close')"
  >
    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold">{{ title }}</h2>
        <button class="text-2xl leading-none text-slate-400 hover:text-slate-600" @click="$emit('close')">
          &times;
        </button>
      </div>

      <slot />
    </div>
  </div>
</template>