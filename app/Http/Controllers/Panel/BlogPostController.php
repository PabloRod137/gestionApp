<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\BlogCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BlogPostController extends Controller
{
    /**
     * Listado de artículos de blog.
     */
    public function index()
    {
        $posts = BlogPost::with('category')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('panel.blog.index', compact('posts'));
    }

    /**
     * Formulario de creación de artículo.
     */
    public function create()
    {
        $categorias = BlogCategory::orderBy('nombre', 'asc')->get();
        return view('panel.blog.create', compact('categorias'));
    }

    /**
     * Guarda el nuevo artículo.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:blog_categories,id',
            'titulo' => 'required|string|max:255',
            'contenido' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'imagen' => 'nullable|image|max:2048', // Max 2MB
            'publicado' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:255',
        ], [
            'titulo.required' => 'El título es obligatorio.',
            'contenido.required' => 'El cuerpo del contenido es obligatorio.',
            'category_id.required' => 'La categoría es obligatoria.',
        ]);

        $postData = $validated;
        unset($postData['imagen']);

        // Tratar la subida de imagen de portada
        if ($request->hasFile('imagen')) {
            $path = $request->file('imagen')->store('blog', 'public');
            $postData['imagen_portada'] = $path;
        }

        $postData['publicado'] = $request->boolean('publicado');
        if ($postData['publicado']) {
            $postData['fecha_publicacion'] = now();
        }

        BlogPost::create($postData);

        return redirect()->route('panel.blog.index')
            ->with('success', 'Artículo creado y guardado con éxito.');
    }

    /**
     * Formulario de edición de artículo.
     */
    public function edit($id)
    {
        $post = BlogPost::findOrFail($id);
        $categorias = BlogCategory::orderBy('nombre', 'asc')->get();
        
        return view('panel.blog.edit', compact('post', 'categorias'));
    }

    /**
     * Actualiza el artículo del blog.
     */
    public function update(Request $request, $id)
    {
        $post = BlogPost::findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'required|exists:blog_categories,id',
            'titulo' => 'required|string|max:255',
            'contenido' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'imagen' => 'nullable|image|max:2048',
            'publicado' => 'nullable|boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:255',
        ]);

        $postData = $validated;
        unset($postData['imagen']);

        // Actualizar imagen de portada si se sube una nueva
        if ($request->hasFile('imagen')) {
            // Borrar la anterior del storage
            if ($post->imagen_portada && Storage::disk('public')->exists($post->imagen_portada)) {
                Storage::disk('public')->delete($post->imagen_portada);
            }

            $path = $request->file('imagen')->store('blog', 'public');
            $postData['imagen_portada'] = $path;
        }

        $postData['publicado'] = $request->boolean('publicado');
        
        // Si cambia a publicado y no tenía fecha, se asigna ahora
        if ($postData['publicado'] && !$post->publicado) {
            $postData['fecha_publicacion'] = now();
        } elseif (!$postData['publicado']) {
            $postData['fecha_publicacion'] = null;
        }

        $post->update($postData);

        return redirect()->route('panel.blog.index')
            ->with('success', 'Artículo actualizado con éxito.');
    }

    /**
     * Elimina el artículo del blog.
     */
    public function destroy($id)
    {
        $post = BlogPost::findOrFail($id);

        // Borrar imagen adjunta del storage
        if ($post->imagen_portada && Storage::disk('public')->exists($post->imagen_portada)) {
            Storage::disk('public')->delete($post->imagen_portada);
        }

        $post->delete();

        return redirect()->route('panel.blog.index')
            ->with('success', 'Artículo eliminado con éxito.');
    }
}
