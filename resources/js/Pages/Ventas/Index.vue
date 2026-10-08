<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    products: {
        type: Array,
        default: () => [],
    },
    flash: {
        type: Object,
        default: () => ({}),
    },
    receipt: {
        type: Object,
        default: null,
    },
});

const products = computed(() => props.products);
const cart = ref([]);
const stockLimitMessage = ref('');
const currentReceipt = ref(props.receipt);
const isReceiptOpen = ref(false);
const saleForm = useForm({
    items: [],
});
const availableProductsCount = computed(() => products.value.filter(product => Number(product.stock) > 0).length);

const addToCart = (product) => {
    const existing = cart.value.find(item => item.id === product.id);

    if (Number(product.stock) <= 0 || (existing && existing.quantity >= Number(product.stock))) {
        stockLimitMessage.value = `No hay más unidades disponibles de "${product.name}".`;
        return;
    }

    stockLimitMessage.value = '';

    if (existing) {
        existing.quantity += 1;
    } else {
        cart.value.push({
            ...product,
            quantity: 1
        });
    }
};

const removeFromCart = (id) => {
    cart.value = cart.value.filter(item => item.id !== id);
    stockLimitMessage.value = '';
};

const clearCart = () => {
    cart.value = [];
    stockLimitMessage.value = '';
    saleForm.clearErrors();
};

const checkout = () => {
    saleForm.items = cart.value.map(item => ({
        product_id: item.id,
        quantity: item.quantity,
    }));

    saleForm.post(route('ventas.store'), {
        preserveScroll: true,
        onSuccess: (page) => {
            clearCart();
            currentReceipt.value = page.props.receipt;
            isReceiptOpen.value = Boolean(currentReceipt.value);
        },
    });
};

const printReceipt = () => {
    window.print();
};

const total = computed(() => {
    return cart.value.reduce((acc, item) => acc + (item.price * item.quantity), 0);
});

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(amount);
};
</script>

