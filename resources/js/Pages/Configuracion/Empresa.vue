<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import municipiosColombia from '@/data/colombia-municipios.json';

const props = defineProps({
    empresa: {
        type: Object,
        required: true,
    },
    flash: {
        type: Object,
        default: () => ({}),
    },
});

const activeTab = ref('comercial'); // 'comercial' | 'tributaria' | 'logo'
const logoPreview = ref(props.empresa.logo_url || null);
const logoInput = ref(null);

// Formulario reactivo
const form = useForm({
    _method: 'POST', // Manejo seguro de multipart en Laravel
    razon_social: props.empresa.razon_social || '',
    nombre_comercial: props.empresa.nombre_comercial || '',
    nit: props.empresa.nit || '',
    dv: props.empresa.dv || '',
    direccion: props.empresa.direccion || '',
    pais: props.empresa.pais || 'Colombia',
    departamento: props.empresa.departamento || '',
    municipio: props.empresa.municipio || '',
    codigo_postal: props.empresa.codigo_postal || '',
    telefono: props.empresa.telefono || '',
    email: props.empresa.email || '',
    sitio_web: props.empresa.sitio_web || '',

    tipo_persona: props.empresa.tipo_persona || 'juridica',
    regimen_tributario: props.empresa.regimen_tributario || 'Responsable de IVA',
    responsabilidades_fiscales: props.empresa.responsabilidades_fiscales || ['R-99-PN'],
    ciiu: props.empresa.ciiu || '',
    detalles_tributarios: props.empresa.detalles_tributarios || '',

    logo: null,
    eliminar_logo: false,
});

// Opciones de responsabilidades fiscales comunes en Colombia (DIAN)
const responsabilidadesDisponibles = [
    { codigo: 'O-13', nombre: 'Gran Contribuyente' },
    { codigo: 'O-15', nombre: 'Autorretenedor' },
    { codigo: 'O-23', nombre: 'Agente de retención IVA' },
    { codigo: 'O-47', nombre: 'Régimen Simple de Tributación (RST)' },
    { codigo: 'R-99-PN', nombre: 'No responsable de IVA' },
];

// Opciones de regímenes tributarios en Colombia
const regimenesTributarios = [
    'Responsable de IVA',
    'No responsable de IVA',
    'Régimen Simple de Tributación (RST)',
    'Especial (ESAL / Sin ánimo de lucro)',
];

const departamentosColombia = Object.keys(municipiosColombia).sort((a, b) => a.localeCompare(b, 'es'));
const municipiosDisponibles = computed(() => {
    const municipios = municipiosColombia[form.departamento] || [];

    if (form.municipio && !municipios.includes(form.municipio)) {
        return [form.municipio, ...municipios].sort((a, b) => a.localeCompare(b, 'es'));
    }

    return municipios;
});

// Cálculo automático de dígito de verificación (Algoritmo Módulo 11 DIAN Colombia)
const calcularDV = () => {
    const nitLimpio = form.nit.replace(/\D/g, '');
    if (!nitLimpio) {
        form.dv = '';
        return;
    }

    const factores = [71, 67, 59, 53, 47, 43, 41, 37, 29, 23, 19, 17, 13, 7, 3];
    let suma = 0;
    const nitLongitud = nitLimpio.length;

    for (let i = 0; i < nitLongitud; i++) {
        const digito = parseInt(nitLimpio.charAt(nitLongitud - 1 - i), 10);
        suma += digito * factores[i];
    }

    const residuo = suma % 11;
    if (residuo === 0 || residuo === 1) {
        form.dv = residuo.toString();
    } else {
        form.dv = (11 - residuo).toString();
    }
};

// Toggle de responsabilidades fiscales
const toggleResponsabilidad = (codigo) => {
    const index = form.responsabilidades_fiscales.indexOf(codigo);
    if (index > -1) {
        form.responsabilidades_fiscales.splice(index, 1);
    } else {
        form.responsabilidades_fiscales.push(codigo);
    }
};

// Manejo del archivo de logo
const handleLogoChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    form.logo = file;
    form.eliminar_logo = false;

    // Crear vista previa local
    const reader = new FileReader();
    reader.onload = (event) => {
        logoPreview.value = event.target.result;
    };
    reader.readAsDataURL(file);
};

