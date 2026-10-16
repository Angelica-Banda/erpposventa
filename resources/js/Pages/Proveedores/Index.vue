<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    proveedores: {
        type: Array,
        default: () => [],
    },
    flash: {
        type: Object,
        default: () => ({}),
    },
});

const isModalOpen = ref(false);
const editingId = ref(null);
const search = ref('');

const form = useForm({
    nombre: '',
    nit: '',
    telefono: '',
    correo: '',
    direccion: '',
    activo: true,
});

const totalProveedores = computed(() => props.proveedores.length);
const activosCount = computed(() => props.proveedores.filter((p) => p.activo).length);
const inactivosCount = computed(() => props.proveedores.filter((p) => !p.activo).length);

const filteredSuppliers = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');
    if (!term) {
        return props.proveedores;
    }

    return props.proveedores.filter((proveedor) =>
        [proveedor.nombre, proveedor.nit, proveedor.correo, proveedor.telefono, proveedor.direccion]
            .some((value) => String(value || '').toLocaleLowerCase('es').includes(term)),
    );
});

const openCreateModal = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.activo = true;
    isModalOpen.value = true;
};

const openEditModal = (proveedor) => {
    editingId.value = proveedor.id;
    form.clearErrors();
    form.nombre = proveedor.nombre;
    form.nit = proveedor.nit || '';
    form.telefono = proveedor.telefono || '';
    form.correo = proveedor.correo || '';
    form.direccion = proveedor.direccion || '';
    form.activo = Boolean(proveedor.activo);
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const handleKeyDown = (e) => {
    if (e.key === 'Escape' && isModalOpen.value) {
        closeModal();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});

const saveSupplier = () => {
    const options = {
        onSuccess: () => closeModal(),
    };

    if (editingId.value) {
        form.put(route('proveedores.update', editingId.value), options);
        return;
    }

    form.post(route('proveedores.store'), options);
};

const deleteSupplier = (proveedor) => {
    if (proveedor.compras_count > 0) {
        alert('Este proveedor tiene compras registradas en el sistema. Para deshabilitarlo, puedes editarlo y marcarlo como Inactivo.');
        return;
    }

    if (confirm(`¿Estás seguro de eliminar al proveedor "${proveedor.nombre}"? Esta acción no se puede deshacer.`)) {
        router.delete(route('proveedores.destroy', proveedor.id));
    }
};
</script>

<template>
    <Head title="Proveedores - PosVenta" />

    <AuthenticatedLayout>
        <!-- Header -->
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-purple-600">
                    <span>Abastecimiento</span>
                    <span>/</span>
                    <span>Directorio de Proveedores</span>
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Proveedores</h1>
                <p class="mt-1 text-sm font-medium text-slate-500">
                    Administra los proveedores y distribuidores comerciales del negocio.
                </p>
            </div>
            <div>
                <button
                    type="button"
                    @click="openCreateModal"
                    class="inline-flex items-center gap-2 rounded-xl bg-purple-700 px-5 py-3 text-sm font-bold text-white shadow-md shadow-purple-700/20 transition-all hover:bg-purple-800 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>Nuevo proveedor</span>
                </button>
            </div>
        </div>

        <!-- Alertas Flash -->
        <div v-if="flash?.success" role="status" class="mb-6 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 shadow-xs">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-200 text-emerald-800 font-black text-xs">✓</span>
            <span>{{ flash.success }}</span>
        </div>
        <div v-if="flash?.error" role="alert" class="mb-6 flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800 shadow-xs">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rose-200 text-rose-800 font-black text-xs">!</span>
            <span>{{ flash.error }}</span>
        </div>

        <!-- Tarjetas de Resumen KPI -->
        <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xs">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total proveedores</p>
                <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ totalProveedores }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xs">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Proveedores activos</p>
                <p class="mt-2 text-3xl font-extrabold text-emerald-600">{{ activosCount }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xs">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Inactivos</p>
                <p class="mt-2 text-3xl font-extrabold text-slate-400">{{ inactivosCount }}</p>
            </div>
        </div>

        <!-- Tabla del Directorio de Proveedores -->
        <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xs">
            <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Listado de Proveedores</h2>
                    <p class="text-xs font-medium text-slate-400">
                        {{ filteredSuppliers.length }} de {{ proveedores.length }} proveedores mostrados
                    </p>
                </div>
                <div class="relative w-full sm:max-w-xs">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </span>
                    <input
                        v-model="search"
                        type="search"
                        aria-label="Buscar proveedores"
                        placeholder="Buscar nombre, NIT, contacto..."
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs sm:text-sm text-slate-900 placeholder:text-slate-400 focus:border-purple-400 focus:bg-white focus:ring-2 focus:ring-purple-500/20"
                    />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-6 py-4">Proveedor / Empresa</th>
                            <th class="px-6 py-4">Contacto</th>
                            <th class="px-6 py-4">Dirección</th>
                            <th class="px-6 py-4 text-center">Compras</th>
                            <th class="px-6 py-4 text-center">Estado</th>
                            <th class="px-6 py-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <tr v-for="proveedor in filteredSuppliers" :key="proveedor.id" class="transition-colors hover:bg-slate-50/70">
                            <td class="px-6 py-4">
                                <p class="font-bold text-slate-900">{{ proveedor.nombre }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    <span class="font-medium text-slate-400">NIT:</span> {{ proveedor.nit || 'Sin registrar' }}
                                </p>
                            </td>
                            <td class="px-6 py-4 text-slate-600">
                                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-800">
                                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                    </svg>
                                    <span>{{ proveedor.telefono || 'Sin teléfono' }}</span>
                                </div>
                                <div v-if="proveedor.correo" class="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                    </svg>
                                    <span class="truncate max-w-[190px]">{{ proveedor.correo }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-600">
                                <span>{{ proveedor.direccion || 'Sin dirección' }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center justify-center rounded-lg bg-purple-50 px-2.5 py-1 text-xs font-bold text-purple-700">
                                    {{ proveedor.compras_count || 0 }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    :class="proveedor.activo ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-500'"
                                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-bold"
                                >
                                    <span :class="proveedor.activo ? 'bg-emerald-500' : 'bg-slate-400'" class="h-1.5 w-1.5 rounded-full"></span>
                                    <span>{{ proveedor.activo ? 'Activo' : 'Inactivo' }}</span>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="openEditModal(proveedor)"
                                        class="rounded-lg p-1.5 text-purple-700 transition-colors hover:bg-purple-50 hover:text-purple-900"
                                        title="Editar proveedor"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        :disabled="proveedor.compras_count > 0"
                                        :title="proveedor.compras_count > 0 ? 'Tiene compras asociadas; márcalo como inactivo en lugar de eliminarlo.' : 'Eliminar proveedor'"
                                        @click="deleteSupplier(proveedor)"
                                        class="rounded-lg p-1.5 text-rose-600 transition-colors hover:bg-rose-50 hover:text-rose-800 disabled:cursor-not-allowed disabled:opacity-30"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredSuppliers.length === 0">
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <svg class="h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                    </svg>
                                    <p class="font-semibold text-slate-500">
                                        {{ proveedores.length === 0 ? 'No hay proveedores registrados aún' : 'No se encontraron proveedores que coincidan con la búsqueda' }}
                                    </p>
                                    <button
                                        v-if="proveedores.length === 0"
                                        type="button"
                                        @click="openCreateModal"
                                        class="mt-2 text-xs font-bold text-purple-700 hover:underline"
                                    >
                                        Registrar el primer proveedor
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- MODAL DE AGREGAR / EDITAR PROVEEDOR -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-supplier-title"
        >
            <!-- Fondo Oscuro Backdrop -->
            <div
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
                @click="closeModal"
            ></div>

            <!-- Contenedor del Modal -->
            <div class="relative w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-2xl transition-all">
                <!-- Header del Modal -->
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
                    <div>
                        <h2 id="modal-supplier-title" class="text-lg font-extrabold text-slate-900">
                            {{ editingId ? 'Editar proveedor' : 'Nuevo proveedor' }}
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ editingId ? 'Modifica los datos del proveedor seleccionado' : 'Ingresa los datos para registrar un nuevo proveedor' }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="closeModal"
                        class="rounded-xl p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 focus:outline-none"
                        aria-label="Cerrar modal"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Formulario -->
                <form @submit.prevent="saveSupplier" class="p-6 space-y-4">
                    <div>
                        <label for="supplier-name" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">
                            Razón Social / Nombre Comercial <span class="text-rose-500">*</span>
                        </label>
                        <input
                            id="supplier-name"
                            v-model="form.nombre"
                            type="text"
                            required
                            maxlength="255"
                            placeholder="Ej. Distribuidora Central S.A.S."
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-purple-400 focus:bg-white focus:ring-2 focus:ring-purple-500/20"
                        />
                        <p v-if="form.errors.nombre" class="mt-1 text-xs text-rose-600">{{ form.errors.nombre }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="supplier-nit" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">
                                NIT / Documento
                            </label>
                            <input
                                id="supplier-nit"
                                v-model="form.nit"
                                type="text"
                                maxlength="30"
                                placeholder="Ej. 901234567-1"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-purple-400 focus:bg-white focus:ring-2 focus:ring-purple-500/20"
                            />
                            <p v-if="form.errors.nit" class="mt-1 text-xs text-rose-600">{{ form.errors.nit }}</p>
                        </div>
                        <div>
                            <label for="supplier-phone" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">
                                Teléfono de Contacto
                            </label>
                            <input
                                id="supplier-phone"
                                v-model="form.telefono"
                                type="text"
                                maxlength="30"
                                placeholder="Ej. 300 123 4567"
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-purple-400 focus:bg-white focus:ring-2 focus:ring-purple-500/20"
                            />
                            <p v-if="form.errors.telefono" class="mt-1 text-xs text-rose-600">{{ form.errors.telefono }}</p>
                        </div>
                    </div>

                    <div>
                        <label for="supplier-email" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">
                            Correo Electrónico
                        </label>
                        <input
                            id="supplier-email"
                            v-model="form.correo"
                            type="email"
                            maxlength="255"
                            placeholder="ventas@proveedor.com"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-purple-400 focus:bg-white focus:ring-2 focus:ring-purple-500/20"
                        />
                        <p v-if="form.errors.correo" class="mt-1 text-xs text-rose-600">{{ form.errors.correo }}</p>
                    </div>

                    <div>
                        <label for="supplier-address" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-600">
                            Dirección Física
                        </label>
                        <input
                            id="supplier-address"
                            v-model="form.direccion"
                            type="text"
                            maxlength="255"
                            placeholder="Ej. Carrera 45 # 20 - 15"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-purple-400 focus:bg-white focus:ring-2 focus:ring-purple-500/20"
                        />
                        <p v-if="form.errors.direccion" class="mt-1 text-xs text-rose-600">{{ form.errors.direccion }}</p>
                    </div>

                    <div class="pt-1">
                        <label class="inline-flex cursor-pointer items-center gap-2.5 text-sm font-semibold text-slate-700">
                            <input
                                v-model="form.activo"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-purple-600 focus:ring-purple-500"
                            />
                            <span>Proveedor activo (disponible para compras en inventario)</span>
                        </label>
                    </div>

                    <!-- Footer Botones -->
                    <div class="mt-6 flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                        <button
                            type="button"
                            @click="closeModal"
                            class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs sm:text-sm font-bold text-slate-600 transition-colors hover:bg-slate-50"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-purple-700 px-5 py-2.5 text-xs sm:text-sm font-bold text-white shadow-md shadow-purple-700/20 transition-all hover:bg-purple-800 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <svg v-if="form.processing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>{{ form.processing ? 'Guardando…' : editingId ? 'Actualizar proveedor' : 'Guardar proveedor' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
