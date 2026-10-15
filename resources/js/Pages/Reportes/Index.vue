<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    reporteActual: {
        type: String,
        default: 'ventas',
    },
    filtros: {
        type: Object,
        default: () => ({ desde: '', hasta: '' }),
    },
    resumen: {
        type: Array,
        default: () => [],
    },
    registros: {
        type: Object,
        default: () => ({ data: [], current_page: 1, last_page: 1, total: 0 }),
    },
});

const filtros = reactive({
    ...props.filtros,
    reporte: props.reporteActual,
});

const reportes = [
    { id: 'ventas', nombre: 'Ventas' },
    { id: 'inventario', nombre: 'Inventario' },
    { id: 'compras', nombre: 'Compras' },
    { id: 'caja', nombre: 'Caja' },
    { id: 'domicilios', nombre: 'Domicilios' },
];

const buscarReporte = () => {
    router.get(route('reportes.index'), filtros, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const urlExportacion = (formato) => route('reportes.exportar', {
    formato,
    reporte: filtros.reporte,
    desde: filtros.desde,
    hasta: filtros.hasta,
});

const formatoMoneda = (valor) => new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
}).format(Number(valor || 0));

const formatoResumen = (item) => {
    if (item.format === 'currency') {
        return formatoMoneda(item.value);
    }
    return item.value;
};
</script>

