<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use Illuminate\Http\Request;

class BarberController extends Controller
{
    public function index()
    {
        $barbers = Barber::orderByDesc('active')->orderBy('name')->get();
        return view('barberos.index', compact('barbers'));
    }

    public function create()
    {
        return view('barberos.form', ['barber' => new Barber(['commission_percent' => 50])]);
    }

    public function store(Request $request)
    {
        Barber::create($this->validated($request));
        return redirect()->route('barberos.index')->with('ok', 'Barbero creado.');
    }

    public function edit(Barber $barber)
    {
        return view('barberos.form', compact('barber'));
    }

    public function update(Request $request, Barber $barber)
    {
        $barber->update($this->validated($request));
        return redirect()->route('barberos.index')->with('ok', 'Barbero actualizado.');
    }

    public function toggle(Barber $barber)
    {
        $barber->update(['active' => ! $barber->active]);
        return back()->with('ok', $barber->active ? 'Barbero activado.' : 'Barbero desactivado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'               => 'required|string|max:60',
            'commission_percent' => 'required|numeric|min:0|max:100',
        ], [
            'commission_percent.max' => 'El porcentaje no puede pasar de 100.',
        ]);
    }
}