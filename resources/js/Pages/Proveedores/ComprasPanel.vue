<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    compras: {
        type: Object,
        default: () => ({
            data: [],
            current_page: 1,
            last_page: 1,
            from: 0,
            to: 0,
            total: 0,
        }),
    },
    proveedores: {
        type: Array,
        default: () => [],
    },
    productos: {
        type: Array,
        default: () => [],
    },
    flash: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    targetRoute: {
        type: String,
        default: 'inventario.index',
    },
});

const showForm = ref(false);
const filterForm = useForm({
    compra_busqueda: props.filters?.busqueda || '',
    compra_estado: props.filters?.estado || '',
    compra_desde: props.filters?.desde || '',
    compra_hasta: props.filters?.hasta || '',
});
const form = useForm({
    proveedor_id: '',
    detalles: [{ producto_id: '', cantidad: 1, precio_unitario: 0 }],
});

const totalCompra = computed(() => form.detalles.reduce(
    (total, detalle) => total + Number(detalle.cantidad || 0) * Number(detalle.precio_unitario || 0),
    0,
));

const formatCurrency = (amount) => new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
}).format(Number(amount || 0));

const agregarDetalle = () => {
    form.detalles.push({ producto_id: '', cantidad: 1, precio_unitario: 0 });
};

const quitarDetalle = (index) => {
    if (form.detalles.length > 1) {
        form.detalles.splice(index, 1);
    }
};

const productoYaSeleccionado = (productoId, currentIndex) => form.detalles.some(
    (detalle, index) => index !== currentIndex && String(detalle.producto_id) === String(productoId),
);

const actualizarCostoProducto = (index) => {
    const producto = props.productos.find((item) => String(item.id) === String(form.detalles[index].producto_id));
    if (producto) {
        form.detalles[index].precio_unitario = producto.precio_compra;
    }
};

const resetForm = () => {
    form.reset();
    form.clearErrors();
    form.detalles = [{ producto_id: '', cantidad: 1, precio_unitario: 0 }];
    showForm.value = false;
};

const guardarCompra = () => {
    form.post(route('inventario.compras.store'), {
        onSuccess: resetForm,
    });
};

