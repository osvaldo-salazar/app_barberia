<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, User};
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'usuario' => 'nullable|integer',
            'accion'  => 'nullable|in:created,updated,deleted,login,logout,failed,export,security',
            'desde'   => 'nullable|date_format:Y-m-d',
            'hasta'   => 'nullable|date_format:Y-m-d',
            'q'       => 'nullable|string|max:60',
        ]);

        $logs = ActivityLog::query()
            ->when($request->usuario, fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->accion, fn ($q, $a) => $q->where('action', $a))
            ->when($request->desde, fn ($q, $d) => $q->where('created_at', '>=', $d . ' 00:00:00'))
            ->when($request->hasta, fn ($q, $d) => $q->where('created_at', '<=', $d . ' 23:59:59'))
            ->when($request->q, fn ($q, $t) => $q->where('description', 'like', '%' . addcslashes($t, '%_\\') . '%'))
            ->orderByDesc('id')
            ->paginate(40)
            ->withQueryString();

        return view('actividad.actividad', [
            'logs'  => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
