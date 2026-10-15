<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const props = defineProps({
    products: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    movements: {
        type: Object,
        default: () => ({
            data: [],
            current_page: 1,
            last_page: 1,
            from: 0,
            to: 0,
            total: 0,
        }),
    },
    movementFilters: {
        type: Object,
        default: () => ({}),
    },
    flash: {
        type: Object,
        default: () => ({}),
    },
});

const canManageInventory = computed(() => page.props.auth?.usuario?.roles?.includes('admin') ?? false);

const categoryForm = useForm({
    nombre: '',
    descripcion: '',
    activo: true,
});

const productForm = useForm({
    categoria_id: '',
    codigo: '',
    nombre: '',
    descripcion: '',
    precio_compra: 0,
    precio_venta: 0,
    stock: 0,
    stock_minimo: 0,
    activo: true,
});

const adjustmentForm = useForm({
    stock_nuevo: 0,
    motivo: '',
});

const movementFilterForm = useForm({
    movement_type: props.movementFilters?.movement_type || '',
    movement_product: props.movementFilters?.movement_product || '',
    movement_from: props.movementFilters?.movement_from || '',
    movement_to: props.movementFilters?.movement_to || '',
});

const editingCategoryId = ref(null);
const editingProductId = ref(null);
const selectedProduct = ref(null);
const showCategoryForm = ref(false);
const showProductForm = ref(false);
const search = ref('');
const categoryFilter = ref('');
const lowStockOnly = ref(false);

const createCategory = () => {
    const options = {
        onSuccess: () => {
            categoryForm.reset();
            editingCategoryId.value = null;
            showCategoryForm.value = false;
        },
    };

    if (editingCategoryId.value) {
        categoryForm.put(route('inventario.categorias.update', editingCategoryId.value), options);
        return;
    }

    categoryForm.post(route('inventario.categorias.store'), options);
};

const createProduct = () => {
    const options = {
        onSuccess: () => {
            productForm.reset();
            editingProductId.value = null;
            showProductForm.value = false;
        },
    };

    if (editingProductId.value) {
        productForm.put(route('inventario.productos.update', editingProductId.value), options);
        return;
    }

    productForm.post(route('inventario.productos.store'), options);
};

const editCategory = (category) => {
    editingCategoryId.value = category.id;
    showCategoryForm.value = true;
    showProductForm.value = false;
    categoryForm.clearErrors();
    categoryForm.nombre = category.nombre;
    categoryForm.descripcion = category.descripcion || '';
    categoryForm.activo = Boolean(category.activo);
};

const cancelCategoryEdit = () => {
    editingCategoryId.value = null;
    categoryForm.reset();
    categoryForm.clearErrors();
    showCategoryForm.value = false;
};

const editProduct = (product) => {
    editingProductId.value = product.id;
    showProductForm.value = true;
    showCategoryForm.value = false;
    productForm.clearErrors();
    productForm.categoria_id = product.categoria?.id || '';
    productForm.codigo = product.codigo;
    productForm.nombre = product.nombre;
    productForm.descripcion = product.descripcion || '';
    productForm.precio_compra = Number(product.precio_compra);
    productForm.precio_venta = Number(product.precio_venta);
    productForm.stock = Number(product.stock);
    productForm.stock_minimo = Number(product.stock_minimo);
    productForm.activo = Boolean(product.activo);
};

const cancelProductEdit = () => {
    editingProductId.value = null;
    productForm.reset();
    productForm.clearErrors();
    showProductForm.value = false;
};

const toggleCategoryForm = () => {
    showCategoryForm.value = !showCategoryForm.value;
    if (showCategoryForm.value) {
        showProductForm.value = false;
    }
};

const toggleProductForm = () => {
    showProductForm.value = !showProductForm.value;
    if (showProductForm.value) {
        showCategoryForm.value = false;
    }
};

const adjustStock = (product) => {
    selectedProduct.value = product;
    adjustmentForm.clearErrors();
    adjustmentForm.stock_nuevo = Number(product.stock);
    adjustmentForm.motivo = '';
};