const applyFilters = () => {
    const params = Object.fromEntries(
        Object.entries(filterForm.data()).filter(([, value]) => value !== ''),
    );

    router.get(route(props.targetRoute), { ...params, tab: 'compras' }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    filterForm.reset();
    filterForm.clearErrors();
    router.get(route(props.targetRoute), { tab: 'compras' }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const goToPage = (pageNumber) => {
    if (pageNumber < 1 || pageNumber > props.compras.last_page) {
        return;
    }

    const params = Object.fromEntries(
        Object.entries(filterForm.data()).filter(([, value]) => value !== ''),
    );

    router.get(route(props.targetRoute), { ...params, tab: 'compras', compras_page: pageNumber }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const recibirCompra = (compra) => {
    if (confirm(`¿Confirmas la recepción completa de la compra #${compra.id}? Esto aumentará el stock físico en inventario y actualizará el último costo de compra de cada producto.`)) {
        router.post(route('inventario.compras.receive', compra.id));
    }
};
</script>

<template>
    <section>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-purple-600">
                    <span>Abastecimiento</span><span>/</span><span>Compras a Proveedores</span>
                </div>
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Compras a Proveedores</h2>
                <p class="mt-1 text-sm font-medium text-slate-500">
                    Registra pedidos de compra a tus proveedores y recibe existencias directamente en el inventario.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('proveedores.index')"
                    class="rounded-xl border border-purple-200 bg-white px-4 py-3 text-sm font-bold text-purple-700 shadow-xs hover:bg-purple-50 transition-colors"
                >
                    Directorio de proveedores
                </Link>
                <button
                    type="button"
                    :disabled="proveedores.length === 0 || productos.length === 0"
                    @click="showForm = !showForm"
                    class="inline-flex items-center gap-2 rounded-xl bg-purple-700 px-5 py-3 text-sm font-bold text-white shadow-md shadow-purple-700/20 transition-all hover:bg-purple-800 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path v-if="!showForm" stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        <path v-else stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span>{{ showForm ? 'Cerrar formulario' : 'Nueva compra a proveedor' }}</span>
                </button>
            </div>
        </div>

        <div v-if="flash.success" role="status" class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 shadow-xs">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" role="alert" class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800 shadow-xs">
            {{ flash.error }}
        </div>

        <div v-if="proveedores.length === 0 || productos.length === 0" class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 shadow-xs">
            Para registrar una compra necesitas al menos un proveedor activo y un producto en catálogo.
            <Link v-if="proveedores.length === 0" :href="route('proveedores.index')" class="ml-1 font-bold underline">
                Registrar nuevo proveedor
            </Link>
            <span v-if="proveedores.length === 0 && productos.length === 0"> y </span>
            <span v-if="productos.length === 0" class="ml-1 font-semibold">crear un producto en el catálogo.</span>
        </div>

        <!-- FORMULARIO DE NUEVA COMPRA -->
        <form v-if="showForm" @submit.prevent="guardarCompra" class="mb-8 rounded-3xl border border-purple-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Registrar orden de compra pendiente</h3>
                    <p class="text-xs text-slate-500">El stock físico de los productos aumentará cuando confirmes la recepción del pedido.</p>
                </div>
                <Link :href="route('proveedores.index')" class="text-xs font-bold text-purple-700 hover:text-purple-900">
                    Administrar proveedores &rarr;
                </Link>
            </div>

            <div class="mb-6 max-w-xl">
                <label for="purchase-supplier" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">
                    Proveedor Emisor <span class="text-rose-500">*</span>
                </label>
                <select id="purchase-supplier" v-model.number="form.proveedor_id" required class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm text-slate-900 focus:border-purple-400 focus:ring-purple-500">
                    <option value="">Selecciona el proveedor</option>
                    <option v-for="proveedor in proveedores" :key="proveedor.id" :value="proveedor.id">{{ proveedor.nombre }}</option>
                </select>
                <p v-if="form.errors.proveedor_id" class="mt-1 text-xs text-rose-600">{{ form.errors.proveedor_id }}</p>
            </div>

            <div class="space-y-4">
                <div v-for="(detalle, index) in form.detalles" :key="index" class="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-12 sm:items-center">
                    <div class="sm:col-span-5">
                        <label :for="`purchase-product-${index}`" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">Producto *</label>
                        <select :id="`purchase-product-${index}`" v-model.number="detalle.producto_id" required @change="actualizarCostoProducto(index)" class="w-full rounded-xl border-slate-200 bg-white text-sm text-slate-900 focus:border-purple-400 focus:ring-purple-500">
                            <option value="">Selecciona un producto</option>
                            <option
                                v-for="producto in productos"
                                :key="producto.id"
                                :value="producto.id"
                                :disabled="productoYaSeleccionado(producto.id, index)"
                            >{{ producto.codigo }} — {{ producto.nombre }}</option>
                        </select>
                        <p v-if="form.errors[`detalles.${index}.producto_id`]" class="mt-1 text-xs text-rose-600">{{ form.errors[`detalles.${index}.producto_id`] }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label :for="`purchase-quantity-${index}`" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">Cantidad *</label>
                        <input :id="`purchase-quantity-${index}`" v-model.number="detalle.cantidad" type="number" min="1" max="1000000" required class="w-full rounded-xl border-slate-200 bg-white text-sm text-slate-900 focus:border-purple-400 focus:ring-purple-500" />
                        <p v-if="form.errors[`detalles.${index}.cantidad`]" class="mt-1 text-xs text-rose-600">{{ form.errors[`detalles.${index}.cantidad`] }}</p>
                    </div>
                    <div class="sm:col-span-3">
                        <label :for="`purchase-price-${index}`" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">Costo unitario *</label>
                        <input :id="`purchase-price-${index}`" v-model.number="detalle.precio_unitario" type="number" min="0" step="0.01" required class="w-full rounded-xl border-slate-200 bg-white text-sm text-slate-900 focus:border-purple-400 focus:ring-purple-500" />
                        <p v-if="form.errors[`detalles.${index}.precio_unitario`]" class="mt-1 text-xs text-rose-600">{{ form.errors[`detalles.${index}.precio_unitario`] }}</p>
                    </div>
                    <div class="sm:col-span-2 flex items-center justify-between gap-2 pt-4 sm:pt-0">
                        <div class="text-right">
                            <span class="block text-[11px] font-bold text-slate-400 uppercase">Subtotal</span>
                            <span class="text-sm font-bold text-slate-800">{{ formatCurrency(Number(detalle.cantidad || 0) * Number(detalle.precio_unitario || 0)) }}</span>
                        </div>
                        <button
                            type="button"
                            :disabled="form.detalles.length === 1"
                            @click="quitarDetalle(index)"
                            class="rounded-lg p-2 text-rose-600 transition-colors hover:bg-rose-50 hover:text-rose-800 disabled:cursor-not-allowed disabled:opacity-30"
                            title="Quitar producto"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-4 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <button type="button" @click="agregarDetalle" class="inline-flex items-center gap-1.5 text-xs font-bold text-purple-700 hover:text-purple-900">
                    <span>+ Agregar otro producto</span>
                </button>
                <div class="text-right">
                    <p class="text-xs uppercase font-bold tracking-wider text-slate-400">Total de la orden de compra</p>
                    <p class="text-2xl font-extrabold text-slate-900">{{ formatCurrency(totalCompra) }}</p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
                <button type="button" @click="resetForm" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
                <button type="submit" :disabled="form.processing" class="rounded-xl bg-purple-700 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-purple-700/20 hover:bg-purple-800 disabled:cursor-not-allowed disabled:opacity-60">
                    {{ form.processing ? 'Registrando…' : 'Registrar orden de compra' }}
                </button>
            </div>
        </form>

        <!-- LISTA Y FILTROS DE COMPRAS -->
        <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xs">
            <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Historial de compras</h3>
                    <p class="text-xs font-medium text-slate-400">Órdenes registradas y recepción de inventario</p>
                </div>
            </div>

            <!-- Filtros -->
            <form @submit.prevent="applyFilters" class="grid gap-3 border-b border-slate-100 p-5 sm:grid-cols-2 lg:grid-cols-4">
                <input v-model="filterForm.compra_busqueda" type="search" placeholder="Buscar por ID o proveedor..." class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs sm:text-sm text-slate-900" />
                <select v-model="filterForm.compra_estado" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs sm:text-sm text-slate-900">
                    <option value="">Todos los estados</option>
                    <option value="pendiente">Pendientes de recepción</option>
                    <option value="recibida">Recibidas en inventario</option>
                </select>
                <input v-model="filterForm.compra_desde" type="date" aria-label="Fecha desde" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs sm:text-sm text-slate-900" />
                <div class="flex gap-2">
                    <input v-model="filterForm.compra_hasta" type="date" aria-label="Fecha hasta" class="w-full rounded-xl border-slate-200 bg-slate-50 text-xs sm:text-sm text-slate-900" />
                    <button type="submit" class="rounded-xl bg-purple-700 px-4 py-2 text-xs font-bold text-white hover:bg-purple-800">Filtrar</button>
                    <button type="button" @click="clearFilters" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Limpiar</button>
                </div>
            </form>

            <!-- Listado de Compras -->
            <div v-if="compras.data && compras.data.length > 0" class="divide-y divide-slate-100">
                <article v-for="compra in compras.data" :key="compra.id" class="p-5 transition-colors hover:bg-slate-50/70">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-extrabold text-slate-900">Compra #{{ compra.id }}</span>
                                <span class="text-sm font-semibold text-slate-700">— {{ compra.proveedor_nombre }}</span>
                                <span
                                    :class="compra.estado === 'recibida' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'"
                                    class="rounded-full border px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide"
                                >
                                    {{ compra.estado === 'recibida' ? 'Recibida en stock' : 'Pendiente' }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                Creada: {{ compra.created_at }}
                                <span v-if="compra.recibida_en" class="ml-2">· Recibida: {{ compra.recibida_en }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-base font-black text-slate-900">{{ formatCurrency(compra.total) }}</span>
                            <button
                                v-if="compra.estado === 'pendiente'"
                                type="button"
                                @click="recibirCompra(compra)"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 transition-colors"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <span>Recibir compra</span>
                            </button>
                            <a
                                v-if="compra.estado === 'recibida'"
                                :href="route('inventario.compras.receipt', compra.id)"
                                target="_blank"
                                class="inline-flex items-center gap-1 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100 transition-colors"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                <span>Comprobante</span>
                            </a>
                        </div>
                    </div>

                    <!-- Detalle de productos en la compra -->
                    <div class="mt-3 overflow-x-auto rounded-xl bg-slate-50/80 p-3">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                    <th class="pb-1 pr-3">Producto</th>
                                    <th class="pb-1">Cant.</th>
                                    <th class="pb-1">Costo unitario</th>
                                    <th class="pb-1 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/50">
                                <tr v-for="(detalle, index) in compra.detalles" :key="`${compra.id}-${index}`">
                                    <td class="py-1.5 pr-3 font-medium text-slate-700">{{ detalle.producto_codigo }} — {{ detalle.producto_nombre }}</td>
                                    <td class="py-1.5 font-bold text-slate-800">{{ detalle.cantidad }}</td>
                                    <td class="py-1.5 text-slate-600">{{ formatCurrency(detalle.precio_unitario) }}</td>
                                    <td class="py-1.5 text-right font-bold text-slate-800">{{ formatCurrency(detalle.subtotal) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
            <div v-else class="px-5 py-14 text-center text-sm text-slate-400">
                {{ compras.total === 0 ? 'Aún no hay compras a proveedores registradas.' : 'No hay compras que coincidan con los filtros aplicados.' }}
            </div>

            <!-- Paginación -->
            <div v-if="compras.last_page > 1" class="flex items-center justify-between border-t border-slate-100 p-4">
                <button
                    type="button"
                    :disabled="compras.current_page <= 1"
                    @click="goToPage(compras.current_page - 1)"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                >Anterior</button>
                <span class="text-xs font-semibold text-slate-500">Página {{ compras.current_page }} de {{ compras.last_page }}</span>
                <button
                    type="button"
                    :disabled="compras.current_page >= compras.last_page"
                    @click="goToPage(compras.current_page + 1)"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
                >Siguiente</button>
            </div>
        </section>
    </section>
</template>
