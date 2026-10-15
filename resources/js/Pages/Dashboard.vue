<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    periodoActual: {
        type: String,
        default: 'hoy',
    },
    kpis: {
        type: Object,
        default: () => ({
            ventas: { valor: 0, transacciones: 0 },
            unidadesVendidas: 0,
            stockBajo: { cantidad: 0, criticos: 0 },
            domicilios: { pendientes: 0, entregadosPeriodo: 0 },
        }),
    },
    grafico: {
        type: Array,
        default: () => [],
    },
    productosBajoStock: {
        type: Array,
        default: () => [],
    },
});

const cambiarPeriodo = (periodo) => {
    router.get(route('dashboard'), { periodo }, {
        preserveScroll: true,
        replace: true,
    });
};

const formatoMoneda = (valor) => new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
}).format(Number(valor || 0));

const totalGrafico = () => props.grafico.reduce((total, barra) => total + Number(barra.total || 0), 0);
</script>

<template>
    <Head title="Panel Principal - PosVenta" />

    <AuthenticatedLayout>
        <!-- TOP HEADER SECTION -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">
                    Panel principal
                </h1>
                <p class="mt-1 text-sm text-slate-500 font-medium">
                    Resumen operativo {{ periodoActual === 'hoy' ? 'de hoy' : 'de esta semana' }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <div class="inline-flex rounded-xl p-1 bg-white border border-slate-200/90 shadow-sm">
                    <button
                        @click="cambiarPeriodo('hoy')"
                        :class="[
                            periodoActual === 'hoy'
                                ? 'bg-purple-600 text-white font-semibold shadow-sm'
                                : 'text-slate-600 hover:text-slate-900',
                            'px-3.5 py-1.5 text-xs rounded-lg transition-all'
                        ]"
                    >
                        Hoy
                    </button>
                    <button
                        @click="cambiarPeriodo('semana')"
                        :class="[
                            periodoActual === 'semana'
                                ? 'bg-purple-600 text-white font-semibold shadow-sm'
                                : 'text-slate-600 hover:text-slate-900',
                            'px-3.5 py-1.5 text-xs rounded-lg transition-all'
                        ]"
                    >
                        Esta semana
                    </button>
                </div>

                <Link
                    :href="route('ventas.index')"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-md shadow-purple-600/20 transition-all hover:scale-[1.02] active:scale-[0.98]"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Nueva Venta</span>
                </Link>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-semibold text-slate-500">Ventas {{ periodoActual === 'hoy' ? 'de hoy' : 'de la semana' }}</span>
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-purple-50 text-purple-700">{{ kpis.ventas.transacciones }} ventas</span>
                </div>
                <div class="text-2xl sm:text-3xl font-black text-purple-700 tracking-tight mb-1">{{ formatoMoneda(kpis.ventas.valor) }}</div>
                <p class="text-xs text-slate-400 font-medium">Total facturado en el período</p>
            </div>
            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-semibold text-slate-500">Productos vendidos</span>
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-sky-50 text-sky-700">Unidades</span>
                </div>
                <div class="text-2xl sm:text-3xl font-black text-purple-700 tracking-tight mb-1">{{ kpis.unidadesVendidas }}</div>
                <p class="text-xs text-slate-400 font-medium">Según el detalle de ventas</p>
            </div>
            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-semibold text-slate-500">Stock bajo</span>
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-rose-50 text-rose-600">{{ kpis.stockBajo.criticos }} agotados</span>
                </div>
                <div class="text-2xl sm:text-3xl font-black text-purple-700 tracking-tight mb-1">{{ kpis.stockBajo.cantidad }}</div>
                <p class="text-xs text-slate-400 font-medium">Productos en o bajo su mínimo</p>
            </div>
            <div class="bg-white rounded-3xl p-6 border border-slate-200/80 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-semibold text-slate-500">Domicilios pendientes</span>
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-700">{{ kpis.domicilios.entregadosPeriodo }} entregados</span>
                </div>
                <div class="text-2xl sm:text-3xl font-black text-purple-700 tracking-tight mb-1">{{ kpis.domicilios.pendientes }}</div>
                <p class="text-xs text-slate-400 font-medium">Pendientes por entregar ahora</p>
            </div>
        </div>

        <!-- MAIN 2-COLUMN GRID (GRÁFICO Y ALERTAS) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- VENTAS POR HORARIO (7 COLUMNAS) -->
            <div class="lg:col-span-7 bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900 tracking-tight">
                                {{ periodoActual === 'hoy' ? 'Ventas por franja horaria' : 'Ventas de la semana' }}
                            </h2>
                            <p class="text-xs text-slate-400 font-medium mt-0.5">
                                {{ periodoActual === 'hoy' ? 'Facturación agrupada en bloques de cuatro horas' : 'Facturación diaria de lunes a domingo' }}
                            </p>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-purple-50 text-purple-700 border border-purple-100">
                            {{ grafico.length }} {{ periodoActual === 'hoy' ? 'franjas' : 'días' }}
                        </span>
                    </div>

                    <div class="h-64 sm:h-72 w-full pt-8 pb-4 flex items-end justify-between gap-3 sm:gap-6 border-b border-slate-100">
                        <div
                            v-for="item in grafico"
                            :key="item.etiqueta"
                            class="flex-1 flex flex-col items-center h-full justify-end group relative"
                        >
                            <div class="absolute -top-12 opacity-0 group-hover:opacity-100 transition-all duration-200 pointer-events-none bg-slate-900 text-white text-[11px] font-semibold py-1 px-2.5 rounded-lg shadow-lg whitespace-nowrap z-20">
                                {{ formatoMoneda(item.total) }} ({{ item.transacciones }} ventas)
                                <div class="w-2 h-2 bg-slate-900 rotate-45 absolute -bottom-1 left-1/2 -translate-x-1/2"></div>
                            </div>
                            <div class="w-full bg-slate-100 rounded-2xl h-full flex items-end overflow-hidden p-0.5">
                                <div
                                    class="w-full rounded-2xl bg-purple-400 group-hover:bg-purple-600 transition-all duration-300 shadow-sm"
                                    :style="{ height: `${item.altura}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>

                    <!-- Eje X: Horarios -->
                    <div class="flex items-center justify-between gap-3 sm:gap-6 pt-3 px-1">
                        <div
                            v-for="item in grafico"
                            :key="item.etiqueta"
                            class="flex-1 text-center text-xs font-semibold text-slate-500"
                        >
                            {{ item.etiqueta }}
                        </div>
                    </div>
                </div>

                <div class="pt-6 mt-6 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400">
                    <span>{{ periodoActual === 'hoy' ? 'Total de hoy' : 'Total semanal' }}</span>
                    <span class="font-semibold text-purple-700">{{ formatoMoneda(totalGrafico()) }}</span>
                </div>
            </div>

            <!-- ALERTAS DE INVENTARIO -->
            <div class="lg:col-span-5 bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900 tracking-tight">
                                Alertas de inventario
                            </h2>
                            <p class="text-xs text-slate-400 font-medium mt-0.5">
                                Productos activos en o bajo el stock mínimo
                            </p>
                        </div>
                        <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700">{{ kpis.stockBajo.cantidad }}</span>
                    </div>

                    <div class="space-y-3.5">
                        <div
                            v-for="item in productosBajoStock"
                            :key="item.id"
                            class="p-4 rounded-2xl bg-slate-50/90 hover:bg-purple-50/60 border border-slate-100 transition-all duration-200 flex items-center justify-between group"
                        >
                            <div class="flex items-center gap-3.5">
                                <div
                                    :class="item.stock <= 0 ? 'bg-rose-500 shadow-rose-300/50' : 'bg-amber-500 shadow-amber-300/50'"
                                    class="w-4 h-4 rounded-full shrink-0 shadow-md ring-4 ring-white"
                                ></div>

                                <div>
                                    <h3 class="text-sm font-bold text-slate-800 group-hover:text-purple-900 transition-colors">
                                        {{ item.name }}
                                    </h3>
                                    <span class="text-xs font-medium text-slate-400">
                                        Stock: <span class="font-bold text-slate-700">{{ item.stock }}</span> / mínimo {{ item.stock_minimo }}
                                        <span v-if="item.categoria"> · {{ item.categoria }}</span>
                                    </span>
                                </div>
                            </div>

                            <Link
                                :href="route('proveedores.index')"
                                class="text-xs font-semibold px-3 py-1.5 rounded-xl bg-white border border-slate-200 text-slate-600 hover:border-purple-300 hover:text-purple-700 transition-colors shadow-2xs"
                            >
                                Reponer
                            </Link>
                        </div>
                        <div v-if="productosBajoStock.length === 0" class="rounded-2xl border border-emerald-100 bg-emerald-50 p-5 text-center text-sm font-medium text-emerald-800">
                            No hay productos activos por debajo del stock mínimo.
                        </div>
                    </div>
                </div>

                <div class="pt-6 mt-6 border-t border-slate-100">
                    <Link
                        :href="route('inventario.index')"
                        class="w-full py-3 px-4 rounded-2xl bg-purple-50 hover:bg-purple-100 text-purple-700 font-bold text-xs transition-colors flex items-center justify-center gap-2"
                    >
                        <span>Ver catálogo completo de inventario</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