const saveStockAdjustment = () => {
    if (!selectedProduct.value) {
        return;
    }

    adjustmentForm.post(route('inventario.productos.ajustes.store', selectedProduct.value.id), {
        onSuccess: () => {
            selectedProduct.value = null;
            adjustmentForm.reset();
        },
    });
};

const cancelStockAdjustment = () => {
    selectedProduct.value = null;
    adjustmentForm.reset();
    adjustmentForm.clearErrors();
};

const deleteProduct = (product) => {
    if (confirm(`¿Eliminar el producto "${product.nombre}"?`)) {
        router.delete(route('inventario.productos.destroy', product.id));
    }
};

const deleteCategory = (category) => {
    if (confirm(`¿Eliminar la categoría "${category.nombre}"?`)) {
        router.delete(route('inventario.categorias.destroy', category.id));
    }
};

const lowStockProducts = computed(() => {
    return props.products.filter(isLowStock);
});

const visibleLowStockProducts = computed(() => lowStockProducts.value.slice(0, 5));

const isLowStock = (product) => {
    return Number(product.stock) <= Number(product.stock_minimo || 0);
};

const filteredProducts = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('es');

    return props.products.filter((product) => {
        const matchesSearch = query === ''
            || product.nombre.toLocaleLowerCase('es').includes(query)
            || product.codigo.toLocaleLowerCase('es').includes(query);
        const matchesCategory = categoryFilter.value === ''
            || String(product.categoria?.id) === categoryFilter.value;
        const matchesLowStock = !lowStockOnly.value || isLowStock(product);

        return matchesSearch && matchesCategory && matchesLowStock;
    });
});

const showLowStockProducts = () => {
    search.value = '';
    categoryFilter.value = '';
    lowStockOnly.value = true;
};

const totalInventoryValue = computed(() => {
    return props.products.reduce((sum, product) => sum + Number(product.precio_venta || 0) * Number(product.stock || 0), 0);
});

