<?php

namespace App\Http\Controllers;

use App\Models\{Client, Service};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    /** Autocompletado de la agenda (devuelve JSON) */
    public function search(Request $request)
    {
        $q = trim((string) $request->q);
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $clients = $this->withStats(Client::query())
            ->tap(fn ($c) => $this->applySearch($c, $q))
            ->orderByDesc('visits')
            ->limit(6)
            ->get();

        return response()->json($clients->map(fn ($c) => [
            'id'         => $c->id,
            'name'       => $c->name,
            'last_name'  => $c->last_name,
            'phone'      => $c->phone,
            'visits'     => (int) $c->visits,
            'last_visit' => $c->last_visit ? Carbon::parse($c->last_visit)->translatedFormat('j M Y') : null,
            'notes'      => $c->notes,
        ]));
    }

    public function index(Request $request)
    {
        $q    = trim((string) $request->q);
        $sort = $request->input('orden', 'visitas');

        $clients = $this->withStats(Client::query())
            ->withSum(['appointments as spent' => fn ($a) => $a->where('status', 'completada')], 'price')
            ->when($q !== '', fn ($c) => $this->applySearch($c, $q))
            ->when($sort === 'reciente', fn ($c) => $c->orderByDesc('last_visit'))
            ->when($sort === 'nombre', fn ($c) => $c->orderBy('name')->orderBy('last_name'))
            ->when(! in_array($sort, ['reciente', 'nombre'], true),
                fn ($c) => $c->orderByDesc('visits')->orderBy('name'))
            ->paginate(20)
            ->withQueryString();
            if ($request->ajax()) {
              return view('clientes._list', compact('clients', 'q'));
            }

        $totals = [
            'all'     => Client::count(),
            'new'     => Client::where('created_at', '>=', now()->startOfMonth())->count(),
            'regular' => Client::whereHas('appointments', fn ($a) => $a->where('status', 'completada'), '>=', 5)->count(),
        ];

        return view('clientes.index', compact('clients', 'totals', 'q', 'sort'));
    }

    public function show(Client $client)
    {
        $completed = fn () => $client->appointments()->where('status', 'completada');

        $fav = $completed()->selectRaw('service_id, COUNT(*) as c')
            ->groupBy('service_id')->orderByDesc('c')->first();

        $stats = [
            'visits'   => $completed()->count(),
            'spent'    => (int) $completed()->sum('price'),
            'last'     => $completed()->max('starts_at'),
            'noshows'  => $client->appointments()->where('status', 'no_asistio')->count(),
            'favorite' => $fav ? Service::withTrashed()->find($fav->service_id)?->name : null,
        ];

        $history = $client->appointments()->with(['barber', 'service'])
            ->orderByDesc('starts_at')->limit(30)->get();

        return view('clientes.show', compact('client', 'stats', 'history'));
    }

    public function update(Request $request, Client $client)
    {
        $request->merge(['phone' => Client::normalizePhone($request->phone)]);

        $nameRule = ['required', 'string', 'max:60', 'regex:' . Client::NAME_REGEX];

        $data = $request->validate([
            'name'      => $nameRule,
            'last_name' => $nameRule,
            'phone'     => ['required', 'regex:' . Client::PHONE_REGEX, Rule::unique('clients', 'phone')->ignore($client->id)],
            'notes'     => 'nullable|string|max:1000',
        ], [
            'name.required'      => 'Escribe el nombre.',
            'last_name.required' => 'Escribe el apellido.',
            'name.regex'         => 'El nombre solo puede tener letras.',
            'last_name.regex'    => 'El apellido solo puede tener letras.',
            'phone.required'     => 'Escribe el teléfono.',
            'phone.regex'        => 'El teléfono debe tener 8 dígitos y empezar con 2 a 8.',
            'phone.unique'       => 'Ya existe otro cliente con ese teléfono.',
        ]);

        $client->update($data);

        // Las citas futuras pendientes se actualizan; el historial queda como estaba
        $client->appointments()
            ->where('status', 'pendiente')
            ->where('starts_at', '>=', now())
            ->update([
                'client_name'      => $data['name'],
                'client_last_name' => $data['last_name'],
                'phone'            => $data['phone'],
            ]);

        return back()->with('ok', 'Cliente actualizado.');
    }

    public function destroy(Client $client)
    {
        $client->delete(); // las citas conservan nombre y teléfono
        return redirect()->route('clientes.index')->with('ok', 'Cliente eliminado.');
    }

    /* ---------- Helpers ---------- */

    private function withStats($query)
    {
        return $query
            ->withCount(['appointments as visits' => fn ($a) => $a->where('status', 'completada')])
            ->withMax(['appointments as last_visit' => fn ($a) => $a->where('status', 'completada')], 'starts_at');
    }

    private function applySearch($query, string $q)
    {
        // Si solo trae dígitos, espacios, guiones o +, se busca por teléfono
        if (preg_match('/^[\d\s+\-]+$/', $q)) {
            return $query->where('phone', 'like', Client::normalizePhone($q) . '%');
        }

        $like = '%' . addcslashes($q, '%_\\') . '%';

        return $query->where(function ($w) use ($like) {
            $w->where('name', 'like', $like)
              ->orWhere('last_name', 'like', $like)
              ->orWhereRaw("CONCAT(name, ' ', last_name) like ?", [$like]);
        });
    }
}