const triggerLogoInput = () => {
    if (logoInput.value) {
        logoInput.value.click();
    }
};

const quitarLogo = () => {
    form.logo = null;
    form.eliminar_logo = true;
    logoPreview.value = null;
    if (logoInput.value) {
        logoInput.value.value = '';
    }
};

// Enviar formulario
const submit = () => {
    form.post(route('configuracion.empresa.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.logo = null;
            form.eliminar_logo = false;
        },
    });
};
</script>

<template>
    <Head title="Configuración de Empresa - PosVenta" />

    <AuthenticatedLayout>
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-purple-600 mb-1">
                    <span>Configuraciones</span>
                    <span>/</span>
                    <span>Identidad Corporativa</span>
                </div>
                <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">
                    Información de la Empresa
                </h1>
                <p class="mt-1 text-sm text-slate-500 font-medium">
                    Administra los datos comerciales, fiscales de Colombia y el logotipo institucional de tu negocio
                </p>
            </div>

            <!-- Botón guardar principal -->
            <div>
                <button
                    @click="submit"
                    :disabled="form.processing"
                    type="button"
                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 active:bg-purple-800 text-white text-xs font-bold shadow-md shadow-purple-600/20 transition-all hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50"
                >
                    <svg v-if="form.processing" class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ form.processing ? 'Guardando...' : 'Guardar Información' }}</span>
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

        <!-- Tabs de navegación -->
        <div class="flex items-center gap-2 border-b border-slate-200 mb-8 overflow-x-auto">
            <button
                @click="activeTab = 'comercial'"
                type="button"
                :class="[
                    activeTab === 'comercial'
                        ? 'border-purple-600 text-purple-700 font-bold bg-white'
                        : 'border-transparent text-slate-500 hover:text-slate-800 font-medium',
                    'px-5 py-3 border-b-2 text-sm transition-all flex items-center gap-2 rounded-t-xl shrink-0'
                ]"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <span>Datos Básicos y Comerciales</span>
            </button>

            <button
                @click="activeTab = 'tributaria'"
                type="button"
                :class="[
                    activeTab === 'tributaria'
                        ? 'border-purple-600 text-purple-700 font-bold bg-white'
                        : 'border-transparent text-slate-500 hover:text-slate-800 font-medium',
                    'px-5 py-3 border-b-2 text-sm transition-all flex items-center gap-2 rounded-t-xl shrink-0'
                ]"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>Información Tributaria (DIAN)</span>
            </button>

            <button
                @click="activeTab = 'logo'"
                type="button"
                :class="[
                    activeTab === 'logo'
                        ? 'border-purple-600 text-purple-700 font-bold bg-white'
                        : 'border-transparent text-slate-500 hover:text-slate-800 font-medium',
                    'px-5 py-3 border-b-2 text-sm transition-all flex items-center gap-2 rounded-t-xl shrink-0'
                ]"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>Logo e Identidad Visual</span>
            </button>
        </div>

        <form @submit.prevent="submit">
            <!-- TAB 1: DATOS BÁSICOS Y COMERCIALES -->
            <div v-show="activeTab === 'comercial'" class="space-y-6">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-900 mb-1">Identificación del Establecimiento</h2>
                    <p class="text-xs text-slate-400 mb-6 font-medium">Información oficial utilizada en facturas, cotizaciones y membretes</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <!-- Nombre Comercial -->
                        <div class="lg:col-span-1">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Nombre Comercial <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="form.nombre_comercial"
                                type="text"
                                required
                                placeholder="Ej: PosVenta Express"
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                            />
                            <p class="text-[11px] text-slate-400 mt-1">Se muestra en el Sidebar y encabezados.</p>
                            <InputError class="mt-1" :message="form.errors.nombre_comercial" />
                        </div>

                        <!-- Razón Social -->
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Razón Social Completa <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="form.razon_social"
                                type="text"
                                required
                                placeholder="Ej: PosVenta Soluciones Integrales S.A.S."
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                            />
                            <p class="text-[11px] text-slate-400 mt-1">Nombre legal registrado en Cámara de Comercio.</p>
                            <InputError class="mt-1" :message="form.errors.razon_social" />
                        </div>

                        <!-- NIT & DV -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                NIT <span class="text-rose-500">*</span>
                            </label>
                            <div class="flex items-center gap-2">
                                <input
                                    v-model="form.nit"
                                    @input="calcularDV"
                                    type="text"
                                    required
                                    placeholder="901234567"
                                    class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                                />
                                <span class="text-slate-400 font-bold">-</span>
                                <input
                                    v-model="form.dv"
                                    type="text"
                                    maxlength="1"
                                    placeholder="DV"
                                    class="w-16 px-3 py-2.5 text-center bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                                    title="Dígito de verificación"
                                />
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">Sin puntos ni guiones (DV autocalculado).</p>
                            <InputError class="mt-1" :message="form.errors.nit || form.errors.dv" />
                        </div>

                        <!-- Teléfono -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Teléfono de Contacto <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="form.telefono"
                                type="text"
                                required
                                placeholder="Ej: (604) 444-0000 / 300 123 4567"
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                            />
                            <InputError class="mt-1" :message="form.errors.telefono" />
                        </div>

                        <!-- Correo Electrónico -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Correo Electrónico <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="form.email"
                                type="email"
                                required
                                placeholder="contacto@empresa.com"
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                            />
                            <InputError class="mt-1" :message="form.errors.email" />
                        </div>

                        <!-- Sitio Web -->
                        <div class="lg:col-span-3">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Sitio Web Corporativo (Opcional)
                            </label>
                            <input
                                v-model="form.sitio_web"
                                type="url"
                                placeholder="https://www.miempresa.com"
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                            />
                            <InputError class="mt-1" :message="form.errors.sitio_web" />
                        </div>
                    </div>
                </div>

                <!-- Ubicación Geográfica -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-900 mb-1">Ubicación y Domicilio Fiscal</h2>
                    <p class="text-xs text-slate-400 mb-6 font-medium">Dirección física donde opera el negocio</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
                        <!-- Dirección -->
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Dirección Física <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="form.direccion"
                                type="text"
                                required
                                placeholder="Ej: Carrera 43A # 1-50 Edificio Empresarial Piso 5"
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                            />
                            <InputError class="mt-1" :message="form.errors.direccion" />
                        </div>

                        <!-- País -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                País <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="form.pais"
                                type="text"
                                required
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                            />
                            <InputError class="mt-1" :message="form.errors.pais" />
                        </div>

                        <!-- Departamento -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Departamento <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="form.departamento"
                                required
                                @change="form.municipio = ''"
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                            >
                                <option value="" disabled>Selecciona un departamento</option>
                                <option v-for="dep in departamentosColombia" :key="dep" :value="dep">{{ dep }}</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.departamento" />
                        </div>

                        <!-- Municipio / Ciudad -->
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Municipio / Ciudad <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="form.municipio"
                                required
                                :disabled="!form.departamento"
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                            >
                                <option value="" disabled>
                                    {{ form.departamento ? 'Selecciona un municipio' : 'Primero selecciona un departamento' }}
                                </option>
                                <option v-for="municipio in municipiosDisponibles" :key="municipio" :value="municipio">
                                    {{ municipio }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.municipio" />
                        </div>

                        <!-- Código Postal -->
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Código Postal (Opcional)
                            </label>
                            <input
                                v-model="form.codigo_postal"
                                type="text"
                                placeholder="050001"
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                            />
                            <InputError class="mt-1" :message="form.errors.codigo_postal" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: INFORMACIÓN TRIBUTARIA (DIAN) -->
            <div v-show="activeTab === 'tributaria'" class="space-y-6">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-900 mb-1">Régimen y Clasificación Tributaria</h2>
                    <p class="text-xs text-slate-400 mb-6 font-medium">Definición de responsabilidades fiscales aplicables según la normativa DIAN en Colombia</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Tipo de Persona -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                                Tipo de Persona <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <label
                                    :class="[
                                        form.tipo_persona === 'juridica'
                                            ? 'border-purple-600 bg-purple-50/70 text-purple-900 font-bold ring-1 ring-purple-600'
                                            : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100',
                                        'p-4 rounded-2xl border cursor-pointer transition-all flex items-center gap-3 select-none'
                                    ]"
                                >
                                    <input
                                        type="radio"
                                        value="juridica"
                                        v-model="form.tipo_persona"
                                        class="text-purple-600 focus:ring-purple-500"
                                    />
                                    <div>
                                        <div class="text-sm">Persona Jurídica</div>
                                        <div class="text-[11px] text-slate-400 font-normal">Empresas, sociedades, SAS</div>
                                    </div>
                                </label>

                                <label
                                    :class="[
                                        form.tipo_persona === 'natural'
                                            ? 'border-purple-600 bg-purple-50/70 text-purple-900 font-bold ring-1 ring-purple-600'
                                            : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100',
                                        'p-4 rounded-2xl border cursor-pointer transition-all flex items-center gap-3 select-none'
                                    ]"
                                >
                                    <input
                                        type="radio"
                                        value="natural"
                                        v-model="form.tipo_persona"
                                        class="text-purple-600 focus:ring-purple-500"
                                    />
                                    <div>
                                        <div class="text-sm">Persona Natural</div>
                                        <div class="text-[11px] text-slate-400 font-normal">Comerciante individual</div>
                                    </div>
                                </label>
                            </div>
                            <InputError class="mt-1" :message="form.errors.tipo_persona" />
                        </div>

                        <!-- Régimen Tributario -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">
                                Régimen Tributario <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="form.regimen_tributario"
                                required
                                class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                            >
                                <option v-for="reg in regimenesTributarios" :key="reg" :value="reg">
                                    {{ reg }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.regimen_tributario" />
                        </div>

                        <!-- Código CIIU -->
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1.5">
                                Actividad Económica Principal (Código CIIU)
                            </label>
                            <div class="flex items-center gap-3 max-w-md">
                                <input
                                    v-model="form.ciiu"
                                    type="text"
                                    maxlength="10"
                                    placeholder="Ej: 4711"
                                    class="w-40 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-bold text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                                />
                                <span class="text-xs text-slate-500">
                                    Código de 4 dígitos de la Clasificación Industrial Internacional Uniforme (RUT DIAN).
                                </span>
                            </div>
                            <InputError class="mt-1" :message="form.errors.ciiu" />
                        </div>
                    </div>
                </div>

                <!-- Responsabilidades Fiscales -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-900 mb-1">Responsabilidades Fiscales (RUT)</h2>
                    <p class="text-xs text-slate-400 mb-6 font-medium">Marca todas las casillas que apliquen a tu empresa en la casilla 53 del RUT</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                        <div
                            v-for="resp in responsabilidadesDisponibles"
                            :key="resp.codigo"
                            @click="toggleResponsabilidad(resp.codigo)"
                            :class="[
                                form.responsabilidades_fiscales.includes(resp.codigo)
                                    ? 'border-purple-600 bg-purple-50/70 ring-1 ring-purple-600'
                                    : 'border-slate-200 bg-slate-50 hover:bg-slate-100',
                                'p-4 rounded-2xl border cursor-pointer transition-all flex items-center justify-between select-none'
                            ]"
                        >
                            <div class="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    :checked="form.responsabilidades_fiscales.includes(resp.codigo)"
                                    @change.stop="toggleResponsabilidad(resp.codigo)"
                                    class="rounded text-purple-600 focus:ring-purple-500 border-slate-300"
                                />
                                <div>
                                    <div class="text-xs font-bold text-slate-900">{{ resp.nombre }}</div>
                                    <div class="text-[10px] text-slate-400 font-semibold uppercase">{{ resp.codigo }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Información Adicional y Resoluciones -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-900 mb-1">Información Adicional o Resoluciones DIAN</h2>
                    <p class="text-xs text-slate-400 mb-4 font-medium">Texto que se imprimirá al pie de las tirillas y facturas de venta</p>

                    <textarea
                        v-model="form.detalles_tributarios"
                        rows="3"
                        placeholder="Ej: Resolución DIAN No. 18764000001 de 2026-01-15 del 0001 al 50000. Somos retenedores de ICA en la ciudad de Medellín."
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-600"
                    ></textarea>
                    <InputError class="mt-1" :message="form.errors.detalles_tributarios" />
                </div>
            </div>

            <!-- TAB 3: GESTIÓN DEL LOGO E IDENTIDAD VISUAL -->
            <div v-show="activeTab === 'logo'" class="space-y-6">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-900 mb-1">Logotipo Corporativo</h2>
                    <p class="text-xs text-slate-400 mb-6 font-medium">Este logo se reflejará dinámicamente en la pantalla de Inicio de Sesión y en el Menú Lateral</p>

                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                        <!-- Preview Card -->
                        <div class="lg:col-span-5 flex flex-col items-center p-8 rounded-3xl bg-slate-50 border border-slate-200/80 text-center">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Vista Previa Actual</span>

                            <div class="w-44 h-44 rounded-2xl bg-white border border-slate-200 shadow-inner flex items-center justify-center p-4 overflow-hidden mb-6 group relative">
                                <img
                                    v-if="logoPreview"
                                    :src="logoPreview"
                                    :alt="form.nombre_comercial"
                                    class="max-w-full max-h-full object-contain transition-transform group-hover:scale-105 duration-200"
                                />
                                <div v-else class="flex flex-col items-center justify-center text-slate-300">
                                    <svg class="w-16 h-16 stroke-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-xs mt-2 font-medium text-slate-400">Sin logotipo</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <button
                                    @click="triggerLogoInput"
                                    type="button"
                                    class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-md shadow-purple-600/20 transition-all"
                                >
                                    {{ logoPreview ? 'Cambiar Logo' : 'Cargar Logo' }}
                                </button>
                                <button
                                    v-if="logoPreview"
                                    @click="quitarLogo"
                                    type="button"
                                    class="px-3.5 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold transition-colors"
                                >
                                    Quitar Logo
                                </button>
                            </div>

                            <input
                                ref="logoInput"
                                type="file"
                                accept="image/png, image/jpeg, image/jpg, image/webp"
                                @change="handleLogoChange"
                                class="hidden"
                            />
                            <InputError class="mt-2" :message="form.errors.logo" />
                        </div>

                        <!-- Instrucciones y previsualización en vivo en componentes -->
                        <div class="lg:col-span-7 space-y-6">
                            <div class="p-5 rounded-2xl bg-purple-50/60 border border-purple-100">
                                <h3 class="text-xs font-bold text-purple-900 uppercase tracking-wider mb-1.5">Recomendaciones del archivo</h3>
                                <ul class="text-xs text-purple-800/80 space-y-1 list-disc list-inside font-medium">
                                    <li>Formatos soportados: <strong>PNG (con fondo transparente recomendado)</strong>, JPG, WEBP.</li>
                                    <li>Tamaño máximo permitido: <strong>2 Megabytes (MB)</strong>.</li>
                                    <li>Resolución recomendada: <strong>512x512 píxeles</strong> o superior con proporción cuadrada o rectangular estándar.</li>
                                </ul>
                            </div>

                            <!-- Simulación de apariencia en Menú Lateral -->
                            <div class="p-6 rounded-2xl bg-gradient-to-b from-[#064E3B] via-[#043c2e] to-[#022C22] text-white">
                                <span class="text-[10px] font-bold text-[#D6B46A] uppercase tracking-wider block mb-3">
                                    Simulación: Así lucirá en tu Menú Lateral
                                </span>
                                <div class="flex items-center gap-3.5 p-3 rounded-xl bg-white/10 border border-white/10 max-w-sm">
                                    <div class="h-10 w-10 rounded-xl bg-white/10 flex items-center justify-center p-1 overflow-hidden shrink-0 border border-white/15">
                                        <img
                                            v-if="logoPreview"
                                            :src="logoPreview"
                                            class="max-w-full max-h-full object-contain"
                                        />
                                        <span v-else class="font-extrabold text-[#D6B46A]">PV</span>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-black text-white text-base truncate leading-tight">
                                            {{ form.nombre_comercial || 'PosVenta ERP' }}
                                        </div>
                                        <div class="text-[10px] font-semibold text-[#D6B46A] truncate">
                                            {{ form.razon_social || 'Sistema de Gestión' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botón inferior para guardar -->
            <div class="mt-8 pt-6 border-t border-slate-200 flex items-center justify-end gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 px-8 py-3 rounded-xl bg-purple-600 hover:bg-purple-700 active:bg-purple-800 text-white text-sm font-bold shadow-md shadow-purple-600/20 transition-all hover:scale-[1.01] active:scale-[0.99] disabled:opacity-50"
                >
                    <svg v-if="form.processing" class="animate-spin h-5 w-5 text-white" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>{{ form.processing ? 'Guardando cambios...' : 'Guardar Información de Empresa' }}</span>
                </button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