const goToMovementPage = (pageNumber) => {
    if (pageNumber < 1 || pageNumber > props.movements.last_page) {
        return;
    }

    const params = Object.fromEntries(
        Object.entries(props.movementFilters || {}).filter(([, value]) => value !== null && value !== ''),
    );

    router.get(route('inventario.index'), { ...params, movements_page: pageNumber }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const applyMovementFilters = () => {
    const params = Object.fromEntries(
        Object.entries(movementFilterForm.data()).filter(([, value]) => value !== ''),
    );

    router.get(route('inventario.index'), { ...params, movements_page: 1 }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const clearMovementFilters = () => {
    movementFilterForm.movement_type = '';
    movementFilterForm.movement_product = '';
    movementFilterForm.movement_from = '';
    movementFilterForm.movement_to = '';
    movementFilterForm.clearErrors();

    router.get(route('inventario.index'), { movements_page: 1 }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const exportMovementHistory = () => {
    window.location.href = `${route('inventario.movimientos.export')}${window.location.search}`;
};

const exportProductCatalog = () => {
    window.location.href = route('inventario.productos.export');
};

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0,
    }).format(Number(amount || 0));
};
</script>

<template>
    <Head title="Inventario - PosVenta" />

    <AuthenticatedLayout>
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-purple-600 mb-1">
                    <span>Operación</span>
                    <span>/</span>
                    <span>Inventario</span>
                </div>
                <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">
                    Productos e Inventario
                </h1>
                <p class="mt-1 text-sm text-slate-500 font-medium">
                    Control del catálogo, stock mínimo y categorías del negocio
                </p>
            </div>
            <div v-if="canManageInventory" class="flex flex-wrap gap-3">
                <button
                    type="button"
                    :aria-expanded="showCategoryForm"
                    aria-controls="category-form-panel"
                    @click="toggleCategoryForm"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-purple-200 bg-white px-4 py-3 text-sm font-bold text-purple-700 shadow-sm transition-colors hover:bg-purple-50 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
                >
                    <span aria-hidden="true" class="text-lg leading-none">{{ showCategoryForm ? '−' : '+' }}</span>
                    {{ showCategoryForm ? 'Cerrar categoría' : editingCategoryId ? 'Continuar edición' : 'Nueva categoría' }}
                </button>
                <button
                    type="button"
                    :aria-expanded="showProductForm"
                    aria-controls="product-form-panel"
                    @click="toggleProductForm"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-purple-700 px-5 py-3 text-sm font-bold text-white shadow-md shadow-purple-700/20 transition-colors hover:bg-purple-800 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
                >
                    <span aria-hidden="true" class="text-lg leading-none">{{ showProductForm ? '−' : '+' }}</span>
                    {{ showProductForm ? 'Cerrar producto' : editingProductId ? 'Continuar edición' : 'Nuevo producto' }}
                </button>
            </div>
        </div>

        <div v-if="$page.props.flash?.success" class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold flex items-center gap-2.5 shadow-xs">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span>{{ $page.props.flash.success }}</span>
        </div>
        <div v-if="$page.props.flash?.error" class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm font-semibold flex items-center gap-2.5 shadow-xs">
            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
            <span>{{ $page.props.flash.error }}</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-5">
                <p class="text-xs uppercase tracking-wider text-slate-400 font-bold">Productos</p>
                <p class="mt-3 text-3xl font-extrabold text-slate-900">{{ products.length }}</p>
            </div>
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-5">
                <p class="text-xs uppercase tracking-wider text-slate-400 font-bold">Stock bajo</p>
                <p class="mt-3 text-3xl font-extrabold text-amber-600">{{ lowStockProducts.length }}</p>
            </div>
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-5">
                <p class="text-xs uppercase tracking-wider text-slate-400 font-bold">Valor estimado</p>
                <p class="mt-3 text-xl font-extrabold text-emerald-600">{{ formatCurrency(totalInventoryValue) }}</p>
            </div>
        </div>

        <section
            v-if="lowStockProducts.length > 0"
            role="status"
            aria-live="polite"
            aria-labelledby="low-stock-alert-title"
            class="mb-8 rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm sm:p-6"
        >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span aria-hidden="true" class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-amber-700 font-extrabold">!</span>
                        <h2 id="low-stock-alert-title" class="text-lg font-extrabold text-amber-950">Alerta de stock bajo</h2>
                    </div>
                    <p class="mt-2 text-sm text-amber-900">
                        {{ lowStockProducts.length }} {{ lowStockProducts.length === 1 ? 'producto requiere' : 'productos requieren' }}
                        revisión porque llegaron al stock mínimo o están agotados.
                    </p>
                    <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                        <li
                            v-for="product in visibleLowStockProducts"
                            :key="product.id"
                            class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-amber-200/80 bg-white/80 px-3 py-2 text-sm"
                        >
                            <span class="min-w-0 truncate font-semibold text-slate-800">{{ product.nombre }}</span>
                            <span :class="Number(product.stock) === 0 ? 'text-rose-700' : 'text-amber-800'" class="shrink-0 text-xs font-bold">
                                {{ Number(product.stock) === 0 ? 'Agotado' : `${product.stock} / mín. ${product.stock_minimo}` }}
                            </span>
                        </li>
                    </ul>
                    <p v-if="lowStockProducts.length > visibleLowStockProducts.length" class="mt-2 text-xs font-medium text-amber-800">
                        Y {{ lowStockProducts.length - visibleLowStockProducts.length }} más.
                    </p>
                </div>
                <button
                    type="button"
                    @click="showLowStockProducts"
                    class="shrink-0 rounded-xl bg-amber-700 px-4 py-2.5 text-sm font-bold text-white transition-colors hover:bg-amber-800 focus:outline-none focus:ring-2 focus:ring-amber-600 focus:ring-offset-2"
                >
                    Ver productos
                </button>
            </div>
        </section>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            <div class="xl:col-span-2 bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Catálogo de productos</h2>
                        <p class="text-xs text-slate-400 font-medium">Listado actual del inventario</p>
                    </div>
                    <button v-if="canManageInventory" @click="exportProductCatalog" type="button" class="rounded-xl border border-purple-200 bg-purple-50 px-4 py-2 text-sm font-bold text-purple-700 transition-all hover:bg-purple-100">
                        Exportar catálogo CSV
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-3 border-b border-slate-100 p-5 sm:grid-cols-3">
                    <input v-model="search" type="search" aria-label="Buscar producto por nombre o código" placeholder="Buscar nombre o código" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm text-slate-900 focus:border-purple-400 focus:ring-purple-500" />
                    <select v-model="categoryFilter" aria-label="Filtrar por categoría" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm text-slate-900 focus:border-purple-400 focus:ring-purple-500">
                        <option value="">Todas las categorías</option>
                        <option v-for="category in categories" :key="category.id" :value="String(category.id)">
                            {{ category.nombre }}
                        </option>
                    </select>
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700">
                        <input v-model="lowStockOnly" type="checkbox" class="rounded border-slate-300 text-purple-600 focus:ring-purple-500" />
                        Solo stock bajo
                    </label>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/75 border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                <th class="py-4 px-6">Código</th>
                                <th class="py-4 px-6">Nombre</th>
                                <th class="py-4 px-6">Categoría</th>
                                <th class="py-4 px-6">Stock</th>
                                <th class="py-4 px-6">Precio</th>
                                <th v-if="canManageInventory" class="py-4 px-6 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <tr v-for="product in filteredProducts" :key="product.id" class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-4 px-6 font-bold text-slate-700">{{ product.codigo }}</td>
                                <td class="py-4 px-6">
                                    <div class="font-bold text-slate-900">{{ product.nombre }}</div>
                                    <div v-if="product.descripcion" class="text-xs text-slate-400 mt-1 line-clamp-1">{{ product.descripcion }}</div>
                                </td>
                                <td class="py-4 px-6 text-slate-600">{{ product.categoria?.nombre || 'Sin categoría' }}</td>
                                <td class="py-4 px-6">
                                    <span :class="Number(product.stock) <= Number(product.stock_minimo || 0) ? 'bg-amber-100 text-amber-700 border-amber-200' : 'bg-emerald-100 text-emerald-700 border-emerald-200'" class="px-2.5 py-1 text-xs font-bold rounded-lg border inline-flex items-center gap-1.5">
                                        {{ product.stock }}
                                    </span>
                                </td>
                                <td class="py-4 px-6 font-bold text-slate-800">{{ formatCurrency(product.precio_venta) }}</td>
                                <td v-if="canManageInventory" class="py-4 px-6 text-right">
                                    <div class="inline-flex items-center gap-3">
                                        <button @click="adjustStock(product)" type="button" class="text-emerald-700 hover:text-emerald-800 text-xs font-bold transition-colors">
                                            Ajustar stock
                                        </button>
                                        <button @click="editProduct(product)" type="button" class="text-purple-600 hover:text-purple-700 text-xs font-bold transition-colors">
                                            Editar
                                        </button>
                                        <button @click="deleteProduct(product)" type="button" class="text-rose-600 hover:text-rose-700 text-xs font-bold transition-colors">
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="filteredProducts.length === 0">
                                <td :colspan="canManageInventory ? 6 : 5" class="py-10 text-center text-slate-400 text-sm">
                                    {{ products.length === 0 ? 'No hay productos registrados aún.' : 'No hay productos que coincidan con los filtros.' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-8">
                <div v-if="canManageInventory && showCategoryForm" id="category-form-panel" class="bg-white rounded-3xl border border-purple-200 shadow-sm p-6">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-slate-900">{{ editingCategoryId ? 'Editar categoría' : 'Nueva categoría' }}</h2>
                        <button @click="cancelCategoryEdit" type="button" aria-label="Cerrar formulario de categoría" class="rounded-lg px-2 py-1 text-sm font-bold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800">
                            Cerrar
                        </button>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Nombre</label>
                            <input v-model="categoryForm.nombre" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:ring-purple-500 focus:border-purple-400" placeholder="Ej. Licores" />
                            <p v-if="categoryForm.errors.nombre" class="mt-1 text-xs text-rose-600">{{ categoryForm.errors.nombre }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Descripción</label>
                            <textarea v-model="categoryForm.descripcion" rows="3" class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:ring-purple-500 focus:border-purple-400" placeholder="Descripción breve"></textarea>
                            <p v-if="categoryForm.errors.descripcion" class="mt-1 text-xs text-rose-600">{{ categoryForm.errors.descripcion }}</p>
                        </div>
                        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input v-model="categoryForm.activo" type="checkbox" class="rounded border-slate-300 text-purple-600 focus:ring-purple-500" />
                            Categoría activa
                        </label>
                        <button @click="createCategory" type="button" :disabled="categoryForm.processing" class="w-full px-4 py-3 rounded-xl bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white text-sm font-bold shadow-md shadow-purple-600/20 transition-all">
                            {{ categoryForm.processing ? 'Guardando...' : editingCategoryId ? 'Actualizar categoría' : 'Guardar categoría' }}
                        </button>
                        <button @click="cancelCategoryEdit" type="button" class="w-full px-4 py-3 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-bold transition-all">
                            Cancelar
                        </button>
                    </div>
                </div>

                <div v-if="canManageInventory && showProductForm" id="product-form-panel" class="bg-white rounded-3xl border border-purple-200 shadow-sm p-6">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-slate-900">{{ editingProductId ? 'Editar producto' : 'Nuevo producto' }}</h2>
                        <button @click="cancelProductEdit" type="button" aria-label="Cerrar formulario de producto" class="rounded-lg px-2 py-1 text-sm font-bold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800">
                            Cerrar
                        </button>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Categoría</label>
                            <select v-model="productForm.categoria_id" class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:ring-purple-500 focus:border-purple-400">
                                <option value="">Seleccione una categoría</option>
                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ category.nombre }}
                                </option>
                            </select>
                            <p v-if="productForm.errors.categoria_id" class="mt-1 text-xs text-rose-600">{{ productForm.errors.categoria_id }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Código</label>
                            <input v-model="productForm.codigo" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:ring-purple-500 focus:border-purple-400" placeholder="P-001" />
                            <p v-if="productForm.errors.codigo" class="mt-1 text-xs text-rose-600">{{ productForm.errors.codigo }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Nombre</label>
                            <input v-model="productForm.nombre" type="text" class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:ring-purple-500 focus:border-purple-400" placeholder="Nombre del producto" />
                            <p v-if="productForm.errors.nombre" class="mt-1 text-xs text-rose-600">{{ productForm.errors.nombre }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Descripción</label>
                            <textarea v-model="productForm.descripcion" rows="3" class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:ring-purple-500 focus:border-purple-400" placeholder="Descripción breve"></textarea>
                            <p v-if="productForm.errors.descripcion" class="mt-1 text-xs text-rose-600">{{ productForm.errors.descripcion }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Compra</label>
                                <input v-model.number="productForm.precio_compra" type="number" min="0" step="1" class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:ring-purple-500 focus:border-purple-400" />
                                <p v-if="productForm.errors.precio_compra" class="mt-1 text-xs text-rose-600">{{ productForm.errors.precio_compra }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Venta</label>
                                <input v-model.number="productForm.precio_venta" type="number" min="0" step="1" class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:ring-purple-500 focus:border-purple-400" />
                                <p v-if="productForm.errors.precio_venta" class="mt-1 text-xs text-rose-600">{{ productForm.errors.precio_venta }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div v-if="!editingProductId">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Stock inicial</label>
                                <input v-model.number="productForm.stock" type="number" min="0" class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:ring-purple-500 focus:border-purple-400" />
                                <p v-if="productForm.errors.stock" class="mt-1 text-xs text-rose-600">{{ productForm.errors.stock }}</p>
                            </div>
                            <div v-else class="flex items-center text-xs font-medium text-slate-500">
                                El stock se cambia desde “Ajustar stock” para dejar historial.
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Stock mínimo</label>
                                <input v-model.number="productForm.stock_minimo" type="number" min="0" class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:ring-purple-500 focus:border-purple-400" />
                                <p v-if="productForm.errors.stock_minimo" class="mt-1 text-xs text-rose-600">{{ productForm.errors.stock_minimo }}</p>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input v-model="productForm.activo" type="checkbox" class="rounded border-slate-300 text-purple-600 focus:ring-purple-500" />
                            Producto activo
                        </label>
                        <button @click="createProduct" type="button" :disabled="productForm.processing" class="w-full px-4 py-3 rounded-xl bg-slate-900 hover:bg-slate-700 disabled:opacity-50 text-white text-sm font-bold shadow-md shadow-slate-900/20 transition-all">
                            {{ productForm.processing ? 'Guardando...' : editingProductId ? 'Actualizar producto' : 'Guardar producto' }}
                        </button>
                        <button @click="cancelProductEdit" type="button" class="w-full px-4 py-3 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-bold transition-all">
                            Cancelar
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6">
                    <h2 class="text-lg font-bold text-slate-900 mb-4">Categorías</h2>
                    <div class="space-y-3">
                        <div v-for="category in categories" :key="category.id" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <div>
                                <p class="font-bold text-slate-800">{{ category.nombre }}</p>
                                <p class="text-xs text-slate-400">{{ category.productos_count || 0 }} productos · {{ category.activo ? 'Activa' : 'Inactiva' }}</p>
                            </div>
                            <div v-if="canManageInventory" class="inline-flex items-center gap-3">
                                <button @click="editCategory(category)" type="button" class="text-purple-600 hover:text-purple-700 text-xs font-bold transition-colors">
                                    Editar
                                </button>
                                <button @click="deleteCategory(category)" type="button" class="text-rose-600 hover:text-rose-700 text-xs font-bold transition-colors">
                                    Eliminar
                                </button>
                            </div>
                        </div>
                        <div v-if="categories.length === 0" class="text-sm text-slate-400 text-center py-4">
                            No hay categorías creadas.
                        </div>
                    </div>
                </div>

                <div v-if="canManageInventory && selectedProduct" class="bg-white rounded-3xl border border-emerald-200 shadow-sm p-6">
                    <h2 class="text-lg font-bold text-slate-900">Ajustar existencia</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ selectedProduct.nombre }} · stock actual: {{ selectedProduct.stock }}</p>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Nuevo stock contado</label>
                            <input v-model.number="adjustmentForm.stock_nuevo" type="number" min="0" class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:ring-emerald-500 focus:border-emerald-400" />
                            <p v-if="adjustmentForm.errors.stock_nuevo" class="mt-1 text-xs text-rose-600">{{ adjustmentForm.errors.stock_nuevo }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">Motivo del ajuste</label>
                            <textarea v-model="adjustmentForm.motivo" rows="3" maxlength="2000" class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-900 focus:ring-emerald-500 focus:border-emerald-400" placeholder="Ej. Conteo físico"></textarea>
                            <p v-if="adjustmentForm.errors.motivo" class="mt-1 text-xs text-rose-600">{{ adjustmentForm.errors.motivo }}</p>
                        </div>
                        <button @click="saveStockAdjustment" type="button" :disabled="adjustmentForm.processing" class="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white transition-all hover:bg-emerald-700 disabled:opacity-50">
                            {{ adjustmentForm.processing ? 'Guardando...' : 'Guardar ajuste' }}
                        </button>
                        <button @click="cancelStockAdjustment" type="button" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600 transition-all hover:bg-slate-50">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="canManageInventory" class="mt-8 overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Historial de movimientos de stock</h2>
                    <p class="text-xs font-medium text-slate-400">
                        Mostrando {{ movements.from || 0 }}–{{ movements.to || 0 }} de {{ movements.total || 0 }} movimientos
                    </p>
                </div>
                <button @click="exportMovementHistory" type="button" class="rounded-xl border border-purple-200 bg-purple-50 px-4 py-2 text-sm font-bold text-purple-700 transition-all hover:bg-purple-100">
                    Exportar CSV filtrado
                </button>
            </div>
            <form @submit.prevent="applyMovementFilters" class="grid grid-cols-1 gap-3 border-b border-slate-100 p-5 sm:grid-cols-2 xl:grid-cols-5">
                <select v-model="movementFilterForm.movement_type" aria-label="Filtrar por tipo de movimiento" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm text-slate-900 focus:border-purple-400 focus:ring-purple-500">
                    <option value="">Todos los tipos</option>
                    <option value="inicial">Stock inicial</option>
                    <option value="ajuste">Ajuste manual</option>
                    <option value="venta">Venta</option>
                    <option value="compra">Compra recibida</option>
                </select>
                <div>
                    <input v-model="movementFilterForm.movement_product" type="search" aria-label="Filtrar movimientos por producto" placeholder="Producto o código" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm text-slate-900 focus:border-purple-400 focus:ring-purple-500" />
                    <p v-if="$page.props.errors?.movement_product" class="mt-1 text-xs text-rose-600">{{ $page.props.errors.movement_product }}</p>
                </div>
                <div>
                    <input v-model="movementFilterForm.movement_from" type="date" aria-label="Fecha desde" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm text-slate-900 focus:border-purple-400 focus:ring-purple-500" />
                    <p v-if="$page.props.errors?.movement_from" class="mt-1 text-xs text-rose-600">{{ $page.props.errors.movement_from }}</p>
                </div>
                <div>
                    <input v-model="movementFilterForm.movement_to" type="date" aria-label="Fecha hasta" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm text-slate-900 focus:border-purple-400 focus:ring-purple-500" />
                    <p v-if="$page.props.errors?.movement_to" class="mt-1 text-xs text-rose-600">{{ $page.props.errors.movement_to }}</p>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 rounded-xl bg-purple-600 px-4 py-2 text-sm font-bold text-white transition-all hover:bg-purple-700">
                        Filtrar
                    </button>
                    <button @click="clearMovementFilters" type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-slate-600 transition-all hover:bg-slate-50">
                        Limpiar
                    </button>
                </div>
            </form>
            <div v-if="movements.data?.length > 0" class="divide-y divide-slate-100">
                <div v-for="movement in movements.data" :key="movement.id" class="flex flex-col gap-2 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold text-slate-900">{{ movement.producto_nombre }}</span>
                            <span class="text-xs text-slate-400">{{ movement.producto_codigo }}</span>
                            <span :class="movement.tipo === 'inicial' ? 'bg-purple-100 text-purple-700' : movement.tipo === 'venta' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'" class="rounded-md px-2 py-0.5 text-[10px] font-bold uppercase">
                                {{ movement.tipo === 'inicial' ? 'Stock inicial' : movement.tipo === 'venta' ? 'Venta' : movement.tipo === 'compra' ? 'Compra recibida' : 'Ajuste' }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-slate-600">{{ movement.motivo }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ movement.usuario_nombre }} · {{ movement.created_at }}</p>
                    </div>
                    <div class="text-sm font-bold text-slate-700">
                        {{ movement.stock_anterior }} → {{ movement.stock_nuevo }}
                        <span :class="movement.diferencia > 0 ? 'text-emerald-600' : movement.diferencia < 0 ? 'text-rose-600' : 'text-slate-400'" class="ml-2">
                            {{ movement.diferencia > 0 ? '+' : '' }}{{ movement.diferencia }}
                        </span>
                    </div>
                </div>
            </div>
            <div v-else class="p-8 text-center text-sm text-slate-400">
                Todavía no hay movimientos de stock registrados.
            </div>
            <div v-if="movements.last_page > 1" class="flex items-center justify-between border-t border-slate-100 p-4">
                <button @click="goToMovementPage(movements.current_page - 1)" type="button" :disabled="movements.current_page <= 1" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">
                    Anterior
                </button>
                <span class="text-xs font-semibold text-slate-500">
                    Página {{ movements.current_page }} de {{ movements.last_page }}
                </span>
                <button @click="goToMovementPage(movements.current_page + 1)" type="button" :disabled="movements.current_page >= movements.last_page" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">
                    Siguiente
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
