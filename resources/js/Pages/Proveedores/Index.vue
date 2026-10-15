<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ComprasPanel from '@/Pages/Proveedores/ComprasPanel.vue';

const props = defineProps({
    proveedores: {
        type: Array,
        default: () => [],
    },
    flash: {
        type: Object,
        default: () => ({}),
    },
    proveedoresActivos: {
        type: Array,
        default: () => [],
    },
    productos: {
        type: Array,
        default: () => [],
    },
    compras: {
        type: Object,
        default: () => ({ data: [], current_page: 1, last_page: 1, from: 0, to: 0, total: 0 }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const formVisible = ref(false);
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

const filteredSuppliers = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');
    if (!term) {
        return props.proveedores;
    }

    return props.proveedores.filter((proveedor) =>
        [proveedor.nombre, proveedor.nit, proveedor.correo, proveedor.telefono]
            .some((value) => String(value || '').toLocaleLowerCase('es').includes(term)),
    );
});

const openCreateForm = () => {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.activo = true;
    formVisible.value = true;
};

const openEditForm = (proveedor) => {
    editingId.value = proveedor.id;
    form.clearErrors();
    form.nombre = proveedor.nombre;
    form.nit = proveedor.nit || '';
    form.telefono = proveedor.telefono || '';
    form.correo = proveedor.correo || '';
    form.direccion = proveedor.direccion || '';
    form.activo = proveedor.activo;
    formVisible.value = true;
};

const closeForm = () => {
    formVisible.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const saveSupplier = () => {
    const options = { onSuccess: closeForm };
    if (editingId.value) {
        form.put(route('proveedores.update', editingId.value), options);
        return;
    }
    form.post(route('proveedores.store'), options);
};

const deleteSupplier = (proveedor) => {
    if (proveedor.compras_count > 0) {
        return;
    }
    if (confirm(`¿Eliminar al proveedor "${proveedor.nombre}"?`)) {
        router.delete(route('proveedores.destroy', proveedor.id));
    }
};
</script>

<template>
    <Head title="Proveedores - PosVenta" />

    <AuthenticatedLayout>
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-purple-600">
                    <span>Abastecimiento</span><span>/</span><span>Proveedores</span>
                </div>
                <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Proveedores</h1>
                <p class="mt-1 text-sm font-medium text-slate-500">Administra los contactos que abastecen el negocio.</p>
            </div>
            <button
                type="button"
                @click="formVisible ? closeForm() : openCreateForm()"
                class="rounded-xl bg-purple-700 px-5 py-3 text-sm font-bold text-white shadow-md transition-colors hover:bg-purple-800 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
            >
                {{ formVisible ? 'Cerrar formulario' : 'Nuevo proveedor' }}
            </button>
        </div>

        <div v-if="flash.success" role="status" class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" role="alert" class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800">
            {{ flash.error }}
        </div>

        <form v-if="formVisible" @submit.prevent="saveSupplier" class="mb-8 rounded-3xl border border-purple-200 bg-white p-6 shadow-sm">
            <h2 class="mb-5 text-lg font-bold text-slate-900">{{ editingId ? 'Editar proveedor' : 'Registrar proveedor' }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="supplier-name" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Nombre *</label>
                    <input id="supplier-name" v-model="form.nombre" required maxlength="255" class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:border-purple-400 focus:ring-purple-500" />
                    <p v-if="form.errors.nombre" class="mt-1 text-xs text-rose-600">{{ form.errors.nombre }}</p>
                </div>
                <div>
                    <label for="supplier-nit" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">NIT</label>
                    <input id="supplier-nit" v-model="form.nit" maxlength="30" class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:border-purple-400 focus:ring-purple-500" />
                    <p v-if="form.errors.nit" class="mt-1 text-xs text-rose-600">{{ form.errors.nit }}</p>
                </div>
                <div>
                    <label for="supplier-phone" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Teléfono</label>
                    <input id="supplier-phone" v-model="form.telefono" maxlength="30" class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:border-purple-400 focus:ring-purple-500" />
                    <p v-if="form.errors.telefono" class="mt-1 text-xs text-rose-600">{{ form.errors.telefono }}</p>
                </div>
                <div>
                    <label for="supplier-email" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Correo</label>
                    <input id="supplier-email" v-model="form.correo" type="email" maxlength="255" class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:border-purple-400 focus:ring-purple-500" />
                    <p v-if="form.errors.correo" class="mt-1 text-xs text-rose-600">{{ form.errors.correo }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label for="supplier-address" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Dirección</label>
                    <input id="supplier-address" v-model="form.direccion" maxlength="255" class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:border-purple-400 focus:ring-purple-500" />
                    <p v-if="form.errors.direccion" class="mt-1 text-xs text-rose-600">{{ form.errors.direccion }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input v-model="form.activo" type="checkbox" class="rounded border-slate-300 text-purple-600 focus:ring-purple-500" />
                    Proveedor activo
                </label>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="closeForm" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>
                <button type="submit" :disabled="form.processing" class="rounded-xl bg-purple-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-purple-800 disabled:cursor-not-allowed disabled:opacity-60">
                    {{ form.processing ? 'Guardando…' : 'Guardar proveedor' }}
                </button>
            </div>
        </form>

        <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Directorio de proveedores</h2>
                    <p class="text-xs font-medium text-slate-400">{{ proveedores.length }} registrados</p>
                </div>
                <input v-model="search" type="search" aria-label="Buscar proveedores" placeholder="Buscar nombre, NIT o contacto" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm sm:max-w-sm" />
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-4">Proveedor</th>
                            <th class="px-5 py-4">Contacto</th>
                            <th class="px-5 py-4">Compras</th>
                            <th class="px-5 py-4">Estado</th>
                            <th class="px-5 py-4 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <tr v-for="proveedor in filteredSuppliers" :key="proveedor.id">
                            <td class="px-5 py-4">
                                <p class="font-bold text-slate-900">{{ proveedor.nombre }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ proveedor.nit || 'Sin NIT' }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600">
                                <p>{{ proveedor.telefono || 'Sin teléfono' }}</p>
                                <p class="mt-1 text-xs">{{ proveedor.correo || 'Sin correo' }}</p>
                            </td>
                            <td class="px-5 py-4 font-semibold text-slate-700">{{ proveedor.compras_count }}</td>
                            <td class="px-5 py-4">
                                <span :class="proveedor.activo ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-500'" class="rounded-full border px-2.5 py-1 text-xs font-bold">
                                    {{ proveedor.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="inline-flex items-center gap-3">
                                    <button type="button" @click="openEditForm(proveedor)" class="text-xs font-bold text-purple-700 hover:text-purple-900">Editar</button>
                                    <button
                                        type="button"
                                        :disabled="proveedor.compras_count > 0"
                                        :title="proveedor.compras_count > 0 ? 'Tiene compras asociadas; puedes marcarlo como inactivo.' : 'Eliminar proveedor'"
                                        @click="deleteSupplier(proveedor)"
                                        class="text-xs font-bold text-rose-700 hover:text-rose-900 disabled:cursor-not-allowed disabled:opacity-40"
                                    >Eliminar</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="filteredSuppliers.length === 0">
                            <td colspan="5" class="px-5 py-12 text-center text-sm text-slate-400">
                                {{ proveedores.length === 0 ? 'Aún no hay proveedores registrados.' : 'No hay proveedores que coincidan con la búsqueda.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <ComprasPanel
            class="mt-10"
            :compras="compras"
            :proveedores="proveedoresActivos"
            :productos="productos"
            :filters="filters"
            :flash="flash"
        />
    </AuthenticatedLayout>
</template>
