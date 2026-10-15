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
});

const showForm = ref(false);
const filterForm = useForm({
    busqueda: props.filters?.busqueda || '',
    estado: props.filters?.estado || '',
    desde: props.filters?.desde || '',
    hasta: props.filters?.hasta || '',
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
    form.post(route('proveedores.compras.store'), {
        onSuccess: resetForm,
    });
};

const applyFilters = () => {
    const params = Object.fromEntries(
        Object.entries(filterForm.data()).filter(([, value]) => value !== ''),
    );

    router.get(route('proveedores.index'), params, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    filterForm.reset();
    filterForm.clearErrors();
    router.get(route('proveedores.index'), {}, {
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

    router.get(route('proveedores.index'), { ...params, page: pageNumber }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const recibirCompra = (compra) => {
    if (confirm(`¿Confirmas la recepción completa de la compra #${compra.id}? Esto aumentará el stock y actualizará el último costo de cada producto.`)) {
        router.post(route('proveedores.compras.receive', compra.id));
    }
};
</script>

<template>
    <section>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-purple-600">
                    <span>Proveedores</span><span>/</span><span>Registro de compras</span>
                </div>
                <h2 class="text-2xl font-extrabold tracking-tight text-slate-900">Compras y recepción</h2>
                <p class="mt-1 text-sm font-medium text-slate-500">Registra pedidos y actualiza existencias al recibirlos.</p>
            </div>
            <button
                type="button"
                :disabled="proveedores.length === 0 || productos.length === 0"
                @click="showForm = !showForm"
                class="rounded-xl bg-purple-700 px-5 py-3 text-sm font-bold text-white shadow-md transition-colors hover:bg-purple-800 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {{ showForm ? 'Cerrar' : 'Nueva compra' }}
            </button>
        </div>

        <div v-if="flash.success" role="status" class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">{{ flash.success }}</div>
        <div v-if="flash.error" role="alert" class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800">{{ flash.error }}</div>

        <div v-if="proveedores.length === 0 || productos.length === 0" class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            Para crear una compra necesitas al menos un proveedor activo y un producto activo.
            <a v-if="proveedores.length === 0" :href="route('proveedores.index')" class="ml-1 font-bold underline">Registrar proveedor</a>
            <a v-if="productos.length === 0" :href="route('inventario.index')" class="ml-1 font-bold underline">Revisar inventario</a>
        </div>

        <form v-if="showForm" @submit.prevent="guardarCompra" class="mb-8 rounded-3xl border border-purple-200 bg-white p-6 shadow-sm">
            <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Registrar compra pendiente</h2>
                    <p class="text-xs text-slate-500">El inventario no cambiará hasta que confirmes la recepción.</p>
                </div>
                <a :href="route('proveedores.index')" class="text-sm font-bold text-purple-700 hover:text-purple-900">Gestionar proveedores</a>
            </div>

            <div class="mb-6 max-w-xl">
                <label for="purchase-supplier" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Proveedor *</label>
                <select id="purchase-supplier" v-model.number="form.proveedor_id" required class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:border-purple-400 focus:ring-purple-500">
                    <option value="">Selecciona un proveedor</option>
                    <option v-for="proveedor in proveedores" :key="proveedor.id" :value="proveedor.id">{{ proveedor.nombre }}</option>
                </select>
                <p v-if="form.errors.proveedor_id" class="mt-1 text-xs text-rose-600">{{ form.errors.proveedor_id }}</p>
            </div>

            <div class="space-y-4">
                <div v-for="(detalle, index) in form.detalles" :key="index" class="grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-12">
                    <div class="sm:col-span-5">
                        <label :for="`purchase-product-${index}`" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Producto *</label>
                        <select :id="`purchase-product-${index}`" v-model.number="detalle.producto_id" required @change="actualizarCostoProducto(index)" class="w-full rounded-xl border-slate-200 bg-white text-sm text-slate-900">
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
                        <label :for="`purchase-quantity-${index}`" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Cantidad *</label>
                        <input :id="`purchase-quantity-${index}`" v-model.number="detalle.cantidad" type="number" min="1" step="1" required class="w-full rounded-xl border-slate-200 bg-white text-sm" />
                        <p v-if="form.errors[`detalles.${index}.cantidad`]" class="mt-1 text-xs text-rose-600">{{ form.errors[`detalles.${index}.cantidad`] }}</p>
                    </div>
                    <div class="sm:col-span-3">
                        <label :for="`purchase-cost-${index}`" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Costo unitario *</label>
                        <input :id="`purchase-cost-${index}`" v-model.number="detalle.precio_unitario" type="number" min="0" step="0.01" required class="w-full rounded-xl border-slate-200 bg-white text-sm" />
                        <p v-if="form.errors[`detalles.${index}.precio_unitario`]" class="mt-1 text-xs text-rose-600">{{ form.errors[`detalles.${index}.precio_unitario`] }}</p>
                    </div>
                    <div class="flex items-end justify-between gap-3 sm:col-span-2 sm:justify-end">
                        <p class="pb-2 text-sm font-bold text-slate-800">{{ formatCurrency(Number(detalle.cantidad || 0) * Number(detalle.precio_unitario || 0)) }}</p>
                        <button type="button" :disabled="form.detalles.length === 1" @click="quitarDetalle(index)" class="mb-2 text-xs font-bold text-rose-700 disabled:opacity-40">Quitar</button>
                    </div>
                </div>
            </div>
            <p v-if="form.errors.detalles" class="mt-2 text-sm text-rose-600">{{ form.errors.detalles }}</p>
            <button type="button" @click="agregarDetalle" class="mt-4 rounded-xl border border-purple-200 px-4 py-2 text-sm font-bold text-purple-700 hover:bg-purple-50">Agregar producto</button>

            <div class="mt-6 flex flex-col gap-4 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-lg font-extrabold text-slate-900">Total: {{ formatCurrency(totalCompra) }}</p>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="resetForm" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-xl bg-purple-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-purple-800 disabled:cursor-not-allowed disabled:opacity-60">
                        {{ form.processing ? 'Guardando…' : 'Guardar compra pendiente' }}
                    </button>
                </div>
            </div>
        </form>

        <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <div class="flex flex-col gap-2 border-b border-slate-100 p-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Historial de compras</h2>
                    <p class="text-xs font-medium text-slate-400">
                        {{ compras.from || 0 }}–{{ compras.to || 0 }} de {{ compras.total }} compras
                    </p>
                </div>
            </div>
            <form @submit.prevent="applyFilters" class="grid grid-cols-1 gap-3 border-b border-slate-100 bg-slate-50/60 p-5 sm:grid-cols-2 xl:grid-cols-5">
                <div>
                    <label for="purchase-search" class="sr-only">Buscar compra</label>
                    <input id="purchase-search" v-model="filterForm.busqueda" type="search" maxlength="100" placeholder="Buscar proveedor o número" class="w-full rounded-xl border-slate-200 bg-white text-sm" />
                </div>
                <div>
                    <label for="purchase-status" class="sr-only">Filtrar por estado</label>
                    <select id="purchase-status" v-model="filterForm.estado" class="w-full rounded-xl border-slate-200 bg-white text-sm text-slate-700">
                        <option value="">Todos los estados</option>
                        <option value="pendiente">Pendientes</option>
                        <option value="recibida">Recibidas</option>
                    </select>
                </div>
                <div>
                    <label for="purchase-from" class="sr-only">Fecha desde</label>
                    <input id="purchase-from" v-model="filterForm.desde" type="date" aria-label="Fecha desde" class="w-full rounded-xl border-slate-200 bg-white text-sm" />
                    <p v-if="$page.props.errors?.desde" class="mt-1 text-xs text-rose-600">{{ $page.props.errors.desde }}</p>
                </div>
                <div>
                    <label for="purchase-to" class="sr-only">Fecha hasta</label>
                    <input id="purchase-to" v-model="filterForm.hasta" type="date" aria-label="Fecha hasta" class="w-full rounded-xl border-slate-200 bg-white text-sm" />
                    <p v-if="$page.props.errors?.hasta" class="mt-1 text-xs text-rose-600">{{ $page.props.errors.hasta }}</p>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 rounded-xl bg-purple-700 px-4 py-2 text-sm font-bold text-white hover:bg-purple-800">Filtrar</button>
                    <button type="button" @click="clearFilters" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-slate-600 hover:bg-white">Limpiar</button>
                </div>
            </form>
            <div v-if="compras.data?.length" class="divide-y divide-slate-100">
                <article v-for="compra in compras.data" :key="compra.id" class="p-5 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-extrabold text-slate-900">Compra #{{ compra.id }}</h3>
                                <span :class="compra.estado === 'recibida' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-800'" class="rounded-full border px-2.5 py-1 text-xs font-bold">
                                    {{ compra.estado === 'recibida' ? 'Recibida' : 'Pendiente de recepción' }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600">{{ compra.proveedor_nombre }} · Registrada {{ compra.created_at }}</p>
                            <p v-if="compra.recibida_en" class="mt-1 text-xs text-emerald-700">Recibida {{ compra.recibida_en }}</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <p class="text-lg font-extrabold text-slate-900">{{ formatCurrency(compra.total) }}</p>
                            <Link
                                v-if="compra.estado === 'recibida'"
                                :href="route('proveedores.compras.receipt', compra.id)"
                                class="rounded-xl border border-purple-200 px-4 py-2.5 text-sm font-bold text-purple-700 hover:bg-purple-50"
                            >Comprobante</Link>
                            <button
                                v-if="compra.estado === 'pendiente'"
                                type="button"
                                @click="recibirCompra(compra)"
                                class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2"
                            >Recibir compra</button>
                        </div>
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[500px] text-left text-sm">
                            <thead class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                <tr><th class="py-2">Producto</th><th class="py-2">Cantidad</th><th class="py-2">Costo unitario</th><th class="py-2 text-right">Subtotal</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(detalle, index) in compra.detalles" :key="`${compra.id}-${index}`">
                                    <td class="py-2 pr-3 text-slate-700">{{ detalle.producto_codigo }} — {{ detalle.producto_nombre }}</td>
                                    <td class="py-2 text-slate-600">{{ detalle.cantidad }}</td>
                                    <td class="py-2 text-slate-600">{{ formatCurrency(detalle.precio_unitario) }}</td>
                                    <td class="py-2 text-right font-semibold text-slate-800">{{ formatCurrency(detalle.subtotal) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
            <div v-else class="px-5 py-14 text-center text-sm text-slate-400">
                {{ compras.total === 0 ? 'Aún no hay compras que mostrar.' : 'No hay compras que coincidan con los filtros.' }}
            </div>
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
