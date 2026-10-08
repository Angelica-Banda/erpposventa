<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    usuarios: {
        type: Array,
        required: true,
    },
    roles: {
        type: Array,
        required: true,
    },
    flash: {
        type: Object,
        default: () => ({}),
    },
});

// Modal state
const isModalOpen = ref(false);
const isEditing = ref(false);
const usuarioEditandoId = ref(null);
const search = ref('');

// Form
const form = useForm({
    name: '',
    email: '',
    password: '',
    roles: [],
});

const openCreateModal = () => {
    isEditing.value = false;
    usuarioEditandoId.value = null;
    form.reset();
    form.clearErrors();
    form.roles = [];
    isModalOpen.value = true;
};

const openEditModal = (usuario) => {
    isEditing.value = true;
    usuarioEditandoId.value = usuario.id;
    form.clearErrors();
    form.name = usuario.name;
    form.email = usuario.email;
    form.password = ''; // Opcional al editar
    form.roles = [...usuario.ids_roles];
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
    form.clearErrors();
};

const alternarRol = (rolId) => {
    const idx = form.roles.indexOf(rolId);
    if (idx > -1) {
        form.roles.splice(idx, 1);
    } else {
        form.roles.push(rolId);
    }
};

const guardarUsuario = () => {
    if (isEditing.value) {
        form.put(route('configuracion.usuarios.update', usuarioEditandoId.value), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('configuracion.usuarios.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const eliminarUsuario = (usuario) => {
    if (confirm(`¿Estás seguro de eliminar al usuario "${usuario.name}"?`)) {
        router.delete(route('configuracion.usuarios.destroy', usuario.id));
    }
};

const obtenerEstiloRol = (nombreRol) => {
    if (nombreRol === 'admin') {
        return 'bg-purple-100 text-purple-700 border-purple-200';
    }
    if (nombreRol === 'vendedor') {
        return 'bg-indigo-100 text-indigo-700 border-indigo-200';
    }
    return 'bg-slate-100 text-slate-700 border-slate-200';
};
</script>

<template>
    <Head title="Roles y Usuarios - PosVenta" />

    <AuthenticatedLayout>
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-purple-600 mb-1">
                    <span>Configuraciones</span>
                    <span>/</span>
                    <span>Seguridad y Accesos</span>
                </div>
                <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">
                    Gestión de Roles y Usuarios
                </h1>
                <p class="mt-1 text-sm text-slate-500 font-medium">
                    Asigna y controla los permisos y roles operativos para cada integrante del equipo
                </p>
            </div>

            <div>
                <button
                    @click="openCreateModal"
                    type="button"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-md shadow-purple-600/20 transition-all hover:scale-[1.02] active:scale-[0.98]"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Nuevo Usuario</span>
                </button>
            </div>
        </div>

        <!-- Flash alerts -->
        <div v-if="$page.props.flash?.success" class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>{{ $page.props.flash.success }}</span>
            </div>
        </div>
        <div v-if="$page.props.flash?.error" class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                <span>{{ $page.props.flash.error }}</span>
            </div>
        </div>

        <!-- Table Container -->
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
            <!-- Table Header Bar -->
            <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Usuarios Registrados</h2>
                    <p class="text-xs text-slate-400 font-medium">Total de {{ usuarios.length }} usuarios en la plataforma</p>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/75 border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <th class="py-4 px-6">Usuario</th>
                            <th class="py-4 px-6">Roles Asignados</th>
                            <th class="py-4 px-6">Fecha Registro</th>
                            <th class="py-4 px-6 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <tr
                            v-for="usuario in usuarios"
                            :key="usuario.id"
                            class="hover:bg-slate-50/60 transition-colors"
                        >
                            <!-- Usuario -->
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-700 font-extrabold flex items-center justify-center shrink-0 text-sm">
                                        {{ usuario.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900 leading-tight">{{ usuario.name }}</div>
                                        <div class="text-xs text-slate-400 font-medium mt-0.5">{{ usuario.email }}</div>
                                    </div>
                                </div>
                            </td>

                            <!-- Roles -->
                            <td class="py-4 px-6">
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-for="rol in usuario.roles"
                                        :key="rol.id"
                                        :class="obtenerEstiloRol(rol.nombre)"
                                        class="px-2.5 py-1 text-xs font-bold rounded-lg border inline-flex items-center gap-1"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :class="rol.nombre === 'admin' ? 'bg-purple-600' : 'bg-indigo-600'"></span>
                                        {{ rol.nombre_visible }}
                                    </span>
                                    <span v-if="usuario.roles.length === 0" class="text-xs text-slate-400 italic">
                                        Sin rol asignado
                                    </span>
                                </div>
                            </td>

                            <!-- Fecha -->
                            <td class="py-4 px-6 text-xs text-slate-500 font-medium">
                                {{ usuario.created_at || 'Reciente' }}
                            </td>

                            <!-- Acciones -->
                            <td class="py-4 px-6 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        @click="openEditModal(usuario)"
                                        type="button"
                                        class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-purple-50 hover:text-purple-700 text-slate-700 text-xs font-semibold transition-colors"
                                    >
                                        Editar Roles
                                    </button>
                                    <button
                                        @click="eliminarUsuario(usuario)"
                                        type="button"
                                        class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-500 text-xs font-semibold transition-colors"
                                    >
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL CREAR / EDITAR USUARIO -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
        >
            <div class="w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden transform transition-all animate-in fade-in duration-200">
                <!-- Modal Header -->
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-xl font-extrabold text-slate-900">
                            {{ isEditing ? 'Editar Usuario y Roles' : 'Crear Nuevo Usuario' }}
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ isEditing ? 'Actualiza los datos y permisos asignados' : 'Completa la información para dar de alta al usuario' }}
                        </p>
                    </div>
                    <button
                        @click="closeModal"
                        class="text-slate-400 hover:text-slate-600 p-2 rounded-xl transition-colors"
                    >
                        ✕
                    </button>
                </div>

                <!-- Form -->
                <form @submit.prevent="guardarUsuario" class="p-6 space-y-4">
                    <!-- Nombre -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                            Nombre Completo
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Ej. Juan Pérez"
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                        />
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                            Correo Electrónico
                        </label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            placeholder="usuario@posventa.com"
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                        />
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>

                    <!-- Contraseña -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                            Contraseña {{ isEditing ? '(Opcional si no deseas cambiarla)' : '' }}
                        </label>
                        <input
                            v-model="form.password"
                            type="password"
                            :required="!isEditing"
                            placeholder="••••••••"
                            class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                        />
                        <InputError class="mt-1" :message="form.errors.password" />
                    </div>

                    <!-- Selección de Roles -->
                    <div class="pt-2">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                            Seleccionar Roles Disponibles
                        </label>
                        <div class="space-y-2.5">
                            <div
                                v-for="rol in roles"
                                :key="rol.id"
                                @click="alternarRol(rol.id)"
                                :class="[
                                    form.roles.includes(rol.id)
                                        ? 'border-purple-600 bg-purple-50/70 ring-1 ring-purple-600'
                                        : 'border-slate-200 bg-slate-50 hover:bg-slate-100/70',
                                    'p-3.5 rounded-2xl border cursor-pointer transition-all flex items-start gap-3 select-none'
                                ]"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.roles.includes(rol.id)"
                                    @change.stop="alternarRol(rol.id)"
                                    class="mt-0.5 rounded text-purple-600 focus:ring-purple-500 border-slate-300"
                                />
                                <div>
                                    <div class="text-xs font-bold text-slate-900 flex items-center gap-2">
                                        <span>{{ rol.nombre_visible }}</span>
                                        <span class="text-[10px] font-semibold px-2 py-0.2 rounded-md bg-white border border-slate-200 text-slate-500">
                                            {{ rol.nombre }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        {{ rol.descripcion || 'Sin descripción' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <InputError class="mt-1" :message="form.errors.roles" />
                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs font-bold transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-md shadow-purple-600/20 disabled:opacity-50 transition-all"
                        >
                            {{ form.processing ? 'Guardando...' : (isEditing ? 'Guardar Cambios' : 'Crear Usuario') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
