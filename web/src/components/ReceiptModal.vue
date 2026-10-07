<script setup>
import { rupiah } from '@/lib/format'
import BaseModal from '@/components/BaseModal.vue'

defineProps({
  open: Boolean,
  receipt: Object,
})

defineEmits(['close'])

const methodLabels = {
  cash: 'Tunai',
  qris: 'QRIS',
  transfer: 'Transfer',
}

function print() {
  window.print()
}
</script>

<template>
  <BaseModal :open="open" title="Struk Transaksi" @close="$emit('close')">
    <div v-if="receipt">
      <div id="receipt-print" class="mx-auto w-full max-w-xs font-mono text-xs text-slate-800">
        <div class="text-center">
          <div class="text-sm font-bold">{{ receipt.store.name }}</div>
          <div v-if="receipt.store.address">{{ receipt.store.address }}</div>
          <div v-if="receipt.store.phone">{{ receipt.store.phone }}</div>
        </div>

        <hr class="my-2 border-dashed border-slate-400" />

        <div>No   : {{ receipt.invoice_no }}</div>
        <div>Tgl  : {{ receipt.date }}</div>
        <div>Kasir: {{ receipt.cashier }}</div>
        <div v-if="receipt.status === 'voided'" class="mt-1 font-bold">*** DIBATALKAN ***</div>

        <hr class="my-2 border-dashed border-slate-400" />

        <div v-for="(item, index) in receipt.items" :key="index" class="mb-1">
          <div>{{ item.name }}</div>
          <div class="flex justify-between">
            <span>{{ item.quantity }} x {{ rupiah(item.price) }}</span>
            <span>{{ rupiah(item.subtotal) }}</span>
          </div>
        </div>

        <hr class="my-2 border-dashed border-slate-400" />

        <div class="flex justify-between">
          <span>Subtotal</span>
          <span>{{ rupiah(receipt.subtotal) }}</span>
        </div>
        <div v-if="receipt.discount > 0" class="flex justify-between">
          <span>Diskon</span>
          <span>-{{ rupiah(receipt.discount) }}</span>
        </div>
        <div class="flex justify-between text-sm font-bold">
          <span>Total</span>
          <span>{{ rupiah(receipt.total) }}</span>
        </div>
        <div class="flex justify-between">
          <span>Bayar ({{ methodLabels[receipt.payment_method] }})</span>
          <span>{{ rupiah(receipt.amount_paid) }}</span>
        </div>
        <div v-if="receipt.payment_method === 'cash'" class="flex justify-between">
          <span>Kembali</span>
          <span>{{ rupiah(receipt.change) }}</span>
        </div>

        <div v-if="receipt.footer" class="mt-3 text-center">{{ receipt.footer }}</div>
      </div>

      <div class="mt-5 flex justify-end gap-2">
        <button
          class="rounded-lg px-4 py-2 text-sm font-medium text-indigo-600 ring-1 ring-indigo-200 hover:bg-indigo-50"
          @click="print"
        >
          Cetak
        </button>
        <button
          class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
          @click="$emit('close')"
        >
          Selesai
        </button>
      </div>
    </div>
  </BaseModal>
</template>