<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Inicio de sesión - Licores TITO" />

    <main class="login-shell min-h-screen p-3 sm:p-6 lg:p-8">
        <div class="login-card mx-auto grid min-h-[710px] w-full max-w-[1440px] overflow-hidden rounded-[2rem] border border-[#e8e5dc] bg-white shadow-[0_30px_90px_-42px_rgba(2,44,34,0.32)] lg:min-h-[80vh] lg:grid-cols-[0.86fr_1fr]">
            <section class="flex flex-col justify-between bg-[#F8F7F2] px-6 py-8 sm:px-10 sm:py-10 lg:px-14 lg:py-12">
                <div>
                    <Link href="/" class="group inline-flex items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-[#064E3B] focus-visible:ring-offset-4">
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#064E3B] text-[#D6B46A] shadow-lg shadow-[#064E3B]/15">
                            <svg class="h-9 w-9" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                                <path d="M10 8h8v5l2 3v16H8V16l2-3V8Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                                <path d="M10.5 21h9M12 12h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                                <path d="M25 21h8v10a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2V21Zm0 2h-2a2 2 0 0 0 0 4h2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M27 28h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                            </svg>
                        </span>
                        <span>
                            <span class="block text-[1.45rem] font-extrabold leading-tight tracking-[-0.045em] text-[#022C22]">Licores <span class="text-[#064E3B]">TITO</span></span>
                            <span class="mt-0.5 block text-sm font-medium leading-tight text-[#68736e]">Sistema de Gestión</span>
                        </span>
                    </Link>

                    <div class="mt-12 sm:mt-16">
                        <h1 class="text-3xl font-extrabold tracking-[-0.045em] text-[#022C22] sm:text-[2.5rem]">Inicio de sesión</h1>
                        <p class="mt-3 max-w-sm text-sm leading-6 text-[#73766e]">
                            Acceso seguro para administradores y vendedores
                        </p>
                    </div>

                    <div v-if="status" role="status" class="mt-7 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" />
                        </svg>
                        <span>{{ status }}</span>
                    </div>

                    <form @submit.prevent="submit" class="mt-8 space-y-5 sm:mt-9">
                        <div>
                            <label for="email" class="mb-2 block text-sm font-semibold text-[#27362f]">Correo electrónico</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-[#8b9188]">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5v10.5H3.75zM4.5 7.5l7.5 6 7.5-6" />
                                    </svg>
                                </span>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="nombre@licorestito.com"
                                    class="login-input block w-full rounded-xl border border-[#deded5] bg-white py-3.5 pl-12 pr-4 text-sm font-medium text-[#17251e] placeholder:text-[#a4a69f]"
                                />
                            </div>
                            <InputError class="mt-1.5" :message="form.errors.email" />
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <label for="password" class="block text-sm font-semibold text-[#27362f]">Contraseña</label>
                                <Link
                                    v-if="canResetPassword"
                                    :href="route('password.request')"
                                    class="rounded-md text-xs font-semibold text-[#064E3B] transition-colors hover:text-[#987a3d] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#064E3B]"
                                >
                                    ¿Olvidaste tu contraseña?
                                </Link>
                            </div>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-[#8b9188]">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 10V7.5a4.5 4.5 0 0 1 9 0V10m-10.5 0h12A1.5 1.5 0 0 1 19.5 11.5v7A1.5 1.5 0 0 1 18 20H6a1.5 1.5 0 0 1-1.5-1.5v-7A1.5 1.5 0 0 1 6 10Zm6 4v2" />
                                    </svg>
                                </span>
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Ingresa tu contraseña"
                                    class="login-input block w-full rounded-xl border border-[#deded5] bg-white py-3.5 pl-12 pr-12 text-sm font-medium text-[#17251e] placeholder:text-[#a4a69f]"
                                />
                                <button
                                    type="button"
                                    :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                    :aria-pressed="showPassword"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 flex items-center rounded-r-xl px-4 text-[#8b9188] transition-colors hover:text-[#064E3B] focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#064E3B]"
                                >
                                    <svg v-if="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.5-6.25 9.75-6.25S21.75 12 21.75 12 18.25 18.25 12 18.25 2.25 12 2.25 12Z" />
                                        <circle cx="12" cy="12" r="2.5" />
                                    </svg>
                                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.9A10.8 10.8 0 0 1 12 5.75c6.25 0 9.75 6.25 9.75 6.25a16 16 0 0 1-3.05 3.75M6.1 6.1C3.6 7.7 2.25 12 2.25 12s3.5 6.25 9.75 6.25c1.3 0 2.45-.3 3.45-.8" />
                                    </svg>
                                </button>
                            </div>
                            <InputError class="mt-1.5" :message="form.errors.password" />
                        </div>

                        <label class="inline-flex cursor-pointer items-center gap-2.5 pt-1 text-sm text-[#5f675f]">
                            <Checkbox
                                name="remember"
                                v-model:checked="form.remember"
                                class="rounded border-[#c9cdc3] text-[#064E3B] focus:ring-[#064E3B]"
                            />
                            <span>Recordar sesión</span>
                        </label>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="login-submit flex w-full items-center justify-center gap-2.5 rounded-xl bg-[#064E3B] px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-[#064E3B]/15 transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#022C22] hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-[#064E3B] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <svg
                                v-if="form.processing"
                                class="h-5 w-5 animate-spin"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>{{ form.processing ? 'Ingresando...' : 'Ingresar al sistema' }}</span>
                            <svg v-if="!form.processing" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
                            </svg>
                        </button>
                    </form>
                </div>

                <footer class="mt-10 flex items-center justify-between border-t border-[#e5e2d8] pt-5 text-xs text-[#85877e]">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-[#064E3B]"></span>
                        Acceso protegido
                    </span>
                    <span>&copy; {{ new Date().getFullYear() }} Licores TITO</span>
                </footer>
            </section>

            <aside class="login-hero relative isolate flex min-h-[520px] flex-col justify-between overflow-hidden px-6 py-8 text-white sm:px-10 sm:py-10 lg:min-h-full lg:px-12 lg:py-12">
                <div class="hero-photo absolute inset-0 -z-20" aria-hidden="true"></div>
                <div class="hero-shade absolute inset-0 -z-10" aria-hidden="true"></div>
                <div class="hero-glow hero-glow-one absolute -left-24 -top-24 -z-10 h-80 w-80 rounded-full bg-emerald-400/25 blur-[90px]" aria-hidden="true"></div>
                <div class="hero-glow hero-glow-two absolute -bottom-28 right-0 -z-10 h-96 w-96 rounded-full bg-[#D6B46A]/20 blur-[100px]" aria-hidden="true"></div>
                <div class="hero-glow hero-glow-three absolute right-1/3 top-1/3 -z-10 h-64 w-64 rounded-full bg-emerald-300/15 blur-[80px]" aria-hidden="true"></div>
                <div class="hero-bottle-scene absolute inset-0 -z-10" aria-hidden="true">
                    <span class="hero-bottle hero-bottle-one"></span>
                    <span class="hero-bottle hero-bottle-two"></span>
                    <span class="hero-bottle hero-bottle-three"></span>
                </div>
                <div class="hero-ornament hero-ornament-top absolute -right-24 -top-52 -z-10 h-96 w-96 rounded-full border border-[#D6B46A]/35" aria-hidden="true"></div>
                <div class="hero-ornament hero-ornament-bottom absolute -bottom-64 -right-40 -z-10 h-[30rem] w-[30rem] rounded-full border border-[#D6B46A]/30" aria-hidden="true"></div>

                <div class="relative z-10">
                    <div class="inline-flex items-center gap-2.5 rounded-full border border-white/15 bg-[#022C22]/45 px-3.5 py-2 text-[0.68rem] font-bold uppercase tracking-[0.2em] text-[#e9d8ad] backdrop-blur-md">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#D6B46A]"></span>
                        Sistema POS &amp; ERP
                    </div>

                    <h2 class="mt-9 text-5xl font-extrabold leading-[0.98] tracking-[-0.055em] sm:mt-12 sm:text-6xl xl:text-7xl">
                        Licores <span class="text-[#D6B46A]">TITO</span>
                    </h2>
                    <p class="mt-5 max-w-md text-sm leading-6 text-white/75 sm:text-base sm:leading-7">
                        Control de ventas, inventario, caja y reportes en un solo lugar
                    </p>
                </div>

                <div class="relative z-10 my-auto grid gap-4 py-10">
                    <article class="flex items-center gap-5 rounded-2xl border border-white/15 bg-[#022C22]/45 px-5 py-5 shadow-lg shadow-black/5 backdrop-blur-md transition-colors duration-200 hover:bg-[#022C22]/60">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-[#D6B46A]/25 bg-[#D6B46A]/10 text-[#D6B46A]">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l2.2 11.2a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.6L21 8H6M10 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2Zm8 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" />
                            </svg>
                        </span>
                        <span>
                            <span class="block text-base font-bold text-white">Ventas y facturación ágil</span>
                            <span class="mt-1.5 block text-sm leading-5 text-white/65">Registra pedidos y pagos de forma sencilla.</span>
                        </span>
                    </article>

                    <article class="flex items-center gap-5 rounded-2xl border border-white/15 bg-[#022C22]/45 px-5 py-5 shadow-lg shadow-black/5 backdrop-blur-md transition-colors duration-200 hover:bg-[#022C22]/60">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-[#D6B46A]/25 bg-[#D6B46A]/10 text-[#D6B46A]">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m12 3 8.5 4.5v9L12 21l-8.5-4.5v-9L12 3Zm0 0v9m8.5-4.5L12 12m0 0-8.5-4.5M12 12v9" />
                            </svg>
                        </span>
                        <span>
                            <span class="block text-base font-bold text-white">Control de stock e inventario</span>
                            <span class="mt-1.5 block text-sm leading-5 text-white/65">Consulta existencias, movimientos y alertas.</span>
                        </span>
                    </article>

                    <article class="flex items-center gap-5 rounded-2xl border border-white/15 bg-[#022C22]/45 px-5 py-5 shadow-lg shadow-black/5 backdrop-blur-md transition-colors duration-200 hover:bg-[#022C22]/60">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-[#D6B46A]/25 bg-[#D6B46A]/10 text-[#D6B46A]">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 15v-4m4 4V7m4 8v-6m4 6V4" />
                            </svg>
                        </span>
                        <span>
                            <span class="block text-base font-bold text-white">Reportes y métricas</span>
                            <span class="mt-1.5 block text-sm leading-5 text-white/65">Revisa ventas, cierres y resultados del negocio.</span>
                        </span>
                    </article>
                </div>

            </aside>
        </div>
    </main>
</template>

<style>
.login-shell {
    background:
        radial-gradient(ellipse at 10% 15%, rgba(214, 180, 106, 0.09), transparent 40%),
        #f2f1eb;
    font-family: Inter, Manrope, Figtree, ui-sans-serif, system-ui, sans-serif;
}

.login-input {
    transition: border-color 180ms ease, box-shadow 180ms ease, background-color 180ms ease;
}

.login-input:focus {
    border-color: #064e3b;
    background-color: #fff;
    box-shadow: 0 0 0 3px rgba(6, 78, 59, 0.12);
    outline: none;
}

.login-hero {
    background-color: #022c22;
    isolation: isolate;
}

.hero-photo {
    /* Cambiar a url('/images/licores-tito-hero.jpg') al agregar una foto autorizada. */
    --licores-tito-foto: none;
    background-color: #064e3b;
    background-image:
        linear-gradient(135deg, rgba(2, 44, 34, 0.25), rgba(2, 44, 34, 0.45)),
        var(--licores-tito-foto);
    background-position: center, 68% center;
    background-size: cover;
    opacity: 0.38;
}

.hero-shade {
    background:
        linear-gradient(105deg, rgba(2, 44, 34, 0.96) 0%, rgba(2, 44, 34, 0.88) 48%, rgba(2, 44, 34, 0.48) 100%),
        linear-gradient(0deg, rgba(2, 44, 34, 0.55), transparent 60%);
}

.hero-bottle-scene {
    overflow: hidden;
    background:
        radial-gradient(circle at 84% 22%, rgba(214, 180, 106, 0.48) 0 0.35rem, transparent 0.5rem),
        radial-gradient(circle at 73% 48%, rgba(214, 180, 106, 0.38) 0 0.45rem, transparent 0.65rem),
        radial-gradient(circle at 93% 63%, rgba(214, 180, 106, 0.55) 0 0.3rem, transparent 0.48rem),
        radial-gradient(circle at 68% 82%, rgba(214, 180, 106, 0.32) 0 0.35rem, transparent 0.55rem),
        radial-gradient(ellipse at 88% 72%, rgba(214, 180, 106, 0.12), transparent 32%);
}

.hero-bottle {
    position: absolute;
    bottom: -5%;
    width: clamp(4.5rem, 13vw, 9rem);
    height: 55%;
    border: 1px solid rgba(214, 180, 106, 0.5);
    border-radius: 18% 18% 11% 11%;
    background:
        linear-gradient(90deg, rgba(214, 180, 106, 0.04), rgba(214, 180, 106, 0.33) 18%, rgba(6, 78, 59, 0.18) 48%, rgba(214, 180, 106, 0.22) 82%, rgba(214, 180, 106, 0.04)),
        linear-gradient(0deg, rgba(214, 180, 106, 0.35), rgba(214, 180, 106, 0.02) 66%);
    box-shadow: inset 0 0 1.2rem rgba(255, 224, 160, 0.2), 0 0 2.5rem rgba(214, 180, 106, 0.14);
    filter: blur(2.5px);
    opacity: 0.64;
}

.hero-bottle::before {
    position: absolute;
    top: -17%;
    left: 34%;
    width: 32%;
    height: 19%;
    border: 1px solid rgba(214, 180, 106, 0.45);
    border-bottom: 0;
    border-radius: 18% 18% 0 0;
    background: linear-gradient(90deg, rgba(214, 180, 106, 0.1), rgba(214, 180, 106, 0.33), rgba(214, 180, 106, 0.08));
    content: '';
}

.hero-bottle::after {
    position: absolute;
    top: -22%;
    left: 34%;
    width: 32%;
    height: 6%;
    border: 1px solid rgba(232, 202, 135, 0.55);
    border-radius: 3px;
    background: linear-gradient(90deg, rgba(214, 180, 106, 0.16), rgba(214, 180, 106, 0.65), rgba(214, 180, 106, 0.14));
    content: '';
}

.hero-bottle-one {
    right: 7%;
    height: 72%;
}

.hero-bottle-two {
    right: 27%;
    bottom: -10%;
    width: clamp(3.8rem, 10vw, 7.5rem);
    height: 51%;
    opacity: 0.48;
    filter: blur(4px);
}

.hero-bottle-three {
    right: 45%;
    bottom: -8%;
    width: clamp(3.5rem, 9vw, 6.5rem);
    height: 43%;
    opacity: 0.3;
    filter: blur(6px);
}

.hero-ornament {
    box-shadow: 0 0 0 1px rgba(214, 180, 106, 0.04), inset 0 0 2rem rgba(214, 180, 106, 0.025);
}

.hero-glow {
    pointer-events: none;
    animation: hero-drift 18s ease-in-out infinite alternate;
    will-change: transform;
}

.hero-glow-two {
    animation-delay: -7s;
    animation-duration: 22s;
}

.hero-glow-three {
    animation-delay: -11s;
    animation-duration: 26s;
}

@keyframes hero-drift {
    0% {
        transform: translate3d(-4%, 2%, 0) scale(0.94);
    }
    50% {
        transform: translate3d(5%, -3%, 0) scale(1.06);
    }
    100% {
        transform: translate3d(1%, 5%, 0) scale(0.98);
    }
}

@media (prefers-reduced-motion: reduce) {
    .hero-glow {
        animation: none;
        will-change: auto;
    }

    .login-input,
    .login-submit,
    .login-hero article {
        transition: none;
    }
}
</style>
