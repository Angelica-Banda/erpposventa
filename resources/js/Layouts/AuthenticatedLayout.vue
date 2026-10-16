<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const showingMobileMenu = ref(false);
const activeTab = ref('dashboard');
const configDropdownOpen = ref(route().current('configuracion.*') || false);

const rolesUsuario = computed(() => page.props.auth?.usuario?.roles || []);
const esAdministrador = computed(() => rolesUsuario.value.includes('admin'));
const empresa = computed(() => page.props.empresa || {});

const allMenuItems = [
    { id: 'dashboard', name: 'Dashboard', route: 'dashboard', roles: ['admin'], icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { id: 'ventas', name: 'Ventas', route: 'ventas.index', roles: ['admin', 'vendedor'], icon: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z' },
    { id: 'inventario', name: 'Inventario', route: 'inventario.index', roles: ['admin', 'vendedor'], icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
    { id: 'domicilios', name: 'Domicilios', route: 'domicilios.index', roles: ['admin', 'vendedor'], icon: 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0 2 2 0 00-4 0zm10 0a2 2 0 104 0 2 2 0 00-4 0z' },
    { id: 'proveedores', name: 'Proveedores', route: 'proveedores.index', roles: ['admin'], icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' },
    { id: 'caja', name: 'Caja', route: 'caja.index', roles: ['admin', 'vendedor'], icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
    { id: 'reportes', name: 'Reportes', route: 'reportes.index', roles: ['admin'], icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
];

const menuItems = computed(() => {
    return allMenuItems.filter(item => {
        if (!item.roles || item.roles.length === 0) return true;
        return item.roles.some(r => rolesUsuario.value.includes(r));
    });
});
</script>

<template>
    <div class="min-h-screen bg-[#F8F7F2] flex flex-col lg:flex-row font-sans">
        <!-- MOBILE HEADER -->
        <div class="lg:hidden bg-[#022C22] text-white flex items-center justify-between px-5 py-4 shadow-md sticky top-0 z-50">
            <div class="flex items-center gap-3 min-w-0">
                <div v-if="empresa.logo_url" class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 p-1 border border-white/20 shrink-0">
                    <img :src="empresa.logo_url" :alt="empresa.nombre_comercial || 'Logo'" class="max-h-full max-w-full object-contain" />
                </div>
                <div v-else class="flex h-9 w-9 items-center justify-center rounded-xl border border-[#D6B46A]/30 bg-[#064E3B] text-[#D6B46A] shrink-0 font-bold text-sm">
                    {{ (empresa.nombre_comercial || 'PV').substring(0, 2).toUpperCase() }}
                </div>
                <div class="min-w-0">
                    <h1 class="font-extrabold text-lg leading-none truncate max-w-[190px]">{{ empresa.nombre_comercial || 'PosVenta' }}</h1>
                    <span class="text-[11px] text-[#D6B46A] truncate block max-w-[190px]">{{ empresa.razon_social || 'Sistema de Gestión' }}</span>
                </div>
            </div>
            <button
                @click="showingMobileMenu = !showingMobileMenu"
                class="p-2 rounded-lg bg-white/10 text-[#e4d4ad] hover:bg-white/15 hover:text-white focus:outline-none"
            >
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path v-if="!showingMobileMenu" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- SIDEBAR (DESKTOP + MOBILE DRAWER) -->
        <aside
            :class="[
                showingMobileMenu ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                'fixed lg:sticky top-0 left-0 z-40 h-screen w-64 bg-gradient-to-b from-[#064E3B] via-[#043c2e] to-[#022C22] text-white flex flex-col justify-between transition-transform duration-300 ease-in-out shrink-0 shadow-2xl lg:shadow-none'
            ]"
        >
            <!-- TOP BRAND HEADER -->
            <div>
                <div class="p-6 border-b border-purple-400/20">
                    <Link :href="esAdministrador ? route('dashboard') : route('ventas.index')" class="flex items-center gap-3 group">
                        <div v-if="empresa.logo_url" class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/10 p-1.5 border border-white/20 shadow-lg backdrop-blur-md shrink-0">
                            <img :src="empresa.logo_url" :alt="empresa.nombre_comercial || 'Logo'" class="max-h-full max-w-full object-contain" />
                        </div>
                        <div v-else class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#D6B46A]/30 bg-white/10 text-[#D6B46A] shadow-lg backdrop-blur-md shrink-0 font-extrabold text-base">
                            {{ (empresa.nombre_comercial || 'PV').substring(0, 2).toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-xl font-black tracking-tight text-white leading-tight truncate">{{ empresa.nombre_comercial || 'PosVenta' }}</h2>
                            <p class="text-[11px] font-semibold text-[#e4d4ad] tracking-wide truncate">{{ empresa.razon_social || 'Sistema de Gestión' }}</p>
                        </div>
                    </Link>
                </div>

                <!-- NAVIGATION MENU -->
                <nav class="p-4 space-y-1.5 overflow-y-auto max-h-[calc(100vh-220px)]">
                    <template v-for="item in menuItems" :key="item.id">
                        <!-- Direct Inertia Link (Dashboard / Ventas) -->
                        <Link
                            v-if="item.route"
                            :href="route(item.route)"
                            @click="showingMobileMenu = false"
                            :class="[
                                route().current(item.route)
                                    ? 'bg-purple-500/50 text-white font-bold shadow-md shadow-purple-950/30 border border-purple-300/25'
                                    : 'text-purple-200 hover:bg-white/10 hover:text-white',
                                'flex items-center gap-3.5 px-4 py-3 rounded-2xl text-sm transition-all duration-200'
                            ]"
                        >
                            <svg class="w-5 h-5 shrink-0 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                            </svg>
                            <span>{{ item.name }}</span>
                        </Link>

                        <!-- Other Menu Items (Mocked Navigation / Tab select) -->
                        <button
                            v-else
                            type="button"
                            @click="activeTab = item.id; showingMobileMenu = false"
                            :class="[
                                activeTab === item.id
                                    ? 'bg-purple-500/50 text-white font-bold shadow-md shadow-purple-950/30 border border-purple-300/25'
                                    : 'text-purple-200 hover:bg-white/10 hover:text-white',
                                'w-full flex items-center gap-3.5 px-4 py-3 rounded-2xl text-sm transition-all duration-200 text-left'
                            ]"
                        >
                            <svg class="w-5 h-5 shrink-0 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                            </svg>
                            <span>{{ item.name }}</span>
                        </button>
                    </template>

                    <!-- DROPDOWN CONFIGURACIONES (SOLO ADMINISTRADOR) -->
                    <div v-if="esAdministrador" class="pt-2">
                        <button
                            type="button"
                            @click="configDropdownOpen = !configDropdownOpen"
                            :class="[
                                route().current('configuracion.*') || configDropdownOpen
                                    ? 'bg-white/15 text-white font-semibold'
                                    : 'text-purple-200 hover:bg-white/10 hover:text-white',
                                'w-full flex items-center justify-between px-4 py-3 rounded-2xl text-sm transition-all duration-200'
                            ]"
                        >
                            <div class="flex items-center gap-3.5">
                                <svg class="w-5 h-5 shrink-0 opacity-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span>Configuraciones</span>
                            </div>
                            <svg
                                :class="[
                                    configDropdownOpen ? 'rotate-180' : 'rotate-0',
                                    'w-4 h-4 transition-transform duration-200 opacity-75'
                                ]"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="2.5"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Sub-items del dropdown -->
                        <div
                            v-if="configDropdownOpen"
                            class="mt-1 ml-4 pl-4 border-l border-purple-400/30 space-y-1 py-1"
                        >
                            <Link
                                :href="route('configuracion.empresa.edit')"
                                @click="showingMobileMenu = false"
                                :class="[
                                    route().current('configuracion.empresa.*')
                                        ? 'bg-purple-500/50 text-white font-bold shadow-xs border border-purple-300/30'
                                        : 'text-purple-200 hover:text-white hover:bg-white/10',
                                    'flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs transition-all duration-150'
                                ]"
                            >
                                <svg class="w-4 h-4 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <span>Datos de Empresa</span>
                            </Link>

                            <Link
                                :href="route('configuracion.roles.index')"
                                @click="showingMobileMenu = false"
                                :class="[
                                    route().current('configuracion.roles.index')
                                        ? 'bg-purple-500/50 text-white font-bold shadow-xs border border-purple-300/30'
                                        : 'text-purple-200 hover:text-white hover:bg-white/10',
                                    'flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs transition-all duration-150'
                                ]"
                            >
                                <svg class="w-4 h-4 shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                                <span>Roles y Usuarios</span>
                            </Link>
                        </div>
                    </div>
                </nav>
            </div>

            <!-- USER INFO & LOGOUT FOOTER -->
            <div class="p-4 border-t border-purple-400/20 bg-purple-950/40">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-full bg-purple-400/30 border border-purple-300/40 flex items-center justify-center font-bold text-white text-sm shrink-0">
                            {{ $page.props.auth.usuario?.name ? $page.props.auth.usuario.name.charAt(0).toUpperCase() : 'U' }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-white truncate leading-tight">{{ $page.props.auth.usuario?.name }}</p>
                            <p class="text-[11px] text-purple-300 truncate capitalize">
                                Rol: {{ rolesUsuario.length ? rolesUsuario.join(', ') : 'Usuario' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-1 text-xs">
                    <Link
                        :href="route('profile.edit')"
                        class="flex-1 py-1.5 px-2 rounded-lg bg-white/10 hover:bg-white/20 text-purple-100 text-center font-medium transition-colors"
                    >
                        Perfil
                    </Link>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="py-1.5 px-3 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 text-rose-200 text-center font-medium transition-colors"
                    >
                        Salir
                    </Link>
                </div>
            </div>
        </aside>

        <!-- BACKDROP FOR MOBILE MENU -->
        <div
            v-if="showingMobileMenu"
            @click="showingMobileMenu = false"
            class="fixed inset-0 bg-black/60 z-30 lg:hidden backdrop-blur-sm"
        ></div>

        <!-- MAIN CONTENT AREA -->
        <div class="flex-1 min-w-0 flex flex-col">
            <!-- Header slot (optional) -->
            <div v-if="$slots.header" class="bg-white border-b border-slate-200/80 px-6 sm:px-8 py-5">
                <slot name="header" />
            </div>

            <!-- Page Body Slot -->
            <main class="flex-1 p-5 sm:p-8 lg:p-10 max-w-7xl w-full mx-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
