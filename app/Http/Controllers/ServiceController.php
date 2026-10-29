<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::orderByDesc('active')
            ->orderBy('name')
            ->get();

        return view('servicios.index', compact('services'));
    }

    public function create()
    {
        return view('servicios.form', [
            'service' => new Service(['duration_minutes' => 30])
        ]);
    }

    public function store(Request $request)
    {
        Service::create($this->validated($request));

        return redirect()
            ->route('servicios.index')
            ->with('ok', 'Servicio creado.');
    }

    public function edit(Service $service)
    {
        return view('servicios.form', compact('service'));
    }

    public function update(Request $request, Service $service)
    {
        $service->update($this->validated($request));

        return redirect()
            ->route('servicios.index')
            ->with('ok', 'Servicio actualizado.');
    }

    public function toggle(Service $service)
    {
        $service->update([
            'active' => ! $service->active
        ]);

        return back()->with(
            'ok',
            $service->active ? 'Servicio activado.' : 'Servicio desactivado.'
        );
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return redirect()
            ->route('servicios.index')
            ->with('ok', 'Servicio eliminado.'
        );
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:60',
            'price' => 'required|integer|min:0|max:1000000',
            'duration_minutes' => 'required|integer|min:15|max:240',
        ]);
    }
}