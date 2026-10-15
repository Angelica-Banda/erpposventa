<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    domicilios: {
        type: Object,
        default: () => ({ data: [], current_page: 1, last_page: 1, total: 0 }),
    },
    filters: {
        type: Object,
        default: () => ({ busqueda: '', estado: '' }),
    },
    flash: {
        type: Object,
        default: () => ({}),
    },
});

const filters = reactive({ ...props.filters });
const statusForm = useForm({});

const formatCurrency = (amount) => new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
}).format(Number(amount || 0));

const search = () => {
    router.get(route('domicilios.index'), filters, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const clearFilters = () => {
    filters.busqueda = '';
    filters.estado = '';
    search();
};

const markDelivered = (domicilio) => {
    if (!confirm(`¿Confirmas que el pedido #${domicilio.id} fue entregado?`)) {
        return;
    }

    statusForm.patch(route('domicilios.entregar', domicilio.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Domicilios - PosVenta" />

    <AuthenticatedLayout>
        <div class="mb-8">
            <div class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-purple-600">
                <span>Operación</span><span>/</span><span>Domicilios</span>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Seguimiento de domicilios</h1>
            <p class="mt-1 text-sm font-medium text-slate-500">Consulta tus pedidos a domicilio y actualiza su estado al entregarlos.</p>
        </div>

        <div v-if="flash.success" role="status" class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" role="alert" class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800">
            {{ flash.error }}
        </div>
        <div v-if="statusForm.errors.domicilio_estado" role="alert" class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900">
            {{ statusForm.errors.domicilio_estado }}
        </div>

        <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <form @submit.prevent="search" class="grid gap-3 border-b border-slate-100 p-5 sm:grid-cols-[1fr_220px_auto_auto] sm:items-end">
                <div>
                    <label for="delivery-search" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Buscar pedido</label>
                    <input id="delivery-search" v-model="filters.busqueda" type="search" maxlength="100" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-purple-400 focus:ring-purple-500" placeholder="Cliente, teléfono, dirección o número" />
                </div>
                <div>
                    <label for="delivery-status" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Estado</label>
                    <select id="delivery-status" v-model="filters.estado" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-purple-400 focus:ring-purple-500">
                        <option value="">Todos</option>
                        <option value="pendiente">Pendientes</option>
                        <option value="entregado">Entregados</option>
                    </select>
                </div>
                <button type="submit" class="rounded-xl bg-purple-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-purple-800">Buscar</button>
                <button type="button" @click="clearFilters" class="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-50">Limpiar</button>
            </form>

            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Mis pedidos a domicilio</h2>
                    <p class="text-xs font-medium text-slate-400">{{ domicilios.total }} pedidos</p>
                </div>
                <Link :href="route('ventas.index')" class="text-sm font-bold text-purple-700 hover:text-purple-900">Registrar venta</Link>
            </div>

            <div v-if="domicilios.data?.length" class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-left text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Pedido</th>
                            <th class="px-5 py-4">Cliente</th>
                            <th class="px-5 py-4">Dirección</th>
                            <th class="px-5 py-4">Domicilio</th>
                            <th class="px-5 py-4">Total venta</th>
                            <th class="px-5 py-4">Fecha</th>
                            <th class="px-5 py-4">Estado / acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="domicilio in domicilios.data" :key="domicilio.id">
                            <td class="px-5 py-4 font-bold text-slate-800">#{{ domicilio.id }}</td>
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-800">{{ domicilio.cliente_nombre }}</p>
                                <a :href="`tel:${domicilio.cliente_telefono}`" class="mt-1 inline-block text-xs text-purple-700 hover:underline">{{ domicilio.cliente_telefono }}</a>
                            </td>
                            <td class="max-w-xs whitespace-normal px-5 py-4 text-slate-600">{{ domicilio.domicilio_direccion }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ formatCurrency(domicilio.costo_domicilio) }}</td>
                            <td class="px-5 py-4 font-semibold text-slate-800">{{ formatCurrency(domicilio.total) }}</td>
                            <td class="px-5 py-4 text-slate-500">{{ domicilio.creado_en }}</td>
                            <td class="px-5 py-4">
                                <span :class="domicilio.estado === 'entregado' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-800'" class="inline-flex rounded-full border px-2.5 py-1 text-[11px] font-bold uppercase">
                                    {{ domicilio.estado }}
                                </span>
                                <button v-if="domicilio.estado === 'pendiente'" type="button" :disabled="statusForm.processing" @click="markDelivered(domicilio)" class="mt-2 block whitespace-nowrap text-xs font-bold text-purple-700 hover:text-purple-900 disabled:opacity-50">
                                    Marcar entregado
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex items-center justify-between border-t border-slate-100 px-5 py-4">
                    <span class="text-xs font-medium text-slate-500">Página {{ domicilios.current_page }} de {{ domicilios.last_page }} · {{ domicilios.total }} pedidos</span>
                    <div class="flex gap-2">
                        <Link v-if="domicilios.prev_page_url" :href="domicilios.prev_page_url" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Anterior</Link>
                        <Link v-if="domicilios.next_page_url" :href="domicilios.next_page_url" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Siguiente</Link>
                    </div>
                </div>
            </div>
            <div v-else class="px-5 py-14 text-center">
                <p class="text-sm font-semibold text-slate-600">No hay pedidos a domicilio que coincidan con la búsqueda.</p>
                <p class="mt-1 text-xs text-slate-400">Registra una venta a domicilio desde el POS para verla aquí.</p>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
