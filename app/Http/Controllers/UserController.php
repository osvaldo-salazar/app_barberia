<?php

namespace App\Http\Controllers;

use App\Models\{Barber, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('barber')->orderByDesc('active')->orderBy('name')->get();
        return view('usuarios.index', compact('users'));
    }

    public function create()
    {
        return view('usuarios.form', [
            'user'    => new User(['role' => 'barbero', 'active' => true]),
            'barbers' => Barber::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['password'] = Hash::make($data['password']);
        User::create($data);

        return redirect()->route('usuarios.index')->with('ok', 'Usuario creado.');
    }

    public function edit(User $user)
    {
        return view('usuarios.form', [
            'user'    => $user,
            'barbers' => Barber::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);

        if ($user->id === auth()->id() && $data['role'] !== 'admin') {
            return back()->withErrors(['role' => 'No puedes quitarte el rol de admin a ti mismo.'])->withInput();
        }

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return redirect()->route('usuarios.index')->with('ok', 'Usuario actualizado.');
    }

    public function toggle(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'No puedes desactivarte a ti mismo.']);
        }

        $user->update(['active' => ! $user->active]);
        return back()->with('ok', $user->active ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'No puedes eliminarte a ti mismo.']);
        }

        $user->delete();
        return redirect()->route('usuarios.index')->with('ok', 'Usuario eliminado.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name'      => 'required|string|max:60',
            'email'     => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user?->id)],
            'role'      => ['required', Rule::in(['admin', 'barbero', 'recepcion'])],
            'barber_id' => ['nullable', 'required_if:role,barbero', 'exists:barbers,id'],
            'password'  => [$user ? 'nullable' : 'required', 'string', 'min:8'],
        ], [
            'barber_id.required_if' => 'Elige a qué barbero corresponde este usuario.',
            'password.min'          => 'La clave debe tener al menos 8 caracteres.',
        ]);

        if ($data['role'] !== 'barbero') {
            $data['barber_id'] = null;
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