<template>
    <Head title="Reportes - PosVenta" />

    <AuthenticatedLayout>
        <div class="mb-8">
            <div class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-purple-600">
                <span>Administración</span><span>/</span><span>Reportes</span>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Reportes</h1>
            <p class="mt-1 text-sm font-medium text-slate-500">Consulta ventas, inventario, compras, cierres de caja y domicilios en un rango de fechas.</p>
        </div>

        <section class="mb-6 rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
            <form @submit.prevent="buscarReporte" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_auto] xl:items-end">
                <div>
                    <label for="report-kind" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Tipo de reporte</label>
                    <select id="report-kind" v-model="filtros.reporte" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-purple-400 focus:ring-purple-500">
                        <option v-for="reporte in reportes" :key="reporte.id" :value="reporte.id">{{ reporte.nombre }}</option>
                    </select>
                </div>
                <div>
                    <label for="report-from" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Desde</label>
                    <input id="report-from" v-model="filtros.desde" type="date" required class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-purple-400 focus:ring-purple-500" />
                </div>
                <div>
                    <label for="report-to" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Hasta</label>
                    <input id="report-to" v-model="filtros.hasta" type="date" required :min="filtros.desde" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-purple-400 focus:ring-purple-500" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="rounded-xl bg-purple-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-purple-800">Consultar</button>
                    <a :href="urlExportacion('pdf')" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-bold text-rose-800 hover:bg-rose-100">Descargar PDF</a>
                    <a :href="urlExportacion('excel')" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-bold text-emerald-800 hover:bg-emerald-100">Descargar Excel</a>
                </div>
            </form>
            <p v-if="filtros.desde && filtros.hasta && filtros.hasta < filtros.desde" class="mt-3 text-xs font-semibold text-rose-600">
                La fecha final no puede ser anterior a la fecha inicial.
            </p>
        </section>

        <section class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="(item, index) in resumen" :key="item.label" class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ item.label }}</p>
                <p :class="index === 0 ? 'text-purple-800' : 'text-slate-900'" class="mt-2 text-2xl font-extrabold">{{ formatoResumen(item) }}</p>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <div class="flex flex-col gap-2 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Detalle de {{ reportes.find((reporte) => reporte.id === reporteActual)?.nombre || 'reporte' }}</h2>
                    <p class="text-xs font-medium text-slate-400">{{ registros.total }} registros · {{ filtros.desde }} al {{ filtros.hasta }}</p>
                </div>
                <span class="rounded-full border border-purple-200 bg-purple-50 px-3 py-1 text-xs font-bold uppercase text-purple-700">Administrador</span>
            </div>

            <div v-if="registros.data?.length" class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr v-if="reporteActual === 'ventas'">
                            <th class="px-5 py-4">Venta</th><th class="px-5 py-4">Fecha</th><th class="px-5 py-4">Usuario</th><th class="px-5 py-4">Tipo</th><th class="px-5 py-4">Estado domicilio</th><th class="px-5 py-4">Costo domicilio</th><th class="px-5 py-4">Total</th>
                        </tr>
                        <tr v-else-if="reporteActual === 'inventario'">
                            <th class="px-5 py-4">Fecha</th><th class="px-5 py-4">Producto</th><th class="px-5 py-4">Código</th><th class="px-5 py-4">Tipo</th><th class="px-5 py-4">Cambio</th><th class="px-5 py-4">Stock anterior</th><th class="px-5 py-4">Stock nuevo</th><th class="px-5 py-4">Usuario</th>
                        </tr>
                        <tr v-else-if="reporteActual === 'compras'">
                            <th class="px-5 py-4">Compra</th><th class="px-5 py-4">Fecha</th><th class="px-5 py-4">Proveedor</th><th class="px-5 py-4">Estado</th><th class="px-5 py-4">Total</th><th class="px-5 py-4">Recibida</th>
                        </tr>
                        <tr v-else-if="reporteActual === 'caja'">
                            <th class="px-5 py-4">Caja</th><th class="px-5 py-4">Cierre</th><th class="px-5 py-4">Usuario</th><th class="px-5 py-4">Apertura</th><th class="px-5 py-4">Esperado</th><th class="px-5 py-4">Contado</th><th class="px-5 py-4">Diferencia</th>
                        </tr>
                        <tr v-else>
                            <th class="px-5 py-4">Pedido</th><th class="px-5 py-4">Fecha</th><th class="px-5 py-4">Cliente</th><th class="px-5 py-4">Teléfono</th><th class="px-5 py-4">Dirección</th><th class="px-5 py-4">Estado</th><th class="px-5 py-4">Domicilio</th><th class="px-5 py-4">Total</th><th class="px-5 py-4">Usuario</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="registro in registros.data" :key="registro.id">
                            <template v-if="reporteActual === 'ventas'">
                                <td class="px-5 py-4 font-bold text-slate-800">#{{ registro.id }}</td><td class="px-5 py-4 text-slate-500">{{ registro.fecha }}</td><td class="px-5 py-4 text-slate-600">{{ registro.usuario }}</td><td class="px-5 py-4 text-slate-600">{{ registro.tipo }}</td><td class="px-5 py-4 text-slate-600">{{ registro.estado || '—' }}</td><td class="px-5 py-4 text-slate-600">{{ formatoMoneda(registro.costo_adicional) }}</td><td class="px-5 py-4 font-bold text-slate-800">{{ formatoMoneda(registro.total) }}</td>
                            </template>
                            <template v-else-if="reporteActual === 'inventario'">
                                <td class="px-5 py-4 text-slate-500">{{ registro.fecha }}</td><td class="px-5 py-4 font-semibold text-slate-800">{{ registro.producto }}</td><td class="px-5 py-4 text-slate-600">{{ registro.codigo }}</td><td class="px-5 py-4 text-slate-600">{{ registro.tipo }}</td><td :class="registro.diferencia < 0 ? 'text-rose-700' : 'text-emerald-700'" class="px-5 py-4 font-bold">{{ registro.diferencia > 0 ? '+' : '' }}{{ registro.diferencia }}</td><td class="px-5 py-4 text-slate-600">{{ registro.stock_anterior }}</td><td class="px-5 py-4 text-slate-600">{{ registro.stock_nuevo }}</td><td class="px-5 py-4 text-slate-600">{{ registro.usuario }}</td>
                            </template>
                            <template v-else-if="reporteActual === 'compras'">
                                <td class="px-5 py-4 font-bold text-slate-800">#{{ registro.id }}</td><td class="px-5 py-4 text-slate-500">{{ registro.fecha }}</td><td class="px-5 py-4 text-slate-700">{{ registro.proveedor }}</td><td class="px-5 py-4 text-slate-600">{{ registro.estado }}</td><td class="px-5 py-4 font-bold text-slate-800">{{ formatoMoneda(registro.total) }}</td><td class="px-5 py-4 text-slate-500">{{ registro.recibida_en || '—' }}</td>
                            </template>
                            <template v-else-if="reporteActual === 'caja'">
                                <td class="px-5 py-4 font-bold text-slate-800">#{{ registro.id }}</td><td class="px-5 py-4 text-slate-500">{{ registro.fecha }}</td><td class="px-5 py-4 text-slate-700">{{ registro.usuario }}</td><td class="px-5 py-4 text-slate-600">{{ formatoMoneda(registro.monto_inicial) }}</td><td class="px-5 py-4 text-slate-600">{{ formatoMoneda(registro.monto_esperado) }}</td><td class="px-5 py-4 text-slate-600">{{ formatoMoneda(registro.monto_contado) }}</td><td :class="registro.diferencia < 0 ? 'text-rose-700' : registro.diferencia > 0 ? 'text-emerald-700' : 'text-slate-600'" class="px-5 py-4 font-bold">{{ registro.diferencia > 0 ? '+' : '' }}{{ formatoMoneda(registro.diferencia) }}</td>
                            </template>
                            <template v-else>
                                <td class="px-5 py-4 font-bold text-slate-800">#{{ registro.id }}</td><td class="px-5 py-4 text-slate-500">{{ registro.fecha }}</td><td class="px-5 py-4 text-slate-700">{{ registro.cliente }}</td><td class="px-5 py-4 text-slate-600">{{ registro.telefono }}</td><td class="max-w-xs whitespace-normal px-5 py-4 text-slate-600">{{ registro.direccion }}</td><td class="px-5 py-4 text-slate-600">{{ registro.estado }}</td><td class="px-5 py-4 text-slate-600">{{ formatoMoneda(registro.costo_domicilio) }}</td><td class="px-5 py-4 font-bold text-slate-800">{{ formatoMoneda(registro.total) }}</td><td class="px-5 py-4 text-slate-600">{{ registro.usuario }}</td>
                            </template>
                        </tr>
                    </tbody>
                </table>
                <div class="flex items-center justify-between border-t border-slate-100 px-5 py-4">
                    <span class="text-xs font-medium text-slate-500">Página {{ registros.current_page }} de {{ registros.last_page }} · {{ registros.total }} registros</span>
                    <div class="flex gap-2">
                        <Link v-if="registros.prev_page_url" :href="registros.prev_page_url" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Anterior</Link>
                        <Link v-if="registros.next_page_url" :href="registros.next_page_url" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Siguiente</Link>
                    </div>
                </div>
            </div>
            <div v-else class="px-5 py-14 text-center text-sm text-slate-400">No hay registros para este reporte y rango de fechas.</div>
        </section>
    </AuthenticatedLayout>
</template>
