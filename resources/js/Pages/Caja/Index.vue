<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    cajaAbierta: {
        type: Object,
        default: null,
    },
    historial: {
        type: Object,
        default: () => ({ data: [], current_page: 1, last_page: 1, total: 0 }),
    },
    flash: {
        type: Object,
        default: () => ({}),
    },
    esAdministrador: {
        type: Boolean,
        default: false,
    },
    filtrosReporte: {
        type: Object,
        default: () => ({ usuario: '', desde: '', hasta: '' }),
    },
    reporteCierres: {
        type: Object,
        default: null,
    },
    resumenCierres: {
        type: Object,
        default: null,
    },
});

const filtrosReporte = reactive({ ...props.filtrosReporte });
const aperturaForm = useForm({
    monto_inicial: 0,
});

const cierreForm = useForm({
    monto_contado: 0,
});

const formatCurrency = (amount) => new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
}).format(Number(amount || 0));

const abrirCaja = () => {
    aperturaForm.post(route('caja.open'), {
        onSuccess: () => aperturaForm.reset('monto_inicial'),
    });
};

const cerrarCaja = (caja) => {
    if (!confirm('¿Confirmas el cierre de tu caja? Se comparará el efectivo contado con el monto esperado.')) {
        return;
    }

    cierreForm.post(route('caja.close', caja.id), {
        onSuccess: () => cierreForm.reset('monto_contado'),
    });
};