<template>
    <Head title="Módulo de Ventas - PosVenta" />

    <AuthenticatedLayout>
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">
                        Módulo de Ventas
                    </h1>
                    <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-purple-100 text-purple-700 border border-purple-200">
                        Rol: Vendedor
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-500 font-medium">
                    Terminal de punto de venta (POS) para registro de órdenes y facturación
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    Caja abierta #01
                </span>
            </div>
        </div>

        <div v-if="flash.success" class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold" role="status">
            {{ flash.success }}
        </div>

        <!-- POS Workspace (2 columns) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Products Catalog (Left) -->
            <div class="lg:col-span-7 bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-bold text-slate-900">Catálogo de Productos</h2>
                    <span class="text-xs text-slate-400 font-medium">{{ availableProductsCount }} artículos disponibles</span>
                </div>

                <div v-if="stockLimitMessage" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800" role="status">
                    {{ stockLimitMessage }}
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div
                        v-for="product in products"
                        :key="product.id"
                        @click="addToCart(product)"
                        :class="Number(product.stock) > 0
                            ? 'p-4 rounded-2xl bg-slate-50 hover:bg-purple-50/70 border border-slate-200/80 hover:border-purple-300 transition-all cursor-pointer group flex flex-col justify-between'
                            : 'p-4 rounded-2xl bg-slate-100 border border-slate-200 transition-all cursor-not-allowed opacity-60 flex flex-col justify-between'"
                    >
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-purple-600 bg-purple-100/70 px-2 py-0.5 rounded-md">
                                    {{ product.category }}
                                </span>
                                <h3 class="font-bold text-sm text-slate-800 mt-1.5 group-hover:text-purple-700 transition-colors">
                                    {{ product.name }}
                                </h3>
                            </div>
                            <span :class="Number(product.stock) > 0 ? 'text-slate-400' : 'text-rose-600'" class="text-xs font-semibold">
                                {{ Number(product.stock) > 0 ? `Stock: ${product.stock}` : 'Agotado' }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-slate-200/60">
                            <span class="text-base font-extrabold text-slate-900">
                                {{ formatCurrency(product.price) }}
                            </span>
                            <span :class="Number(product.stock) > 0 ? 'bg-purple-600 group-hover:bg-purple-700' : 'bg-slate-400'" class="w-7 h-7 rounded-lg text-white flex items-center justify-center font-bold text-sm shadow-sm transition-transform group-hover:scale-105">
                                {{ Number(product.stock) > 0 ? '+' : '−' }}
                            </span>
                        </div>
                    </div>
                    <div v-if="products.length === 0" class="sm:col-span-2 py-12 text-center text-slate-400">
                        <p class="text-sm font-semibold">No hay productos activos en el inventario.</p>
                        <p class="mt-1 text-xs">Agrega productos desde el módulo de Inventario para verlos aquí.</p>
                    </div>
                </div>
            </div>

            <!-- Current Order / Ticket (Right) -->
            <div class="lg:col-span-5 bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Orden Actual</h2>
                            <p class="text-xs text-slate-400">Venta en curso</p>
                        </div>
                        <button
                            v-if="cart.length > 0"
                            @click="clearCart"
                            class="text-xs font-semibold text-rose-600 hover:text-rose-700 transition-colors"
                        >
                            Vaciar ticket
                        </button>
                    </div>

                    <!-- Items List -->
                    <div v-if="cart.length === 0" class="py-16 text-center text-slate-400">
                        <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <p class="text-sm font-medium">El carrito está vacío</p>
                        <p class="text-xs mt-1">Haz clic en los productos para agregarlos al pedido</p>
                    </div>

                    <div v-else class="space-y-3 max-h-80 overflow-y-auto pr-1">
                        <div
                            v-for="item in cart"
                            :key="item.id"
                            class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100"
                        >
                            <div class="min-w-0 flex-1 pr-3">
                                <h4 class="text-xs font-bold text-slate-800 truncate">{{ item.name }}</h4>
                                <span class="text-xs text-purple-700 font-semibold">{{ formatCurrency(item.price) }} × {{ item.quantity }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-black text-slate-900">{{ formatCurrency(item.price * item.quantity) }}</span>
                                <button
                                    @click="removeFromCart(item.id)"
                                    class="text-slate-400 hover:text-rose-600 p-1 transition-colors"
                                    title="Quitar"
                                >
                                    ✕
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total & Checkout -->
                <div class="pt-6 mt-6 border-t border-slate-100">
                    <div v-if="saleForm.errors.items" class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700" role="alert">
                        {{ saleForm.errors.items }}
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-sm font-semibold text-slate-500">Total a Pagar</span>
                        <span class="text-2xl font-black text-purple-700">{{ formatCurrency(total) }}</span>
                    </div>

                    <button
                        :disabled="cart.length === 0 || saleForm.processing"
                        @click="checkout"
                        class="w-full py-3.5 rounded-xl bg-purple-600 hover:bg-purple-700 disabled:bg-slate-200 disabled:text-slate-400 disabled:cursor-not-allowed text-white font-bold text-sm shadow-lg shadow-purple-600/30 transition-all flex items-center justify-center gap-2"
                    >
                        <span>{{ saleForm.processing ? 'Registrando venta...' : 'Registrar venta' }}</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </div>
            </div>

        </div>

        <div v-if="isReceiptOpen && currentReceipt" class="receipt-overlay fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/60 p-4">
            <section id="sales-receipt" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="receipt-title">
                <div class="text-center border-b border-dashed border-slate-300 pb-4">
                    <h2 id="receipt-title" class="text-xl font-black text-slate-900">PosVenta</h2>
                    <p class="mt-1 text-xs text-slate-500">Comprobante de venta</p>
                    <p class="mt-3 text-sm font-bold text-slate-800">Venta #{{ currentReceipt.id }}</p>
                    <p class="text-xs text-slate-500">{{ currentReceipt.date }}</p>
                </div>

                <div class="py-4 text-xs text-slate-600">
                    <p>Vendedor: <span class="font-semibold text-slate-800">{{ currentReceipt.seller }}</span></p>
                </div>

                <div class="border-y border-dashed border-slate-300 py-3">
                    <div v-for="(item, index) in currentReceipt.items" :key="`${item.code}-${index}`" class="py-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <span class="font-semibold text-slate-800">{{ item.name }}</span>
                            <span class="shrink-0 font-bold text-slate-900">{{ formatCurrency(item.subtotal) }}</span>
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            {{ item.code }} · {{ item.quantity }} × {{ formatCurrency(item.unit_price) }}
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between py-4">
                    <span class="font-bold text-slate-700">Total</span>
                    <span class="text-xl font-black text-purple-700">{{ formatCurrency(currentReceipt.total) }}</span>
                </div>

                <p class="border-t border-dashed border-slate-300 pt-4 text-center text-xs text-slate-500">
                    Gracias por su compra
                </p>

                <div class="receipt-screen-actions mt-5 flex gap-3">
                    <button @click="isReceiptOpen = false" type="button" class="flex-1 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600 hover:bg-slate-50">
                        Cerrar
                    </button>
                    <button @click="printReceipt" type="button" class="flex-1 rounded-xl bg-purple-600 px-4 py-3 text-sm font-bold text-white hover:bg-purple-700">
                        Imprimir
                    </button>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@media print {
    :global(body *) {
        visibility: hidden !important;
    }

    .receipt-overlay,
    #sales-receipt,
    #sales-receipt * {
        visibility: visible !important;
    }

    .receipt-overlay {
        position: absolute !important;
        inset: 0 !important;
        display: block !important;
        padding: 0 !important;
        background: white !important;
    }

    #sales-receipt {
        position: absolute !important;
        inset: 0 auto auto 0 !important;
        width: 80mm !important;
        max-width: none !important;
        margin: 0 auto !important;
        padding: 8mm !important;
        box-shadow: none !important;
    }

    .receipt-screen-actions {
        display: none !important;
    }
}
</style>
