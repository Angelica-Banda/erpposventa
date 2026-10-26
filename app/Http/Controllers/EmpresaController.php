<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaController extends Controller
{
    /**
     * Muestra la vista de configuración y administración de la empresa.
     */
    public function edit(): Response
    {
        $empresa = Empresa::obtenerConfiguracion();

        return Inertia::render('Configuracion/Empresa', [
            'empresa' => $empresa,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Actualiza la información básica, comercial, tributaria y el logo de la empresa.
     */
    public function update(Request $request): RedirectResponse
    {
        $empresa = Empresa::obtenerConfiguracion();

        $validated = $request->validate([
            // Información básica y comercial
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['required', 'string', 'max:255'],
            'nit' => ['required', 'string', 'max:20', 'regex:/^[0-9\.\-]+$/'],
            'dv' => ['nullable', 'string', 'max:1', 'regex:/^[0-9]$/'],
            'direccion' => ['required', 'string', 'max:255'],
            'pais' => ['required', 'string', 'max:100'],
            'departamento' => ['required', 'string', 'max:100'],
            'municipio' => ['required', 'string', 'max:100'],
            'codigo_postal' => ['nullable', 'string', 'max:20'],
            'telefono' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'sitio_web' => ['nullable', 'url', 'max:255'],

            // Información tributaria
            'tipo_persona' => ['required', 'in:natural,juridica'],
            'regimen_tributario' => ['required', 'string', 'max:100'],
            'responsabilidades_fiscales' => ['nullable', 'array'],
            'responsabilidades_fiscales.*' => ['string', 'max:50'],
            'ciiu' => ['nullable', 'string', 'max:10'],
            'detalles_tributarios' => ['nullable', 'string', 'max:1000'],

            // Archivo de logo
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'eliminar_logo' => ['nullable', 'boolean'],
        ], [
            'razon_social.required' => 'La razón social es obligatoria.',
            'nombre_comercial.required' => 'El nombre comercial es obligatorio.',
            'nit.required' => 'El número de identificación tributaria (NIT) es obligatorio.',
            'nit.regex' => 'El NIT solo puede contener números, puntos o guiones.',
            'dv.regex' => 'El dígito de verificación debe ser un solo número entre 0 y 9.',
            'direccion.required' => 'La dirección comercial es obligatoria.',
            'pais.required' => 'El país es obligatorio.',
            'departamento.required' => 'El departamento es obligatorio.',
            'municipio.required' => 'El municipio o ciudad es obligatorio.',
            'telefono.required' => 'El teléfono de contacto es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'sitio_web.url' => 'El sitio web debe incluir el protocolo (ej. https://empresa.com).',
            'tipo_persona.required' => 'Selecciona el tipo de persona (Natural o Jurídica).',
            'regimen_tributario.required' => 'Selecciona el régimen tributario.',
            'logo.image' => 'El archivo seleccionado debe ser una imagen válida.',
            'logo.mimes' => 'El logo debe estar en formato PNG, JPG, JPEG, WEBP o SVG.',
            'logo.max' => 'El logo no debe exceder los 2MB de tamaño.',
        ]);

        // Manejo de eliminación del logo si se solicita
        if ($request->boolean('eliminar_logo')) {
            if ($empresa->logo_path && Storage::disk('public')->exists($empresa->logo_path)) {
                Storage::disk('public')->delete($empresa->logo_path);
            }
            $empresa->logo_path = null;
        }

        // Manejo de carga o reemplazo del logo
        if ($request->hasFile('logo')) {
            if ($empresa->logo_path && Storage::disk('public')->exists($empresa->logo_path)) {
                Storage::disk('public')->delete($empresa->logo_path);
            }

            $rutaLogo = $request->file('logo')->store('empresa/logos', 'public');
            $empresa->logo_path = $rutaLogo;
        }

        // Asignación de datos
        $empresa->fill([
            'razon_social' => $validated['razon_social'],
            'nombre_comercial' => $validated['nombre_comercial'],
            'nit' => $validated['nit'],
            'dv' => $validated['dv'] ?? null,
            'direccion' => $validated['direccion'],
            'pais' => $validated['pais'],
            'departamento' => $validated['departamento'],
            'municipio' => $validated['municipio'],
            'codigo_postal' => $validated['codigo_postal'] ?? null,
            'telefono' => $validated['telefono'],
            'email' => $validated['email'],
            'sitio_web' => $validated['sitio_web'] ?? null,
            'tipo_persona' => $validated['tipo_persona'],
            'regimen_tributario' => $validated['regimen_tributario'],
            'responsabilidades_fiscales' => $validated['responsabilidades_fiscales'] ?? [],
            'ciiu' => $validated['ciiu'] ?? null,
            'detalles_tributarios' => $validated['detalles_tributarios'] ?? null,
        ]);

        $empresa->save();

        return redirect()->back()->with('success', 'Información comercial y tributaria de la empresa actualizada con éxito.');
    }
}
