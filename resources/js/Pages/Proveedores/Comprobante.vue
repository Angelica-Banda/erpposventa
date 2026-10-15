<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    compra: {
        type: Object,
        required: true,
    },
});

const formatCurrency = (amount) => new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
}).format(Number(amount || 0));

const printReceipt = () => {
    window.print();
};
</script>

<template>
    <Head :title="`Comprobante de compra #${compra.id} - PosVenta`" />

    <main class="min-h-screen bg-slate-100 p-4 sm:p-8">
        <div class="no-print mx-auto mb-5 flex w-full max-w-xl items-center justify-between gap-3">
            <a :href="route('proveedores.index')" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">
                Volver a compras
            </a>
            <button type="button" @click="printReceipt" class="rounded-xl bg-purple-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-purple-800">
                Imprimir comprobante
            </button>
        </div>

        <section id="purchase-receipt" class="mx-auto w-full max-w-xl rounded-2xl bg-white p-6 shadow-sm">
            <header class="border-b border-dashed border-slate-300 pb-4 text-center">
                <h1 class="text-xl font-black text-slate-900">PosVenta</h1>
                <p class="mt-1 text-xs text-slate-500">Comprobante de compra recibida</p>
                <p class="mt-3 text-sm font-bold text-slate-800">Compra #{{ compra.id }}</p>
            </header>

            <dl class="grid grid-cols-1 gap-2 py-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-slate-500">Proveedor</dt>
                    <dd class="font-semibold text-slate-800">{{ compra.proveedor_nombre }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Registrada</dt>
                    <dd class="font-semibold text-slate-800">{{ compra.fecha_registro || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Fecha de recepción</dt>
                    <dd class="font-semibold text-slate-800">{{ compra.recibida_en || '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Estado</dt>
                    <dd class="font-semibold text-emerald-700">Recibida</dd>
                </div>
            </dl>

            <div class="border-y border-dashed border-slate-300 py-3">
                <div class="mb-2 grid grid-cols-12 gap-2 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                    <span class="col-span-6">Producto</span>
                    <span class="col-span-2 text-right">Cant.</span>
                    <span class="col-span-4 text-right">Subtotal</span>
                </div>
                <div v-for="(detalle, index) in compra.detalles" :key="`${detalle.producto_codigo}-${index}`" class="grid grid-cols-12 gap-2 py-2 text-sm">
                    <div class="col-span-6 min-w-0">
                        <p class="truncate font-semibold text-slate-800">{{ detalle.producto_nombre }}</p>
                        <p class="mt-0.5 text-[10px] text-slate-500">{{ detalle.producto_codigo }} · {{ formatCurrency(detalle.precio_unitario) }} c/u</p>
                    </div>
                    <span class="col-span-2 text-right text-slate-700">{{ detalle.cantidad }}</span>
                    <span class="col-span-4 text-right font-bold text-slate-900">{{ formatCurrency(detalle.subtotal) }}</span>
                </div>
            </div>

            <div class="flex items-center justify-between py-4">
                <span class="font-bold text-slate-700">Total de la compra</span>
                <span class="text-xl font-black text-purple-700">{{ formatCurrency(compra.total) }}</span>
            </div>
            <p class="border-t border-dashed border-slate-300 pt-4 text-center text-xs text-slate-500">
                Documento de control interno de recepción de inventario.
            </p>
        </section>
    </main>
</template>

<style scoped>
@media print {
    :global(body *) {
        visibility: hidden !important;
    }

    #purchase-receipt,
    #purchase-receipt * {
        visibility: visible !important;
    }

    #purchase-receipt {
        position: absolute !important;
        inset: 0 auto auto 0 !important;
        width: 80mm !important;
        max-width: none !important;
        margin: 0 auto !important;
        padding: 8mm !important;
        box-shadow: none !important;
    }
}
</style>