const buscarCierres = () => {
    router.get(route('caja.index'), filtrosReporte, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

const limpiarFiltros = () => {
    filtrosReporte.usuario = '';
    filtrosReporte.desde = '';
    filtrosReporte.hasta = '';
    buscarCierres();
};
</script>

<template>
    <Head title="Caja - PosVenta" />

    <AuthenticatedLayout>
        <div class="mb-8">
            <div class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-purple-600">
                <span>Operación</span><span>/</span><span>Caja</span>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Mi caja</h1>
            <p class="mt-1 text-sm font-medium text-slate-500">Controla tu efectivo de apertura y concilia las ventas al finalizar el turno.</p>
        </div>

        <div v-if="flash.success" role="status" class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" role="alert" class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800">
            {{ flash.error }}
        </div>

        <section v-if="cajaAbierta" class="mb-8 rounded-3xl border border-emerald-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                        <h2 class="text-xl font-extrabold text-slate-900">Caja abierta #{{ cajaAbierta.id }}</h2>
                    </div>
                    <p class="mt-2 text-sm text-slate-500">Abierta {{ cajaAbierta.abierta_en }}. Las ventas se asociarán a esta caja.</p>
                </div>
                <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold uppercase text-emerald-700">En operación</span>
            </div>

            <dl class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Monto de apertura</dt>
                    <dd class="mt-2 text-xl font-extrabold text-slate-900">{{ formatCurrency(cajaAbierta.monto_inicial) }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Ventas en efectivo</dt>
                    <dd class="mt-2 text-xl font-extrabold text-purple-700">{{ formatCurrency(cajaAbierta.ventas_total) }}</dd>
                </div>
                <div class="rounded-2xl bg-purple-50 p-4">
                    <dt class="text-xs font-bold uppercase tracking-wider text-purple-700">Efectivo esperado</dt>
                    <dd class="mt-2 text-xl font-extrabold text-purple-900">{{ formatCurrency(cajaAbierta.monto_esperado) }}</dd>
                </div>
            </dl>

            <form @submit.prevent="cerrarCaja(cajaAbierta)" class="mt-7 border-t border-slate-100 pt-6">
                <h3 class="text-base font-bold text-slate-900">Cerrar y conciliar caja</h3>
                <p class="mt-1 text-sm text-slate-500">Cuenta el efectivo disponible e ingresa el total. Se registrará cualquier sobrante o faltante.</p>
                <div class="mt-4 flex flex-col gap-3 sm:max-w-xl sm:flex-row">
                    <div class="flex-1">
                        <label for="cash-counted" class="sr-only">Efectivo contado</label>
                        <input id="cash-counted" v-model.number="cierreForm.monto_contado" type="number" min="0" step="0.01" required class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:border-purple-400 focus:ring-purple-500" placeholder="Efectivo contado al cierre" />
                        <p v-if="cierreForm.errors.monto_contado" class="mt-1 text-xs text-rose-600">{{ cierreForm.errors.monto_contado }}</p>
                        <p v-if="cierreForm.errors.caja" class="mt-1 text-xs text-rose-600">{{ cierreForm.errors.caja }}</p>
                    </div>
                    <button type="submit" :disabled="cierreForm.processing" class="rounded-xl bg-rose-700 px-5 py-3 text-sm font-bold text-white hover:bg-rose-800 disabled:cursor-not-allowed disabled:opacity-60">
                        {{ cierreForm.processing ? 'Cerrando…' : 'Cerrar caja' }}
                    </button>
                </div>
            </form>
        </section>

        <section v-else class="mb-8 rounded-3xl border border-amber-200 bg-white p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                        <h2 class="text-xl font-extrabold text-slate-900">No tienes una caja abierta</h2>
                    </div>
                    <p class="mt-2 max-w-2xl text-sm text-slate-500">Abre tu caja con el efectivo inicial del turno. El POS permitirá registrar ventas cuando la caja esté abierta.</p>
                </div>
                <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-bold uppercase text-amber-800">Cerrada</span>
            </div>

            <form @submit.prevent="abrirCaja" class="mt-6 flex flex-col gap-3 border-t border-slate-100 pt-6 sm:max-w-xl sm:flex-row">
                <div class="flex-1">
                    <label for="cash-opening" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Efectivo inicial</label>
                    <input id="cash-opening" v-model.number="aperturaForm.monto_inicial" type="number" min="0" step="0.01" required class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:border-purple-400 focus:ring-purple-500" />
                    <p v-if="aperturaForm.errors.monto_inicial" class="mt-1 text-xs text-rose-600">{{ aperturaForm.errors.monto_inicial }}</p>
                    <p v-if="aperturaForm.errors.caja" class="mt-1 text-xs text-rose-600">{{ aperturaForm.errors.caja }}</p>
                </div>
                <button type="submit" :disabled="aperturaForm.processing" class="self-end rounded-xl bg-purple-700 px-5 py-3 text-sm font-bold text-white hover:bg-purple-800 disabled:cursor-not-allowed disabled:opacity-60">
                    {{ aperturaForm.processing ? 'Abriendo…' : 'Abrir caja' }}
                </button>
            </form>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-5">
                <h2 class="text-lg font-bold text-slate-900">Mis cierres anteriores</h2>
                <p class="text-xs font-medium text-slate-400">{{ historial.total }} cierres registrados</p>
            </div>
            <div v-if="historial.data?.length" class="overflow-x-auto">
                <table class="w-full min-w-[650px] text-left text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Caja</th>
                            <th class="px-5 py-4">Apertura</th>
                            <th class="px-5 py-4">Ventas</th>
                            <th class="px-5 py-4">Esperado</th>
                            <th class="px-5 py-4">Contado</th>
                            <th class="px-5 py-4">Diferencia</th>
                            <th class="px-5 py-4">Cierre</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="caja in historial.data" :key="caja.id">
                            <td class="px-5 py-4 font-bold text-slate-800">#{{ caja.id }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ formatCurrency(caja.monto_inicial) }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ formatCurrency(caja.ventas_total) }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ formatCurrency(caja.monto_esperado) }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ formatCurrency(caja.monto_contado) }}</td>
                            <td :class="caja.diferencia < 0 ? 'text-rose-700' : caja.diferencia > 0 ? 'text-emerald-700' : 'text-slate-600'" class="px-5 py-4 font-bold">
                                {{ caja.diferencia > 0 ? '+' : '' }}{{ formatCurrency(caja.diferencia) }}
                            </td>
                            <td class="px-5 py-4 text-slate-500">{{ caja.cerrada_en }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="px-5 py-12 text-center text-sm text-slate-400">Aún no tienes cierres de caja registrados.</div>
        </section>

        <section v-if="esAdministrador" class="mt-8 overflow-hidden rounded-3xl border border-purple-200 bg-white shadow-sm">
            <div class="border-b border-purple-100 bg-purple-50/70 p-5 sm:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-900">Reporte de cierres de caja</h2>
                        <p class="mt-1 text-sm text-slate-500">Consulta y concilia los cierres de todos los usuarios.</p>
                    </div>
                    <span class="w-fit rounded-full border border-purple-200 bg-white px-3 py-1 text-xs font-bold uppercase text-purple-700">Solo administrador</span>
                </div>
            </div>

            <form @submit.prevent="buscarCierres" class="grid gap-3 border-b border-slate-100 p-5 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="filter-cashier" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Usuario</label>
                    <input id="filter-cashier" v-model="filtrosReporte.usuario" type="search" maxlength="100" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-purple-400 focus:ring-purple-500" placeholder="Nombre del cajero" />
                </div>
                <div>
                    <label for="filter-from" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Desde</label>
                    <input id="filter-from" v-model="filtrosReporte.desde" type="date" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-purple-400 focus:ring-purple-500" />
                </div>
                <div>
                    <label for="filter-to" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Hasta</label>
                    <input id="filter-to" v-model="filtrosReporte.hasta" type="date" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-purple-400 focus:ring-purple-500" />
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 rounded-xl bg-purple-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-purple-800">Filtrar</button>
                    <button type="button" @click="limpiarFiltros" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-50">Limpiar</button>
                </div>
                <p v-if="filtrosReporte.hasta && filtrosReporte.desde && filtrosReporte.hasta < filtrosReporte.desde" class="text-xs font-semibold text-rose-600 sm:col-span-2 lg:col-span-4">
                    La fecha final no puede ser anterior a la fecha inicial.
                </p>
            </form>

            <dl v-if="resumenCierres" class="grid gap-3 border-b border-slate-100 p-5 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Cierres encontrados</dt>
                    <dd class="mt-2 text-xl font-extrabold text-slate-900">{{ resumenCierres.total_cierres }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Efectivo esperado</dt>
                    <dd class="mt-2 text-xl font-extrabold text-slate-900">{{ formatCurrency(resumenCierres.monto_esperado) }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Efectivo contado</dt>
                    <dd class="mt-2 text-xl font-extrabold text-purple-800">{{ formatCurrency(resumenCierres.monto_contado) }}</dd>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Diferencia acumulada</dt>
                    <dd :class="resumenCierres.diferencia < 0 ? 'text-rose-700' : resumenCierres.diferencia > 0 ? 'text-emerald-700' : 'text-slate-900'" class="mt-2 text-xl font-extrabold">
                        {{ resumenCierres.diferencia > 0 ? '+' : '' }}{{ formatCurrency(resumenCierres.diferencia) }}
                    </dd>
                </div>
            </dl>

            <div v-if="reporteCierres?.data?.length" class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Usuario</th>
                            <th class="px-5 py-4">Caja</th>
                            <th class="px-5 py-4">Apertura</th>
                            <th class="px-5 py-4">Ventas</th>
                            <th class="px-5 py-4">Esperado</th>
                            <th class="px-5 py-4">Contado</th>
                            <th class="px-5 py-4">Diferencia</th>
                            <th class="px-5 py-4">Cierre</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="caja in reporteCierres.data" :key="caja.id">
                            <td class="px-5 py-4 font-semibold text-slate-800">{{ caja.usuario_nombre }}</td>
                            <td class="px-5 py-4 text-slate-600">#{{ caja.id }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ formatCurrency(caja.monto_inicial) }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ formatCurrency(caja.ventas_total) }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ formatCurrency(caja.monto_esperado) }}</td>
                            <td class="px-5 py-4 text-slate-600">{{ formatCurrency(caja.monto_contado) }}</td>
                            <td :class="caja.diferencia < 0 ? 'text-rose-700' : caja.diferencia > 0 ? 'text-emerald-700' : 'text-slate-600'" class="px-5 py-4 font-bold">
                                {{ caja.diferencia > 0 ? '+' : '' }}{{ formatCurrency(caja.diferencia) }}
                            </td>
                            <td class="px-5 py-4 text-slate-500">{{ caja.cerrada_en }}</td>
                        </tr>
                    </tbody>
                </table>
                <div class="flex items-center justify-between border-t border-slate-100 px-5 py-4">
                    <span class="text-xs font-medium text-slate-500">Página {{ reporteCierres.current_page }} de {{ reporteCierres.last_page }} · {{ reporteCierres.total }} cierres</span>
                    <div class="flex gap-2">
                        <a v-if="reporteCierres.prev_page_url" :href="reporteCierres.prev_page_url" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Anterior</a>
                        <a v-if="reporteCierres.next_page_url" :href="reporteCierres.next_page_url" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">Siguiente</a>
                    </div>
                </div>
            </div>
            <div v-else class="px-5 py-12 text-center text-sm text-slate-400">No hay cierres que coincidan con los filtros seleccionados.</div>
        </section>
    </AuthenticatedLayout>
</template>
